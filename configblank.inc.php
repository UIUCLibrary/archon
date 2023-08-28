<?php
/**
 * Configuration file for Archon
 *
 * IMPORTANT:
 *
 * The following variables MUST be defined or Archon
 * will NOT work properly:
 *
 *      $_ARCHON->db->ServerType
 *      $_ARCHON->db->ServerAddress
 *      $_ARCHON->db->Login
 *      $_ARCHON->db->Password
 *      $_ARCHON->db->Database
 *
 * @package Archon
 * @author Chris Rishel
 */



// ***********************************************
// * Database Configuration                      *
// ***********************************************

   // ### REQUIRED ###
   // ********************************************
   // * $_ARCHON->db->ServerType                    *
   // ********************************************
   //
   //   - Explanation:
   //       Contains a string which indicates
   //       what type of Database server Archon
   //       will run on.
   //
   //   - Allowed Values:
   //       Any compatable database module installed in
   //       /(Archon Installation Directory)/db/
   //
   //       Archon's Database Module is installed with support
   //       for the following values for ServerType:
   //
   //     $_ARCHON->db->ServerType = 'MSSQL'      (Microsoft SQL Server)
   //     $_ARCHON->db->ServerType = 'MySQL'      (MySQL)
   //
   //       Unless you have added an additional module, please
   //       use one of the above options.
   //
   //         (NOTE: A custom database module MUST be named as
   //           (ServerType)Database.php
   //         For example, the module for 'MSSQL' is named:
   //           MSSQLDatabase.php
   //
   //        IMPORTANT: This variable IS case sensitive.
   //
   //   - Example:
   //       $_ARCHON->db->ServerType = 'MySQL';

   $_ARCHON->db->ServerType = 'MySQL';

   // ### REQUIRED ###
   // ********************************************
   // * $_ARCHON->db->ServerAddress                 *
   // ********************************************
   //
   //   - Explanation:
   //       Contains a string with either
   //       a numerical IP Address or a DNS
   //       Hostname that represents the location of
   //       the database server.
   //
   //   - Allowed Values:
   //       Any valid TCP/IP address
   //
   //   - Example:
   //       $_ARCHON->db->ServerAddress = 'localhost';
   //     OR
   //       $_ARCHON->db->ServerAddrses = '127.0.0.1';
   //

   $_ARCHON->db->ServerAddress = 'localhost';

   // ### REQUIRED ###
   // ********************************************
   // * $_ARCHON->db->Login                         *
   // ********************************************
   //
   //   - Explanation:
   //       This variable contains a string with
   //       a login for Archon to use to connect to the
   //       database server.
   //
   //   - Allowed Values:
   //       Any string
   //
   //   - Example:
   //       $_ARCHON->db->ServerAddress = 'ArchonWebUser';
   //

   $_ARCHON->db->Login = 'ArchonWebUser';

   // ### REQUIRED ###
   // ********************************************
   // * $_ARCHON->db->Password                      *
   // ********************************************
   //
   //   - Explanation:
   //       This variable contains a string with
   //       the password for the user above for
   //       Archon to use to connect to the database server.
   //
   //   - Allowed Values:
   //       Any string
   //
   //   - Example:
   //       $_ARCHON->db->Password = 'Archon';
   //

   $_ARCHON->db->Password = 'Archon';

   // ### REQUIRED ###
   // ********************************************
   // * $_ARCHON->db->DatabaseName                  *
   // ********************************************
   //
   //   - Explanation:
   //       This variable contains a string with
   //       the name of the database for Archon to use
   //       on the database server specified above.
   //
   //       IMPORTANT: The login specified above MUST
   //                  be granted the following permissions
   //                  on this database:
   //
   //         SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP
   //
   //
   //   - Allowed Values:
   //       Any string
   //
   //   - Example:
   //       $_ARCHON->db->DatabaseName = 'Archon';
   //

   $_ARCHON->db->DatabaseName = 'Archon';



  // ********************************************
   // * $_ARCHON->db->VersionTDS                        *
   // ********************************************
   //
   //   - Explanation:
   //       Enter the TDS version in use.
   //   - If nothing is entered, Archon will default
   //       to 70.
   //

  // $_ARCHON->db->VersionTDS = '70';


// ***********************************************
// * Database Encoding Conversion Settings       *
// ***********************************************
// Only used for MSSQL servers.
//
// Set to false to have the php convert from
// Latin-1 to UTF-8 (previous behavior in Archon).
//
// Set to true to turn this conversion off if
// the database is already in UTF-8 or if the
// text is getting converted elsewhere already.
//
// Note: If you have trouble with Archon converting
// special characters to multiple other characters
// you may want to try setting this to true to 
// see if double encoding is the culprit.

   //$_ARCHON->config->DatabaseEncodingUTF8=false;

//  ***********************************************
// * Google Analytics Configuration              *
// ***********************************************

//   $_ARCHON->config->GACode = "UA-0000000-0";
//   $_ARCHON->config->GACollectionsURL = "/downloads/pdfsfa";
//   $_ARCHON->config->GADigContentPrefix = "/digcontent";



// ***********************************************
// * Security Configuration                      *
// ***********************************************

//   $_ARCHON->config->ForceHTTPS = true;


   
   
// ***********************************************
// * File Cache Configuration                    *
// * Requires existence of directory             *
// * packages/digitallibrary/files
// ***********************************************

//   $_ARCHON->config->CacheFiles = true;
//   $_ARCHON->config->CachePermissions = 0755;


// ***********************************************
// * Search handling for identifiers             *
// ***********************************************
//
//    If TRUE, will only return an exact match.
//
//    IF FALSE, will return an exact match if
//       found and else will return a partial
//       match. Note it will only return a single
//       partial match, not all possible matches.
//
// ***********************************************

$_ARCHON->config->SearchExactIdentifier=false;

// ***********************************************
// * Subject heading IDs for CHSTM list *
// ***********************************************
// Note: Referenced from the theme

$_ARCHON->config->CHSTMSubjectList = array();

 // ***********************************************
// * Request link configuration *
// ***********************************************
include("config_request.inc.php");
?>