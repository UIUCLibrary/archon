<?php
/**
 * SQL Server access for the sample-data generator: batched multi-row inserts with explicit IDs.
 *
 * Connects with CharacterSet UTF-8, the same client encoding the Archon sqlsrv adapter uses when
 * DatabaseEncodingUTF8 is on, so text is stored exactly as the PHP 8.2 application would store it.
 */
class SampleDatabase
{
   public $Connection;
   private $Columns = array();
   private $Counts = array();

   public function __construct($Host, $Login, $Secret, $Database)
   {
      sqlsrv_configure('WarningsReturnAsErrors', 0);
      $this->Connection = sqlsrv_connect($Host, array(
         'UID' => $Login, 'PWD' => $Secret, 'Database' => $Database,
         'TrustServerCertificate' => true, 'CharacterSet' => 'UTF-8', 'LoginTimeout' => 15,
      ));
      if($this->Connection === false)
      {
         throw new RuntimeException("cannot connect to $Database: " . self::errors());
      }
   }

   public static function errors()
   {
      $Messages = array();
      foreach((array)sqlsrv_errors(SQLSRV_ERR_ERRORS) as $Error)
      {
         $Messages[] = $Error['message'];
      }
      return implode(' | ', $Messages);
   }

   public function query($Sql, $Params = array())
   {
      $Statement = sqlsrv_query($this->Connection, $Sql, $Params, array('QueryTimeout' => 0));
      if($Statement === false)
      {
         throw new RuntimeException(self::errors() . "\n  in: " . substr($Sql, 0, 300));
      }
      $Rows = array();
      do
      {
         if(sqlsrv_num_fields($Statement) > 0)
         {
            while(($Row = sqlsrv_fetch_array($Statement, SQLSRV_FETCH_ASSOC)) !== NULL && $Row !== false)
            {
               $Rows[] = $Row;
            }
         }
         $Next = sqlsrv_next_result($Statement);
      }
      while($Next === true);
      if($Next === false)
      {
         throw new RuntimeException(self::errors() . "\n  in: " . substr($Sql, 0, 300));
      }
      sqlsrv_free_stmt($Statement);
      return $Rows;
   }

   public function value($Sql, $Params = array())
   {
      $Rows = $this->query($Sql, $Params);
      return $Rows ? reset($Rows[0]) : NULL;
   }

   public function begin()
   {
      if(!sqlsrv_begin_transaction($this->Connection))
      {
         throw new RuntimeException('cannot start a transaction: ' . self::errors());
      }
   }

   public function commit()
   {
      if(!sqlsrv_commit($this->Connection))
      {
         throw new RuntimeException('commit failed: ' . self::errors());
      }
   }

   public function rollback()
   {
      @sqlsrv_rollback($this->Connection);
   }

   /**
    * Column name => array(type, max length, nullable, identity) for a table.
    */
   public function columns($Table)
   {
      if(!isset($this->Columns[$Table]))
      {
         $this->Columns[$Table] = array();
         $Rows = $this->query("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMNPROPERTY(OBJECT_ID(TABLE_NAME), COLUMN_NAME, 'IsIdentity') AS IsIdentity
            FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION", array($Table));
         foreach($Rows as $Row)
         {
            $this->Columns[$Table][$Row['COLUMN_NAME']] = array(
               'type' => $Row['DATA_TYPE'], 'length' => $Row['CHARACTER_MAXIMUM_LENGTH'] === NULL ? NULL : (int)$Row['CHARACTER_MAXIMUM_LENGTH'],
               'nullable' => $Row['IS_NULLABLE'] == 'YES', 'identity' => (bool)$Row['IsIdentity'],
            );
         }
         if(!$this->Columns[$Table])
         {
            throw new RuntimeException("table $Table does not exist (is the package installed?)");
         }
      }
      return $this->Columns[$Table];
   }

   public function nextId($Table)
   {
      return (int)$this->value("SELECT ISNULL(MAX(ID), 0) + 1 FROM $Table");
   }

   /**
    * Inserts rows (associative arrays) with explicit IDs. Columns the table does not have are dropped;
    * strings longer than a varchar column are truncated (the way a careful data entry would be).
    */
   public function insert($Table, array $Rows)
   {
      if(!$Rows)
      {
         return;
      }
      $Columns = $this->columns($Table);
      $Names = array();
      foreach($Rows as $Row)
      {
         foreach(array_keys($Row) as $Name)
         {
            if(isset($Columns[$Name]) && !in_array($Name, $Names, true))
            {
               $Names[] = $Name;
            }
         }
      }
      $HasIdentity = in_array('ID', $Names, true) && $Columns['ID']['identity'];
      $Binary = array();
      foreach($Names as $Name)
      {
         $Binary[$Name] = $Columns[$Name]['type'] == 'varbinary';
      }

      $PerBatch = max(1, min(500, intdiv(2000, count($Names))));
      $Placeholder = '(' . implode(', ', array_fill(0, count($Names), '?')) . ')';
      if($HasIdentity)
      {
         $this->query("SET IDENTITY_INSERT $Table ON");
      }
      try
      {
         foreach(array_chunk($Rows, $PerBatch) as $Batch)
         {
            $Params = array();
            foreach($Batch as $Row)
            {
               foreach($Names as $Name)
               {
                  $Value = array_key_exists($Name, $Row) ? $Row[$Name] : NULL;
                  if($Value === NULL && !$Columns[$Name]['nullable'])
                  {
                     $Value = in_array($Columns[$Name]['type'], array('int', 'bit', 'decimal', 'bigint', 'smallint', 'tinyint'), true) ? 0 : '';
                  }
                  if(is_string($Value) && $Columns[$Name]['length'] > 0 && !$Binary[$Name] && mb_strlen($Value, 'UTF-8') > $Columns[$Name]['length'])
                  {
                     $Value = mb_substr($Value, 0, $Columns[$Name]['length'], 'UTF-8');
                  }
                  if($Binary[$Name])
                  {
                     $Params[] = array($Value === NULL ? '' : $Value, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_VARBINARY('max'));
                  }
                  elseif(is_bool($Value))
                  {
                     $Params[] = $Value ? 1 : 0;
                  }
                  else
                  {
                     $Params[] = $Value;
                  }
               }
            }
            $Sql = "INSERT INTO $Table (" . implode(', ', $Names) . ') VALUES ' . implode(', ', array_fill(0, count($Batch), $Placeholder));
            $this->query($Sql, $Params);
         }
      }
      finally
      {
         if($HasIdentity)
         {
            $this->query("SET IDENTITY_INSERT $Table OFF");
         }
      }
      $this->Counts[$Table] = (isset($this->Counts[$Table]) ? $this->Counts[$Table] : 0) + count($Rows);
   }

   public function counts()
   {
      return $this->Counts;
   }
}
