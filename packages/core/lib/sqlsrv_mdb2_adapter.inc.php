<?php
/**
 * MDB2-compatible database layer for Microsoft SQL Server (PHP 8.2, Microsoft "sqlsrv" driver).
 *
 * Archon was written against PEAR MDB2's "mssql" driver, which relies on the mssql extension that
 * was removed in PHP 7.  This file implements the part of the MDB2 API that Archon uses and keeps
 * the observable behaviour of that driver as Archon configured it
 * (portability = MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_FIX_CASE):
 *
 *  - prepared statements are emulated: values are quoted and inlined into the SQL text exactly
 *    as MDB2 did (integers are cast, text is quoted with '' escaping, decimals are normalised);
 *  - the empty string is written as NULL (MDB2_PORTABILITY_EMPTY_TO_NULL);
 *  - fetched empty strings become NULL and other strings are right-trimmed
 *    (MDB2_PORTABILITY_EMPTY_TO_NULL + MDB2_PORTABILITY_RTRIM).  Binary columns are deliberately
 *    not trimmed, because trimming corrupts stored files;
 *  - setLimit() turns SELECT into SELECT TOP (offset + limit) and skips the offset rows client side;
 *  - fetchRow() returns NULL after the last row; unnamed columns are called computed, computed1...;
 *  - the session SET options default to the ones a FreeTDS/DB-Library (mssql extension)
 *    connection receives, e.g. ANSI_NULLS OFF so that "column = NULL" keeps matching NULL rows.
 *
 * Optional configuration (config.inc.php):
 *  $_ARCHON->db->SqlsrvOptions        = array('Encrypt' => true, 'TrustServerCertificate' => false);
 *  $_ARCHON->db->SqlsrvSessionOptions = array('ANSI_NULLS' => 'ON', ...); // or array() for driver defaults
 *  $_ARCHON->db->SqlsrvClientBufferMaxKBSize = 524288;
 *
 * @package Archon
 * @subpackage core
 */

if(!defined('MDB2_OK')) { define('MDB2_OK', true); }
if(!defined('MDB2_ERROR')) { define('MDB2_ERROR', -1); }
if(!defined('MDB2_ERROR_SYNTAX')) { define('MDB2_ERROR_SYNTAX', -2); }
if(!defined('MDB2_ERROR_CONSTRAINT')) { define('MDB2_ERROR_CONSTRAINT', -3); }
if(!defined('MDB2_ERROR_NOT_FOUND')) { define('MDB2_ERROR_NOT_FOUND', -4); }
if(!defined('MDB2_ERROR_UNSUPPORTED')) { define('MDB2_ERROR_UNSUPPORTED', -6); }
if(!defined('MDB2_ERROR_INVALID')) { define('MDB2_ERROR_INVALID', -8); }
if(!defined('MDB2_ERROR_INVALID_NUMBER')) { define('MDB2_ERROR_INVALID_NUMBER', -11); }
if(!defined('MDB2_ERROR_TRUNCATED')) { define('MDB2_ERROR_TRUNCATED', -10); }
if(!defined('MDB2_ERROR_NEED_MORE_DATA')) { define('MDB2_ERROR_NEED_MORE_DATA', -20); }
if(!defined('MDB2_ERROR_CONNECT_FAILED')) { define('MDB2_ERROR_CONNECT_FAILED', -24); }
if(!defined('MDB2_ERROR_EXTENSION_NOT_FOUND')) { define('MDB2_ERROR_EXTENSION_NOT_FOUND', -25); }

if(!defined('MDB2_FETCHMODE_DEFAULT')) { define('MDB2_FETCHMODE_DEFAULT', 0); }
if(!defined('MDB2_FETCHMODE_ORDERED')) { define('MDB2_FETCHMODE_ORDERED', 1); }
if(!defined('MDB2_FETCHMODE_ASSOC')) { define('MDB2_FETCHMODE_ASSOC', 2); }
if(!defined('MDB2_FETCHMODE_OBJECT')) { define('MDB2_FETCHMODE_OBJECT', 3); }
if(!defined('MDB2_FETCHMODE_FLIPPED')) { define('MDB2_FETCHMODE_FLIPPED', 4); }

if(!defined('MDB2_PORTABILITY_NONE')) { define('MDB2_PORTABILITY_NONE', 0); }
if(!defined('MDB2_PORTABILITY_FIX_CASE')) { define('MDB2_PORTABILITY_FIX_CASE', 1); }
if(!defined('MDB2_PORTABILITY_RTRIM')) { define('MDB2_PORTABILITY_RTRIM', 2); }
if(!defined('MDB2_PORTABILITY_DELETE_COUNT')) { define('MDB2_PORTABILITY_DELETE_COUNT', 4); }
if(!defined('MDB2_PORTABILITY_NUMROWS')) { define('MDB2_PORTABILITY_NUMROWS', 8); }
if(!defined('MDB2_PORTABILITY_ERRORS')) { define('MDB2_PORTABILITY_ERRORS', 16); }
if(!defined('MDB2_PORTABILITY_EMPTY_TO_NULL')) { define('MDB2_PORTABILITY_EMPTY_TO_NULL', 32); }
if(!defined('MDB2_PORTABILITY_FIX_ASSOC_FIELD_NAMES')) { define('MDB2_PORTABILITY_FIX_ASSOC_FIELD_NAMES', 64); }
if(!defined('MDB2_PORTABILITY_ALL')) { define('MDB2_PORTABILITY_ALL', 127); }

