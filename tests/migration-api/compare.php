<?php
/**
 * Compares two harvests made by harvest.php (for example production and the PHP 8.2 test environment restored from
 * the same backup) record by record and field by field, and sorts every difference into a class:
 *
 *   status              a page or file answered with a different HTTP status (500 means the list or file is lost)
 *   paging              a list stopped at a different batch, or with a different terminator
 *   record-only-in-A/B  a record is present on one side only
 *   field-only-in-A/B   a field is present on one side only
 *   null-in-A/B         a field is null on one side and has a value on the other. PHP 5.4's json_encode() writes null
 *                       for a string that is not valid UTF-8, so this is the usual sign of text that the old stack
 *                       could not deliver
 *   decimal-zero        "0.50" on one side and ".50" on the other
 *   double-encoded      one side is the other's UTF-8 bytes read as Windows-1252 ("Café" against "CafÃ©")
 *   question-marks      the same text, but characters outside ASCII are "?" on one side
 *   type                the same value with a different JSON type (string against number, and so on)
 *   value               any other difference
 *   file                a digital file differs in size or content
 *
 * Usage: php compare.php [--limit=N] [--json=REPORT.json] DIR_A DIR_B
 *    DIR_A is usually the current production install, DIR_B the install being tested.
 *    --limit  examples printed per list and class (default 5; 0 prints only the counts)
 *    --json   also writes every difference to REPORT.json
 * Exit status: 0 when the harvests match, 1 when they differ, 2 on a usage error.
 *
 * @package Archon
 */

if(PHP_SAPI != 'cli')
{
   exit(2);
}

$Limit = 5;
$ReportPath = NULL;
$Directories = array();
foreach(array_slice($argv, 1) as $Argument)
{
   if(preg_match('/^--limit=(\d+)$/', $Argument, $Matches))
   {
      $Limit = (int)$Matches[1];
   }
   elseif(preg_match('/^--json=(.+)$/', $Argument, $Matches))
   {
      $ReportPath = $Matches[1];
   }
   elseif(substr($Argument, 0, 2) == '--')
   {
      fwrite(STDERR, "unknown option $Argument\n");
      exit(2);
   }
   else
   {
      $Directories[] = rtrim($Argument, '/');
   }
}
if(count($Directories) != 2 || !is_dir($Directories[0]) || !is_dir($Directories[1]))
{
   fwrite(STDERR, "usage: php compare.php [--limit=N] [--json=REPORT.json] DIR_A DIR_B\n");
   exit(2);
}
list($DirA, $DirB) = $Directories;

$Differences = array();


function compare_add($List, $Class, $Where, $A, $B)
{
   global $Differences;

   $Differences[] = array('list' => $List, 'class' => $Class, 'where' => $Where, 'a' => $A, 'b' => $B);
}


