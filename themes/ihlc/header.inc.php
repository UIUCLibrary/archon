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
      $_ARCHON->PublicInterface->addNavigation("Search Results For \"" . $_ARCHON->getString(QueryString) . "\"", "?p=core/search&amp;q=" . $_ARCHON->QueryStringURL, true);
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

//adds to breadcrumbs
$_ARCHON->PublicInterface->addNavigation('Manuscript Collections Database', 'index.php', true);
$_ARCHON->PublicInterface->addNavigation('IHLC', 'https://www.library.illinois.edu/ihx/', true);


header('Content-type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <meta name="og:site_name" content="Illinois History and Lincoln Collections Manuscript Collections Database"/>
	  <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?php echo(strip_tags($_ARCHON->PublicInterface->Title)); ?></title>
      <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->Theme); ?>/style.css?version=3.0" />
      <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.css" />
      <link rel="icon" type="image/ico" href="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/favicon.ico"/>
      <!--[if IE]>
        <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->Theme); ?>/ie.css" />
        <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.ie.css" />
      <![endif]-->
<?php echo($_ARCHON->getJavascriptTags('jquery.min')); ?>
<?php echo($_ARCHON->getJavascriptTags('jquery-ui.custom.min')); ?>
      <?php echo($_ARCHON->getJavascriptTags('jquery-expander')); ?>
      <script type="text/javascript" src="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/jquery.hoverIntent.js"></script>
      <script type="text/javascript" src="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.js"></script>
      
      <script type="text/javascript" src="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/jquery.scrollTo-min.js"></script>
