<?php
/**
 * Finds regular expressions that no longer compile under PHP 7.3+ (PCRE2), e.g. "[\w-.]",
 * which PCRE1 (PHP 5) accepted as a literal hyphen. Every preg_* call whose pattern argument
 * is a constant string literal is compiled with the running PHP.
 *
 * Usage: php tests/tools/find-regex-issues.php [dir ...]   (default: the repository root)
 * Exit status 1 when a pattern fails to compile.
 */
$Roots = array_slice($argv, 1) ?: array(dirname(__DIR__, 2));
$Functions = array('preg_match', 'preg_match_all', 'preg_replace', 'preg_replace_callback', 'preg_split', 'preg_grep', 'preg_filter', 'preg_quote_never');
$Problems = 0;
$Checked = 0;

foreach($Roots as $Root)
{
   $Iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($Root, FilesystemIterator::SKIP_DOTS));
   foreach($Iterator as $File)
   {
      $Path = $File->getPathname();
      if(!preg_match('/\.(php|inc|php_)$/', $Path) || preg_match('#/(\.git|vendor|tests/docker/artifacts)/#', $Path))
      {
         continue;
      }
      $Tokens = @token_get_all(file_get_contents($Path));
      $Count = count($Tokens);
      for($i = 0; $i < $Count; $i++)
      {
         if(!is_array($Tokens[$i]) || $Tokens[$i][0] != T_STRING || !in_array(strtolower($Tokens[$i][1]), $Functions, true))
         {
            continue;
         }
         // Next significant tokens: "(" then the first argument.
         $j = $i + 1;
         while($j < $Count && is_array($Tokens[$j]) && $Tokens[$j][0] == T_WHITESPACE)
         {
            $j++;
         }
         if($j >= $Count || $Tokens[$j] !== '(')
         {
            continue;
         }
         $j++;
         while($j < $Count && is_array($Tokens[$j]) && $Tokens[$j][0] == T_WHITESPACE)
         {
            $j++;
         }
         $Patterns = array();
         if(is_array($Tokens[$j]) && $Tokens[$j][0] == T_CONSTANT_ENCAPSED_STRING)
         {
            $k = $j + 1;
            while($k < $Count && is_array($Tokens[$k]) && $Tokens[$k][0] == T_WHITESPACE)
            {
               $k++;
            }
            if($Tokens[$k] === ',' || $Tokens[$k] === ')')
            {
               $Patterns[] = $Tokens[$j];
            }
         }
         elseif(is_array($Tokens[$j]) && $Tokens[$j][0] == T_ARRAY || $Tokens[$j] === '[')
         {
            // array('pattern', 'pattern', ...) literal
            $Depth = 0;
            for($k = $j; $k < $Count; $k++)
            {
               if($Tokens[$k] === '(' || $Tokens[$k] === '[')
               {
                  $Depth++;
               }
               elseif($Tokens[$k] === ')' || $Tokens[$k] === ']')
               {
                  if(--$Depth <= 0)
                  {
                     break;
                  }
               }
               elseif($Depth == 1 && is_array($Tokens[$k]) && $Tokens[$k][0] == T_CONSTANT_ENCAPSED_STRING)
               {
                  $Next = $k + 1;
                  while($Next < $Count && is_array($Tokens[$Next]) && $Tokens[$Next][0] == T_WHITESPACE)
                  {
                     $Next++;
                  }
                  if($Tokens[$Next] === ',' || $Tokens[$Next] === ')' || $Tokens[$Next] === ']')
                  {
                     $Patterns[] = $Tokens[$k];
                  }
               }
            }
         }
         foreach($Patterns as $Token)
         {
            $Pattern = eval('return ' . $Token[1] . ';');
            if(!is_string($Pattern) || $Pattern === '')
            {
               continue;
            }
            $Checked++;
            if(@preg_match($Pattern, '') === false)
            {
               $Problems++;
               $Error = error_get_last();
               printf("%s:%d %s\n    %s\n", substr($Path, strlen($Root) + 1), $Token[2], $Token[1], $Error ? preg_replace('/^preg_match\(\): /', '', $Error['message']) : preg_last_error_msg());
            }
         }
      }
   }
}

printf("%d literal patterns checked, %d do not compile on PHP %s\n", $Checked, $Problems, PHP_VERSION);
exit($Problems ? 1 : 0);