// In MDB2 the third argument of prepare() is the list of result types: FALSE means "manipulation".
if(!defined('MDB2_PREPARE_MANIP')) { define('MDB2_PREPARE_MANIP', false); }
if(!defined('MDB2_PREPARE_RESULT')) { define('MDB2_PREPARE_RESULT', null); }

/**
 * Error object returned instead of a result (checked with pear_isError()).
 */
class ArchonSqlsrvError
{
   public $code;
   public $message;
   public $userinfo;
   public $nativeErrors = array();

   public function __construct($Message, $Code = MDB2_ERROR, $UserInfo = NULL, $NativeErrors = array())
   {
      $this->message = (string)$Message;
      $this->code = $Code;
      $this->userinfo = $UserInfo;
      $this->nativeErrors = is_array($NativeErrors) ? $NativeErrors : array();
   }

   public function getMessage()
   {
      return $this->message;
   }

   public function getCode()
   {
      return $this->code;
   }

   public function getUserInfo()
   {
      return $this->userinfo;
   }

   public function getDebugInfo()
   {
      return $this->userinfo;
   }

   public function getNativeErrors()
   {
      return $this->nativeErrors;
   }

   public function toString()
   {
      return $this->message;
   }

   public function __toString()
   {
      return $this->message;
   }
}

/**
 * Connection object ($_ARCHON->mdb2).
 */
class ArchonSqlsrvDatabase
{
   /**
    * Session options received by a FreeTDS/DB-Library connection (the PHP mssql extension used by
    * the legacy installation).  ODBC drivers turn these on by default, which changes the results
    * of comparisons with NULL, string concatenation with NULL, truncation errors and the meaning
    * of double quotes.
    */
   const LEGACY_SESSION_OPTIONS = array(
      'ANSI_NULLS' => 'OFF',
      'ANSI_WARNINGS' => 'OFF',
      'ANSI_PADDING' => 'OFF',
      'ANSI_NULL_DFLT_ON' => 'OFF',
      'CONCAT_NULL_YIELDS_NULL' => 'OFF',
      'QUOTED_IDENTIFIER' => 'OFF',
      'TEXTSIZE' => '2147483647',
   );

   public $phptype = 'mssql';
   public $dbsyntax = 'mssql';
   public $connection = NULL;
   public $database_name = '';
   public $fetchmode = MDB2_FETCHMODE_ORDERED;
   public $options = array(
      'portability' => 126, // MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_FIX_CASE
      'result_buffering' => true,
      'debug' => 1,
   );
   public $limit = 0;
   public $offset = 0;
   public $in_transaction = false;
   public $last_query = '';
   public $wildcards = array('%', '_');

   private $QueryLog = NULL;
   private $DebugOutput = array();
   private $ServerAddress = NULL;
   private $ConnectionOptions = array();
   private $SessionOptions = array();

   /**
    * Connects using the Archon database configuration object ($_ARCHON->db).
    *
    * @param object $Configuration
    * @param object $QueryLog
    * @return ArchonSqlsrvDatabase|ArchonSqlsrvError
    */
   public static function connect($Configuration, $QueryLog = NULL)
   {
      if(!extension_loaded('sqlsrv') || !function_exists('sqlsrv_connect'))
      {
         return new ArchonSqlsrvError('The Microsoft SQL Server driver for PHP (sqlsrv extension) is not loaded. Install the Microsoft ODBC Driver for SQL Server and the sqlsrv extension.', MDB2_ERROR_EXTENSION_NOT_FOUND);
      }

      sqlsrv_configure('WarningsReturnAsErrors', 0);

      $BufferSize = (isset($Configuration->SqlsrvClientBufferMaxKBSize) && is_numeric($Configuration->SqlsrvClientBufferMaxKBSize)) ? (int)$Configuration->SqlsrvClientBufferMaxKBSize : 524288;
      if($BufferSize > 0)
      {
         sqlsrv_configure('ClientBufferMaxKBSize', $BufferSize);
      }

      $Options = array(
         'Database' => (string)$Configuration->DatabaseName,
         'UID' => (string)$Configuration->Login,
         'PWD' => (string)$Configuration->Password,
         'CharacterSet' => self::usesUTF8() ? 'UTF-8' : SQLSRV_ENC_CHAR,
         'ReturnDatesAsStrings' => true,
         'MultipleActiveResultSets' => true,
         'APP' => 'Archon',
      );

      // sqlsrv returns decimal(...) values below 1 without the leading zero ('.50'); the mssql
      // driver returned '0.50'. FormatDecimals (sqlsrv 5.6+) restores the mssql format.
      if(version_compare((string)phpversion('sqlsrv'), '5.6.0', '>='))
      {
         $Options['FormatDecimals'] = true;
      }

      if(isset($Configuration->SqlsrvOptions) && is_array($Configuration->SqlsrvOptions))
      {
         $Options = array_merge($Options, $Configuration->SqlsrvOptions);
      }

      $SessionOptions = self::LEGACY_SESSION_OPTIONS;
      if(isset($Configuration->SqlsrvSessionOptions) && is_array($Configuration->SqlsrvSessionOptions))
      {
         $SessionOptions = $Configuration->SqlsrvSessionOptions;
      }

      $Database = new self(NULL, $QueryLog);
      $Database->database_name = (string)$Configuration->DatabaseName;
      $Database->ServerAddress = self::normalizeServerAddress($Configuration->ServerAddress);
      $Database->ConnectionOptions = $Options;
      $Database->SessionOptions = $SessionOptions;

      $Result = $Database->ensureConnected();
      if(self::isError($Result))
      {
         return $Result;
      }

      return $Database;
   }

