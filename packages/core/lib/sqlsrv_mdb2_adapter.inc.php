<?php

if(!defined('MDB2_PREPARE_RESULT'))
{
   define('MDB2_PREPARE_RESULT', 1);
}
if(!defined('MDB2_PREPARE_MANIP'))
{
   define('MDB2_PREPARE_MANIP', 2);
}
if(!defined('MDB2_FETCHMODE_ASSOC'))
{
   define('MDB2_FETCHMODE_ASSOC', 2);
}

class ArchonSqlsrvError
{
   public function __construct($Message)
   {
      $this->Message = $Message;
   }

   public function getMessage()
   {
      return $this->Message;
   }

   public $Message;
}

class ArchonSqlsrvDatabase
{
   public static function connect($Configuration, $QueryLog = NULL)
   {
      if(!function_exists('sqlsrv_connect'))
      {
         return new ArchonSqlsrvError('The Microsoft PHP sqlsrv extension is required for Microsoft SQL Server.');
      }

      $ConnectionInfo = array(
         'Database' => $Configuration->DatabaseName,
         'UID' => $Configuration->Login,
         'PWD' => $Configuration->Password,
         'CharacterSet' => 'UTF-8',
         'ReturnDatesAsStrings' => true
      );

      $Connection = @sqlsrv_connect($Configuration->ServerAddress, $ConnectionInfo);
      if($Connection === false)
      {
         return new ArchonSqlsrvError(self::getErrorMessage());
      }

      return new self($Connection, $QueryLog);
   }

   private function __construct($Connection, $QueryLog)
   {
      $this->Connection = $Connection;
      $this->QueryLog = $QueryLog;
   }

   public function query($Query)
   {
      $Query = $this->applyLimit($Query);
      $Statement = @sqlsrv_query($this->Connection, $Query, array(), $this->getQueryOptions(true));
      $this->logQuery($Query, true);

      if($Statement === false)
      {
         return new ArchonSqlsrvError(self::getErrorMessage());
      }

      return new ArchonSqlsrvResult($Statement);
   }

   public function exec($Query)
   {
      $Query = $this->applyLimit($Query);
      $Statement = @sqlsrv_query($this->Connection, $Query);
      $this->logQuery($Query, false);

      if($Statement === false)
      {
         return new ArchonSqlsrvError(self::getErrorMessage());
      }

      $AffectedRows = sqlsrv_rows_affected($Statement);
      sqlsrv_free_stmt($Statement);
      return $AffectedRows === false ? 0 : $AffectedRows;
   }

   public function prepare($Query, $Types = NULL, $Mode = MDB2_PREPARE_RESULT)
   {
      $Limit = $this->Limit;
      $this->Limit = NULL;
      return new ArchonSqlsrvPreparedStatement($this, $this->applyLimit($Query, $Limit), $Types, $Mode);
   }

   public function setLimit($Limit, $Offset = 0)
   {
      $this->Limit = array(max(0, (int)$Limit), max(0, (int)$Offset));
      return true;
   }

   public function quoteIdentifier($Identifier)
   {
      return implode('.', array_map(function($Part)
      {
         $Part = trim($Part, "[] ");
         return '[' . str_replace(']', ']]', $Part) . ']';
      }, explode('.', $Identifier)));
   }

   public function escape($Value, $EscapeWildcards = false)
   {
      $Value = str_replace("\0", '', (string)$Value);
      $Value = str_replace("'", "''", $Value);

      if($EscapeWildcards)
      {
         $Value = str_replace(array('[', '%', '_'), array('[[]', '[%]', '[_]'), $Value);
      }

      return $Value;
   }

   public function escapePattern($Value)
   {
      return str_replace(array('[', '%', '_'), array('[[]', '[%]', '[_]'), (string)$Value);
   }

   public function setFetchMode($Mode)
   {
      return $Mode == MDB2_FETCHMODE_ASSOC;
   }

   public function disconnect()
   {
      if($this->Connection)
      {
         sqlsrv_close($this->Connection);
         $this->Connection = NULL;
      }
      return true;
   }

