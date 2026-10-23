<?php
/**
 * Archon CRUD harness.
 *
 * Drives the administrative and public interfaces the way a browser running admin.js does.
 * For each module it loads the edit form, submits the successful controls of #mainform
 * (same rules as the jQuery Form plugin) with test values, and verifies every create, read,
 * update and delete twice: through the interface (the XML response and the re-rendered
 * form) and directly in the database. The modification log, public pages and relation
 * tables are checked as well. PHP diagnostics recorded by the test image
 * (tests/docker/php/error-capture.php) are attributed to the request that produced them.
 *
 * Usage (inside the php82 container; normally through `tests/bin/archon-test crud`):
 *   php tests/tools/crud.php [--base=URL] [--database=NAME] [--only=REGEX]
 *       [--discover] [--keep] [--report=FILE] [--verbose]
 *
 *   --only=REGEX   run only the scenarios whose name matches (e.g. --only='collections')
 *   --discover     print the parsed edit form of every admin module and exit
 *   --keep         do not delete the records created by the scenarios
 *
 * Exit status: 0 when every check passed and no fatal error was recorded, 1 otherwise.
 */

$Options = getopt('', array('base:', 'site:', 'database:', 'only:', 'discover', 'keep', 'report:', 'diagnostics:', 'verbose'));
$Base = rtrim(isset($Options['base']) ? $Options['base'] : 'http://127.0.0.1/archon/', '/') . '/';
$Database = isset($Options['database']) ? $Options['database'] : (getenv('ARCHON_TEST_DATABASE') ?: 'archon_test');
$Only = isset($Options['only']) ? $Options['only'] : NULL;
$Keep = isset($Options['keep']);
$Verbose = isset($Options['verbose']);
$ReportFile = isset($Options['report']) ? $Options['report'] : NULL;
$DiagnosticsFile = isset($Options['diagnostics']) ? $Options['diagnostics'] : (getenv('ARCHON_TEST_DIAGNOSTICS') ?: '/var/log/archon/diagnostics.jsonl');
$AdminLogin = getenv('ARCHON_TEST_ADMIN_LOGIN') ?: 'admin';
$AdminSecret = getenv('ARCHON_TEST_ADMIN_SECRET') ?: getenv('ARCHON_TEST_MSSQL_SECRET');

if(!function_exists('curl_init') || !function_exists('sqlsrv_connect'))
{
   fwrite(STDERR, "crud.php needs the curl and sqlsrv extensions (run it in the php82 container)\n");
   exit(2);
}
if($Only !== NULL && @preg_match('/' . str_replace('/', '\/', $Only) . '/i', '') === false)
{
   fwrite(STDERR, "invalid --only expression\n");
   exit(2);
}

const FATAL_TYPES = array('E_ERROR', 'E_PARSE', 'E_CORE_ERROR', 'E_COMPILE_ERROR', 'E_USER_ERROR', 'E_RECOVERABLE_ERROR');
const WARNING_TYPES = array('E_WARNING', 'E_CORE_WARNING', 'E_COMPILE_WARNING', 'E_USER_WARNING');

// PHP 8 raised these former notices (or silent null reads) to warnings; the result is unchanged
// (NULL), so they are counted but not reported individually unless --verbose is given.
const LEGACY_NOTICE_PATTERN = '/^(Undefined (array key|property|variable|offset|index)|Attempt to read property "[^"]*" on (null|bool|int|float|string|array)|Trying to access array offset on (value of type )?(null|bool|int|float)|Trying to get property)/';

// Characters that survive a varchar (code page 1252) column, as in production (FreeTDS client charset UTF-8).
const CP1252_TEXT = "Ünïcødé – “q” ‘s’ … € ©";

/* ------------------------------------------------------------------------------------------
 * Reporting
 * ---------------------------------------------------------------------------------------- */

final class Report
{
   public static $Scenarios = array();
   public static $Current = NULL;
   public static $Failures = 0;
   public static $Fatals = 0;
   public static $Warnings = 0;
   public static $LegacyNotices = 0;
   public static $Diagnostics = array();

   public static function begin($Name)
   {
      self::$Current = array('name' => $Name, 'status' => 'pass', 'steps' => array(), 'notes' => array());
      echo "\n== $Name\n";
   }

   public static function end()
   {
      if(self::$Current === NULL)
      {
         return;
      }
      self::$Scenarios[] = self::$Current;
      printf("   => %s\n", strtoupper(self::$Current['status']));
      self::$Current = NULL;
   }

   public static function step($Name, $Ok, $Detail = '', $Extra = array())
   {
      $Step = array('step' => $Name, 'ok' => (bool)$Ok, 'detail' => $Detail) + $Extra;
      if(self::$Current !== NULL)
      {
         self::$Current['steps'][] = $Step;
         if(!$Ok)
         {
            self::$Current['status'] = 'fail';
         }
      }
      if(!$Ok)
      {
         self::$Failures++;
      }
      if(!$Ok || $GLOBALS['Verbose'])
      {
         printf("   %s %s%s\n", $Ok ? 'ok  ' : 'FAIL', $Name, $Detail !== '' ? " - $Detail" : '');
      }

      return (bool)$Ok;
   }

   public static function note($Text)
   {
      if(self::$Current !== NULL)
      {
         self::$Current['notes'][] = $Text;
      }
      echo "   note $Text\n";
   }