   /**
    * (Re)connects if there is no open connection. Like MDB2, the connection is opened again on
    * demand after disconnect() (footer.inc.php disconnects, but session writes and some admin
    * pages still query the database afterwards).
    *
    * @return mixed MDB2_OK or an ArchonSqlsrvError
    */
   private function ensureConnected()
   {
      if($this->connection)
      {
         return MDB2_OK;
      }

      $Connection = @sqlsrv_connect($this->ServerAddress, $this->ConnectionOptions);
      if($Connection === false)
      {
         return self::createError('Unable to connect to the Microsoft SQL Server database', MDB2_ERROR_CONNECT_FAILED);
      }
      $this->connection = $Connection;

      $Result = $this->applySessionOptions($this->SessionOptions);
      if(self::isError($Result))
      {
         sqlsrv_close($Connection);
         $this->connection = NULL;
         return $Result;
      }

      return MDB2_OK;
   }

   /**
    * Converts the FreeTDS style "host:port" address to the "host,port" format used by sqlsrv.
    *
    * @param string $Address
    * @return string
    */
   public static function normalizeServerAddress($Address)
   {
      $Address = trim((string)$Address);

      if(preg_match('/^([^:,\\\\\\[\\]\\s]+):(\\d+)$/', $Address, $Matches))
      {
         return $Matches[1] . ',' . $Matches[2];
      }

      return $Address;
   }

   public static function isError($Value)
   {
      return $Value instanceof ArchonSqlsrvError;
   }

   /**
    * Builds an error object from the last sqlsrv errors.
    *
    * @param string $Message
    * @param integer $Code
    * @param string $Query
    * @return ArchonSqlsrvError
    */
   public static function createError($Message, $Code = MDB2_ERROR, $Query = NULL)
   {
      $Errors = function_exists('sqlsrv_errors') ? sqlsrv_errors(SQLSRV_ERR_ERRORS) : NULL;
      $NativeErrors = is_array($Errors) ? $Errors : array();
      $Native = array();

      foreach($NativeErrors as $Error)
      {
         $Native[] = '[' . $Error['SQLSTATE'] . '] (' . $Error['code'] . ') ' . $Error['message'];
         if($Code == MDB2_ERROR)
         {
            $Code = self::mapNativeErrorCode($Error['code']);
         }
      }

      $FullMessage = $Message;
      if(!empty($Native))
      {
         $FullMessage .= ': ' . implode('; ', $Native);
      }

      $UserInfo = $Query !== NULL ? $Query . ' [Native message: ' . implode('; ', $Native) . ']' : implode('; ', $Native);

      return new ArchonSqlsrvError($FullMessage, $Code, $UserInfo, $NativeErrors);
   }

   private static function mapNativeErrorCode($NativeCode)
   {
      switch((int)$NativeCode)
      {
         case 102:
         case 105:
         case 156:
         case 170:
            return MDB2_ERROR_SYNTAX;
         case 208:
         case 207:
            return MDB2_ERROR_NOT_FOUND;
         case 515:
         case 547:
         case 2601:
         case 2627:
            return MDB2_ERROR_CONSTRAINT;
         case 8152:
         case 2628:
            return MDB2_ERROR_TRUNCATED;
      }

      return MDB2_ERROR;
   }

   private static function usesUTF8()
   {
      global $_ARCHON;

      if(isset($_ARCHON) && is_object($_ARCHON) && isset($_ARCHON->config) && is_object($_ARCHON->config) && isset($_ARCHON->config->DatabaseEncodingUTF8))
      {
         return (bool)$_ARCHON->config->DatabaseEncodingUTF8;
      }

      return true;
   }

   private function __construct($Connection, $QueryLog)
   {
      $this->connection = $Connection;
      $this->QueryLog = $QueryLog;
   }

   /**
    * Applies session SET options, e.g. array('ANSI_NULLS' => 'OFF').
    *
    * @param array $SessionOptions
    * @return mixed
    */
   public function applySessionOptions($SessionOptions)
   {
      $Statements = array();
      foreach($SessionOptions as $Option => $Value)
      {
         if(!preg_match('/^[A-Z_]+$/i', $Option) || !preg_match('/^(ON|OFF|\d+)$/i', (string)$Value))
         {
            return new ArchonSqlsrvError("Invalid SQL Server session option: $Option", MDB2_ERROR_INVALID);
         }
         $Statements[] = 'SET ' . strtoupper($Option) . ' ' . strtoupper((string)$Value);
      }

      if(empty($Statements))
      {
         return MDB2_OK;
      }

      $Result = $this->exec(implode('; ', $Statements));
      return self::isError($Result) ? $Result : MDB2_OK;
   }

   /**
    * Executes a query that returns rows.
    *
    * @param string $Query
    * @param mixed $Types
    * @return ArchonSqlsrvResult|ArchonSqlsrvError
    */
   public function query($Query, $Types = NULL)
   {
      $Limit = $this->limit;
      $Offset = $this->offset;
      $this->limit = $this->offset = 0;

      $Query = $this->modifyQuery($Query, false, $Limit, $Offset);

      $Statement = $this->doQuery($Query, false);
      if(self::isError($Statement))
      {
         return $Statement;
      }

      return new ArchonSqlsrvResult($this, $Statement, $Limit, $Offset);
   }

