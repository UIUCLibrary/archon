<?php
function pear_isError($arg) {
   if($arg instanceof ArchonSqlsrvError)
   {
      return true;
   }

   require_once("PEAR.php");
   static $pear;
   if(!isset($pear))
      $pear = new PEAR();
   return $pear->isError($arg);
}
?>
