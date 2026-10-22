<?php
/**
 * Crawls the test site the way the five production instances are used (theme + template set +
 * repository), plus the administrative interface, and reports the PHP diagnostics recorded for
 * every request by tests/docker/php/error-capture.php.
 *
 * Run inside the php82 container (tests/bin/archon-test crawl):
 *
 *   php crawl.php --base=http://127.0.0.1/archon/ --site=/var/www/html/archon
 *                 [--report=/var/log/archon/crawl-report.json] [--profiles=ihlc,admin]
 *                 [--max-per-profile=300] [--max-per-route=3]
 *
 * Only GET requests are made, except the administrator login. URLs whose f= action looks like it
 * changes data (store, delete, logout, ...) are never requested.
 *
 * Exit status: 0 = no fatal errors and no HTTP 5xx, 1 = problems found, 2 = setup error.
 */

$Options = getopt('', array('base:', 'site:', 'report:', 'profiles:', 'max-per-profile:', 'max-per-route:', 'diagnostics:'));
$Base = rtrim(isset($Options['base']) ? $Options['base'] : 'http://127.0.0.1/archon/', '/') . '/';
$SiteDir = isset($Options['site']) ? rtrim($Options['site'], '/') : '/var/www/html/archon';
$ReportFile = isset($Options['report']) ? $Options['report'] : NULL;
$MaxPerProfile = isset($Options['max-per-profile']) ? (int)$Options['max-per-profile'] : 300;
$MaxPerRoute = isset($Options['max-per-route']) ? (int)$Options['max-per-route'] : 3;
$DiagnosticsFile = isset($Options['diagnostics']) ? $Options['diagnostics'] : (getenv('ARCHON_TEST_DIAGNOSTICS') ?: '/var/log/archon/diagnostics.jsonl');
$AdminLogin = getenv('ARCHON_TEST_ADMIN_LOGIN') ?: 'admin';
$AdminSecret = getenv('ARCHON_TEST_ADMIN_SECRET') ?: getenv('ARCHON_TEST_MSSQL_SECRET');

// The production instances (see tests/research/live-survey/README.md).
$Profiles = array(
   'default' => array('setup' => array('unsettheme' => 1, 'unsetrepositoryid' => 1), 'params' => array()),
   'ihlc' => array('setup' => array('settheme' => 'ihlc', 'setrepositoryid' => 0), 'params' => array('templateset' => 'ihlc')),
   'library_web' => array('setup' => array('settheme' => 'library_web', 'setrepositoryid' => 0), 'params' => array('templateset' => 'illinois')),
   'sousa2' => array('setup' => array('settheme' => 'sousa2', 'setrepositoryid' => 2), 'params' => array('templateset' => 'illinois')),
   'rbml' => array('setup' => array('settheme' => 'rbml', 'setrepositoryid' => 0), 'params' => array('templateset' => 'rbml')),
   'ala' => array('setup' => array('settheme' => 'ala_redo', 'setrepositoryid' => 0), 'params' => array('templateset' => 'ala')),
   'admin' => array('admin' => true, 'setup' => array(), 'params' => array()),
);
if(!empty($Options['profiles']))
{
   $Profiles = array_intersect_key($Profiles, array_flip(explode(',', $Options['profiles'])));
}

// f= actions that change data or end the session.
$UnsafeAction = '/(^|_)(store|save|delete|remove|logout|add|update|insert|unset|clear|send|email|upload|import|install|uninstall|upgrade|enable|disable|reset|merge|move|copy|process|exec|purge|cache|register|setpassword)/i';

function fetch($Url, $CookieJar, $Post = NULL)
{
   $Curl = curl_init($Url);
   curl_setopt_array($Curl, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_COOKIEJAR => $CookieJar,
      CURLOPT_COOKIEFILE => $CookieJar,
      CURLOPT_TIMEOUT => 300,
      CURLOPT_HEADER => false,
   ));
   if($Post !== NULL)
   {
      curl_setopt($Curl, CURLOPT_POST, true);
      curl_setopt($Curl, CURLOPT_POSTFIELDS, http_build_query($Post));
   }
   $Start = microtime(true);
   $Body = curl_exec($Curl);
   $Result = array(
      'status' => curl_getinfo($Curl, CURLINFO_RESPONSE_CODE),
      'type' => (string)curl_getinfo($Curl, CURLINFO_CONTENT_TYPE),
      'location' => (string)curl_getinfo($Curl, CURLINFO_REDIRECT_URL),
      'seconds' => round(microtime(true) - $Start, 3),
      'body' => (string)$Body,
   );
   curl_close($Curl);

   return $Result;
}

function withParams($Url, $Params)
{
   if(empty($Params))
   {
      return $Url;
   }
   $Parts = parse_url($Url);
   parse_str(isset($Parts['query']) ? $Parts['query'] : '', $Query);
   foreach($Params as $Name => $Value)
   {
      isset($Query[$Name]) or $Query[$Name] = $Value;
   }

   return strtok($Url, '?') . '?' . http_build_query($Query);
}