   /**
    * Executes a manipulation query and returns the number of affected rows.
    *
    * @param string $Query
    * @return integer|ArchonSqlsrvError
    */
   public function exec($Query)
   {
      $Limit = $this->limit;
      $Offset = $this->offset;
      $this->limit = $this->offset = 0;

      $Query = $this->modifyQuery($Query, true, $Limit, $Offset);

      $Statement = $this->doQuery($Query, true);
      if(self::isError($Statement))
      {
         return $Statement;
      }

      $AffectedRows = sqlsrv_rows_affected($Statement);

      // Consume the rest of a multi-statement batch so that errors are not lost.
      while(($Next = sqlsrv_next_result($Statement)) === true)
      {
         $Rows = sqlsrv_rows_affected($Statement);
         if($Rows !== false && $Rows >= 0)
         {
            $AffectedRows = $Rows;
         }
      }

      if($Next === false)
      {
         $Error = self::createError('Query failed', MDB2_ERROR, $Query);
         sqlsrv_free_stmt($Statement);
         return $Error;
      }

      sqlsrv_free_stmt($Statement);

      return ($AffectedRows === false || $AffectedRows < 0) ? 0 : $AffectedRows;
   }

   /**
    * Prepares a statement (emulated, as with MDB2's mssql driver).
    *
    * @param string $Query
    * @param mixed $Types
    * @param mixed $ResultTypes MDB2_PREPARE_MANIP (false) for manipulation queries
    * @return ArchonSqlsrvPreparedStatement|ArchonSqlsrvError
    */
   public function prepare($Query, $Types = NULL, $ResultTypes = NULL, $Lobs = array())
   {
      $IsManip = ($ResultTypes === MDB2_PREPARE_MANIP);
      $Limit = $this->limit;
      $Offset = $this->offset;
      $this->limit = $this->offset = 0;

      $this->debug($Query, 'prepare', array('is_manip' => $IsManip, 'when' => 'pre'));

      $Positions = $this->findPlaceholders($Query);
      if(self::isError($Positions))
      {
         return $Positions;
      }

      if(!empty($Positions) && is_array($Types))
      {
         $Types = array_values($Types);
      }

      return new ArchonSqlsrvPreparedStatement($this, $Query, $Positions, $Types, $ResultTypes, $IsManip, $Limit, $Offset);
   }

   /**
    * Returns the positions of the ? placeholders that are not inside strings, identifiers or comments.
    *
    * @param string $Query
    * @return array|ArchonSqlsrvError position => parameter number
    */
   public function findPlaceholders($Query)
   {
      $Positions = array();
      $Length = strlen($Query);
      $Position = 0;

      while($Position < $Length)
      {
         $Char = $Query[$Position];
         $Next = ($Position + 1 < $Length) ? $Query[$Position + 1] : '';

         if($Char == "'" || $Char == '[')
         {
            $End = ($Char == "'") ? "'" : ']';
            $Position++;
            while(true)
            {
               $Found = strpos($Query, $End, $Position);
               if($Found === false)
               {
                  return new ArchonSqlsrvError('query with an unterminated text string specified', MDB2_ERROR_SYNTAX, $Query);
               }
               if($Found + 1 < $Length && $Query[$Found + 1] == $End)
               {
                  $Position = $Found + 2;
                  continue;
               }
               $Position = $Found + 1;
               break;
            }
            continue;
         }
         elseif($Char == '-' && $Next == '-')
         {
            $Found = strpos($Query, "\n", $Position + 2);
            $Position = ($Found === false) ? $Length : $Found + 1;
            continue;
         }
         elseif($Char == '/' && $Next == '*')
         {
            $Found = strpos($Query, '*/', $Position + 2);
            $Position = ($Found === false) ? $Length : $Found + 2;
            continue;
         }
         elseif($Char == '?')
         {
            $Positions[$Position] = count($Positions);
         }

         $Position++;
      }

      return $Positions;
   }

