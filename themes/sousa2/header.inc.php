<?php
/**
 * Header file for default theme
 *
 * @package Archon
 * @author Paul Sorensen, originally adapted from "default" by Chris Rishel, Chris Prom, Kyle Fox
 */
isset($_ARCHON) or die();

// *** This is now a configuration directive. Please set in the Configuration Manager ***
//$_ARCHON->PublicInterface->EscapeXML = false;


if($_ARCHON->Script == 'packages/collections/pub/findingaid.php')
{
   require("faheader.inc.php");
   return;
}

$_ARCHON->PublicInterface->Header->OnLoad .= "externalLinks();";

if($_ARCHON->Error)
{
   $_ARCHON->PublicInterface->Header->OnLoad .= " alert('" . encode(str_replace(';', "\n", $_ARCHON->processPhrase($_ARCHON->Error)), ENCODE_JAVASCRIPT) . "');";
}

if(defined('PACKAGE_COLLECTIONS'))
{

   if($objCollection->Repository)
   {
      $RepositoryName = $objCollection->Repository->getString('Name');
   }
   elseif($objDigitalContent->Collection->Repository)
   {
      $RepositoryName = $objDigitalContent->Collection->Repository->getString('Name');
   }
   else
   {
      $RepositoryName = $_ARCHON->Repository ? $_ARCHON->Repository->getString('Name') : '';
   }

   $_ARCHON->PublicInterface->Title = $_ARCHON->PublicInterface->Title ? $_ARCHON->PublicInterface->Title . ' | ' . $RepositoryName : $RepositoryName;

   if($_ARCHON->QueryString && $_ARCHON->Script == 'packages/core/pub/search.php')
   {
      $_ARCHON->PublicInterface->addNavigation("Search Results For \"" . $_ARCHON->getString('QueryString') . "\"", "?p=core/search&amp;q=" . $_ARCHON->QueryStringURL, true);
   }
}
else
{
   $RepositoryName = $_ARCHON->Repository ? $_ARCHON->Repository->getString('Name') : 'Archon';

   $_ARCHON->PublicInterface->Title = $_ARCHON->PublicInterface->Title ? $_ARCHON->PublicInterface->Title . ' | ' . $RepositoryName : $RepositoryName;

   if($_ARCHON->QueryString)
   {
      $_ARCHON->PublicInterface->addNavigation("Search Results For \"" . encode($_ARCHON->QueryString, ENCODE_HTML) . "\"", "?p=core/search&amp;q=" . $_ARCHON->QueryStringURL, true);
   }
}

require('commonheader.inc.php');
?>

      <div id="main">
