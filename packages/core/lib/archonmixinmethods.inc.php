<?php
/**
 * Mixin method runtime for ArchonObject.
 *
 * Archon composes its classes at run time: a class such as Collection is
 * declared empty and receives its methods from abstract "mixin" classes
 * (Collections_Collection, DigitalLibrary_Collection, ...) that are registered
 * with Archon::mixClasses().
 *
 * Up to Archon 3.21 the dispatcher simply called MixinClass::method()
 * statically from inside ArchonObject::__call() and relied on PHP 5 handing
 * the calling object to the mixin method as $this ("$this from incompatible
 * context").  PHP 7 removed that behaviour, so calling a mixin method
 * statically no longer gives it an object to work on.
 *
 * ArchonMixinMethods keeps every mixin file unchanged: the first time a mixin
 * method is needed, its parameter list and body are read from the source file
 * (via Reflection and the tokenizer), compiled once into a closure and cached
 * for the rest of the request.  Each call then runs that closure with $this
 * bound to the target object (Closure::call()), so the method sees exactly the
 * same object, static variables, magic constants and line numbers it saw
 * under PHP 5.
 *
 * Requires PHP 7.0 or newer (Closure::call()) and ext/tokenizer.
 *
 * @package Archon
 * @subpackage API
 */
final class ArchonMixinMethods
{
   /**
    * Compiled methods, keyed by lowercase "mixinclass::method".
    *
    * @var array
    */
   private static $Methods = array();

   /**
    * Extracted, not yet compiled method sources, keyed by file name and then
    * by lowercase "class::method".
    *
    * @var array
    */
   private static $Sources = array();

   /**
    * Runs $MixinClass::$Method() with $this bound to $Object.
    *
    * Arguments are passed positionally exactly as the PHP 5 dispatcher did
    * ($Arguments[0] ... $Arguments[count - 1]).  Required parameters that were
    * not supplied are passed as NULL with a warning (PHP 5 behaviour) instead
    * of throwing ArgumentCountError.
    *
    * @param object $Object
    * @param string $MixinClass
    * @param string $Method
    * @param array $Arguments
    * @return mixed
    */
   public static function invoke($Object, $MixinClass, $Method, array $Arguments = array())
   {
      $Key = strtolower($MixinClass . '::' . $Method);
      $Compiled = isset(self::$Methods[$Key]) ? self::$Methods[$Key] : self::compile($MixinClass, $Method);

      $Count = count($Arguments);
      $Positional = array();
      for($i = 0; $i < $Count; $i++)
      {
         $Positional[] = array_key_exists($i, $Arguments) ? $Arguments[$i] : NULL;
      }

      if($Count < $Compiled->RequiredParameters)
      {
         trigger_error(
            sprintf('Missing argument %d for %s::%s()', $Count + 1, $Compiled->ClassName, $Compiled->MethodName),
            E_USER_WARNING
         );
         $Positional = array_pad($Positional, $Compiled->RequiredParameters, NULL);
      }

      if($Compiled->IsStatic)
      {
         return call_user_func_array($Compiled->Closure, $Positional);
      }

      return $Compiled->Closure->call($Object, ...$Positional);
   }

   /**
    * Returns true if the mixin method can be compiled (used by the test suite).
    *
    * @param string $MixinClass
    * @param string $Method
    * @return boolean
    */
   public static function isCompilable($MixinClass, $Method)
   {
      try
      {
         self::compile($MixinClass, $Method);
      }
      catch(Exception $e)
      {
         return false;
      }

      return true;
   }

