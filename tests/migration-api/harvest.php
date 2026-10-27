<?php
/**
 * Reads every record that archon-migration (ArchonClient.java) reads, the same way it reads them, and saves the
 * responses so that two Archon installs can be compared with compare.php.
 *
 * What it emulates:
 *  - Login: GET ?p=core/authenticate with Basic auth; the reply is {"session":"<id>"}.
 *  - Every later request sends the header "session: <id>" and keeps the cookies the server set. ArchonClient uses one
 *    commons-httpclient instance, which keeps cookies; without the "archon" cookie LiveSession::verifySession() reaches
 *    "new User2x" and the request dies with HTTP 500 on any PHP version.
 *  - Paging: batch_start=1, 101, 201, ... until a body does not start with "{" or "[" (ArchonClient stops silently
 *    there, so an error message, a PHP warning before the JSON or an empty body ends that list early).
 *  - The enum lists, repositories, users, subjects, creators, classifications, accessions, digital content, digital
 *    files, collections, the content of every collection (?p=core/content&cid=N) and every digital file
 *    (?p=core/digitalfileblob&fileid=N). Files are not kept: only their status, size, SHA-256 and last bytes.
 *
 * Usage (PHP 5.4 or later with the curl extension):
 *    ARCHON_PASSWORD=... php harvest.php [--no-blobs] [--no-content] [--insecure] BASE_URL LOGIN OUTDIR
 *
 *    BASE_URL  the Archon index.php, e.g. https://archives.example.edu/archon/index.php
 *    LOGIN     an administrator login (authenticate.php refuses other accounts)
 *    OUTDIR    a new or empty directory
 *
 * The password is read from ARCHON_PASSWORD, or from standard input when that is not set.
 * The requests are the ones the migration tool makes: they read data, but logging in adds a row to tblCore_Sessions
 * and every request updates that row's expiry time.
 *
 * @package Archon
 */

if(PHP_SAPI != 'cli')
{
   exit(1);
}

$Options = array('blobs' => true, 'content' => true, 'insecure' => false);
$Arguments = array();
foreach(array_slice($argv, 1) as $Argument)
{
   if($Argument == '--no-blobs')
   {
      $Options['blobs'] = false;
   }
   elseif($Argument == '--no-content')
   {
      $Options['content'] = false;
   }
   elseif($Argument == '--insecure')
   {
      $Options['insecure'] = true;
   }
   elseif(substr($Argument, 0, 2) == '--')
   {
      fwrite(STDERR, "unknown option $Argument\n");
      exit(2);
   }
   else
   {
      $Arguments[] = $Argument;
   }
}

if(count($Arguments) != 3)
{
   fwrite(STDERR, "usage: ARCHON_PASSWORD=... php harvest.php [--no-blobs] [--no-content] [--insecure] BASE_URL LOGIN OUTDIR\n");
   exit(2);
}
if(!function_exists('curl_init'))
{
   fwrite(STDERR, "the PHP curl extension is required\n");
   exit(2);
}

list($BaseURL, $Login, $OutDir) = $Arguments;
$BaseURL = preg_replace('/\?.*$/', '', $BaseURL);

$Password = getenv('ARCHON_PASSWORD');
if($Password === false || $Password === '')
{
   fwrite(STDERR, "password for $Login: ");
   $Password = rtrim((string)fgets(STDIN), "\r\n");
}

if(is_dir($OutDir) && count(glob("$OutDir/*")))
{
   fwrite(STDERR, "$OutDir is not empty\n");
   exit(2);
}
foreach(array($OutDir, "$OutDir/content", "$OutDir/blobs") as $Dir)
{
   if(!is_dir($Dir) && !mkdir($Dir, 0700, true))
   {
      fwrite(STDERR, "cannot create $Dir\n");
      exit(2);
   }
}

$CookieJar = tempnam(sys_get_temp_dir(), 'archon-harvest-');
register_shutdown_function('unlink', $CookieJar);

$Log = array();
$Counts = array('requests' => 0, 'non_200' => 0, 'lists' => 0, 'records' => 0, 'blobs' => 0);


function harvest_get($URL, $Headers = array(), $Credentials = NULL)
{
   global $CookieJar, $Options, $Counts;

   $Counts['requests']++;

   $Curl = curl_init($URL);
   curl_setopt_array($Curl, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_TIMEOUT => 1800,
      CURLOPT_HTTPHEADER => $Headers,
      CURLOPT_COOKIEFILE => $CookieJar,
      CURLOPT_COOKIEJAR => $CookieJar,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_SSL_VERIFYPEER => !$Options['insecure'],
      CURLOPT_SSL_VERIFYHOST => $Options['insecure'] ? 0 : 2,
   ));
   if($Credentials !== NULL)
   {
      curl_setopt($Curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($Curl, CURLOPT_USERPWD, $Credentials);
   }

   $Response = curl_exec($Curl);
   if($Response === false)
   {
      $Error = curl_error($Curl);
      curl_close($Curl);
      return array(0, '', '', $Error);
   }

   $Status = curl_getinfo($Curl, CURLINFO_HTTP_CODE);
   $HeaderSize = curl_getinfo($Curl, CURLINFO_HEADER_SIZE);
   curl_close($Curl);

   // With "100 Continue" there are several header blocks; keep the last one.
   $Blocks = preg_split("/\r?\n\r?\n/", trim(substr($Response, 0, $HeaderSize)));

   return array($Status, end($Blocks), (string)substr($Response, $HeaderSize), '');
}