   /**
    * Quotes a value for inclusion in SQL text exactly like MDB2_Driver_Datatype_Common::quote().
    *
    * @param mixed $Value
    * @param string $Type
    * @param boolean $Quote
    * @param boolean $EscapeWildcards
    * @return mixed
    */
   public function quote($Value, $Type = NULL, $Quote = true, $EscapeWildcards = false)
   {
      if($Value === NULL || ($Value === '' && ($this->options['portability'] & MDB2_PORTABILITY_EMPTY_TO_NULL)))
      {
         return $Quote ? 'NULL' : NULL;
      }

      if($Type === NULL)
      {
         switch(gettype($Value))
         {
            case 'integer':
               $Type = 'integer';
               break;
            case 'double':
               $Type = 'decimal';
               break;
            case 'boolean':
               $Type = 'boolean';
               break;
            case 'array':
               $Value = serialize($Value);
               $Type = 'text';
               break;
            case 'object':
               $Type = 'text';
               break;
            default:
               if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $Value))
               {
                  $Type = 'timestamp';
               }
               elseif(preg_match('/^\d{2}:\d{2}$/', $Value))
               {
                  $Type = 'time';
               }
               elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/', $Value))
               {
                  $Type = 'date';
               }
               else
               {
                  $Type = 'text';
               }
               break;
         }
      }

      switch(strtolower($Type))
      {
         case 'integer':
            return (int)$Value;

         case 'boolean':
            return $Value ? 1 : 0;

         case 'decimal':
         case 'float':
            $Number = self::quoteDecimal($Value);
            if(strtolower($Type) == 'float' && preg_match('/^(.*)e([-+])(\d+)$/i', (string)$Value, $Matches))
            {
               $Mantissa = self::quoteDecimal($Matches[1]);
               $Number = ($Mantissa === NULL) ? NULL : $Mantissa . 'E' . $Matches[2] . str_pad($Matches[3], 2, '0', STR_PAD_LEFT);
            }
            if($Number === NULL)
            {
               return new ArchonSqlsrvError('Invalid numeric value: ' . (is_scalar($Value) ? (string)$Value : gettype($Value)), MDB2_ERROR_INVALID_NUMBER);
            }
            return $Number;

         case 'date':
         case 'timestamp':
         case 'time':
            $Constants = array('date' => 'CURRENT_DATE', 'timestamp' => 'CURRENT_TIMESTAMP', 'time' => 'CURRENT_TIME');
            if($Value === $Constants[strtolower($Type)])
            {
               return strtolower($Type) == 'date' ? 'CONVERT(date, GETDATE())' : (strtolower($Type) == 'time' ? 'CONVERT(time, GETDATE())' : 'CURRENT_TIMESTAMP');
            }
            // fall through to text
         case 'text':
         case 'clob':
            if(is_object($Value))
            {
               if(!method_exists($Value, '__toString'))
               {
                  return new ArchonSqlsrvError('Unable to quote an object of class ' . get_class($Value), MDB2_ERROR_INVALID);
               }
               $Value = (string)$Value;
            }
            elseif(is_bool($Value))
            {
               $Value = $Value ? '1' : '';
            }
            $Value = (string)$Value;
            return $Quote ? "'" . $this->escape($Value, $EscapeWildcards) . "'" : $Value;

         case 'blob':
            return $Quote ? '0x' . bin2hex((string)$Value) : $Value;
      }

      return new ArchonSqlsrvError('type not defined: ' . $Type, MDB2_ERROR_UNSUPPORTED);
   }

   /**
    * Port of MDB2_Driver_Datatype_Common::_quoteDecimal().
    *
    * @param mixed $Value
    * @return string|null NULL when the value is not a valid number
    */
   public static function quoteDecimal($Value)
   {
      $Value = preg_replace('/[^\d\.,\-+eE]/', '', (string)$Value);
      if(preg_match('/[^\.\d]/', $Value))
      {
         if(strpos($Value, ','))
         {
            if(!strpos($Value, '.'))
            {
               // 1000,00
               $Value = strrev(str_replace(',', '.', strrev($Value)));
            }
            elseif(strpos($Value, '.') && strpos($Value, '.') < strpos($Value, ','))
            {
               // 1.000,00
               $Value = str_replace('.', '', $Value);
               $Value = strrev(str_replace(',', '.', strrev($Value)));
            }
            else
            {
               // 1,000.00
               $Value = str_replace(',', '', $Value);
            }
         }
      }

      // MDB2 inlined the result unquoted (malformed numbers gave SQL errors); refuse anything that
      // is not a plain number so that the value can never change the statement.
      if(!preg_match('/^[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?$/', $Value))
      {
         return NULL;
      }

      return $Value;
   }

   public function setLimit($Limit, $Offset = NULL)
   {
      $Limit = (int)$Limit;
      if($Limit < 0)
      {
         return new ArchonSqlsrvError('it was not specified a valid selected range row limit', MDB2_ERROR_SYNTAX);
      }
      $this->limit = $Limit;

      if($Offset !== NULL)
      {
         $Offset = (int)$Offset;
         if($Offset < 0)
         {
            return new ArchonSqlsrvError('it was not specified a valid first selected range row', MDB2_ERROR_SYNTAX);
         }
         $this->offset = $Offset;
      }

      return MDB2_OK;
   }

   /**
    * Port of MDB2_Driver_mssql::_modifyQuery().
    */
   public function modifyQuery($Query, $IsManip, $Limit, $Offset)
   {
      if($Limit > 0 && !$IsManip)
      {
         $Fetch = $Offset + $Limit;
         return preg_replace('/^([\s(])*SELECT( DISTINCT)?(?!\s*TOP\s*\()/i', "\\1SELECT\\2 TOP $Fetch", $Query);
      }

      return $Query;
   }

   public function quoteIdentifier($Identifier, $CheckOption = false)
   {
      $Identifier = str_replace(']', ']]', (string)$Identifier);
      $Parts = explode('.', $Identifier);
      foreach($Parts as $Key => $Part)
      {
         $Parts[$Key] = '[' . $Part . ']';
      }

      return implode('.', $Parts);
   }

   public function escape($Text, $EscapeWildcards = false)
   {
      $Text = (string)$Text;
      if($EscapeWildcards)
      {
         $Text = $this->escapePattern($Text);
      }

      return str_replace("'", "''", $Text);
   }

   public function escapePattern($Text)
   {
      $Text = str_replace('[', '[ [ ]', (string)$Text);
      foreach($this->wildcards as $Wildcard)
      {
         $Text = str_replace($Wildcard, '[' . $Wildcard . ']', $Text);
      }

      return $Text;
   }

   public function setFetchMode($FetchMode, $ObjectClass = 'stdClass')
   {
      $this->fetchmode = $FetchMode;
      return MDB2_OK;
   }

   public function setOption($Option, $Value)
   {
      $this->options[$Option] = $Value;
      return MDB2_OK;
   }

   public function getOption($Option)
   {
      return isset($this->options[$Option]) ? $this->options[$Option] : NULL;
   }

   public function loadModule($Module, $Property = NULL, $PhpTypeSpecific = NULL)
   {
      // Manager, Reverse and Function are implemented directly on this object.
      return $this;
   }

   public function supports($Feature)
   {
      $Supported = array('transactions', 'savepoints', 'limit_queries', 'prepared_statements', 'identifiers_quoting', 'summary_functions', 'order_by_text', 'current_id', 'auto_increment', 'primary_key', 'result_introspection', 'sub_selects', 'LOBs', 'replace');

      return in_array($Feature, $Supported, true);
   }

   public function listTables($Database = NULL)
   {
      $Result = $this->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME NOT IN ('dtproperties', 'sysdiagrams') ORDER BY TABLE_NAME");
      if(self::isError($Result))
      {
         return $Result;
      }

      $Tables = array();
      while($Row = $Result->fetchRow(MDB2_FETCHMODE_ORDERED))
      {
         $Tables[] = $Row[0];
      }
      $Result->free();

      return $Tables;
   }

   public function tableInfo($Table, $Mode = NULL)
   {
      $Query = "SELECT c.COLUMN_NAME, c.DATA_TYPE, c.CHARACTER_MAXIMUM_LENGTH, c.NUMERIC_PRECISION, c.IS_NULLABLE, c.COLUMN_DEFAULT, COLUMNPROPERTY(OBJECT_ID(c.TABLE_SCHEMA + '.' + c.TABLE_NAME), c.COLUMN_NAME, 'IsIdentity') AS IsIdentity, c.COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS c WHERE c.TABLE_NAME = " . $this->quote($Table, 'text') . " ORDER BY c.ORDINAL_POSITION";
      $Result = $this->query($Query);
      if(self::isError($Result))
      {
         return $Result;
      }

      $Info = array();
      while($Row = $Result->fetchRow(MDB2_FETCHMODE_ASSOC))
      {
         $Flags = array();
         $Flags[] = ($Row['IS_NULLABLE'] == 'YES') ? 'null' : 'not_null';
         if($Row['IsIdentity'])
         {
            $Flags[] = 'identity';
            $Flags[] = 'auto_increment';
         }
         if($Row['COLUMN_DEFAULT'] !== NULL)
         {
            $Flags[] = 'default_' . rawurlencode($Row['COLUMN_DEFAULT']);
         }

         $Info[] = array(
            'table' => $Table,
            'name' => $Row['COLUMN_NAME'],
            'type' => $Row['DATA_TYPE'],
            'nativetype' => $Row['DATA_TYPE'],
            'len' => ($Row['CHARACTER_MAXIMUM_LENGTH'] !== NULL) ? $Row['CHARACTER_MAXIMUM_LENGTH'] : $Row['NUMERIC_PRECISION'],
            'flags' => implode(' ', $Flags),
            'collation' => $Row['COLLATION_NAME'],
         );
      }
      $Result->free();

      return $Info;
   }

   public function beginTransaction($Savepoint = NULL)
   {
      if($Savepoint !== NULL)
      {
         return $this->exec('SAVE TRANSACTION ' . $this->quoteIdentifier($Savepoint));
      }
      if($this->in_transaction)
      {
         return MDB2_OK;
      }

      $Connected = $this->ensureConnected();
      if(self::isError($Connected))
      {
         return $Connected;
      }

      $this->debug('BEGIN TRANSACTION', 'beginTransaction', array('is_manip' => true, 'when' => 'pre'));
      if(!sqlsrv_begin_transaction($this->connection))
      {
         return self::createError('Unable to begin transaction');
      }
      $this->in_transaction = true;

      return MDB2_OK;
   }

   public function commit($Savepoint = NULL)
   {
      if(!$this->in_transaction)
      {
         return new ArchonSqlsrvError('commit: transaction changes are being auto committed', MDB2_ERROR_INVALID);
      }
      if($Savepoint !== NULL)
      {
         return MDB2_OK;
      }

      $this->debug('COMMIT TRANSACTION', 'commit', array('is_manip' => true, 'when' => 'pre'));
      if(!sqlsrv_commit($this->connection))
      {
         return self::createError('Unable to commit transaction');
      }
      $this->in_transaction = false;

      return MDB2_OK;
   }

   public function rollback($Savepoint = NULL)
   {
      if(!$this->in_transaction)
      {
         return new ArchonSqlsrvError('rollback: transactions can not be rolled back when changes are auto committed', MDB2_ERROR_INVALID);
      }
      if($Savepoint !== NULL)
      {
         return $this->exec('ROLLBACK TRANSACTION ' . $this->quoteIdentifier($Savepoint));
      }

      $this->debug('ROLLBACK TRANSACTION', 'rollback', array('is_manip' => true, 'when' => 'pre'));
      if(!sqlsrv_rollback($this->connection))
      {
         return self::createError('Unable to roll back transaction');
      }
      $this->in_transaction = false;

      return MDB2_OK;
   }

   public function inTransaction($IgnoreNested = false)
   {
      return $this->in_transaction;
   }

   public function disconnect($Force = true)
   {
      if($this->connection)
      {
         sqlsrv_close($this->connection);
      }
      $this->connection = NULL;
      $this->in_transaction = false;

      return MDB2_OK;
   }

   public function getDebugOutput()
   {
      return implode("\n", $this->DebugOutput);
   }

   public function getServerVersion($Native = false)
   {
      $Info = $this->connection ? sqlsrv_server_info($this->connection) : false;
      if(!$Info)
      {
         return self::createError('Unable to read server information');
      }

      return $Native ? $Info['SQLServerVersion'] : $Info;
   }

   /**
    * Runs a query.  Row-returning queries use a client-buffered cursor so that numRows() works and
    * other queries can run while the rows are being read, as with the mssql extension.
    */
   private function doQuery($Query, $IsManip)
   {
      $Connected = $this->ensureConnected();
      if(self::isError($Connected))
      {
         return $Connected;
      }

      $this->last_query = $Query;
      $this->debug($Query, 'query', array('is_manip' => $IsManip, 'when' => 'pre'));

      $Options = $IsManip ? array() : array('Scrollable' => SQLSRV_CURSOR_CLIENT_BUFFERED);
      $Statement = @sqlsrv_query($this->connection, $Query, array(), $Options);
      if($Statement === false)
      {
         return self::createError('Query failed', MDB2_ERROR, $Query);
      }

      $this->debug($Query, 'query', array('is_manip' => $IsManip, 'when' => 'post'));

      return $Statement;
   }

   private function debug($Message, $Scope, $Context)
   {
      if(count($this->DebugOutput) < 1000)
      {
         $this->DebugOutput[] = $Scope . '(' . $Context['when'] . '): ' . $Message;
      }

      if(is_object($this->QueryLog) && method_exists($this->QueryLog, 'logQuery'))
      {
         $this->QueryLog->logQuery($this, $Scope, $Message, $Context);
      }
   }
}

