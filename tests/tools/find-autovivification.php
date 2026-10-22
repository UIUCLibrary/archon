<?php
/**
 * Static check for a PHP 8 incompatibility that the crawler can only find on visited code paths:
 * assigning a property on a local variable that was never assigned, e.g.
 *
 *     function f() { $Result->Count = 1; }      // PHP 5/7: creates a stdClass; PHP 8: Error
 *
 * Every function and method body in the given files/directories is scanned. A variable counts as
 * assigned when it is a parameter, a global/static, a closure "use" variable, a foreach/list/catch
 * target, or appears on the left of "=" / "=&" earlier in the function. Variables passed by
 * reference to functions (e.g. preg_match(..., $Matches)) also count as assigned.
 *
 * Usage: php find-autovivification.php [path ...]   (default: the repository root)
 * Exit status: 0 = nothing found, 1 = candidates found.
 */

$Root = dirname(__DIR__, 2);
$Paths = array_slice($argv, 1) ?: array($Root);
$Skip = '#/(tests|_dev|vendor|packages/core/install/upgrade)/#';

$Files = array();
foreach($Paths as $Path)
{
   if(is_file($Path))
   {
      $Files[] = $Path;
      continue;
   }
   $Iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($Path, FilesystemIterator::SKIP_DOTS));
   foreach($Iterator as $File)
   {
      $Name = $File->getPathname();
      if(preg_match('/\.(php|php_|inc)$/', $Name) && !preg_match($Skip, $Name))
      {
         $Files[] = $Name;
      }
   }
}
sort($Files);

$AssignmentOperators = array('=', T_CONCAT_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_MOD_EQUAL,
   T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_POW_EQUAL, T_COALESCE_EQUAL, T_INC, T_DEC);
$Superglobals = array('$this', '$GLOBALS', '$_SERVER', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES', '$_ENV', '$_SESSION');

function significant($Tokens, $i, $Direction)
{
   for($i += $Direction; isset($Tokens[$i]); $i += $Direction)
   {
      if(!is_array($Tokens[$i]) || !in_array($Tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT)))
      {
         return $i;
      }
   }

   return NULL;
}

function tokenIs($Token, $Type)
{
   return is_array($Token) ? $Token[0] === $Type : $Token === $Type;
}

