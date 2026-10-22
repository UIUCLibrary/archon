<?php

define('SQLSRV_PARAM_IN', 1);
define('SQLSRV_PHPTYPE_INT', 2);
define('SQLSRV_SQLTYPE_INT', 3);
define('SQLSRV_SQLTYPE_BIT', 4);
define('SQLSRV_SQLTYPE_DATE', 5);
define('SQLSRV_CURSOR_STATIC', 6);
define('SQLSRV_FETCH_ASSOC', 7);

function SQLSRV_PHPTYPE_STRING($Encoding)
{
   return $Encoding;
}

function SQLSRV_SQLTYPE_NVARCHAR($Length)
{
   return $Length;
}

function sqlsrv_connect($Server, $Options)
{
   $GLOBALS['sqlsrvConnectionOptions'] = $Options;
   return $Server;
}

function sqlsrv_close($Connection)
{
   return true;
}

function sqlsrv_query($Connection, $Query, $Parameters = array(), $Options = array())
{
   $GLOBALS['sqlsrvLastQuery'] = $Query;
   $GLOBALS['sqlsrvLastParameters'] = $Parameters;
   $GLOBALS['sqlsrvLastOptions'] = $Options;

   if(strpos($Query, 'FAIL') !== false)
   {
      return false;
   }
   elseif(strpos($Query, 'sys.columns') !== false)
   {
      $Rows = array(array('name' => 'ID', 'type' => 'int', 'len' => 4, 'nullable' => false, 'identity' => true));
   }
   elseif(strpos($Query, 'sys.tables') !== false)
   {
      $Rows = array(array('name' => 'tblExample'));
   }
   elseif(strpos($Query, 'SELECT') === 0)
   {
      $Rows = array(array('ID' => 12, 'Title' => 'Test'));
   }
   else
   {
      $Rows = array();
   }

   return (object)array('rows' => $Rows, 'position' => 0);
}

function sqlsrv_rows_affected($Statement)
{
   return 1;
}

function sqlsrv_fetch_array($Statement, $FetchType)
{
   if(!isset($Statement->rows[$Statement->position]))
   {
      return null;
   }
   return $Statement->rows[$Statement->position++];
}

function sqlsrv_num_rows($Statement)
{
   return count($Statement->rows);
}

function sqlsrv_free_stmt($Statement)
{
   return true;
}

function sqlsrv_errors()
{
   return array();
}

function sqlsrv_begin_transaction($Connection)
{
   return true;
}

function sqlsrv_commit($Connection)
{
   return true;
}

function sqlsrv_rollback($Connection)
{
   return true;
}

require_once(__DIR__ . '/../../patches.php');
require_once(__DIR__ . '/../../packages/core/lib/sqlsrv_mdb2_adapter.inc.php');

class SqlsrvAdapterTestQueryLog
{
   public function logQuery(&$Database, $Scope, $Message, $IsManip = NULL)
   {
      $this->Count++;
   }

   public $Count = 0;
}

$Configuration = (object)array('ServerAddress' => 'testserver', 'DatabaseName' => 'archon', 'Login' => 'archon', 'Password' => 'test');
$QueryLog = new SqlsrvAdapterTestQueryLog();
$Database = ArchonSqlsrvDatabase::connect($Configuration, $QueryLog);
if(pear_isError($Database) || $GLOBALS['sqlsrvConnectionOptions']['CharacterSet'] != 'UTF-8')
{
   throw new RuntimeException('SQL Server connection setup failed.');
}

if($Database->quoteIdentifier('dbo.tbl]Example') != '[dbo].[tbl]]Example]')
{
   throw new RuntimeException('Identifier quoting failed.');
}

$Database->setLimit(1);
$Prepared = $Database->prepare('SELECT ID, Title FROM tblExample WHERE ID = ?', array('integer'), MDB2_PREPARE_RESULT);
$Result = $Prepared->execute(12);
if(pear_isError($Result) || $GLOBALS['sqlsrvLastQuery'] != 'SELECT TOP (1) ID, Title FROM tblExample WHERE ID = ?')
{
   throw new RuntimeException('Prepared query/limit failed.');
}
if($Result->numRows() != 1 || $Result->fetchRow()['ID'] != 12)
{
   throw new RuntimeException('Result fetching failed.');
}
$Result->free();

$Prepared = $Database->prepare('UPDATE tblExample SET Title = ? WHERE ID = ?', array('text', 'integer'), MDB2_PREPARE_MANIP);
$Affected = $Prepared->execute(array('Test', 12));
if($Affected != 1 || $GLOBALS['sqlsrvLastParameters'][0][0] != 'Test' || $GLOBALS['sqlsrvLastParameters'][1][0] != 12)
{
   throw new RuntimeException('Prepared update or parameter conversion failed.');
}

$Database->setLimit(2, 5);
$Database->query('SELECT ID FROM tblExample ORDER BY ID');
if($GLOBALS['sqlsrvLastQuery'] != 'SELECT ID FROM tblExample ORDER BY ID OFFSET 5 ROWS FETCH NEXT 2 ROWS ONLY')
{
   throw new RuntimeException('Offset pagination failed.');
}

$Tables = $Database->listTables();
if($Tables != array('tblExample'))
{
   throw new RuntimeException('Table listing failed.');
}

$Columns = $Database->tableInfo('tblExample');
if($Columns[0]['name'] != 'ID' || strpos($Columns[0]['flags'], 'auto_increment') === false)
{
   throw new RuntimeException('Table metadata lookup failed.');
}

if(!$Database->supports('transactions') || !$Database->beginTransaction() || !$Database->inTransaction() || !$Database->commit() || $Database->inTransaction() || !$QueryLog->Count)
{
   throw new RuntimeException('Transactions or query logging failed.');
}

if(!pear_isError($Database->query('FAIL')))
{
   throw new RuntimeException('SQL Server errors were not reported in the PEAR-compatible format.');
}

echo "SQL Server adapter tests passed\n";
