<?php
/**
 * Header file for illiois theme
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
      $RepositoryName = $_ARCHON->Repository ? $_ARCHON->Repository->getString('Name') : 'ARLIS NA Archives: Holdings, Images, and E-Records';
   }

   $_ARCHON->PublicInterface->Title = $_ARCHON->PublicInterface->Title ? $_ARCHON->PublicInterface->Title . ' | ' . $RepositoryName : $RepositoryName;

   if($_ARCHON->QueryString && $_ARCHON->Script == 'packages/core/pub/search.php')
   {
      $_ARCHON->PublicInterface->addNavigation("Search Results For \"" . $_ARCHON->getString(QueryString) . "\"", "?p=core/search&amp;q=" . $_ARCHON->QueryStringURL, true);
   }
   
}
else
{
   //$RepositoryName = $_ARCHON->Repository ? $_ARCHON->Repository->getString('Name') : 'Archon';
	$RepositoryName = "Art Libraries Society of North America Archives";


   $_ARCHON->PublicInterface->Title = $_ARCHON->PublicInterface->Title ? $_ARCHON->PublicInterface->Title . ' | ' . $RepositoryName : $RepositoryName;

   if($_ARCHON->QueryString)
   {
      $_ARCHON->PublicInterface->addNavigation("Search Results For \"" . encode($_ARCHON->QueryString, ENCODE_HTML) . "\"", "?p=core/search&amp;q=" . $_ARCHON->QueryStringURL, true);
   }

}

$_ARCHON->PublicInterface->addNavigation('Holdings', 'index.php', true);
$_ARCHON->PublicInterface->addNavigation('University Archives', 'http://archives.library.edu/', true);

header('Content-type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <meta name="og:site_name" content="Art Libraries Society of North America Archives Database"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?php echo(strip_tags($_ARCHON->PublicInterface->Title)); ?></title>
      <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->Theme); ?>/style.css?v=20230315" />
      <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.css" />
      <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/jgrowl/jquery.jgrowl.css" />
      <link rel="icon" type="image/ico" href="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/favicon.ico"/>
      <!--[if lte IE 7]>
        <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->Theme); ?>/ie.css" />
        <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.ie.css" />
      <![endif]-->
      <?php echo($_ARCHON->getJavascriptTags('jquery.min')); ?>
      <?php echo($_ARCHON->getJavascriptTags('jquery-ui.custom.min')); ?>
      <?php echo($_ARCHON->getJavascriptTags('jquery-expander')); ?>
      <script type="text/javascript" src="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/jquery.hoverIntent.js"></script>
      <script type="text/javascript" src="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.js"></script>      
      <script type="text/javascript" src="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/jquery.scrollTo-min.js"></script>
      <?php echo($_ARCHON->getJavascriptTags('jquery.jgrowl.min')); ?>
      <?php echo($_ARCHON->getJavascriptTags('archon')); ?>
      <script type="text/javascript">
         /* <![CDATA[ */
         imagePath = '<?php echo($_ARCHON->PublicInterface->ImagePath); ?>';   
         $(document).ready(function() {          
            $('div.listitem:nth-child(even)').addClass('evenlistitem');
            $('div.listitem:last-child').addClass('lastlistitem');
            $('#locationtable tr:nth-child(odd)').addClass('oddtablerow');
            $('.expandable').expander({
               slicePoint:       600,             // make expandable if over this x chars
               widow:            100,             // do not make expandable unless total length > slicePoint + widow
               expandPrefix:     '. . . ',        // text to come before the expand link
               expandText:       'more',     			//text to use for expand link
               expandEffect:     'fadeIn',        // or slideDown
               expandSpeed:      0,              	// in milliseconds
               collapseTimer:    0,               // milliseconds before auto collapse; default is 0 (don't re-collape)
               userCollapseText: '[collapse]'     // text for collaspe link
            });
				$('.expandablesmall').expander({
               slicePoint:       100,             // make expandable if over this x chars
               widow:            10,              // do not make expandable unless total length > slicePoint + widow
               expandPrefix:     '. . . ',       	// text to come before the expand link
               expandText:       'more',  				//text to use for expand link
               expandEffect:     'fadeIn',        // or slideDown
               expandSpeed:      0,              	// in milliseconds
               collapseTimer:    0,              	// milliseconds before auto collapse; default is 0 (don't re-collape)
               userCollapseText: '[collapse]'     // text for collaspe link
            });
         });

         function js_highlighttoplink(selectedSpan)
         {
            $('.currentBrowseLink').toggleClass('browseLink').toggleClass('currentBrowseLink');
            $(selectedSpan).toggleClass('currentBrowseLink');
            $(selectedSpan).effect('highlight', {}, 400);
         }

         $(document).ready(function() {<?php echo($_ARCHON->PublicInterface->Header->OnLoad); ?>});
         $(window).unload(function() {<?php echo($_ARCHON->PublicInterface->Header->OnUnload); ?>});
         /* ]]> */
      </script>
      <?php
      if($_ARCHON->PublicInterface->Header->Message && $_ARCHON->PublicInterface->Header->Message != $_ARCHON->Error)
      {
         $message = $_ARCHON->PublicInterface->Header->Message;
      }
      $_ARCHON->PublicInterface->outputGoogleAnalyticsCode();
      ?>
   </head>
   <body>
      <?php
     
      if($message)
      {
         echo("<div class='message'>" . encode($message, ENCODE_HTML) . "</div>\n");
      }
      ?>
      <div id='top'>

         <div id="logosearchwrapper">
            <div id="logo"><a href="index.php" ><img src="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/logo.gif" alt="logo" /></a><br/>Archives</div>
            <div id="searchblock">
               <form action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
                  <div>
                     <label for="q" class='bold' style='color:white;font-size:1em;padding-bottom:0.3em;'>Search ARLIS/NA Archives ONLY:</label><br />
					 <input type="hidden" name="p" value="core/search" />
					 <input type="hidden" name="setrepositoryid" value="4" />
                     <input type="text" size="25" maxlength="150" name="q" id="q" title="input box for search field" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" />
                     <input type="submit" value="Search" tabindex="300" class='button' title="Search" /> 
                     <?php
                     if(defined('PACKAGE_COLLECTIONS') && CONFIG_COLLECTIONS_SEARCH_BOX_LISTS)
                     {
                        ?>
                        <input type="hidden" name="content" value="1" />
                        <?php
                     }
                     ?>
					 <a class='bold' style='color:white' href='?p=core/index&amp;f=pdfsearch'>Search PDF lists</a>
                  </div></form></div>

         </div>
         <div id="researchblock">

            <?php
            if($_ARCHON->Security->isAuthenticated())
            {
               echo("<span class='bold'>Welcome, " . $_ARCHON->Security->Session->User->toString() . "</span><br/>");

               $logoutURI = preg_replace('/(&|\\?)f=([\\w])*/', '', $_SERVER['REQUEST_URI']);
               $Logout = (encoding_strpos($logoutURI, '?') !== false) ? '&amp;f=logout' : '?f=logout';
               $strLogout = encode($logoutURI, ENCODE_HTML) . $Logout;
               echo("<a href='$strLogout'>Logout</a>");
            }
            elseif($_ARCHON->config->ForceHTTPS)
            {
               echo("<a href='index.php?p=core/login&amp;go='>Log In</a>");
            }
            else
            {
               echo("<a href='#' onclick='$(window).scrollTo(\"#archoninfo\"); if($(\"#userlogin\").is(\":visible\")) $(\"#loginlink\").html(\"Log In\"); else $(\"#loginlink\").html(\"Hide\"); $(\"#userlogin\").slideToggle(\"normal\"); $(\"#ArchonLoginField\").focus(); return false;'>Log In</a>");
            }

            if(!$_ARCHON->Security->userHasAdministrativeAccess())
            {
               $emailpage = defined('PACKAGE_COLLECTIONS') ? "collections/research" : "core/contact";

               //echo(" | <a href='?p={$emailpage}&amp;f=email&amp;referer=" . urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>Contact Us</a>");
               echo(" | <a href='https://archives.library.illinois.edu/arlis/about/contact.php' target='_blank'>Contact Us</a>");
               if($_ARCHON->Security->isAuthenticated())
               {
                  echo(" | <a href='?p=core/account&amp;f=account'>My Account</a>");
               }
               if(defined('PACKAGE_COLLECTIONS'))
               {
                  $_ARCHON->Security->Session->ResearchCart->getCart();
                  $EntryCount = $_ARCHON->Security->Session->ResearchCart->getCartCount();
                  $class = $_ARCHON->Repository->ResearchFunctionality & RESEARCH_COLLECTIONS ? '' : 'hidewhenempty';
                  $hidden = ($_ARCHON->Repository->ResearchFunctionality & RESEARCH_COLLECTIONS || $EntryCount) ? '' : "style='display:none'";

                  echo("<span id='viewcartlink' class='$class' $hidden>| <a href='?p=collections/research&amp;f=cart&amp;referer=" . urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>View Cart (<span id='cartcount'>$EntryCount</span>)</a></span>");
               }
            }
            ?>
         </div>



         <?php
         $arrP = explode('/', $_REQUEST['p']);
         $TitleClass = $arrP[0] == 'collections' && $arrP[1] != 'classifications' ? 'currentBrowseLink' : 'browseLink';
         $ClassificationsClass = $arrP[1] == 'classifications' ? 'currentBrowseLink' : 'browseLink';
         $SubjectsClass = $arrP[0] == 'subjects' ? 'currentBrowseLink' : 'browseLink';
         $CreatorsClass = $arrP[0] == 'creators' ? 'currentBrowseLink' : 'browseLink';
         $DigitalLibraryClass = $arrP[0] == 'digitallibrary' ? 'currentBrowseLink' : 'browseLink';
         ?>
       
      </div>

      <div id="breadcrumbblock">
         <?php echo($_ARCHON->PublicInterface->createNavigation()); ?>
      </div>
      <div id="breadcrumbclearblock">.</div>

      <script type="text/javascript">
         /* <![CDATA[ */
         if ($.browser.msie && parseInt($.browser.version, 10) <= 8){
            $.getScript('packages/core/js/jquery.corner.js', function(){
               $("#searchblock").corner("5px");
               $("#browsebyblock").corner("tl 10px");

               $(function(){
                  $(".bground").corner("20px");
                  $(".mdround").corner("10px");
                  $(".smround").corner("5px");
                  $("#dlsearchblock").corner("bottom 10px");
               });
            });
         }
         /* ]]> */
      </script>
      <div id="main">