   /**
    * Compiles $MixinClass::$Method into a closure.
    *
    * @param string $MixinClass
    * @param string $Method
    * @return stdClass
    * @throws RuntimeException
    */
   private static function compile($MixinClass, $Method)
   {
      $Key = strtolower($MixinClass . '::' . $Method);

      try
      {
         $Reflection = new ReflectionMethod($MixinClass, $Method);
      }
      catch(ReflectionException $e)
      {
         throw new RuntimeException("Mixin method {$MixinClass}::{$Method}() does not exist", 0, $e);
      }

      if($Reflection->isAbstract())
      {
         throw new RuntimeException("Mixin method {$MixinClass}::{$Method}() is abstract");
      }

      $ClassName = $Reflection->getDeclaringClass()->getName();
      $MethodName = $Reflection->getName();
      $FileName = $Reflection->getFileName();

      if(!$FileName || !is_readable($FileName))
      {
         throw new RuntimeException("Source code for mixin method {$ClassName}::{$MethodName}() is not available");
      }

      if(!isset(self::$Sources[$FileName]))
      {
         self::$Sources[$FileName] = self::extractMethods($FileName);
      }

      $SourceKey = strtolower($ClassName . '::' . $MethodName);
      if(!isset(self::$Sources[$FileName][$SourceKey]))
      {
         throw new RuntimeException("Could not locate the source code of mixin method {$ClassName}::{$MethodName}() in {$FileName}");
      }

      $Source = self::$Sources[$FileName][$SourceKey];
      unset(self::$Sources[$FileName][$SourceKey]);

      $IsStatic = $Reflection->isStatic();

      // Pad with newlines so that line numbers reported for the compiled code
      // match the line numbers of the original source file.
      $Code = str_repeat("\n", max(0, $Source->Line - 1)) . 'return ' . ($IsStatic ? 'static ' : '') . $Source->Code . ';';

      $Closure = self::evaluate($Code);

      if(!($Closure instanceof Closure))
      {
         throw new RuntimeException("Could not compile mixin method {$ClassName}::{$MethodName}() from {$FileName}");
      }

      if($IsStatic)
      {
         $Closure = Closure::bind($Closure, NULL, $ClassName);
      }

      $Compiled = new stdClass();
      $Compiled->Closure = $Closure;
      $Compiled->IsStatic = $IsStatic;
      $Compiled->ClassName = $ClassName;
      $Compiled->MethodName = $MethodName;
      $Compiled->RequiredParameters = $Reflection->getNumberOfRequiredParameters();

      self::$Methods[$Key] = $Compiled;

      return $Compiled;
   }

   /**
    * Evaluates generated code outside of any object or class context.
    *
    * @param string $Code
    * @return mixed
    */
   private static function evaluate($Code)
   {
      try
      {
         return eval($Code);
      }
      catch(ParseError $e)
      {
         throw new RuntimeException('Could not compile mixin method: ' . $e->getMessage(), 0, $e);
      }
   }

   /**
    * Extracts the parameter lists and bodies of all methods declared in $FileName.
    *
    * @param string $FileName
    * @return stdClass[] keyed by lowercase "class::method"
    */
   private static function extractMethods($FileName)
   {
      $Tokens = token_get_all(file_get_contents($FileName));
      $Count = count($Tokens);

      $Methods = array();
      $Depth = 0;
      $ClassStack = array();
      $PendingClass = NULL;

      for($i = 0; $i < $Count; $i++)
      {
         $Token = $Tokens[$i];

         if(is_array($Token))
         {
            switch($Token[0])
            {
               case T_CLASS:
               case T_INTERFACE:
               case T_TRAIT:
                  $Previous = self::previousSignificantToken($Tokens, $i);
                  if(is_array($Previous) && ($Previous[0] == T_DOUBLE_COLON || $Previous[0] == T_NEW))
                  {
                     // Foo::class or an anonymous class.
                     break;
                  }

                  $Next = self::nextSignificantTokenIndex($Tokens, $i);
                  if($Next !== NULL && is_array($Tokens[$Next]) && $Tokens[$Next][0] == T_STRING)
                  {
                     $PendingClass = $Tokens[$Next][1];
                  }
                  break;

               case T_CURLY_OPEN:
               case T_DOLLAR_OPEN_CURLY_BRACES:
                  $Depth++;
                  break;

               case T_FUNCTION:
                  $CurrentClass = end($ClassStack);
                  if($CurrentClass && $CurrentClass->Depth == $Depth)
                  {
                     $Method = self::extractMethod($Tokens, $i, $CurrentClass->Name, $FileName);
                     if($Method)
                     {
                        $Methods[strtolower($CurrentClass->Name . '::' . $Method->Name)] = $Method;
                        // Continue after the method body.
                        $i = $Method->EndIndex;
                     }
                  }
                  break;
            }

            continue;
         }

         if($Token === '{')
         {
            $Depth++;

            if($PendingClass !== NULL)
            {
               $Class = new stdClass();
               $Class->Name = $PendingClass;
               $Class->Depth = $Depth;
               $ClassStack[] = $Class;
               $PendingClass = NULL;
            }
         }
         elseif($Token === '}')
         {
            $CurrentClass = end($ClassStack);
            if($CurrentClass && $CurrentClass->Depth == $Depth)
            {
               array_pop($ClassStack);
            }

            $Depth--;
         }
      }

      return $Methods;
   }