/**
 * Emulated prepared statement.
 */
class ArchonSqlsrvPreparedStatement
{
   public $db;
   public $query;
   public $positions;
   public $types;
   public $result_types;
   public $is_manip;
   public $limit;
   public $offset;
   public $values = array();

   public function __construct($Database, $Query, $Positions, $Types, $ResultTypes, $IsManip, $Limit, $Offset)
   {
      $this->db = $Database;
      $this->query = $Query;
      $this->positions = $Positions;
      $this->types = (array)$Types;
      $this->result_types = (array)$ResultTypes;
      $this->is_manip = $IsManip;
      $this->limit = $Limit;
      $this->offset = $Offset;
   }

   public function bindValue($Parameter, $Value, $Type = NULL)
   {
      if(!in_array($Parameter, $this->positions))
      {
         return new ArchonSqlsrvError('Unable to bind to missing placeholder: ' . $Parameter, MDB2_ERROR_NOT_FOUND);
      }

      $this->values[$Parameter] = $Value;
      if($Type !== NULL)
      {
         $this->types[$Parameter] = $Type;
      }

      return MDB2_OK;
   }

   /**
    * Executes the statement.  Like MDB2, values for missing placeholders are ignored and values
    * stay bound between executions.
    *
    * @param mixed $Values
    * @return ArchonSqlsrvResult|integer|ArchonSqlsrvError
    */
   public function execute($Values = NULL)
   {
      if($this->positions === NULL)
      {
         return new ArchonSqlsrvError('Prepared statement has already been freed', MDB2_ERROR);
      }

      foreach((array)$Values as $Parameter => $Value)
      {
         if(in_array($Parameter, $this->positions))
         {
            $this->values[$Parameter] = $Value;
         }
      }

      $Query = '';
      $LastPosition = 0;
      foreach($this->positions as $CurrentPosition => $Parameter)
      {
         if(!array_key_exists($Parameter, $this->values))
         {
            return new ArchonSqlsrvError('Unable to bind to missing placeholder: ' . $Parameter, MDB2_ERROR_NOT_FOUND, $this->query);
         }

         $Value = $this->values[$Parameter];
         $Query .= substr($this->query, $LastPosition, $CurrentPosition - $LastPosition);

         if(!isset($Value))
         {
            $Quoted = 'NULL';
         }
         else
         {
            $Type = !empty($this->types[$Parameter]) ? $this->types[$Parameter] : NULL;
            $Quoted = $this->db->quote($Value, $Type);
            if(ArchonSqlsrvDatabase::isError($Quoted))
            {
               return $Quoted;
            }
         }

         $Query .= $Quoted;
         $LastPosition = $CurrentPosition + 1;
      }
      $Query .= substr($this->query, $LastPosition);

      $this->db->limit = $this->limit;
      $this->db->offset = $this->offset;

      return $this->is_manip ? $this->db->exec($Query) : $this->db->query($Query, $this->result_types);
   }

