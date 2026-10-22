<?php
/**
 * Header file for ihlc theme
 *
 * @package Archon
 * @author Paul Sorensen, originally adapted from "default" by Chris Rishel, Chris Prom, Kyle Fox
 */
isset($_ARCHON) or die();
$_REQUEST['templateset'] = "illinois";

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
      $RepositoryName = $_ARCHON->Repository ? $_ARCHON->Repository->getString('Name') : 'Illinois History and Lincoln Collections: Holdings';
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

if(defined('PACKAGE_COLLECTIONS'))
{
	if($_ARCHON->QueryString && $_ARCHON->Script == 'packages/core/pub/search.php')
   {
      if(is_numeric($_ARCHON->getString('QueryString')))
	  {
		// add note "did you mean?"
		echo("<div id='ihlc-message'>Did you mean to search by collection identifier? Click here to <a href='?f=identifiersearch&q=");
		echo(encode($_ARCHON->QueryString, ENCODE_HTML));
		echo("'>lookup by collection identifier</a>.</div>");
	  }
	  
	  if(strpos($_ARCHON->getString('QueryString'),"civil war")>-1)
	  {
		echo("<div id='ihlc-message-gray'><strong>Related resources:</strong> <a href='https://www.library.illinois.edu/ihx/wp-content/uploads/sites/41/2017/05/ihlc-regiments-guide.pdf' target='_blank'>IHLC Civil War Manuscript Collections PDF Guide</a>, arranged by regiment.</div>");
	  }
   } elseif($_ARCHON->config->CollectionDetailList and $_ARCHON->Script == 'packages/collections/pub/collections.php')
   {
	   if(isset($_REQUEST['char']) or isset($_REQUEST['browse']))
	   {
	   echo("<button class='morelessbutton' data-text-swap='expand descriptions'>hide descriptions</button>");
	   } 
   }
   
   
}
?>
      <div id="main">
	  