   public static function diagnostics($Request, array $Entries)
   {
      foreach($Entries as $Entry)
      {
         $Type = isset($Entry['type']) ? $Entry['type'] : '?';
         $Where = (isset($Entry['file']) ? $Entry['file'] : '?') . ':' . (isset($Entry['line']) ? $Entry['line'] : '?');
         $Message = strtok(isset($Entry['message']) ? $Entry['message'] : '', "\n");
         $Key = "$Type $Where $Message";
         if(!isset(self::$Diagnostics[$Key]))
         {
            self::$Diagnostics[$Key] = array('type' => $Type, 'where' => $Where, 'message' => $Message, 'count' => 0, 'first_request' => $Request,
               'scenario' => self::$Current ? self::$Current['name'] : NULL, 'trace' => isset($Entry['trace']) ? $Entry['trace'] : array());
            if(in_array($Type, FATAL_TYPES, true))
            {
               self::$Fatals++;
               printf("   FATAL %s %s <- %s\n", $Where, $Message, $Request);
               if(self::$Current !== NULL)
               {
                  self::$Current['status'] = 'fail';
               }
            }
            elseif(in_array($Type, WARNING_TYPES, true) && !preg_match(LEGACY_NOTICE_PATTERN, $Message))
            {
               self::$Warnings++;
               printf("   warn  %s %s <- %s\n", $Where, $Message, $Request);
            }
            elseif(in_array($Type, WARNING_TYPES, true))
            {
               self::$LegacyNotices++;
               self::$Diagnostics[$Key]['legacy_notice'] = true;
               if($GLOBALS['Verbose'])
               {
                  printf("   notice %s %s\n", $Where, $Message);
               }
            }
            elseif($GLOBALS['Verbose'])
            {
               printf("   %-5s %s %s\n", strtolower(substr($Type, 2, 5)), $Where, $Message);
            }
         }
         self::$Diagnostics[$Key]['count'] += isset($Entry['count']) ? (int)$Entry['count'] : 1;
      }
   }
}

/* ------------------------------------------------------------------------------------------
 * HTTP client (one cookie jar per client, like one browser session)
 * ---------------------------------------------------------------------------------------- */

final class Client
{
   public $Base;
   private $Jar;
   private static $DiagnosticsOffset = NULL;

   public function __construct($Base)
   {
      $this->Base = $Base;
      $this->Jar = tempnam(sys_get_temp_dir(), 'crudjar');
   }

   public function __destruct()
   {
      @unlink($this->Jar);
   }

   public static function diagnosticsOffset()
   {
      global $DiagnosticsFile;
      clearstatcache();

      return is_file($DiagnosticsFile) ? filesize($DiagnosticsFile) : 0;
   }

   /**
    * @param array      $Get   query parameters
    * @param array|null $Post  list of array(name, value) pairs (repeatable names) or an associative array
    * @param array      $Files name => array(path, mime type, file name)
    */
   public function request($Get = array(), $Post = NULL, $Files = array())
   {
      global $DiagnosticsFile;
      if(self::$DiagnosticsOffset === NULL)
      {
         self::$DiagnosticsOffset = self::diagnosticsOffset();
      }

      $Url = $this->Base . 'index.php' . ($Get ? '?' . http_build_query($Get, '', '&', PHP_QUERY_RFC3986) : '');
      $Curl = curl_init($Url);
      curl_setopt_array($Curl, array(
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_FOLLOWLOCATION => false,
         CURLOPT_COOKIEJAR => $this->Jar,
         CURLOPT_COOKIEFILE => $this->Jar,
         CURLOPT_TIMEOUT => 300,
         CURLOPT_HEADER => false,
      ));
      $Label = 'GET ' . substr($Url, strlen($this->Base));
      if($Post !== NULL)
      {
         $Pairs = isPairList($Post) ? $Post : pairsFromArray($Post);
         curl_setopt($Curl, CURLOPT_POST, true);
         if($Files)
         {
            $Fields = array();
            $Counters = array();
            foreach($Pairs as $Pair)
            {
               $Name = $Pair[0];
               if(substr($Name, -2) == '[]')
               {
                  $Stem = substr($Name, 0, -2);
                  $Counters[$Stem] = isset($Counters[$Stem]) ? $Counters[$Stem] + 1 : 0;
                  $Name = $Stem . '[' . $Counters[$Stem] . ']';
               }
               $Fields[$Name] = (string)$Pair[1];
            }
            foreach($Files as $Name => $File)
            {
               $Fields[$Name] = new CURLFile($File[0], $File[1], $File[2]);
            }
            curl_setopt($Curl, CURLOPT_POSTFIELDS, $Fields);
         }
         else
         {
            $Body = array();
            foreach($Pairs as $Pair)
            {
               $Body[] = rawurlencode($Pair[0]) . '=' . rawurlencode((string)$Pair[1]);
            }
            curl_setopt($Curl, CURLOPT_POSTFIELDS, implode('&', $Body));
         }
         $Label = 'POST ' . substr($Url, strlen($this->Base)) . ' ' . summarizePairs($Pairs);
      }
      $Start = microtime(true);
      $Body = curl_exec($Curl);
      $Response = array(
         'status' => (int)curl_getinfo($Curl, CURLINFO_RESPONSE_CODE),
         'type' => (string)curl_getinfo($Curl, CURLINFO_CONTENT_TYPE),
         'location' => (string)curl_getinfo($Curl, CURLINFO_REDIRECT_URL),
         'seconds' => round(microtime(true) - $Start, 3),
         'body' => (string)$Body,
         'error' => curl_error($Curl),
         'label' => $Label,
      );
      curl_close($Curl);

      // Diagnostics are flushed by the request's shutdown function, before the response completes.
      $Entries = readDiagnostics($DiagnosticsFile, self::$DiagnosticsOffset);
      self::$DiagnosticsOffset = self::diagnosticsOffset();
      $Response['diagnostics'] = $Entries;
      Report::diagnostics($Label, $Entries);
      $Response['fatal'] = false;
      foreach($Entries as $Entry)
      {
         if(in_array($Entry['type'], FATAL_TYPES, true))
         {
            $Response['fatal'] = $Entry['file'] . ':' . $Entry['line'] . ' ' . strtok($Entry['message'], "\n");
         }
      }

      return $Response;
   }

