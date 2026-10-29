<?php
/**
 * Includes file for Archon
 *
 * @package Archon
 * @author Chris Rishel
 */

// do a check to see if '.' is in the include path
// ini_set('include_path', '.:' . get_include_path());


if(defined('E_DEPRECATED'))
{
   // This is the recommended verbosity for production. If you are an Archon
   // developer, or you are debugging an Archon problem, set this to E_ALL.
   // Every fatal type, so that Archon's own fatal errors (trigger_error(..., E_USER_ERROR),
   // e.g. "Could not load Archon: ...") reach the error log instead of ending in an empty HTTP 500.
   error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
}
else
{
   error_reporting(E_ALL ^ E_NOTICE);
}

if(file_exists('cryptor.inc.php'))
{
   require_once('cryptor.inc.php');
}
else
{
   require_once('cryptorblank.inc.php');
}

// Let core installer handle it's own includes.
if($_REQUEST['p'] == 'install')
{
   require_once('packages/core/install/install.php');
   return;
}
elseif($_REQUEST['p'] == 'upgrade')
{
   require_once('packages/core/install/upgrade.php');
   return;
}

$arrP = explode('/', $_REQUEST['p']);

if(file_exists('packages/core/install/install.php') && $arrP[0] != 'admin' && $_REQUEST['p'] != 'install')
{
   header('Location: index.php?p=install');
}

if(!file_exists('packages/core/index.php'))
{
   die('The Archon Core could not be found in packages/core/');
}

require_once('common.inc.php');


$cwd = getcwd();
chdir('packages/core/lib/');
require_once('index.php');
chdir($cwd);

require_once('config.inc.php');
require_once('start.inc.php');
?>