// One signature per route and parameter-name set, so each distinct page type is sampled a few times.
function routeSignature($Url)
{
   $Parts = parse_url($Url);
   parse_str(isset($Parts['query']) ? $Parts['query'] : '', $Query);
   $P = isset($Query['p']) ? $Query['p'] : 'core/index';
   $F = isset($Query['f']) ? $Query['f'] : '';
   unset($Query['p'], $Query['f'], $Query['templateset']);
   $Names = array_keys($Query);
   sort($Names);

   return "$P|$F|" . implode(',', $Names);
}

function extractLinks($Body, $PageUrl, $Base)
{
   $Links = array();
   if(!preg_match_all('/(?:href|src|action)\s*=\s*["\']([^"\'#]+)/i', $Body, $Matches))
   {
      return $Links;
   }
   foreach($Matches[1] as $Href)
   {
      $Href = html_entity_decode(trim($Href), ENT_QUOTES, 'UTF-8');
      if($Href === '' || preg_match('/^(javascript|mailto|data):/i', $Href))
      {
         continue;
      }
      if(preg_match('#^https?://#i', $Href))
      {
         if(strpos($Href, $Base) !== 0)
         {
            continue;
         }
         $Href = substr($Href, strlen($Base));
      }
      $Href = ltrim($Href, '/');
      $BasePath = parse_url($Base, PHP_URL_PATH);
      if($BasePath && strpos('/' . $Href, $BasePath) === 0)
      {
         $Href = substr('/' . $Href, strlen($BasePath));
      }
      if(!preg_match('/^(index\.php)?\?/', $Href))
      {
         continue;
      }
      $Links[] = $Base . (strpos($Href, 'index.php') === 0 ? $Href : 'index.php' . $Href);
   }

   return array_unique($Links);
}

function readDiagnostics($File, $Offset)
{
   clearstatcache();
   $Entries = array();
   if(!is_readable($File) || filesize($File) <= $Offset)
   {
      return $Entries;
   }
   $Handle = fopen($File, 'r');
   fseek($Handle, $Offset);
   while(($Line = fgets($Handle)) !== false)
   {
      $Entry = json_decode($Line, true);
      if(is_array($Entry))
      {
         $Entries[] = $Entry;
      }
   }
   fclose($Handle);

   return $Entries;
}

if(!function_exists('curl_init'))
{
   fwrite(STDERR, "crawl.php needs the curl extension\n");
   exit(2);
}

// Seeds: the home page plus every public script (or admin module) of the installed code.
$PublicSeeds = array($Base . 'index.php');
foreach(glob("$SiteDir/packages/*/pub/*.php") as $Script)
{
   $Package = basename(dirname(dirname($Script)));
   $Name = basename($Script, '.php');
   if($Name == 'index' || ($Package == 'core' && $Name == 'login'))
   {
      continue;
   }
   $PublicSeeds[] = $Base . "index.php?p=$Package/$Name";
}
$AdminSeeds = array($Base . 'index.php?p=admin/core/home');
foreach(glob("$SiteDir/packages/*/admin/*.php") as $Script)
{
   $Package = basename(dirname(dirname($Script)));
   $Name = basename($Script, '.php');
   if(in_array($Name, array('index', 'login', 'login.old', 'ajax')))
   {
      continue;
   }
   $AdminSeeds[] = $Base . "index.php?p=admin/$Package/$Name";
   $AdminSeeds[] = $Base . "index.php?p=admin/$Package/$Name&id=1";
}

$Fatal = array('E_ERROR', 'E_PARSE', 'E_CORE_ERROR', 'E_COMPILE_ERROR', 'E_USER_ERROR', 'E_RECOVERABLE_ERROR');
$Report = array('base' => $Base, 'started' => date('c'), 'profiles' => array());
$Problems = 0;

