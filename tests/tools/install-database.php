<?php
/**
 * Installs Archon into the Docker test environment by driving the real web installer
 * (packages/core/install/install.php_) over HTTP, then loads the synthetic fixtures.
 *
 *   php tests/tools/install-database.php --site=/var/www/html/archon --database=archon_test
 *        [--base=http://127.0.0.1/archon/] [--no-fixtures]
 *
 * Runs inside the php82 container (see tests/bin/archon-test install).
 */

$Options = getopt('', array('site:', 'database:', 'base::', 'no-fixtures'));
$SiteDir = rtrim(isset($Options['site']) ? $Options['site'] : '/var/www/html/archon', '/');
$Database = isset($Options['database']) ? $Options['database'] : 'archon_test';
$Base = isset($Options['base']) ? $Options['base'] : 'http://127.0.0.1/' . basename($SiteDir) . '/';

if(!preg_match('/^[A-Za-z0-9_]+$/', $Database))
{
   fwrite(STDERR, "Invalid database name\n");
   exit(1);
}

$Host = getenv('ARCHON_TEST_MSSQL_HOST') ?: 'mssql';
$Login = getenv('ARCHON_TEST_MSSQL_USER') ?: 'sa';
$Secret = getenv('ARCHON_TEST_MSSQL_SECRET');
$AdminSecret = getenv('ARCHON_TEST_ADMIN_SECRET') ?: $Secret;

function step($Message)
{
   echo '[install] ' . $Message . "\n";
}

function fail($Message)
{
   fwrite(STDERR, '[install] ERROR: ' . $Message . "\n");
   exit(1);
}

// 1. Fresh database.
$Master = sqlsrv_connect($Host, array('UID' => $Login, 'PWD' => $Secret, 'Database' => 'master', 'TrustServerCertificate' => true, 'CharacterSet' => 'UTF-8'));
if($Master === false)
{
   fail('cannot connect to SQL Server: ' . print_r(sqlsrv_errors(), true));
}
$Batch = "IF DB_ID('$Database') IS NOT NULL BEGIN ALTER DATABASE [$Database] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE [$Database]; END; CREATE DATABASE [$Database];";
if(sqlsrv_query($Master, $Batch) === false)
{
   fail('cannot create database: ' . print_r(sqlsrv_errors(), true));
}
$Row = sqlsrv_fetch_array(sqlsrv_query($Master, "SELECT CONVERT(varchar(128), DATABASEPROPERTYEX('$Database', 'Collation')) AS c, @@VERSION AS v"), SQLSRV_FETCH_ASSOC);
step("created database $Database (collation {$Row['c']})");
sqlsrv_close($Master);

// 2. Enable the web installer.
if(!copy("$SiteDir/packages/core/install/install.php_", "$SiteDir/packages/core/install/install.php"))
{
   fail('cannot enable install.php');
}

$CookieJar = tempnam(sys_get_temp_dir(), 'archon-install');

function http($Url, $Post = NULL)
{
   global $CookieJar;

   $Curl = curl_init($Url);
   curl_setopt_array($Curl, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_COOKIEJAR => $CookieJar,
      CURLOPT_COOKIEFILE => $CookieJar,
      CURLOPT_TIMEOUT => 900,
   ));
   if($Post !== NULL)
   {
      curl_setopt($Curl, CURLOPT_POST, true);
      curl_setopt($Curl, CURLOPT_POSTFIELDS, http_build_query($Post));
   }
   $Body = curl_exec($Curl);
   $Status = curl_getinfo($Curl, CURLINFO_RESPONSE_CODE);
   curl_close($Curl);

   return array($Status, (string)$Body);
}

function checkPage($Label, $Response)
{
   list($Status, $Body) = $Response;
   if($Status >= 500 || preg_match('/(Fatal error|Parse error|Uncaught )/i', $Body))
   {
      fail("$Label failed (HTTP $Status): " . substr(strip_tags($Body), 0, 2000));
   }
   step("$Label: HTTP $Status");

   return $Body;
}

