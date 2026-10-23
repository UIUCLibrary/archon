<?php
/**
 * Summarises the PHP diagnostics recorded by tests/docker/php/error-capture.php.
 *
 *   php tests/tools/diagnostics-report.php [/var/log/archon/diagnostics.jsonl] [--type=E_WARNING] [--json]
 *
 * Entries are grouped by type, file, line and message, most severe first, with the number of occurrences,
 * the number of distinct requests and one example request for each group.
 */
$Arguments = array_slice($argv, 1);
$File = '/var/log/archon/diagnostics.jsonl';
$Type = NULL;
$Json = false;
foreach($Arguments as $Argument)
{
   if($Argument == '--json')
   {
      $Json = true;
   }
   elseif(strpos($Argument, '--type=') === 0)
   {
      $Type = strtoupper(substr($Argument, 7));
   }
   elseif($Argument == '--help' || $Argument == '-h')
   {
      fwrite(STDERR, "usage: php diagnostics-report.php [FILE] [--type=E_WARNING] [--json]\n");
      exit(0);
   }
   else
   {
      $File = $Argument;
   }
}
if(!is_readable($File))
{
   echo "No diagnostics recorded yet ($File does not exist).\n";
   exit(0);
}

$Severity = array('E_ERROR' => 0, 'E_PARSE' => 0, 'E_CORE_ERROR' => 0, 'E_COMPILE_ERROR' => 0, 'E_RECOVERABLE_ERROR' => 1, 'E_USER_ERROR' => 1,
   'E_WARNING' => 2, 'E_CORE_WARNING' => 2, 'E_COMPILE_WARNING' => 2, 'E_USER_WARNING' => 2, 'E_NOTICE' => 3, 'E_USER_NOTICE' => 3,
   'E_DEPRECATED' => 4, 'E_USER_DEPRECATED' => 4, 'E_STRICT' => 5);
$Groups = array();
$Lines = 0;
$Handle = fopen($File, 'r');
while(($Line = fgets($Handle)) !== false)
{
   $Entry = json_decode($Line, true);
   if(!is_array($Entry) || !isset($Entry['type']))
   {
      continue;
   }
   $Lines++;
   if($Type !== NULL && $Entry['type'] !== $Type)
   {
      continue;
   }
   $Key = $Entry['type'] . '|' . $Entry['file'] . '|' . $Entry['line'] . '|' . $Entry['message'];
   if(!isset($Groups[$Key]))
   {
      $Groups[$Key] = array('type' => $Entry['type'], 'file' => $Entry['file'], 'line' => $Entry['line'], 'message' => $Entry['message'],
         'count' => 0, 'requests' => array(), 'example' => isset($Entry['request']) ? $Entry['request'] : '',
         'trace' => isset($Entry['trace']) ? $Entry['trace'] : array());
   }
   $Groups[$Key]['count'] += isset($Entry['count']) ? (int)$Entry['count'] : 1;
   if(isset($Entry['request']) && count($Groups[$Key]['requests']) < 1000)
   {
      $Groups[$Key]['requests'][$Entry['request']] = true;
   }
}
fclose($Handle);

uasort($Groups, function($a, $b) use ($Severity) {
   $Sa = isset($Severity[$a['type']]) ? $Severity[$a['type']] : 9;
   $Sb = isset($Severity[$b['type']]) ? $Severity[$b['type']] : 9;
   return $Sa != $Sb ? $Sa - $Sb : $b['count'] - $a['count'];
});

if($Json)
{
   $Output = array();
   foreach($Groups as $Group)
   {
      $Group['requests'] = count($Group['requests']);
      $Output[] = $Group;
   }
   echo json_encode($Output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
   exit(0);
}

$Totals = array();
foreach($Groups as $Group)
{
   $Totals[$Group['type']] = (isset($Totals[$Group['type']]) ? $Totals[$Group['type']] : 0) + 1;
}
printf("%d distinct diagnostics from %d records in %s\n", count($Groups), $Lines, $File);
foreach($Totals as $Name => $Count)
{
   printf("  %-20s %5d\n", $Name, $Count);
}
foreach($Groups as $Group)
{
   printf("\n[%s] %s:%d (x%d, %d request%s)\n  %s\n  e.g. %s\n", $Group['type'], $Group['file'], $Group['line'], $Group['count'],
      count($Group['requests']), count($Group['requests']) == 1 ? '' : 's', $Group['message'], $Group['example']);
   foreach(array_slice($Group['trace'], 0, 3) as $Frame)
   {
      echo "     at $Frame\n";
   }
}