   public function loginAdmin($Login, $Secret)
   {
      $Response = $this->request(array('p' => 'admin/core/home'), array('archonlogin' => $Login, 'archonpassword' => $Secret));
      if($Response['status'] >= 400 || stripos($Response['body'], 'name="archonlogin"') !== false || stripos($Response['body'], "name='archonlogin'") !== false)
      {
         return false;
      }

      return true;
   }
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
         $Entries[] = $Entry + array('type' => '?', 'file' => '?', 'line' => 0, 'message' => '');
      }
   }
   fclose($Handle);

   return $Entries;
}

function isPairList($Post)
{
   if(!is_array($Post) || empty($Post))
   {
      return is_array($Post);
   }
   foreach($Post as $Key => $Value)
   {
      if(!is_int($Key) || !is_array($Value) || count($Value) != 2 || !isset($Value[0]))
      {
         return false;
      }
   }

   return true;
}

function pairsFromArray(array $Array, $Prefix = NULL)
{
   $Pairs = array();
   foreach($Array as $Key => $Value)
   {
      $Name = $Prefix === NULL ? (string)$Key : $Prefix . '[' . $Key . ']';
      if(is_array($Value))
      {
         $Pairs = array_merge($Pairs, pairsFromArray($Value, $Name));
      }
      else
      {
         $Pairs[] = array($Name, (string)$Value);
      }
   }

   return $Pairs;
}

function summarizePairs(array $Pairs)
{
   $Parts = array();
   foreach($Pairs as $Pair)
   {
      if(in_array(strtolower($Pair[0]), array('p', 'f', 'ids[]', 'id'), true))
      {
         $Parts[] = $Pair[0] . '=' . $Pair[1];
      }
   }

   return '[' . implode(' ', $Parts) . ' +' . (count($Pairs) - count($Parts)) . ' fields]';
}

/* ------------------------------------------------------------------------------------------
 * Form handling (#mainform, browser "successful controls" semantics)
 * ---------------------------------------------------------------------------------------- */

function loadHtml($Html)
{
   $Doc = new DOMDocument();
   $Previous = libxml_use_internal_errors(true);
   $Doc->loadHTML('<?xml encoding="UTF-8">' . $Html, LIBXML_NONET | LIBXML_COMPACT);
   libxml_clear_errors();
   libxml_use_internal_errors($Previous);

   return $Doc;
}

function hasClass(DOMElement $Element, $Class)
{
   return in_array($Class, preg_split('/\s+/', trim($Element->getAttribute('class'))), true);
}

/**
 * Returns the controls of a form, in document order:
 * array(name, tag, type, id, value, checked, disabled, required, classes, maxlength, multiple, options)
 */
function parseForm($Html, $FormId = 'mainform')
{
   $Doc = loadHtml($Html);
   $XPath = new DOMXPath($Doc);
   $Form = $XPath->query($FormId[0] == '/' ? $FormId : "//form[@id='$FormId']")->item(0);
   if(!$Form)
   {
      return NULL;
   }
   // Required text areas are flagged on the editable div that replaces them (id without "Input").
   $RequiredEditors = array();
   foreach($XPath->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' requiredfield ')]", $Form) as $Div)
   {
      $RequiredEditors[$Div->getAttribute('id') . 'Input'] = true;
   }
   $Controls = array();
   foreach($XPath->query('.//input|.//select|.//textarea', $Form) as $Element)
   {
      $Tag = strtolower($Element->nodeName);
      $Control = array(
         'name' => $Element->getAttribute('name'),
         'tag' => $Tag,
         'type' => $Tag == 'input' ? strtolower($Element->getAttribute('type') ?: 'text') : $Tag,
         'id' => $Element->getAttribute('id'),
         'value' => $Element->getAttribute('value'),
         'checked' => $Element->hasAttribute('checked'),
         'disabled' => $Element->hasAttribute('disabled'),
         'required' => hasClass($Element, 'requiredfield'),
         'classes' => trim($Element->getAttribute('class')),
         'maxlength' => $Element->hasAttribute('maxlength') ? (int)$Element->getAttribute('maxlength') : NULL,
         'multiple' => $Element->hasAttribute('multiple'),
         'options' => array(),
      );
      if($Tag == 'textarea')
      {
         $Control['value'] = $Element->textContent;
         $Control['required'] = $Control['required'] || isset($RequiredEditors[$Control['id']]);
      }
      elseif($Tag == 'select')
      {
         foreach($XPath->query('.//option', $Element) as $Option)
         {
            $Control['options'][] = array(
               'value' => $Option->hasAttribute('value') ? $Option->getAttribute('value') : trim($Option->textContent),
               'text' => trim($Option->textContent),
               'selected' => $Option->hasAttribute('selected'),
            );
         }
      }
      $Controls[] = $Control;
   }

   return $Controls;
}

/** The name/value pairs a browser submits for the controls (jQuery Form plugin formToArray). */
function successfulPairs(array $Controls)
{
   $Pairs = array();
   foreach($Controls as $Control)
   {
      if($Control['name'] === '' || $Control['disabled'])
      {
         continue;
      }
      switch($Control['type'])
      {
         case 'checkbox':
         case 'radio':
            if($Control['checked'])
            {
               $Pairs[] = array($Control['name'], $Control['value'] !== '' ? $Control['value'] : 'on');
            }
            break;
         case 'submit':
         case 'button':
         case 'image':
         case 'reset':
         case 'file':
            break;
         case 'select':
            $Selected = array();
            foreach($Control['options'] as $Option)
            {
               if($Option['selected'])
               {
                  $Selected[] = $Option['value'];
               }
            }
            if(!$Control['multiple'])
            {
               if(empty($Selected) && !empty($Control['options']))
               {
                  $Selected = array($Control['options'][0]['value']);
               }
               $Selected = array_slice($Selected, -1);
            }
            foreach($Selected as $Value)
            {
               $Pairs[] = array($Control['name'], $Value);
            }
            break;
         default:
            $Pairs[] = array($Control['name'], $Control['value']);
      }
   }

   return $Pairs;
}