function waitForProgress($Label)
{
   global $Base;

   $Deadline = time() + 1800;
   $LastMessage = '';
   while(time() < $Deadline)
   {
      list($Status, $Body) = http($Base . 'index.php?p=install&f=dbprogress');
      $Progress = json_decode($Body, true);
      if(is_array($Progress))
      {
         if($Progress['state'] == 'DONE')
         {
            step("$Label: done");
            return;
         }
         if($Progress['state'] == 'ERROR')
         {
            fail("$Label: " . $Progress['message']);
         }
         if($Progress['message'] != $LastMessage && $Progress['message'] !== '' && $Progress['message'] !== NULL)
         {
            $LastMessage = $Progress['message'];
            step("$Label: " . trim($LastMessage));
         }
      }
      elseif($Status >= 500 || preg_match('/(Fatal error|Uncaught )/i', $Body))
      {
         fail("$Label progress check failed (HTTP $Status): " . substr(strip_tags($Body), 0, 2000));
      }
      usleep(500000);
   }

   fail("$Label: timed out");
}

function runExecStep($Label, $Step, $Parameters = array())
{
   global $Base;

   $Parameters = array_merge(array('p' => 'install', 'f' => $Step, 'exec' => 'true'), $Parameters);
   list($Status, $Body) = http($Base . 'index.php', $Parameters);
   if($Status >= 500 || trim($Body) != 'submitted')
   {
      fail("$Label did not start (HTTP $Status): " . substr(strip_tags($Body), 0, 2000));
   }
   waitForProgress($Label);
}

// 3. Core schema and default data.
checkPage('install5 page', http($Base . 'index.php?p=install&f=install5'));
runExecStep('core database', 'install5');

// 4. Administrator account and the SA password.
checkPage('install7 (administrator)', http($Base . 'index.php', array(
   'p' => 'install', 'f' => 'install7',
   'Login' => 'admin', 'Password' => $AdminSecret, 'ConfirmPassword' => $AdminSecret,
   'DisplayName' => 'Test Administrator', 'Email' => 'admin@example.org', 'UsergroupID' => 1,
   'SAPassword' => $AdminSecret, 'ConfirmSAPassword' => $AdminSecret,
)));

$Connection = sqlsrv_connect($Host, array('UID' => $Login, 'PWD' => $Secret, 'Database' => $Database, 'TrustServerCertificate' => true, 'CharacterSet' => 'UTF-8'));
$Result = sqlsrv_query($Connection, "SELECT ID FROM tblCore_Users WHERE Login = 'admin'");
$Row = $Result ? sqlsrv_fetch_array($Result, SQLSRV_FETCH_ASSOC) : NULL;
if(!$Row)
{
   fail('administrator account was not created');
}
$AdminID = $Row['ID'];
step("administrator ID $AdminID");

// 5. First repository.
checkPage('install8 (repository)', http($Base . 'index.php', array(
   'p' => 'install', 'f' => 'install8',
   'Name' => 'University Archives (Test)', 'Code' => 'UA', 'Address' => '1408 W. Gregory Drive',
   'City' => 'Urbana', 'State' => 'IL', 'CountryID' => 226, 'ZIPCode' => '61801', 'ZIPPlusFour' => '',
   'Phone' => '217-333-0798', 'PhoneExtension' => '', 'Fax' => '', 'Email' => 'archives@example.org',
   'URL' => 'https://example.org/archives/', 'UserID' => $AdminID,
)));

// 6. All other packages.
$Packages = array();
foreach(scandir("$SiteDir/packages") as $Dir)
{
   if($Dir != 'core' && $Dir[0] != '.' && file_exists("$SiteDir/packages/$Dir/install/install.php"))
   {
      $Packages[] = $Dir;
   }
}
step('installing packages: ' . implode(', ', $Packages));
checkPage('install9 page', http($Base . 'index.php?p=install&f=install9'));
runExecStep('packages', 'install9', array('install_packages' => $Packages));

// 7. Phrases (English).
checkPage('install10 page', http($Base . 'index.php?p=install&f=install10'));
runExecStep('phrases', 'install11', array('defaultlanguageid' => 2081, 'languageids' => array(2081)));

// 8. Finish.
checkPage('install12 page', http($Base . 'index.php?p=install&f=install12'));
unlink("$SiteDir/packages/core/install/install.php");
@unlink($CookieJar);
step('installer finished');

// 9. Fixtures.
if(!isset($Options['no-fixtures']))
{
   passthru('php ' . escapeshellarg(__DIR__ . '/load-fixtures.php') . ' --site=' . escapeshellarg($SiteDir), $ExitCode);
   if($ExitCode)
   {
      fail('fixture loading failed');
   }
}

step('complete');