   public function free()
   {
      $this->positions = NULL;
      $this->values = array();

      return MDB2_OK;
   }
}

/**
 * Buffered result set.
 */
class ArchonSqlsrvResult
{
   public $db;
   public $result;
   public $limit;
   public $offset;
   public $rownum = -1;

   private $offsetCount = 0;
   private $fieldNames = array();
   private $binaryFields = array();
   private $numRows = 0;

   public function __construct($Database, $Statement, $Limit = 0, $Offset = 0)
   {
      $this->db = $Database;
      $this->result = $Statement;
      $this->limit = (int)$Limit;
      $this->offset = (int)$Offset;

      // Skip results without columns (row counts of leading statements in a batch).
      while(sqlsrv_num_fields($Statement) === 0 && sqlsrv_next_result($Statement) === true)
      {
      }

      $Metadata = sqlsrv_field_metadata($Statement);
      $Computed = 0;
      if(is_array($Metadata))
      {
         foreach($Metadata as $Index => $Field)
         {
            $Name = $Field['Name'];
            if($Name === '' || $Name === NULL)
            {
               $Name = $Computed ? 'computed' . $Computed : 'computed';
               $Computed++;
            }
            $this->fieldNames[$Index] = $Name;

            // SQL_BINARY, SQL_VARBINARY and SQL_LONGVARBINARY (binary, varbinary, image, timestamp)
            if(in_array($Field['Type'], array(-2, -3, -4), true))
            {
               $this->binaryFields[$Index] = true;
            }
         }
      }

      $Rows = empty($this->fieldNames) ? 0 : sqlsrv_num_rows($Statement);
      $this->numRows = ($Rows === false) ? 0 : $Rows;
   }