function compare_show($Value)
{
   if(is_string($Value))
   {
      $Text = json_encode($Value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if($Text === false)
      {
         $Text = '"(not valid UTF-8) ' . bin2hex(substr($Value, 0, 60)) . '"';
      }
   }
   else
   {
      $Text = json_encode($Value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   return strlen($Text) > 160 ? substr($Text, 0, 157) . '...' : $Text;
}


/**
 * Records of one list keyed by record ID (the JSON object keys, or the ID field for JSON arrays).
 */
function compare_records($Pages)
{
   $Records = array();
   foreach($Pages as $Page)
   {
      if(!isset($Page['body']))
      {
         continue;
      }
      $Decoded = json_decode($Page['body'], true);
      if(!is_array($Decoded))
      {
         $Records["batch {$Page['batch']} (not JSON)"] = $Page['body'];
         continue;
      }
      $IsList = array_keys($Decoded) === range(0, count($Decoded) - 1);
      foreach($Decoded as $Key => $Record)
      {
         if($IsList)
         {
            $Key = (is_array($Record) && isset($Record['ID'])) ? 'ID ' . $Record['ID'] : "batch {$Page['batch']} #$Key";
         }
         $Records[(string)$Key] = $Record;
      }
   }

   return $Records;
}


function compare_class_of_strings($A, $B)
{
   if(preg_match('/^(-?)0(\.\d+)$/', $A, $Matches) && $B === $Matches[1] . $Matches[2])
   {
      return 'decimal-zero';
   }
   if(preg_match('/^(-?)0(\.\d+)$/', $B, $Matches) && $A === $Matches[1] . $Matches[2])
   {
      return 'decimal-zero';
   }

   if(function_exists('mb_convert_encoding'))
   {
      $Unknown = array('?', "\xEF\xBF\xBD");
      if(str_replace($Unknown, '', mb_convert_encoding($A, 'UTF-8', 'Windows-1252')) === str_replace($Unknown, '', $B) ||
         str_replace($Unknown, '', mb_convert_encoding($B, 'UTF-8', 'Windows-1252')) === str_replace($Unknown, '', $A))
      {
         return 'double-encoded';
      }
   }

   if(preg_replace('/[^\x00-\x7F]/u', '?', $B) === $A || preg_replace('/[^\x00-\x7F]/u', '?', $A) === $B)
   {
      return 'question-marks';
   }

   return 'value';
}


function compare_values($List, $Where, $A, $B)
{
   if(is_array($A) && is_array($B))
   {
      foreach($A as $Key => $Value)
      {
         if(!array_key_exists($Key, $B))
         {
            compare_add($List, 'field-only-in-A', "$Where.$Key", $Value, NULL);
         }
         else
         {
            compare_values($List, "$Where.$Key", $Value, $B[$Key]);
         }
      }
      foreach($B as $Key => $Value)
      {
         if(!array_key_exists($Key, $A))
         {
            compare_add($List, 'field-only-in-B', "$Where.$Key", NULL, $Value);
         }
      }
      return;
   }

   if($A === $B)
   {
      return;
   }

   if($A === NULL || $B === NULL)
   {
      compare_add($List, $A === NULL ? 'null-in-A' : 'null-in-B', $Where, $A, $B);
   }
   elseif(is_string($A) && is_string($B))
   {
      compare_add($List, compare_class_of_strings($A, $B), $Where, $A, $B);
   }
   elseif(gettype($A) != gettype($B) && !is_array($A) && !is_array($B) && (string)$A === (string)$B)
   {
      compare_add($List, 'type', $Where, $A, $B);
   }
   else
   {
      compare_add($List, 'value', $Where, $A, $B);
   }
}


function compare_load($Path)
{
   if(!is_file($Path))
   {
      return NULL;
   }
   $Decoded = json_decode(file_get_contents($Path), true);

   return is_array($Decoded) ? $Decoded : NULL;
}


$Lists = array();
foreach(array($DirA, $DirB) as $Dir)
{
   foreach(array_merge(glob("$Dir/*.json"), glob("$Dir/content/*.json")) as $Path)
   {
      $Name = substr($Path, strlen($Dir) + 1, -5);
      if($Name[0] != '_')
      {
         $Lists[$Name] = true;
      }
   }
}
$Lists = array_keys($Lists);
natsort($Lists);

$Summary = array();
foreach($Lists as $List)
{
   $PagesA = compare_load("$DirA/$List.json");
   $PagesB = compare_load("$DirB/$List.json");
   if($PagesA === NULL || $PagesB === NULL)
   {
      compare_add($List, $PagesA === NULL ? 'record-only-in-B' : 'record-only-in-A', '(whole list)', $PagesA === NULL ? NULL : count($PagesA) . ' pages', $PagesB === NULL ? NULL : count($PagesB) . ' pages');
      continue;
   }

   foreach(array('A' => $PagesA, 'B' => $PagesB) as $Side => $Pages)
   {
      foreach($Pages as $Page)
      {
         if($Page['status'] != 200)
         {
            $Other = ($Side == 'A') ? $PagesB : $PagesA;
            $OtherStatus = NULL;
            foreach($Other as $OtherPage)
            {
               if($OtherPage['batch'] == $Page['batch'])
               {
                  $OtherStatus = $OtherPage['status'];
               }
            }
            if($OtherStatus != $Page['status'] && ($Side == 'A' || $OtherStatus === NULL || $OtherStatus == 200))
            {
               compare_add($List, 'status', "batch {$Page['batch']}", $Side == 'A' ? $Page['status'] : $OtherStatus, $Side == 'A' ? $OtherStatus : $Page['status']);
            }
         }
      }
   }

   $LastA = end($PagesA);
   $LastB = end($PagesB);
   if($LastA['batch'] != $LastB['batch'] || (isset($LastA['terminator']) ? $LastA['terminator'] : NULL) !== (isset($LastB['terminator']) ? $LastB['terminator'] : NULL))
   {
      compare_add($List, 'paging', 'last page', 'batch ' . $LastA['batch'] . ': ' . (isset($LastA['terminator']) ? $LastA['terminator'] : ''), 'batch ' . $LastB['batch'] . ': ' . (isset($LastB['terminator']) ? $LastB['terminator'] : ''));
   }

   $RecordsA = compare_records($PagesA);
   $RecordsB = compare_records($PagesB);
   $Summary[$List] = array(count($RecordsA), count($RecordsB));

   foreach($RecordsA as $Key => $Record)
   {
      if(!array_key_exists($Key, $RecordsB))
      {
         compare_add($List, 'record-only-in-A', $Key, NULL, NULL);
      }
      else
      {
         compare_values($List, $Key, $Record, $RecordsB[$Key]);
      }
   }
   foreach($RecordsB as $Key => $Record)
   {
      if(!array_key_exists($Key, $RecordsA))
      {
         compare_add($List, 'record-only-in-B', $Key, NULL, NULL);
      }
   }
}

$BlobsA = compare_load("$DirA/blobs/digitalfileblob.json");
$BlobsB = compare_load("$DirB/blobs/digitalfileblob.json");
if($BlobsA !== NULL || $BlobsB !== NULL)
{
   $BlobsA = (array)$BlobsA;
   $BlobsB = (array)$BlobsB;
   $Summary['digital files'] = array(count($BlobsA), count($BlobsB));
   foreach(array_unique(array_merge(array_keys($BlobsA), array_keys($BlobsB))) as $FileID)
   {
      $A = isset($BlobsA[$FileID]) ? $BlobsA[$FileID] : NULL;
      $B = isset($BlobsB[$FileID]) ? $BlobsB[$FileID] : NULL;
      if($A === NULL || $B === NULL)
      {
         compare_add('digital files', $A === NULL ? 'record-only-in-B' : 'record-only-in-A', "file $FileID", NULL, NULL);
      }
      elseif($A['status'] != $B['status'])
      {
         compare_add('digital files', 'status', "file $FileID", $A['status'], $B['status']);
      }
      elseif($A['sha256'] !== $B['sha256'])
      {
         compare_add('digital files', 'file', "file $FileID", "{$A['bytes']} bytes, ends " . $A['last_bytes_hex'], "{$B['bytes']} bytes, ends " . $B['last_bytes_hex']);
      }
   }
}

$Counts = array();
foreach($Differences as $Difference)
{
   $Key = $Difference['list'] . "\t" . $Difference['class'];
   $Counts[$Key] = isset($Counts[$Key]) ? $Counts[$Key] + 1 : 1;
}

echo "A = $DirA\nB = $DirB\n\n";
printf("%-34s %9s %9s  %s\n", 'list', 'records A', 'records B', 'differences');
foreach($Summary as $List => $Pair)
{
   $Classes = array();
   foreach($Counts as $Key => $Count)
   {
      list($CountList, $Class) = explode("\t", $Key);
      if($CountList == $List)
      {
         $Classes[] = "$Class $Count";
      }
   }
   if($Classes || $Pair[0] != $Pair[1] || strpos($List, 'content/') !== 0)
   {
      printf("%-34s %9d %9d  %s\n", $List, $Pair[0], $Pair[1], $Classes ? implode(', ', $Classes) : '-');
   }
}

$Totals = array();
foreach($Differences as $Difference)
{
   $Totals[$Difference['class']] = isset($Totals[$Difference['class']]) ? $Totals[$Difference['class']] + 1 : 1;
}
ksort($Totals);
echo "\nTotal: " . ($Totals ? json_encode($Totals) : 'no differences') . "\n";

if($Limit > 0 && $Differences)
{
   echo "\nExamples (up to $Limit per list and class):\n";
   $Shown = array();
   foreach($Differences as $Difference)
   {
      $Key = $Difference['list'] . "\t" . $Difference['class'];
      $Shown[$Key] = isset($Shown[$Key]) ? $Shown[$Key] + 1 : 1;
      if($Shown[$Key] <= $Limit)
      {
         printf("  [%s] %s  %s\n      A: %s\n      B: %s\n", $Difference['class'], $Difference['list'], $Difference['where'], compare_show($Difference['a']), compare_show($Difference['b']));
      }
   }
}

if($ReportPath !== NULL)
{
   $Report = json_encode(array('a' => $DirA, 'b' => $DirB, 'totals' => $Totals, 'differences' => $Differences), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? JSON_PARTIAL_OUTPUT_ON_ERROR : 0));
   file_put_contents($ReportPath, $Report);
   echo "\nReport written to $ReportPath\n";
}

exit($Differences ? 1 : 0);