function pairValue(array $Pairs, $Name)
{
   foreach($Pairs as $Pair)
   {
      if(strcasecmp($Pair[0], $Name) == 0)
      {
         return $Pair[1];
      }
   }

   return NULL;
}

function pairValues(array $Pairs, $Name)
{
   $Values = array();
   foreach($Pairs as $Pair)
   {
      if(strcasecmp($Pair[0], $Name) == 0)
      {
         $Values[] = $Pair[1];
      }
   }

   return $Values;
}

/** Replaces every pair called $Name with one pair per value (a scalar sets a single value). */
function setPair(array &$Pairs, $Name, $Values)
{
   $Values = is_array($Values) ? array_values($Values) : array($Values);
   $Result = array();
   $Inserted = false;
   foreach($Pairs as $Pair)
   {
      if(strcasecmp($Pair[0], $Name) == 0)
      {
         if(!$Inserted)
         {
            foreach($Values as $Value)
            {
               $Result[] = array($Name, (string)$Value);
            }
            $Inserted = true;
         }
         continue;
      }
      $Result[] = $Pair;
   }
   if(!$Inserted)
   {
      foreach($Values as $Value)
      {
         $Result[] = array($Name, (string)$Value);
      }
   }
   $Pairs = $Result;
}

function findControl(array $Controls, $Name)
{
   foreach($Controls as $Control)
   {
      if(strcasecmp($Control['name'], $Name) == 0)
      {
         return $Control;
      }
   }

   return NULL;
}

function describeForm(array $Controls)
{
   $Lines = array();
   foreach($Controls as $Control)
   {
      if($Control['type'] == 'hidden' && !$Control['required'])
      {
         continue;
      }
      $Info = sprintf('%-40s %-9s', $Control['name'], $Control['type']);
      if($Control['required'])
      {
         $Info .= ' REQUIRED';
      }
      if($Control['maxlength'])
      {
         $Info .= ' max=' . $Control['maxlength'];
      }
      if($Control['tag'] == 'select')
      {
         $Info .= sprintf(' %s options=%d%s', $Control['multiple'] ? 'multi' : 'single', count($Control['options']), strpos($Control['classes'], 'advancedselect') !== false ? ' advanced' : '');
      }
      elseif($Control['value'] !== '')
      {
         $Info .= ' value=' . json_encode(mb_substr($Control['value'], 0, 30));
      }
      $Lines[] = $Info;
   }

   return $Lines;
}

/* ------------------------------------------------------------------------------------------
 * Archon responses
 * ---------------------------------------------------------------------------------------- */

/** Parses an <archonresponse> document. Returns NULL if the body is not one (e.g. a PHP fatal). */
function parseArchonResponse($Body)
{
   $Start = strpos($Body, '<archonresponse');
   if($Start === false)
   {
      return NULL;
   }
   $Previous = libxml_use_internal_errors(true);
   $Xml = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?>' . substr($Body, $Start), 'SimpleXMLElement', LIBXML_NONET);
   libxml_clear_errors();
   libxml_use_internal_errors($Previous);
   if($Xml === false)
   {
      return NULL;
   }
   $Result = array('error' => ((string)$Xml['error']) === 'true', 'message' => trim((string)$Xml->message), 'ids' => array(), 'problemfields' => array(), 'location' => (string)$Xml->location, 'leading_output' => trim(substr($Body, 0, $Start)));
   foreach($Xml->id as $ID)
   {
      $Result['ids'][] = trim((string)$ID);
   }
   foreach($Xml->problemfield as $Field)
   {
      $Result['problemfields'][] = (string)$Field;
   }
   $Result['leading_output'] = trim(preg_replace('/<\?xml[^>]*\?>/', '', $Result['leading_output']));

   return $Result;
}

function snippet($Text, $Length = 300)
{
   $Text = trim(preg_replace('/\s+/', ' ', strip_tags($Text)));

   return mb_strlen($Text) > $Length ? mb_substr($Text, 0, $Length) . '...' : $Text;
}

/* ------------------------------------------------------------------------------------------
 * Database access for verification (read-only checks, plus cleanup of test rows)
 * ---------------------------------------------------------------------------------------- */

final class Db
{
   private static $Connection = NULL;

   public static function connect()
   {
      global $Database;
      if(self::$Connection === NULL)
      {
         self::$Connection = sqlsrv_connect(getenv('ARCHON_TEST_MSSQL_HOST') ?: 'mssql', array(
            'UID' => getenv('ARCHON_TEST_MSSQL_USER') ?: 'sa', 'PWD' => getenv('ARCHON_TEST_MSSQL_SECRET'),
            'Database' => $Database, 'TrustServerCertificate' => true, 'CharacterSet' => 'UTF-8', 'ReturnDatesAsStrings' => true));
         if(!self::$Connection)
         {
            fwrite(STDERR, "cannot connect to the test database: " . print_r(sqlsrv_errors(), true));
            exit(2);
         }
      }

      return self::$Connection;
   }

   public static function rows($Query, array $Params = array())
   {
      $Result = sqlsrv_query(self::connect(), $Query, $Params);
      if($Result === false)
      {
         throw new RuntimeException("SQL failed: $Query: " . json_encode(sqlsrv_errors()));
      }
      $Rows = array();
      while(($Row = sqlsrv_fetch_array($Result, SQLSRV_FETCH_ASSOC)) !== NULL && $Row !== false)
      {
         $Rows[] = $Row;
      }
      sqlsrv_free_stmt($Result);

      return $Rows;
   }

   public static function row($Query, array $Params = array())
   {
      $Rows = self::rows($Query, $Params);

      return $Rows ? $Rows[0] : NULL;
   }