foreach($Profiles as $ProfileName => $Profile)
{
   $CookieJar = tempnam(sys_get_temp_dir(), 'archon-crawl');
   $Admin = !empty($Profile['admin']);
   clearstatcache();
   $ProfileOffset = is_file($DiagnosticsFile) ? filesize($DiagnosticsFile) : 0;

   if($Admin)
   {
      if(!$AdminSecret)
      {
         fwrite(STDERR, "admin profile skipped: set ARCHON_TEST_ADMIN_SECRET\n");
         continue;
      }
      $Login = fetch($Base . 'index.php?p=admin/core/home', $CookieJar, array('archonlogin' => $AdminLogin, 'archonpassword' => $AdminSecret));
      if($Login['status'] >= 400 || stripos($Login['body'], 'archonlogin') !== false)
      {
         fwrite(STDERR, "admin login failed (HTTP {$Login['status']})\n");
         $Problems++;
      }
      $Queue = $AdminSeeds;
   }
   else
   {
      fetch(withParams($Base . 'index.php', $Profile['setup']), $CookieJar);
      $Queue = $PublicSeeds;
   }

   $Seen = array();
   $RouteCounts = array();
   $Pages = array();
   while($Queue && count($Pages) < $MaxPerProfile)
   {
      $Url = withParams(array_shift($Queue), $Profile['params']);
      if(isset($Seen[$Url]))
      {
         continue;
      }
      $Seen[$Url] = true;

      parse_str((string)parse_url($Url, PHP_URL_QUERY), $Query);
      $P = isset($Query['p']) ? $Query['p'] : '';
      if((isset($Query['f']) && preg_match($UnsafeAction, $Query['f'])) || preg_match('/(logout|unsetall|delete)/i', $Url))
      {
         continue;
      }
      if(!$Admin && strpos($P, 'admin/') === 0)
      {
         continue;
      }
      if($Admin && strpos($P, 'admin/') !== 0)
      {
         continue;
      }
      $Signature = routeSignature($Url);
      $RouteCounts[$Signature] = isset($RouteCounts[$Signature]) ? $RouteCounts[$Signature] + 1 : 1;
      if($RouteCounts[$Signature] > $MaxPerRoute)
      {
         continue;
      }

      $Response = fetch($Url, $CookieJar);
      $Page = array(
         'url' => $Url,
         'status' => $Response['status'],
         'type' => $Response['type'],
         'bytes' => strlen($Response['body']),
         'seconds' => $Response['seconds'],
      );
      if($Response['location'])
      {
         $Page['location'] = $Response['location'];
      }
      if(preg_match('/<b>(Fatal error|Parse error|Warning|Notice|Deprecated)<\/b>:/', $Response['body'], $Match))
      {
         $Page['visible_php_message'] = $Match[1];
      }
      $Pages[] = $Page;

      if(strpos($Response['type'], 'html') !== false || strpos($Response['type'], 'xml') !== false)
      {
         foreach(extractLinks($Response['body'], $Url, $Base) as $Link)
         {
            isset($Seen[withParams($Link, $Profile['params'])]) or $Queue[] = $Link;
         }
      }
   }

   // Attribute the diagnostics recorded during this profile to the requests that produced them.
   $ByRequest = array();
   foreach(readDiagnostics($DiagnosticsFile, $ProfileOffset) as $Entry)
   {
      $Request = isset($Entry['request']) ? $Entry['request'] : '';
      $ByRequest[$Request][] = $Entry;
   }
   $Summary = array('requests' => count($Pages), 'http_5xx' => 0, 'fatal' => 0, 'warning' => 0, 'deprecated' => 0, 'notice' => 0);
   $FatalList = array();
   foreach($Pages as &$Page)
   {
      $Path = parse_url($Page['url'], PHP_URL_PATH) . '?' . parse_url($Page['url'], PHP_URL_QUERY);
      $Entries = isset($ByRequest["GET $Path"]) ? $ByRequest["GET $Path"] : array();
      $Page['diagnostics'] = array();
      foreach($Entries as $Entry)
      {
         $Type = $Entry['type'];
         $Key = in_array($Type, $Fatal) ? 'fatal' : (strpos($Type, 'DEPRECATED') !== false ? 'deprecated' : (strpos($Type, 'WARNING') !== false ? 'warning' : 'notice'));
         $Summary[$Key]++;
         $Page['diagnostics'][] = "$Type {$Entry['file']}:{$Entry['line']} " . strtok($Entry['message'], "\n");
         if($Key == 'fatal')
         {
            $FatalList["{$Entry['file']}:{$Entry['line']}"] = array('message' => strtok($Entry['message'], "\n"), 'url' => $Page['url']);
         }
      }
      if($Page['status'] >= 500)
      {
         $Summary['http_5xx']++;
      }
   }
   unset($Page);

   $Problems += $Summary['fatal'] + $Summary['http_5xx'];
   $Report['profiles'][$ProfileName] = array('summary' => $Summary, 'fatal' => $FatalList, 'pages' => $Pages);
   printf("%-12s %4d requests  %3d HTTP 5xx  %3d fatal  %4d warning  %4d deprecated\n", $ProfileName, $Summary['requests'], $Summary['http_5xx'], $Summary['fatal'], $Summary['warning'], $Summary['deprecated']);
   foreach($FatalList as $Location => $Fatal5)
   {
      printf("    FATAL %s %s\n          <- %s\n", $Location, substr($Fatal5['message'], 0, 300), $Fatal5['url']);
   }
   foreach($Pages as $Page)
   {
      if($Page['status'] >= 500 && empty($Page['diagnostics']))
      {
         printf("    HTTP %d without diagnostics <- %s\n", $Page['status'], $Page['url']);
      }
   }
   @unlink($CookieJar);
}

$Report['finished'] = date('c');
if($ReportFile)
{
   file_put_contents($ReportFile, json_encode($Report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
   echo "report: $ReportFile\n";
}

exit($Problems ? 1 : 0);