<?php echo($_ARCHON->getJavascriptTags('archon')); ?>
      <script type="text/javascript">
         /* <![CDATA[ */
         imagePath = '<?php echo($_ARCHON->PublicInterface->ImagePath); ?>';
         $(document).ready(function() {          
            $('div.listitem:nth-child(even)').addClass('evenlistitem');
            $('div.listitem:last-child').addClass('lastlistitem');
            $('#locationtable tr:nth-child(odd)').addClass('oddtablerow');
            $('.expandable').expander({
               slicePoint:       1000,              // make expandable if over this x chars
               widow:            100,              // do not make expandable unless total length > slicePoint + widow
               expandPrefix:     '',         // text to come before the expand link
               expandText:         'READ MORE',  //text to use for expand link
               expandEffect:     'fadeIn',         // or slideDown
               expandSpeed:      700,              // in milliseconds
               collapseTimer:    0,                // milliseconds before auto collapse; default is 0 (don't re-collapse)
               userCollapseText: 'COLLAPSE TEXT'      // text for collapse link
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
	  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
			<script>
			$(document).ready(function(){
			  $(".morelessbutton").click(function(){
					var el = $(this);
					if (el.text() == el.data("text-swap")) {
						el.text(el.data("text-original"));
					} else {
						el.data("text-original", el.text());
						el.text(el.data("text-swap"));
					}
				$(".collDetail").toggle();
			  });
			});
	</script>	
<?php
      if($_ARCHON->PublicInterface->Header->Message && $_ARCHON->PublicInterface->Header->Message != $_ARCHON->Error)
      {
         $message = $_ARCHON->PublicInterface->Header->Message;
      } ?>
   </head>
   <body>
<?php
    
	// only apply google analytics if not logged in as admin
	if(!$_ARCHON->Security->userHasAdministrativeAccess())
    {
	  $_ARCHON->PublicInterface->outputGoogleAnalyticsCode();
	}

      if($message)
      {
         echo("<div class='message'>" . encode($message, ENCODE_HTML) . "</div>\n");
      }
?>
      <div id='top'>
		<div id="headerbar">
			<div id="topnavblock">
      <?php
	  /*
      echo("<a href='http://illinois.edu'>University of Illinois at Urbana-Champaign</a> > <a href='http://library.illinois.edu'>Library</a> > <a href='http://www.library.illinois.edu/ihx'>IHLC</a> > <a href='http://www.library.illinois.edu/ihx/archon/'>Manuscript Collections Database</a>");
	  */
	  ?>
	  
	  <a href="https://library.illinois.edu"><img src="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/library-wordmark-white.png"></a>
      </div>
			<div id="researchblock">
<?php
                  if($_ARCHON->Security->isAuthenticated())
                  {
                     echo("<div id='loggedinblock'>");
					 echo("<span class='bold'>Welcome, " . $_ARCHON->Security->Session->User->toString() . "</span><br/>");

                     $logoutURI = preg_replace('/(&|\\?)f=([\\w])*/', '', $_SERVER['REQUEST_URI']);
                     $Logout = (encoding_strpos($logoutURI, '?') !== false) ? '&amp;f=logout' : '?f=logout';
                     $strLogout = encode($logoutURI, ENCODE_HTML) . $Logout;
                     echo("<a href='$strLogout'>Logout</a>");
					 echo("</div>");
                  }
                  else
                  {
                     echo("<a href='#' onclick='$(window).scrollTo(\"#archoninfo\"); if($(\"#userlogin\").is(\":visible\")) $(\"#loginlink\").html(\"Log In (Registered Researchers or Staff)\"); else $(\"#loginlink\").html(\"Hide\"); $(\"#userlogin\").slideToggle(\"normal\"); $(\"#ArchonLoginField\").focus(); return false;'>Log In</a>");
                  }

                  if(!$_ARCHON->Security->userHasAdministrativeAccess())
                  {
                     $emailpage = defined('PACKAGE_COLLECTIONS') ? "collections/research" : "core/contact";

                     /*echo(" | <a href='?p={$emailpage}&amp;f=email&amp;referer=" . urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>Contact Us</a>");*/
					 
					 echo(" | <a href='mailto:ihlc@library.illinois.edu'>Contact Us</a>");

                     if($_ARCHON->Security->isAuthenticated())
                     {
                        echo(" | <a href='?p=core/account&amp;f=account'>My Account</a>");
                     }
                     /*if(defined('PACKAGE_COLLECTIONS'))
                     {
                        $_ARCHON->Security->Session->ResearchCart->getCart();
                        $EntryCount = $_ARCHON->Security->Session->ResearchCart->getCartCount();

                        echo(" | <a href='?p=collections/research&amp;f=cart&amp;referer=" . urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>View Cart (<span id='cartcount'>$EntryCount</span>)</a>");
                     }*/
                  }
?>
               </div>
		</div>
		
		<div id="headercenter">
			<div id="headertitleblock">
				<a href='http://www.library.illinois.edu/ihx/index.html'>Illinois History and Lincoln Collections</a> | <a href='./index.php'>Manuscript Collections</a>
			</div>
			<div id="searchwrapper">
            <!--<div id="logo"><a href="index.php" ><img src="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/logo.gif" alt="logo" /></a><br/>Archives</div> -->
            <div id="searchblock">
               <form action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
                  <div>
                     <input type="hidden" name="p" value="core/search" />
                     <label for='q'></label>
					 <input type="text" size="35" maxlength="150" name="q" id="q" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" placeholder="Search collection summaries"/>
                     <input type="submit" value="Find" tabindex="300" class='button' title="Search" /> 
					 
<?php
      if(defined('PACKAGE_COLLECTIONS') && CONFIG_COLLECTIONS_SEARCH_BOX_LISTS)
      {
?>
                     <input type="hidden" name="content" value="1" />
                     <?php
                  }
                     ?>
                  </div></form></div>
				  
				<div id="pdfsearchlink"><a href='?p=core/index'>Return to main search page</a></div>
         </div>
		</div>
		
		
         
</div>

<div id="contact-info-line">
324 Main Library | 1408 West Gregory Drive | Urbana IL 61801 | 217-333-1777 | <a href="mailto:ihlc@library.illinois.edu">ihlc@library.illinois.edu</a>
</div>
<div id="headernav" class='noprint'>
			<?php
                  $arrP = explode('/', $_REQUEST['p']);
                  $TitleClass = $arrP[0] == 'collections' && $arrP[1] != 'classifications' ? 'currentBrowseLink' : 'browseLink';
                  $ClassificationsClass = $arrP[1] == 'classifications' ? 'currentBrowseLink' : 'browseLink';
                  $SubjectsClass = $arrP[0] == 'subjects' ? 'currentBrowseLink' : 'browseLink';
                  $CreatorsClass = $arrP[0] == 'creators' ? 'currentBrowseLink' : 'browseLink';
                  $DigitalLibraryClass = $arrP[0] == 'digitallibrary' ? 'currentBrowseLink' : 'browseLink';
            ?>
                  <div id="browsebyblock">
            
					 <span id="browsebyspan">
                        Browse by:
                     </span>
					 
                     <span class="<?php echo($TitleClass); ?>">
                        <a href="?p=collections/collections" onclick="js_highlighttoplink(this.parentNode); return true;">Collection Titles</a>
                     </span>

                     <span class="<?php echo($SubjectsClass); ?>">
                        <a href="?p=subjects/subjects" onclick="js_highlighttoplink(this.parentNode); return true;">Subjects</a>
                     </span>
					 
                     <span class="<?php echo($CreatorsClass); ?>">
                        <a href="?p=creators/creators" onclick="js_highlighttoplink(this.parentNode); return true;">Persons or Organizations</a>
                     </span>
					 
                     <!--<span class="<?php echo($DigitalLibraryClass); ?>">
                        <a href="?p=digitallibrary/digitallibrary" onclick="js_highlighttoplink(this.parentNode); return true;">Images</a>
                     </span> -->

                  </div>
		</div>
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
	  	  <?php

if($_ARCHON->config->AlertNoticeIHLC){
	if ($_REQUEST['f'] != 'searchprimo') {
		echo("<div id='ihlc-message'>".encode($_ARCHON->config->AlertNoticeIHLC, ENCODE_HTML)."</div>");
	}
}

if(defined('PACKAGE_COLLECTIONS'))
{
	if($_ARCHON->QueryString && $_ARCHON->Script == 'packages/core/pub/search.php')
   {
      if(is_numeric($_ARCHON->getString(QueryString)))
	  {
		// add note "did you mean?"
		echo("<div id='ihlc-message'>Did you mean to search by collection identifier? Click here to <a href='?f=identifiersearch&q=");
		echo(encode($_ARCHON->QueryString, ENCODE_HTML));
		echo("'>lookup by collection identifier</a>.</div>");
	  }
	  
	  if(strpos($_ARCHON->getString(QueryString),"civil war")>-1)
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
	  
