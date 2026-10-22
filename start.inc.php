<?php
/**
 * Initalizes the Archon system
 *
 * @package Archon
 * @author Chris Rishel
 */



if(!$_REQUEST)
{
   $_REQUEST = array();
}

$arrP = explode('/', $_REQUEST['p']);

if(file_exists('packages/core/install/install.php') && $arrP[0] != 'admin' && $_REQUEST['p'] != 'install' && $_REQUEST['p'] != 'upgrade')
{
   trigger_error("packages/core/install/install.php MUST be deleted before Archon will function!", E_USER_ERROR);
}

$_ARCHON->DefaultOBLevel = ob_get_level();

if($_FILES)
{   
   $_FILES = array_change_key_case_recursive($_FILES);
}

$_REQUEST = array_change_key_case_recursive($_REQUEST);

$_SERVER['REQUEST_URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "{$_SERVER['SCRIPT_NAME']}?{$_SERVER['QUERY_STRING']}";

if(!$_ARCHON->db->ServerType)
{
   trigger_error("Fatal Error: The database server type is not configured in config.inc.php.", E_USER_ERROR);
}
elseif(!$_ARCHON->db->ServerAddress)
{
   trigger_error("Fatal Error: The database server address is not configured in config.inc.php.", E_USER_ERROR);
}
elseif(!$_ARCHON->db->Login)
{
   trigger_error("Fatal Error: The database server login is not configured in config.inc.php.", E_USER_ERROR);
}

if($_ARCHON->db->ServerType == 'MSSQL')
{
   require_once('packages/core/lib/sqlsrv_mdb2_adapter.inc.php');
   $_ARCHON->QueryLog = New QueryLog();
   $mdb2 = ArchonSqlsrvDatabase::connect($_ARCHON->db, $_ARCHON->QueryLog);
}
else
{
   ob_start();
   $success = include_once('MDB2.php');
   ob_end_clean();

   if($success == false)
   {
      die("MDB2 is either not correctly installed or not in your include paths. <br /><br /> <a href='http://archon.org/mdb2.html'>Click here</a> for more information on how to make sure MDB2 is installed and correctly configured.");
   }

   $phpservertype = in_array($_ARCHON->db->ServerType, array('MySQL', 'MySQLi'), true) ? 'mysqli' : strtolower($_ARCHON->db->ServerType);
   $dbdsn = $phpservertype."://".$_ARCHON->db->Login.":".$_ARCHON->db->Password."@".$_ARCHON->db->ServerAddress."/".$_ARCHON->db->DatabaseName;
   $dbdsn .= "?CharSet=utf8";

   $_ARCHON->QueryLog = New QueryLog();
   $dboptions = array('debug' => 1, 'debug_handler' => array($_ARCHON->QueryLog, 'logQuery'), 'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_FIX_CASE);
   $mdb2 = MDB2::connect($dbdsn, $dboptions);

   if(pear_isError($mdb2))
   {
      echo("Error connecting to database!<br />\n");
      trigger_error($mdb2->getMessage(), E_USER_ERROR);
   }

   $mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);

   $query = "SET NAMES 'utf8'";
   $affected = $mdb2->exec($query);
   if(pear_isError($affected))
   {
      trigger_error($affected->getMessage(), E_USER_ERROR);
   }
   $mdb2->exec("SET max_allowed_packet=1073741824");
}

if(pear_isError($mdb2))
{
   echo("Error connecting to database!<br />\n");
   trigger_error($mdb2->getMessage(), E_USER_ERROR);
}

$_ARCHON->mdb2 =& $mdb2;
if($_ARCHON->db->ServerType == 'MSSQL')
{
   $_ARCHON->mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);
}

if($_ARCHON->mdb2)
{
   if($_REQUEST['p'] != 'install' && $_REQUEST['p'] != 'upgrade')
   {
      $_ARCHON->initialize();
   }
   elseif($_REQUEST['p'] == 'install')
   {
      $_ARCHON->Script = 'packages/core/install/install.php';
   }
   else
   {
      $_ARCHON->Script = 'packages/core/install/upgrade.php';
   }

   if($_ARCHON->Disabled || (!(defined('CONFIG_CORE_PUBLIC_ENABLED') ? CONFIG_CORE_PUBLIC_ENABLED : true) && $arrP[0] != 'admin' && $_REQUEST['p'] != 'install' && $_REQUEST['p'] != 'upgrade'))
   {
      if(!$_ARCHON->Security->userHasAdministrativeAccess())
      {
         include('header.inc.php');
         echo('<div class="center bold">' . CONFIG_CORE_DISABLED_MESSAGE . '</div>');
         include('footer.inc.php');
         exit();
      }
      else
      {
         $_ARCHON->PublicInterface->Header->Message = $_ARCHON->PublicInterface->Header->Message ? $_ARCHON->PublicInterface->Header->Message."\n Archon is currently closed to the public.\n" : "Archon is currently closed to the public.\n";
      }
   }
}

?>