function harvest_header($Headers, $Name)
{
   return preg_match('/^' . preg_quote($Name, '/') . ':\s*(.*?)\s*$/mi', $Headers, $Matches) ? $Matches[1] : '';
}


/**
 * One list, page by page, stopping where ArchonClient.getAllRecords() stops. Saved as OUTDIR/NAME.json:
 * [{"batch": 1, "status": 200, "content_type": "...", "body": "..."}, ..., {"batch": N, ..., "terminator": "..."}]
 */
function harvest_list($Name, $Endpoint)
{
   global $BaseURL, $SessionHeader, $OutDir, $Log, $Counts;

   $Counts['lists']++;
   $Pages = array();
   for($Batch = 1; $Batch < 10000000; $Batch += 100)
   {
      list($Status, $Headers, $Body, $Error) = harvest_get($BaseURL . $Endpoint . '&batch_start=' . $Batch, $SessionHeader);
      $Page = array('batch' => $Batch, 'status' => $Status, 'content_type' => harvest_header($Headers, 'Content-Type'));
      if($Error)
      {
         $Page['error'] = $Error;
      }
      if($Status != 200)
      {
         $Counts['non_200']++;
      }

      $Log[] = sprintf("%-28s batch %7d  HTTP %3d  %-32s %9d bytes  starts with %s%s", $Name, $Batch, $Status, $Page['content_type'], strlen($Body), json_encode((string)substr($Body, 0, 1)), $Error ? "  ($Error)" : '');

      if($Status != 200 || $Body === '' || ($Body[0] != '{' && $Body[0] != '['))
      {
         $Page['terminator'] = $Body;
         $Pages[] = $Page;
         break;
      }

      $Page['body'] = $Body;
      $Pages[] = $Page;

      $Decoded = json_decode($Body, true);
      $Counts['records'] += is_array($Decoded) ? count($Decoded) : 0;
   }

   file_put_contents("$OutDir/$Name.json", json_encode($Pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

   return $Pages;
}


function harvest_ids($Pages)
{
   $IDs = array();
   foreach($Pages as $Page)
   {
      $Decoded = isset($Page['body']) ? json_decode($Page['body'], true) : NULL;
      if(!is_array($Decoded))
      {
         continue;
      }
      foreach($Decoded as $Record)
      {
         if(is_array($Record) && isset($Record['ID']))
         {
            $IDs[] = (string)$Record['ID'];
         }
      }
   }

   return array_values(array_unique($IDs));
}


list($Status, $Headers, $Body, $Error) = harvest_get($BaseURL . '?p=core/authenticate', array(), $Login . ':' . $Password);
file_put_contents("$OutDir/_authenticate.txt", "HTTP $Status\n" . preg_replace('/^Set-Cookie:.*$/mi', 'Set-Cookie: (removed)', $Headers) . "\n\n" . preg_replace('/"session":"[^"]*"/', '"session":"(removed)"', $Body) . "\n");
$Reply = json_decode($Body, true);
if(!is_array($Reply) || empty($Reply['session']))
{
   fwrite(STDERR, "login failed: HTTP $Status $Error " . substr($Body, 0, 200) . "\n");
   exit(1);
}
$SessionHeader = array('session: ' . $Reply['session']);

// ArchonClient.getAllEnums(); its "accession types" endpoint is the material types list again.
foreach(array('usergroups', 'subjectsources', 'containertypes', 'creatorsources', 'extentunits', 'filetypes', 'materialtypes', 'processingpriorities', 'countries') as $Enum)
{
   harvest_list("enum_$Enum", "?p=core/enums&enum_type=$Enum");
}

foreach(array('repositories', 'users', 'subjects', 'creators', 'classifications', 'accessions', 'digitalcontent', 'digitalfiles', 'collections') as $List)
{
   $Pages = harvest_list($List, "?p=core/$List");

   if($List == 'collections' && $Options['content'])
   {
      foreach(harvest_ids($Pages) as $CollectionID)
      {
         harvest_list('content/collection_' . preg_replace('/[^0-9A-Za-z_-]/', '_', $CollectionID), '?p=core/content&cid=' . rawurlencode($CollectionID));
      }
   }

   if($List == 'digitalfiles' && $Options['blobs'])
   {
      $Blobs = array();
      foreach(harvest_ids($Pages) as $FileID)
      {
         list($Status, $Headers, $Body, $Error) = harvest_get($BaseURL . '?p=core/digitalfileblob&fileid=' . rawurlencode($FileID), $SessionHeader);
         $Blobs[$FileID] = array('status' => $Status, 'content_type' => harvest_header($Headers, 'Content-Type'), 'bytes' => strlen($Body), 'sha256' => hash('sha256', $Body), 'last_bytes_hex' => bin2hex(substr($Body, -8)), 'error' => $Error);
         $Counts['blobs']++;
         if($Status != 200)
         {
            $Counts['non_200']++;
         }
      }
      file_put_contents("$OutDir/blobs/digitalfileblob.json", json_encode($Blobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
   }
}

file_put_contents("$OutDir/_requests.log", implode("\n", $Log) . "\n");
file_put_contents("$OutDir/_summary.json", json_encode(array('base_url' => $BaseURL, 'finished' => date('c'), 'counts' => $Counts), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

printf("%s: %d requests, %d lists, %d records, %d files, %d responses that were not HTTP 200\n", $OutDir, $Counts['requests'], $Counts['lists'], $Counts['records'], $Counts['blobs'], $Counts['non_200']);
exit($Counts['non_200'] ? 3 : 0);