   public static function value($Query, array $Params = array())
   {
      $Row = self::row($Query, $Params);

      return $Row ? reset($Row) : NULL;
   }

   public static function exec($Query, array $Params = array())
   {
      $Result = sqlsrv_query(self::connect(), $Query, $Params);
      if($Result === false)
      {
         throw new RuntimeException("SQL failed: $Query: " . json_encode(sqlsrv_errors()));
      }
      $Affected = sqlsrv_rows_affected($Result);
      sqlsrv_free_stmt($Result);

      return $Affected;
   }

   public static function columns($Table)
   {
      static $Cache = array();
      if(!isset($Cache[$Table]))
      {
         $Cache[$Table] = array();
         foreach(self::rows("SELECT c.name, t.name AS type, c.max_length FROM sys.columns c JOIN sys.types t ON t.user_type_id = c.user_type_id WHERE c.object_id = OBJECT_ID(?)", array($Table)) as $Row)
         {
            $Cache[$Table][strtolower($Row['name'])] = $Row;
         }
      }

      return $Cache[$Table];
   }

   public static function tableExists($Table)
   {
      return (bool)self::value("SELECT COUNT(*) FROM sys.tables WHERE name = ?", array($Table));
   }

   public static function maxModificationLogId()
   {
      return (int)self::value("SELECT ISNULL(MAX(ID), 0) FROM tblCore_ModificationLog");
   }
}

function quoteIdentifier($Name)
{
   return '[' . str_replace(']', ']]', $Name) . ']';
}

/* ------------------------------------------------------------------------------------------
 * Generic CRUD engine
 * ---------------------------------------------------------------------------------------- */

final class Crud
{
   /** @var Client */
   public static $Admin;
   public static $RunId;
   public static $Counter = 0;
   public static $Cleanup = array();

   public static function marker($Label = 'crud')
   {
      self::$Counter++;

      return sprintf('%s-%s-%d', $Label, self::$RunId, self::$Counter);
   }

   public static function truncate($Value, $Max)
   {
      return ($Max && mb_strlen($Value) > $Max) ? mb_substr($Value, 0, $Max) : $Value;
   }

   /** Character-column metadata of a table: lower-cased name => max characters (0 = unlimited). */
   public static function textColumns($Table)
   {
      $Result = array();
      foreach(Db::columns($Table) as $Name => $Column)
      {
         $Type = strtolower($Column['type']);
         if(in_array($Type, array('varchar', 'char', 'text'), true))
         {
            $Result[$Name] = $Column['max_length'] < 0 ? 0 : (int)$Column['max_length'];
         }
         elseif(in_array($Type, array('nvarchar', 'nchar', 'ntext'), true))
         {
            $Result[$Name] = $Column['max_length'] < 0 ? 0 : (int)($Column['max_length'] / 2);
         }
      }

      return $Result;
   }

   public static function form($P, $ID = NULL, $Extra = array())
   {
      $Get = array('p' => $P) + ($ID ? array('id' => $ID) : array()) + $Extra;
      $Response = self::$Admin->request($Get);
      $Controls = $Response['status'] == 200 ? parseForm($Response['body']) : NULL;

      return array($Response, $Controls);
   }

   /**
    * Fills the controls for a store: spec values first, then every empty character column the
    * form exposes (bounded by the column and the input's maxlength), then required selects.
    */
   public static function fill(array $Controls, array $Spec, $Marker)
   {
      $Pairs = successfulPairs($Controls);
      $TextColumns = isset($Spec['table']) ? self::textColumns($Spec['table']) : array();
      $Skip = array_map('strtolower', isset($Spec['skip']) ? $Spec['skip'] : array());
      $Filled = array();
      foreach($Controls as $Control)
      {
         $Name = $Control['name'];
         $Lower = strtolower($Name);
         if($Name === '' || $Control['disabled'] || in_array($Lower, $Skip, true) || isset($Spec['fill'][$Name]))
         {
            continue;
         }
         $IsText = in_array($Control['type'], array('text', 'textarea'), true);
         if($IsText && array_key_exists($Lower, $TextColumns) && trim($Control['value']) === '' && (!empty($Spec['fill_optional']) || $Control['required'] || !isset($Spec['fill_optional'])))
         {
            $Max = $TextColumns[$Lower];
            $Max = $Control['maxlength'] ? ($Max ? min($Max, $Control['maxlength']) : $Control['maxlength']) : $Max;
            $Value = self::truncate("$Name $Marker", $Max ?: 200);
            if($Max && $Max < strlen($Marker))
            {
               $Value = substr(md5($Marker . $Name), 0, $Max);
            }
            setPair($Pairs, $Name, $Value);
            $Filled[$Name] = $Value;
         }
         elseif($IsText && $Control['required'] && trim($Control['value']) === '')
         {
            $Value = self::truncate("$Name $Marker", $Control['maxlength'] ?: 100);
            setPair($Pairs, $Name, $Value);
            $Filled[$Name] = $Value;
         }
         elseif($Control['type'] == 'select' && !$Control['multiple'] && $Control['required'])
         {
            $Current = pairValue($Pairs, $Name);
            if($Current === NULL || $Current === '' || $Current === '0')
            {
               foreach($Control['options'] as $Option)
               {
                  if($Option['value'] !== '' && $Option['value'] !== '0')
                  {
                     setPair($Pairs, $Name, $Option['value']);
                     $Filled[$Name] = $Option['value'];
                     break;
                  }
               }
            }
         }
      }
      foreach(isset($Spec['fill']) ? $Spec['fill'] : array() as $Name => $Value)
      {
         if($Value instanceof Closure)
         {
            $Value = $Value($Marker);
         }
         if($Value === NULL)
         {
            setPair($Pairs, $Name, array());
            continue;
         }
         setPair($Pairs, $Name, $Value);
         if(!is_array($Value))
         {
            $Filled[$Name] = (string)$Value;
         }
      }

      return array($Pairs, $Filled);
   }