   /**
    * Extracts a single method starting at the T_FUNCTION token $Start.
    *
    * The generated code keeps every token (and therefore every newline) of the
    * original declaration except the method name, so line numbers are kept.
    *
    * @param array $Tokens
    * @param integer $Start
    * @param string $ClassName
    * @param string $FileName
    * @return stdClass|null
    */
   private static function extractMethod($Tokens, $Start, $ClassName, $FileName)
   {
      $Count = count($Tokens);

      $NameIndex = self::nextSignificantTokenIndex($Tokens, $Start);
      if($NameIndex !== NULL && self::isAmpersand($Tokens[$NameIndex]))
      {
         $NameIndex = self::nextSignificantTokenIndex($Tokens, $NameIndex);
      }

      if($NameIndex === NULL || !is_array($Tokens[$NameIndex]) || !preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $Tokens[$NameIndex][1]))
      {
         return NULL;
      }

      $MethodName = $Tokens[$NameIndex][1];

      // Find the end of the parameter list, then the start of the body.
      $ParenthesisDepth = 0;
      $BodyStart = NULL;
      $SeenParameters = false;
      for($j = $NameIndex + 1; $j < $Count; $j++)
      {
         $Token = $Tokens[$j];
         if($Token === '(')
         {
            $ParenthesisDepth++;
            $SeenParameters = true;
         }
         elseif($Token === ')')
         {
            $ParenthesisDepth--;
         }
         elseif($ParenthesisDepth == 0 && $SeenParameters)
         {
            if($Token === '{')
            {
               $BodyStart = $j;
               break;
            }
            elseif($Token === ';')
            {
               // Abstract or interface method without a body.
               return NULL;
            }
         }
      }

      if($BodyStart === NULL)
      {
         return NULL;
      }

      $BraceDepth = 0;
      $BodyEnd = NULL;
      for($j = $BodyStart; $j < $Count; $j++)
      {
         $Token = $Tokens[$j];
         if($Token === '{' || (is_array($Token) && ($Token[0] == T_CURLY_OPEN || $Token[0] == T_DOLLAR_OPEN_CURLY_BRACES)))
         {
            $BraceDepth++;
         }
         elseif($Token === '}')
         {
            $BraceDepth--;
            if($BraceDepth == 0)
            {
               $BodyEnd = $j;
               break;
            }
         }
      }

      if($BodyEnd === NULL)
      {
         return NULL;
      }

      $MagicConstants = array(
         T_CLASS_C => var_export($ClassName, true),
         T_FUNC_C => var_export($MethodName, true),
         T_METHOD_C => var_export($ClassName . '::' . $MethodName, true),
         T_FILE => var_export($FileName, true),
         T_DIR => var_export(dirname($FileName), true),
         T_NS_C => "''",
         T_TRAIT_C => "''",
      );

      $Code = '';
      for($j = $Start; $j <= $BodyEnd; $j++)
      {
         $Token = $Tokens[$j];

         if($j == $NameIndex)
         {
            continue;
         }

         // By-reference parameters only ever receive a copy of the caller's
         // value through ArchonObject::__call(), so they are compiled as
         // by-value parameters (Closure::call() cannot pass references).
         // Return-by-reference ("function &name") is kept.
         if($j > $NameIndex && $j < $BodyStart && self::isAmpersand($Token))
         {
            $Next = self::nextSignificantTokenIndex($Tokens, $j);
            if($Next !== NULL && is_array($Tokens[$Next]) && ($Tokens[$Next][0] == T_VARIABLE || $Tokens[$Next][0] == T_ELLIPSIS))
            {
               continue;
            }
         }

         if(!is_array($Token))
         {
            $Code .= $Token;
         }
         elseif($Token[0] == T_LINE)
         {
            $Code .= $Token[2];
         }
         elseif(isset($MagicConstants[$Token[0]]))
         {
            $Code .= $MagicConstants[$Token[0]];
         }
         else
         {
            $Code .= $Token[1];
         }
      }

      $Method = new stdClass();
      $Method->Name = $MethodName;
      $Method->Code = $Code;
      $Method->Line = $Tokens[$Start][2];
      $Method->EndIndex = $BodyEnd;

      return $Method;
   }

   private static function isAmpersand($Token)
   {
      // PHP 8.1+ tokenizes '&' as T_AMPERSAND_(NOT_)FOLLOWED_BY_VAR_OR_VARARG.
      return $Token === '&' || (is_array($Token) && $Token[1] === '&');
   }

   private static function previousSignificantToken($Tokens, $Index)
   {
      for($i = $Index - 1; $i >= 0; $i--)
      {
         if(!is_array($Tokens[$i]) || !in_array($Tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT)))
         {
            return $Tokens[$i];
         }
      }

      return NULL;
   }

   private static function nextSignificantTokenIndex($Tokens, $Index)
   {
      $Count = count($Tokens);
      for($i = $Index + 1; $i < $Count; $i++)
      {
         if(!is_array($Tokens[$i]) || !in_array($Tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT)))
         {
            return $i;
         }
      }

      return NULL;
   }
}