$Findings = array();
foreach($Files as $FileName)
{
   $Tokens = token_get_all(file_get_contents($FileName));
   $Count = count($Tokens);

   for($i = 0; $i < $Count; $i++)
   {
      if(!tokenIs($Tokens[$i], T_FUNCTION) && !tokenIs($Tokens[$i], T_FN))
      {
         continue;
      }

      // Parameters (and closure "use" variables) up to the opening brace.
      $Assigned = array();
      $j = $i + 1;
      while($j < $Count && !tokenIs($Tokens[$j], '{') && !tokenIs($Tokens[$j], ';'))
      {
         if(tokenIs($Tokens[$j], T_VARIABLE))
         {
            $Assigned[$Tokens[$j][1]] = true;
         }
         $j++;
      }
      if($j >= $Count || !tokenIs($Tokens[$j], '{'))
      {
         continue;
      }

      // Function body.
      $Depth = 0;
      $Start = $j;
      for($k = $Start; $k < $Count; $k++)
      {
         $Token = $Tokens[$k];
         if(tokenIs($Token, '{') || tokenIs($Token, T_CURLY_OPEN) || tokenIs($Token, T_DOLLAR_OPEN_CURLY_BRACES))
         {
            $Depth++;
         }
         elseif(tokenIs($Token, '}'))
         {
            $Depth--;
            if($Depth == 0)
            {
               break;
            }
         }
         elseif(tokenIs($Token, T_GLOBAL) || tokenIs($Token, T_STATIC) || tokenIs($Token, T_USE))
         {
            for($m = $k + 1; $m < $Count && !tokenIs($Tokens[$m], ';') && !tokenIs($Tokens[$m], '{'); $m++)
            {
               if(tokenIs($Tokens[$m], T_VARIABLE))
               {
                  $Assigned[$Tokens[$m][1]] = true;
               }
            }
         }
         elseif(tokenIs($Token, T_AS) || tokenIs($Token, T_LIST) || tokenIs($Token, T_CATCH) || tokenIs($Token, T_UNSET))
         {
            // foreach (... as $k => $v), list($a, $b) = ..., catch (E $e)
            $Nesting = 0;
            for($m = $k + 1; $m < $Count; $m++)
            {
               if(tokenIs($Tokens[$m], '('))
               {
                  $Nesting++;
               }
               elseif(tokenIs($Tokens[$m], ')'))
               {
                  if(--$Nesting < 0 || ($Nesting == 0 && !tokenIs($Token, T_AS)))
                  {
                     break;
                  }
               }
               elseif(tokenIs($Tokens[$m], T_VARIABLE))
               {
                  $Assigned[$Tokens[$m][1]] = true;
               }
            }
         }
         elseif(tokenIs($Token, '['))
         {
            // [$a, $b] = ... short list syntax
            $Close = $k;
            $Nesting = 0;
            for($m = $k; $m < $Count; $m++)
            {
               if(tokenIs($Tokens[$m], '['))
               {
                  $Nesting++;
               }
               elseif(tokenIs($Tokens[$m], ']') && --$Nesting == 0)
               {
                  $Close = $m;
                  break;
               }
            }
            $Previous = significant($Tokens, $k, -1);
            $Next = significant($Tokens, $Close, 1);
            if($Next !== NULL && tokenIs($Tokens[$Next], '=') && ($Previous === NULL || !tokenIs($Tokens[$Previous], T_VARIABLE)))
            {
               for($m = $k; $m < $Close; $m++)
               {
                  if(tokenIs($Tokens[$m], T_VARIABLE))
                  {
                     $Assigned[$Tokens[$m][1]] = true;
                  }
               }
            }
         }
         elseif(tokenIs($Token, T_VARIABLE))
         {
            $Name = $Token[1];
            $Next = significant($Tokens, $k, 1);
            $Previous = significant($Tokens, $k, -1);

            if($Next === NULL)
            {
               continue;
            }

            // Passed as a whole to a function call: may be a by-reference output argument.
            if($Previous !== NULL && (tokenIs($Tokens[$Previous], '(') || tokenIs($Tokens[$Previous], ',') || tokenIs($Tokens[$Previous], '&'))
               && (tokenIs($Tokens[$Next], ',') || tokenIs($Tokens[$Next], ')')))
            {
               $Assigned[$Name] = true;
               continue;
            }

            if(tokenIs($Tokens[$Next], '=') || (is_array($Tokens[$Next]) && in_array($Tokens[$Next][0], $GLOBALS['AssignmentOperators'], true)) || tokenIs($Tokens[$Next], '['))
            {
               $Assigned[$Name] = true;
               continue;
            }

            if(!tokenIs($Tokens[$Next], T_OBJECT_OPERATOR) || in_array($Name, $Superglobals) || isset($Assigned[$Name]))
            {
               continue;
            }

            // $Var->Property (->Property | [...])* followed by an assignment operator.
            $m = $Next;
            $Chain = '';
            while($m !== NULL && (tokenIs($Tokens[$m], T_OBJECT_OPERATOR) || tokenIs($Tokens[$m], '[')))
            {
               if(tokenIs($Tokens[$m], '['))
               {
                  $Nesting = 0;
                  for(; $m < $Count; $m++)
                  {
                     if(tokenIs($Tokens[$m], '['))
                     {
                        $Nesting++;
                     }
                     elseif(tokenIs($Tokens[$m], ']') && --$Nesting == 0)
                     {
                        break;
                     }
                  }
                  $Chain .= '[...]';
               }
               else
               {
                  $Property = significant($Tokens, $m, 1);
                  if($Property === NULL || !tokenIs($Tokens[$Property], T_STRING))
                  {
                     break;
                  }
                  $Chain .= '->' . $Tokens[$Property][1];
                  $m = $Property;
                  $After = significant($Tokens, $m, 1);
                  if($After !== NULL && tokenIs($Tokens[$After], '('))
                  {
                     $Chain = NULL;
                     break;
                  }
               }
               $m = significant($Tokens, $m, 1);
            }
            if($Chain === NULL || $m === NULL)
            {
               continue;
            }
            $Operator = $Tokens[$m];
            if(tokenIs($Operator, '=') || (is_array($Operator) && in_array($Operator[0], $GLOBALS['AssignmentOperators'], true)))
            {
               $Line = $Token[2];
               $Findings[] = sprintf('%s:%d  %s%s', substr($FileName, strlen($Root) + 1), $Line, $Name, $Chain);
               $Assigned[$Name] = true;
            }
         }
      }
      $i = $Start;
   }
}

foreach($Findings as $Finding)
{
   echo $Finding, "\n";
}
fwrite(STDERR, count($Findings) . " candidate(s) in " . count($Files) . " files\n");

exit($Findings ? 1 : 0);