   /** Posts a store and returns array(response, parsed archonresponse). */
   public static function store(array $Pairs, $Client = NULL)
   {
      $Client = $Client ?: self::$Admin;
      $Response = $Client->request(array(), $Pairs);

      return array($Response, parseArchonResponse($Response['body']));
   }

   public static function checkResponse($Step, $Response, $Parsed, $ExpectError = false)
   {
      if($Response['fatal'])
      {
         return Report::step($Step, false, 'PHP fatal: ' . $Response['fatal']);
      }
      if($Parsed === NULL)
      {
         return Report::step($Step, false, "HTTP {$Response['status']}, not an archonresponse: " . snippet($Response['body']));
      }
      if($Parsed['leading_output'] !== '')
      {
         Report::step("$Step (clean XML)", false, 'output before the XML response: ' . snippet($Parsed['leading_output'], 200));
      }
      if($Parsed['error'] != $ExpectError)
      {
         return Report::step($Step, false, ($Parsed['error'] ? 'error: ' : 'unexpected success: ') . $Parsed['message'] . ($Parsed['problemfields'] ? ' [' . implode(',', $Parsed['problemfields']) . ']' : ''));
      }

      return Report::step($Step, true, $Parsed['message']);
   }

   /** Compares submitted values with the stored row (character columns only, as entered). */
   public static function verifyRow($Step, $Table, $ID, array $Expected, array $Transforms = array())
   {
      $Row = Db::row('SELECT * FROM ' . quoteIdentifier($Table) . ' WHERE ID = ?', array($ID));
      if(!$Row)
      {
         return Report::step($Step, false, "$Table row $ID not found");
      }
      $Lower = array_change_key_case($Row, CASE_LOWER);
      $Mismatches = array();
      foreach($Expected as $Name => $Value)
      {
         $Key = strtolower($Name);
         if(!array_key_exists($Key, $Lower))
         {
            continue;
         }
         $Actual = $Lower[$Key];
         if(isset($Transforms[$Name]))
         {
            $Value = $Transforms[$Name]($Value);
         }
         if((string)$Actual !== (string)$Value && !(is_numeric($Value) && is_numeric($Actual) && $Value == $Actual))
         {
            $Mismatches[] = sprintf('%s: stored %s, expected %s', $Name, json_encode($Actual, JSON_UNESCAPED_UNICODE), json_encode($Value, JSON_UNESCAPED_UNICODE));
         }
      }

      return Report::step($Step, empty($Mismatches), implode('; ', $Mismatches));
   }

   /** Checks that the re-rendered edit form shows the stored values. */
   public static function verifyForm($Step, $Controls, array $Expected)
   {
      if($Controls === NULL)
      {
         return Report::step($Step, false, 'edit form not found');
      }
      $Mismatches = array();
      foreach($Expected as $Name => $Value)
      {
         $Control = findControl($Controls, $Name);
         if($Control === NULL || !in_array($Control['type'], array('text', 'textarea', 'hidden'), true))
         {
            continue;
         }
         if($Control['value'] !== (string)$Value)
         {
            $Mismatches[] = sprintf('%s: form shows %s, expected %s', $Name, json_encode($Control['value'], JSON_UNESCAPED_UNICODE), json_encode($Value, JSON_UNESCAPED_UNICODE));
         }
      }

      return Report::step($Step, empty($Mismatches), implode('; ', $Mismatches));
   }

   public static function verifyLog($Step, $Since, $Table, $ID, $Function)
   {
      $Row = Db::row('SELECT TOP 1 ID, Login, ModuleID, RequestData FROM tblCore_ModificationLog WHERE ID > ? AND TableName = ? AND RowID = ? AND ArchonFunction = ? ORDER BY ID DESC', array($Since, $Table, $ID, $Function));
      if(!$Row)
      {
         return Report::step($Step, false, "no modification log entry ($Table, $ID, $Function)");
      }
      $Data = @unserialize($Row['RequestData']);
      if(!is_array($Data))
      {
         return Report::step($Step, false, 'RequestData is not a serialized array');
      }
      foreach($Data as $Key => $Value)
      {
         if(stripos($Key, 'password') !== false && $Value !== '(hidden)')
         {
            return Report::step($Step, false, "password field $Key logged in clear text");
         }
      }

      return Report::step($Step, true, "log #{$Row['ID']} by {$Row['Login']}");
   }

   public static function delete($P, array $IDs, $Client = NULL)
   {
      $Pairs = array(array('p', $P), array('f', 'delete'));
      foreach($IDs as $ID)
      {
         $Pairs[] = array('IDs[]', $ID);
      }

      return self::store($Pairs, $Client);
   }