   public function loadModule($Module)
   {
      return in_array($Module, array('Manager', 'Reverse'), true);
   }

   public function listTables()
   {
      $Result = $this->query("SELECT name FROM sys.tables WHERE is_ms_shipped = 0 ORDER BY name");
      if(pear_isError($Result))
      {
         return $Result;
      }

      $Tables = array();
      while($Row = $Result->fetchRow())
      {
         $Tables[] = $Row['name'];
      }
      $Result->free();
      return $Tables;
   }

   public function tableInfo($Table)
   {
      $Statement = $this->prepare(
         'SELECT c.name AS name, ty.name AS type, c.max_length AS len, c.is_nullable AS nullable, c.is_identity AS identity ' .
         'FROM sys.columns c JOIN sys.tables t ON c.object_id = t.object_id ' .
         'JOIN sys.types ty ON c.user_type_id = ty.user_type_id WHERE t.name = ? ORDER BY c.column_id',
         array('text'),
         MDB2_PREPARE_RESULT
      );
      $Result = $Statement->execute($Table);
      if(pear_isError($Result))
      {
         return $Result;
      }

      $Columns = array();
      while($Row = $Result->fetchRow())
      {
         $Row['flags'] = $Row['nullable'] ? '' : 'not_null';
         if($Row['identity'])
         {
            $Row['flags'] = trim($Row['flags'] . ' auto_increment');
         }
         unset($Row['nullable'], $Row['identity']);
         $Columns[] = $Row;
      }
      $Result->free();
      return $Columns;
   }

   public function supports($Feature)
   {
      return $Feature == 'transactions';
   }

   public function beginTransaction()
   {
      if(sqlsrv_begin_transaction($this->Connection))
      {
         $this->InTransaction = true;
         return true;
      }
      return new ArchonSqlsrvError(self::getErrorMessage());
   }

   public function commit()
   {
      if(sqlsrv_commit($this->Connection))
      {
         $this->InTransaction = false;
         return true;
      }
      return new ArchonSqlsrvError(self::getErrorMessage());
   }

   public function rollback()
   {
      if(sqlsrv_rollback($this->Connection))
      {
         $this->InTransaction = false;
         return true;
      }
      return new ArchonSqlsrvError(self::getErrorMessage());
   }

   public function inTransaction()
   {
      return $this->InTransaction;
   }

   public function getDebugOutput()
   {
      return implode("\n", $this->DebugOutput);
   }

   public function executePrepared($Query, $Types, $Mode, $Values)
   {
      $Query = $this->applyLimit($Query);
      $Parameters = $this->prepareParameters($Types, $Values);
      $IsResult = $Mode == MDB2_PREPARE_RESULT;
      $Statement = @sqlsrv_query($this->Connection, $Query, $Parameters, $this->getQueryOptions($IsResult));
      $this->logQuery($Query, $IsResult);

      if($Statement === false)
      {
         return new ArchonSqlsrvError(self::getErrorMessage());
      }

      if($IsResult)
      {
         return new ArchonSqlsrvResult($Statement);
      }

      $AffectedRows = sqlsrv_rows_affected($Statement);
      sqlsrv_free_stmt($Statement);
      return $AffectedRows === false ? 0 : $AffectedRows;
   }

   private function prepareParameters($Types, $Values)
   {
      $Values = array_values($Values);
      if(!is_array($Types))
      {
         $Types = $Types === NULL ? array() : array($Types);
      }
      $Types = array_values(array_filter($Types, function($Type)
      {
         return in_array($Type, array('integer', 'text', 'date', 'boolean'), true);
      }));

      $Parameters = array();
      foreach($Values as $Index => &$Value)
      {
         if(!isset($Types[$Index]))
         {
            $Parameters[] = $Value;
            continue;
         }

         switch($Types[$Index])
         {
            case 'integer':
               $Value = $Value === NULL ? NULL : (int)$Value;
               $Parameters[] = array(&$Value, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_INT);
               break;
            case 'boolean':
               $Value = $Value ? 1 : 0;
               $Parameters[] = array(&$Value, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_BIT);
               break;
            case 'date':
               $Parameters[] = array(&$Value, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING('UTF-8'), SQLSRV_SQLTYPE_DATE);
               break;
            default:
               $Value = $Value === NULL ? NULL : (string)$Value;
               $Parameters[] = array(&$Value, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING('UTF-8'), SQLSRV_SQLTYPE_NVARCHAR('max'));
               break;
         }
      }
      unset($Value);
      return $Parameters;
   }

