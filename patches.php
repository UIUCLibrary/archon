<?php
/**
 * Returns true if $arg is a database error object.
 *
 * Works without PEAR: errors from the SQL Server layer are ArchonSqlsrvError objects, and the
 * PEAR_Error class is only checked when PEAR has already been loaded (e.g. by MDB2 for MySQL).
 *
 * @param mixed $arg
 * @return boolean
 */
function pear_isError($arg) {
   if($arg instanceof ArchonSqlsrvError)
   {
      return true;
   }

   if(class_exists('PEAR_Error', false))
   {
      return $arg instanceof PEAR_Error;
   }

   return false;
}
?>