   /**
    * Full create / read / update / delete cycle for one admin module.
    * Returns the ID of the record (still present when $Spec['delete'] is false or --keep is used).
    */
   public static function cycle(array $Spec)
   {
      global $Keep;
      $P = $Spec['p'];
      $Table = $Spec['table'];
      $Marker = self::marker(isset($Spec['label']) ? $Spec['label'] : basename($P));

      $Get = isset($Spec['get']) ? $Spec['get'] : array();
      list($Response, $Controls) = self::form($P, NULL, $Get);
      if(!Report::step('new form', $Controls !== NULL && !$Response['fatal'], $Controls === NULL ? "HTTP {$Response['status']}: " . snippet($Response['body']) : (string)$Response['fatal']))
      {
         return NULL;
      }

      // Create.
      list($Pairs, $Filled) = self::fill($Controls, $Spec, $Marker);
      if(isset($Spec['before_store']))
      {
         $Spec['before_store']($Pairs, $Filled, $Controls);
      }
      $LogSince = Db::maxModificationLogId();
      list($Response, $Parsed) = self::store($Pairs);
      if(!self::checkResponse('create', $Response, $Parsed))
      {
         return NULL;
      }
      $ID = $Parsed['ids'] ? (int)reset($Parsed['ids']) : 0;
      if($ID == 0 && !empty($Spec['id_not_returned']))
      {
         // Some exec functions never copy the new ID back into the response (same on PHP 5).
         $Column = $Spec['id_not_returned'];
         $ID = (int)Db::value('SELECT TOP 1 ID FROM ' . quoteIdentifier($Table) . ' WHERE ' . quoteIdentifier($Column) . ' = ? ORDER BY ID DESC', array($Filled[$Column]));
         Report::note("the store response carries ID 0 (upstream behaviour); found ID $ID by $Column");
      }
      if(!Report::step('create returned an ID', $ID > 0, json_encode($Parsed['ids'])))
      {
         return NULL;
      }
      self::$Cleanup[] = array($P, $ID, $Table);
      $Transforms = isset($Spec['transforms']) ? $Spec['transforms'] : array();
      self::verifyRow('create stored the values', $Table, $ID, $Filled, $Transforms);
      if(!isset($Spec['log']) || $Spec['log'])
      {
         self::verifyLog('create logged', $LogSince, $Table, $ID, 'store');
      }

      // Read.
      list($Response, $Controls) = self::form($P, $ID, $Get);
      Report::step('edit form loads', $Controls !== NULL && !$Response['fatal'], (string)$Response['fatal']);
      if($Controls !== NULL)
      {
         self::verifyForm('edit form shows the values', $Controls, array_diff_key($Filled, isset($Spec['form_ignore']) ? array_flip($Spec['form_ignore']) : array()));
         Report::step('edit form carries the ID', pairValue(successfulPairs($Controls), 'IDs[]') === (string)$ID, json_encode(pairValues(successfulPairs($Controls), 'IDs[]')));
      }
      if(isset($Spec['after_create']))
      {
         $Spec['after_create']($ID, $Filled, $Marker);
      }

      // Update: re-submit the loaded form with changed values, as the browser does.
      if($Controls !== NULL && (!isset($Spec['update']) || $Spec['update'] !== false))
      {
         $Pairs = successfulPairs($Controls);
         $Updated = array();
         $Changes = isset($Spec['update']) ? $Spec['update'] : NULL;
         if($Changes === NULL)
         {
            $Field = isset($Spec['update_field']) ? $Spec['update_field'] : NULL;
            if($Field === NULL)
            {
               foreach($Filled as $Name => $Value)
               {
                  $Control = findControl($Controls, $Name);
                  if($Control && in_array($Control['type'], array('text', 'textarea'), true) && !is_numeric($Value))
                  {
                     $Field = $Name;
                     break;
                  }
               }
            }
            $Changes = array();
            if($Field !== NULL)
            {
               $Control = findControl($Controls, $Field);
               $Max = $Control && $Control['maxlength'] ? $Control['maxlength'] : 0;
               $TextColumns = self::textColumns($Table);
               if(isset($TextColumns[strtolower($Field)]) && $TextColumns[strtolower($Field)])
               {
                  $Max = $Max ? min($Max, $TextColumns[strtolower($Field)]) : $TextColumns[strtolower($Field)];
               }
               $Changes[$Field] = self::truncate(CP1252_TEXT . ' ' . $Marker, $Max ?: 200);
               if($Max && $Max < mb_strlen(CP1252_TEXT) + 5)
               {
                  $Changes[$Field] = self::truncate('Ü€ ' . substr(md5($Marker), 0, 20), $Max);
               }
            }
         }
         foreach($Changes as $Name => $Value)
         {
            if($Value instanceof Closure)
            {
               $Value = $Value($Marker);
            }
            setPair($Pairs, $Name, $Value);
            if(!is_array($Value))
            {
               $Updated[$Name] = (string)$Value;
            }
         }
         $LogSince = Db::maxModificationLogId();
         list($Response, $Parsed) = self::store($Pairs);
         if(self::checkResponse('update', $Response, $Parsed))
         {
            Report::step('update kept the ID', $Parsed['ids'] == array((string)$ID) || (!empty($Spec['id_not_returned']) && $Parsed['ids'] == array((string)$ID)), json_encode($Parsed['ids']));
            self::verifyRow('update stored the values', $Table, $ID, $Updated + $Filled, $Transforms);
            if(!isset($Spec['log']) || $Spec['log'])
            {
               self::verifyLog('update logged', $LogSince, $Table, $ID, 'store');
            }
            list($Response, $Controls) = self::form($P, $ID, $Get);
            if($Controls !== NULL)
            {
               self::verifyForm('edit form shows the update', $Controls, array_diff_key($Updated, isset($Spec['form_ignore']) ? array_flip($Spec['form_ignore']) : array()));
            }
         }
         $Filled = $Updated + $Filled;
      }

      // Public pages.
      if(isset($Spec['public']))
      {
         foreach($Spec['public']($ID) as $Get)
         {
            $Public = new Client($GLOBALS['Base']);
            $Response = $Public->request($Get);
            $Expect = isset($Spec['public_expect']) ? $Spec['public_expect']($Filled, $Marker) : $Marker;
            Report::step('public page ' . http_build_query($Get), $Response['status'] == 200 && !$Response['fatal'] && ($Expect === NULL || strpos(html_entity_decode($Response['body'], ENT_QUOTES, 'UTF-8'), $Expect) !== false),
               "HTTP {$Response['status']}" . ($Response['fatal'] ? ' ' . $Response['fatal'] : '') . ($Expect !== NULL && strpos(html_entity_decode($Response['body'], ENT_QUOTES, 'UTF-8'), $Expect) === false ? " (missing \"$Expect\")" : ''));
         }
      }

      if(isset($Spec['after_update']))
      {
         $Spec['after_update']($ID, $Filled, $Marker);
      }

      // Delete.
      if((!isset($Spec['delete']) || $Spec['delete']) && !$Keep)
      {
         self::remove($P, $Table, $ID, isset($Spec['log']) ? $Spec['log'] : true);
      }

      return $ID;
   }