   private function applyLimit($Query, $Limit = NULL)
   {
      if($Limit === NULL)
      {
         $Limit = $this->Limit;
      }
      $this->Limit = NULL;

      if($Limit === NULL || $Limit[0] < 1 || !preg_match('/^\s*SELECT\b/i', $Query))
      {
         return $Query;
      }

      list($Count, $Offset) = $Limit;
      if($Offset > 0)
      {
         $Query = rtrim(trim($Query), ';');
         if(!preg_match('/\bORDER\s+BY\b/i', $Query))
         {
            $Query .= ' ORDER BY (SELECT NULL)';
         }
         return $Query . ' OFFSET ' . $Offset . ' ROWS FETCH NEXT ' . $Count . ' ROWS ONLY';
      }

      if(preg_match('/^\s*SELECT\s+(?:DISTINCT\s+|ALL\s+)?TOP\b/i', $Query))
      {
         return $Query;
      }
      return preg_replace('/^(\s*SELECT\s+(?:DISTINCT\s+|ALL\s+)?)/i', '$1TOP (' . $Count . ') ', $Query, 1);
   }

   private function getQueryOptions($Scrollable)
   {
      return $Scrollable ? array('Scrollable' => SQLSRV_CURSOR_STATIC) : array();
   }

   private function logQuery($Query, $IsResult)
   {
      $this->DebugOutput[] = $Query;
      if(is_object($this->QueryLog) && method_exists($this->QueryLog, 'logQuery'))
      {
         $this->QueryLog->logQuery($this, NULL, $Query, !$IsResult);
      }
   }

   public static function getErrorMessage()
   {
      if(!function_exists('sqlsrv_errors'))
      {
         return 'Microsoft SQL Server operation failed.';
      }

      $Errors = sqlsrv_errors();
      if(!is_array($Errors))
      {
         return 'Microsoft SQL Server operation failed.';
      }

      return implode('; ', array_map(function($Error)
      {
         return trim($Error['message']);
      }, $Errors));
   }

   private $Connection;
   private $QueryLog;
   private $Limit;
   private $InTransaction = false;
   private $DebugOutput = array();
}

class ArchonSqlsrvPreparedStatement
{
   public function __construct($Database, $Query, $Types, $Mode)
   {
      $this->Database = $Database;
      $this->Query = $Query;
      $this->Types = $Types;
      $this->Mode = $Mode;
   }

   public function execute($Values = array())
   {
      if(!is_array($Values))
      {
         $Values = array($Values);
      }
      return $this->Database->executePrepared($this->Query, $this->Types, $this->Mode, $Values);
   }

   public function free()
   {
      return true;
   }

   public $Database;
   public $Query;
   public $Types;
   public $Mode;
}

class ArchonSqlsrvResult
{
   public function __construct($Statement)
   {
      $this->Statement = $Statement;
   }

   public function fetchRow()
   {
      $Row = sqlsrv_fetch_array($this->Statement, SQLSRV_FETCH_ASSOC);
      return $Row === null || $Row === false ? false : $Row;
   }

   public function numRows()
   {
      $Count = sqlsrv_num_rows($this->Statement);
      return $Count === false ? 0 : $Count;
   }

   public function free()
   {
      if($this->Statement)
      {
         sqlsrv_free_stmt($this->Statement);
         $this->Statement = NULL;
      }
      return true;
   }

   private $Statement;
}
