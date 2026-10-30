-- One-time data fix for SQL Server databases installed from the stock install-mssql.sql.
-- That script wrote the validation patterns with doubled backslashes (a MySQL escape that
-- SQL Server stores literally), so number, US phone and e-mail validation rejects valid values.
-- It also removes the URL pattern's /e modifier, which PHP 7 and later no longer accept.
-- Safe to run more than once; it only changes the four stock patterns while they are still broken.
UPDATE tblCore_Patterns
   SET Pattern = REPLACE(REPLACE(CAST(Pattern AS varchar(max)), '\\', '\'), '$#ie', '$#i')
 WHERE Name IN ('Non-Negative Number', 'Email Address', 'US Phone Number', 'URL')
   AND (CAST(Pattern AS varchar(max)) LIKE '%\\%' OR CAST(Pattern AS varchar(max)) LIKE '%$#ie');