   public static function remove($P, $Table, $ID, $CheckLog = true)
   {
      $LogSince = Db::maxModificationLogId();
      list($Response, $Parsed) = self::delete($P, array($ID));
      if(self::checkResponse('delete', $Response, $Parsed))
      {
         Report::step('delete removed the row', !Db::row('SELECT ID FROM ' . quoteIdentifier($Table) . ' WHERE ID = ?', array($ID)), "$Table row $ID");
         if($CheckLog)
         {
            self::verifyLog('delete logged', $LogSince, $Table, $ID, 'delete');
         }
      }
      foreach(self::$Cleanup as $Key => $Entry)
      {
         if($Entry[0] == $P && $Entry[1] == $ID)
         {
            unset(self::$Cleanup[$Key]);
         }
      }
   }
}

function selected($Name)
{
   global $Only;

   return $Only === NULL || preg_match('/' . str_replace('/', '\/', $Only) . '/i', $Name);
}

const ADMIN_MODULES = array(
   'accessions/accessions', 'accessions/processingpriorities',
   'avsap/avsapassessments', 'avsap/avsapinstitutions', 'avsap/avsapstoragefacilities', 'avsap/avsapassessmentreport',
   'collections/books', 'collections/classification', 'collections/collectioncontent', 'collections/collections',
   'collections/extentunits', 'collections/levelcontainers', 'collections/locations', 'collections/materialtypes',
   'collections/researchappointmentfields', 'collections/researchappointmentpurposes', 'collections/researchappointments',
   'collections/researchertypes',
   'core/adminusers', 'core/configuration', 'core/database', 'core/modificationlog', 'core/mypreferences', 'core/packages',
   'core/patterns', 'core/phrases', 'core/publicusers', 'core/regions', 'core/repositories', 'core/sessions',
   'core/usergroups', 'core/userprofilefieldcategories', 'core/userprofilefields',
   'creators/creators', 'creators/creatorsources',
   'digitallibrary/digitallibrary', 'digitallibrary/filemanager', 'digitallibrary/filetypes',
   'subjects/subjects', 'subjects/subjectsources',
);

/* ------------------------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------------------------- */

Crud::$RunId = date('His') . substr(md5(uniqid('', true)), 0, 4);
Crud::$Admin = new Client($Base);
Db::connect();

if(!Crud::$Admin->loginAdmin($AdminLogin, $AdminSecret))
{
   fwrite(STDERR, "administrator login failed\n");
   exit(2);
}

if(isset($Options['discover']))
{
   foreach(ADMIN_MODULES as $Module)
   {
      if(!selected($Module))
      {
         continue;
      }
      list($Response, $Controls) = Crud::form("admin/$Module");
      printf("\n== admin/%s (HTTP %d, %.2fs)%s\n", $Module, $Response['status'], $Response['seconds'], $Response['fatal'] ? ' FATAL ' . $Response['fatal'] : '');
      if($Controls === NULL)
      {
         echo "   (no #mainform)\n";
         continue;
      }
      foreach(describeForm($Controls) as $Line)
      {
         echo "   $Line\n";
      }
   }
   exit(0);
}

require __DIR__ . '/crud-scenarios.php';

foreach(crudScenarios() as $Name => $Scenario)
{
   if(!selected($Name))
   {
      continue;
   }
   Report::begin($Name);
   try
   {
      $Scenario();
   }
   catch(Throwable $Exception)
   {
      Report::step('scenario completed', false, get_class($Exception) . ': ' . $Exception->getMessage() . ' @ ' . basename($Exception->getFile()) . ':' . $Exception->getLine());
   }
   Report::end();
}

// Remove whatever a failed scenario left behind (reverse creation order).
if(!$Keep && Crud::$Cleanup)
{
   Report::begin('cleanup');
   foreach(array_reverse(Crud::$Cleanup) as $Entry)
   {
      list($P, $ID, $Table) = $Entry;
      if(Db::row('SELECT ID FROM ' . quoteIdentifier($Table) . ' WHERE ID = ?', array($ID)))
      {
         Crud::remove($P, $Table, $ID, false);
      }
   }
   Report::end();
}

$Passed = 0;
foreach(Report::$Scenarios as $Scenario)
{
   $Passed += $Scenario['status'] == 'pass' ? 1 : 0;
}
printf("\n%d/%d scenarios passed, %d failed checks; diagnostics: %d fatal, %d warnings, %d former notices, %d distinct in total\n",
   $Passed, count(Report::$Scenarios), Report::$Failures, Report::$Fatals, Report::$Warnings, Report::$LegacyNotices, count(Report::$Diagnostics));

if($ReportFile)
{
   $Diagnostics = array_values(Report::$Diagnostics);
   usort($Diagnostics, function($A, $B) { return strcmp($A['type'] . $A['where'], $B['type'] . $B['where']); });
   file_put_contents($ReportFile, json_encode(array(
      'base' => $Base, 'run' => Crud::$RunId, 'php' => PHP_VERSION, 'finished' => date('c'),
      'summary' => array('scenarios' => count(Report::$Scenarios), 'passed' => $Passed, 'failed_checks' => Report::$Failures, 'fatals' => Report::$Fatals, 'warnings' => Report::$Warnings, 'former_notices' => Report::$LegacyNotices),
      'scenarios' => Report::$Scenarios, 'diagnostics' => $Diagnostics,
   ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
   echo "report written to $ReportFile\n";
}

exit(Report::$Failures || Report::$Fatals ? 1 : 0);