   private function skipLimitOffset()
   {
      if($this->limit && $this->rownum >= $this->limit)
      {
         return false;
      }

      if($this->offset)
      {
         while($this->offsetCount < $this->offset)
         {
            ++$this->offsetCount;
            if(!sqlsrv_fetch($this->result))
            {
               $this->offsetCount = $this->limit;
               return false;
            }
         }
      }

      return true;
   }

   /**
    * Fetches the next row, or NULL when there are no more rows.
    *
    * @param integer $FetchMode
    * @param integer $RowNumber
    * @return mixed
    */
   public function fetchRow($FetchMode = MDB2_FETCHMODE_DEFAULT, $RowNumber = NULL)
   {
      if(!$this->result)
      {
         return new ArchonSqlsrvError('resultset has already been freed', MDB2_ERROR_NEED_MORE_DATA);
      }
      if(empty($this->fieldNames))
      {
         return NULL;
      }
      if(!$this->skipLimitOffset())
      {
         return NULL;
      }

      if($RowNumber !== NULL)
      {
         $Values = sqlsrv_fetch_array($this->result, SQLSRV_FETCH_NUMERIC, SQLSRV_SCROLL_ABSOLUTE, (int)$RowNumber + $this->offset);
      }
      else
      {
         $Values = sqlsrv_fetch_array($this->result, SQLSRV_FETCH_NUMERIC);
      }

      if($Values === false)
      {
         return ArchonSqlsrvDatabase::createError('Unable to fetch row');
      }
      if($Values === NULL)
      {
         return NULL;
      }

      $Portability = $this->db->options['portability'];
      foreach($Values as $Index => $Value)
      {
         if($Value === '' && ($Portability & MDB2_PORTABILITY_EMPTY_TO_NULL))
         {
            $Values[$Index] = NULL;
         }
         elseif(is_string($Value) && ($Portability & MDB2_PORTABILITY_RTRIM) && !isset($this->binaryFields[$Index]))
         {
            $Values[$Index] = rtrim($Value);
         }
      }

      if($FetchMode == MDB2_FETCHMODE_DEFAULT)
      {
         $FetchMode = $this->db->fetchmode;
      }

      if($FetchMode == MDB2_FETCHMODE_ASSOC || $FetchMode == MDB2_FETCHMODE_OBJECT)
      {
         $Row = array();
         foreach($Values as $Index => $Value)
         {
            $Row[$this->fieldNames[$Index]] = $Value;
         }
         if($FetchMode == MDB2_FETCHMODE_OBJECT)
         {
            $Row = (object)$Row;
         }
      }
      else
      {
         $Row = $Values;
      }

      ++$this->rownum;

      return $Row;
   }

   public function fetchOne($ColumnNumber = 0, $RowNumber = NULL)
   {
      $Row = $this->fetchRow(MDB2_FETCHMODE_ORDERED, $RowNumber);
      if(!is_array($Row))
      {
         return $Row;
      }

      return array_key_exists($ColumnNumber, $Row) ? $Row[$ColumnNumber] : NULL;
   }

   public function fetchCol($ColumnNumber = 0)
   {
      $Column = array();
      while(is_array($Row = $this->fetchRow(MDB2_FETCHMODE_ORDERED)))
      {
         $Column[] = array_key_exists($ColumnNumber, $Row) ? $Row[$ColumnNumber] : NULL;
      }

      return ArchonSqlsrvDatabase::isError($Row) ? $Row : $Column;
   }

   public function fetchAll($FetchMode = MDB2_FETCHMODE_DEFAULT)
   {
      $Rows = array();
      while(is_array($Row = $this->fetchRow($FetchMode)) || is_object($Row) && !ArchonSqlsrvDatabase::isError($Row))
      {
         $Rows[] = $Row;
      }

      return ArchonSqlsrvDatabase::isError($Row) ? $Row : $Rows;
   }

   /**
    * Port of MDB2_Result_mssql::numRows().
    */
   public function numRows()
   {
      if(!$this->result)
      {
         return new ArchonSqlsrvError('resultset has already been freed', MDB2_ERROR_NEED_MORE_DATA);
      }

      $Rows = $this->numRows;
      if($this->limit)
      {
         $Rows -= $this->offset;
         if($Rows > $this->limit + 1)
         {
            $Rows = $this->limit + 1;
         }
         if($Rows < 0)
         {
            $Rows = 0;
         }
      }

      return $Rows;
   }

   public function numCols()
   {
      return count($this->fieldNames);
   }

   public function getColumnNames($Flip = false)
   {
      $Names = array();
      foreach($this->fieldNames as $Index => $Name)
      {
         $Names[$Name] = $Index;
      }

      return $Flip ? array_flip($Names) : $Names;
   }

   public function free()
   {
      if($this->result)
      {
         sqlsrv_free_stmt($this->result);
      }
      $this->result = false;

      return MDB2_OK;
   }
}
