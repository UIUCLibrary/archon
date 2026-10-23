<?php
/**
 * Loads synthetic sample data shaped like one of the UIUC Archon installs into a freshly installed Archon database.
 *
 *   php tests/sample-data/generate.php --database=archon_ihlc --profile=ihlc [--size=small|standard|large] [--seed=N]
 *
 * Connection settings come from the environment: ARCHON_TEST_MSSQL_HOST (default "mssql"), ARCHON_TEST_MSSQL_USER
 * (default "sa") and ARCHON_TEST_MSSQL_SECRET. ARCHON_TEST_ADMIN_SECRET is the password given to the teststaff,
 * testreader and testresearcher accounts. Everything is written in one transaction; the script refuses to run
 * against a database that already has collections.
 */
require_once __DIR__ . '/lib/SampleDatabase.php';
require_once __DIR__ . '/lib/SampleText.php';
require_once __DIR__ . '/lib/SampleFiles.php';
require_once __DIR__ . '/lib/SampleGenerator.php';

$Options = getopt('', array('database:', 'profile:', 'size::', 'seed::', 'help'));
if(isset($Options['help']) || empty($Options['database']) || empty($Options['profile']))
{
   fwrite(STDERR, "usage: php generate.php --database=NAME --profile=archives|ihlc|rbml|ala [--size=small|standard|large] [--seed=N]\n");
   exit(isset($Options['help']) ? 0 : 2);
}
$Profile = $Options['profile'];
$Size = isset($Options['size']) && $Options['size'] !== false ? $Options['size'] : 'standard';
$Seed = isset($Options['seed']) && $Options['seed'] !== false ? (int)$Options['seed'] : crc32("$Profile.$Size");
$Secret = getenv('ARCHON_TEST_ADMIN_SECRET');
if($Secret === false || $Secret === '')
{
   fwrite(STDERR, "ARCHON_TEST_ADMIN_SECRET must be set (the password for the sample staff and researcher accounts)\n");
   exit(2);
}
if(!extension_loaded('sqlsrv') || !extension_loaded('gd'))
{
   fwrite(STDERR, "the sqlsrv and gd extensions are required\n");
   exit(2);
}

$Host = getenv('ARCHON_TEST_MSSQL_HOST') ?: 'mssql';
$Login = getenv('ARCHON_TEST_MSSQL_USER') ?: 'sa';
$DbSecret = getenv('ARCHON_TEST_MSSQL_SECRET');
ini_set('memory_limit', '1024M');
mt_srand($Seed);
$Started = microtime(true);

try
{
   $Db = new SampleDatabase($Host, $Login, $DbSecret, $Options['database']);
   $Generator = new SampleGenerator($Db, $Profile, $Size, $Secret);
   echo "Generating '$Profile' sample data ($Size, seed $Seed) in {$Options['database']}\n";
   $Db->begin();
   try
   {
      $Generator->run();
      $Db->commit();
   }
   catch(Throwable $e)
   {
      $Db->rollback();
      throw $e;
   }
}
catch(Throwable $e)
{
   fwrite(STDERR, 'sample data failed: ' . $e->getMessage() . "\n");
   exit(1);
}

$Counts = $Db->counts();
ksort($Counts);
foreach($Counts as $Table => $Rows)
{
   printf("  %-52s %7d\n", $Table, $Rows);
}
printf("Done in %.1f s\n", microtime(true) - $Started);
