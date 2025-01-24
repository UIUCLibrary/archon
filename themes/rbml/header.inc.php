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


$_ARCHON->PublicInterface->addNavigation('Collections Database', 'index.php', true);
$_ARCHON->PublicInterface->addNavigation('RBML', 'https://library.illinois.edu/rbx/', true);

//header('Content-type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <meta name="og:site_name" content="Rare Book & Manuscript Library Manuscript Collections Database"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?php echo(strip_tags($_ARCHON->PublicInterface->Title)); ?></title>
      <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->Theme); ?>/style.css?v=20240717" />
      <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.css" />
      <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/jgrowl/jquery.jgrowl.css" />

      <link rel="icon" type="image/ico" href="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/favicon.ico"/>
      <!--[if lte IE 7]>
        <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->Theme); ?>/ie.css" />
        <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.ie.css" />
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
               slicePoint:       600,              // make expandable if over this x chars
               widow:            100,              // do not make expandable unless total length > slicePoint + widow
               expandText:         'READ MORE',  //text to use for expand link
               expandEffect:     'fadeIn',         // or slideDown
               expandSpeed:      700,              // in milliseconds
               collapseTimer:    0,                // milliseconds before auto collapse; default is 0 (don't re-collape)
               userCollapseText: 'COLLAPSE TEXT'      // text for collaspe link
            });
         });

         function js_highlighttoplink(selectedSpan)
         {
            $('.currentBrowseLink').toggleClass('browseLink').toggleClass('currentBrowseLink');
            $(selectedSpan).toggleClass('currentBrowseLink');
            $(selectedSpan).effect('highlight', {}, 300);
         }

         $(document).ready(function() {<?php echo($_ARCHON->PublicInterface->Header->OnLoad); ?>});
         $(window).unload(function() {<?php echo($_ARCHON->PublicInterface->Header->OnUnload); ?>});
         /* ]]> */
      
      </script>
	        <?php 
      //script to handle filters within the sidebar location table
      ?>
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
      <script>
      var $k =jQuery.noConflict();
      $k(document).ready(function(){
      $k("#ccardstaff .locationFilter").on("keyup", function() {
         var value = $k(this).val().toLowerCase();
         $k("#ccardstaff .locationTableBody tr").filter(function() {
            var $t = $(this).children().last();
            $k(this).toggle($k($t).text().toLowerCase().indexOf(value) > -1)
            });
         });
      $k("#ccardstaff .staffBoxFilter").on("keyup", function() {
         var value = $k(this).val().toLowerCase();
         $k("#ccardstaff .locationTableBody tr").filter(function() {
            var $h = $(this).children().first();
            $k(this).toggle($k($h).text().toLowerCase().indexOf(value) > -1)
            });
         });
      $k("#ccardstaff .staffLocationFilter").on("keyup", function() {
         var value = $k(this).val().toLowerCase();
         $k("#ccardstaff .locationTableBody tr").filter(function() {
            $k(this).toggle($k(this).text().toLowerCase().indexOf(value) > -1)
            });
         });
      });
      </script>

      <?php
      if($_ARCHON->PublicInterface->Header->Message && $_ARCHON->PublicInterface->Header->Message != $_ARCHON->Error)
      {
         $message = $_ARCHON->PublicInterface->Header->Message;
      }
      ?>
   </head>
   <body>
      <?php
      $_ARCHON->PublicInterface->outputGoogleAnalyticsCode();

      if($message)
      {
         echo("<div class='message'>" . encode($message, ENCODE_HTML) . "</div>\n");
      }
      ?>
      <div id="top">
		<div id="headerbar">
			<div id="topnavblock">

	  
	  <a href="https://library.illinois.edu"><img src="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/library-wordmark-white.png"></a>
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

						?>
						<a href='#archoninfo' onclick='$(window).scrollTo("#archoninfo"); if($("#userlogin").is(":visible")) {$("#loginlink").html="Log In";} else {$("#loginlink").html="Hide";} $("#userlogin").slideToggle("normal"); $("#ArchonLoginField").focus(); return false;'>Log In</a>");
						<?php
                     }

                     if(!$_ARCHON->Security->userHasAdministrativeAccess())
                     {
                        $emailpage = defined('PACKAGE_COLLECTIONS') ? "collections/research" : "core/contact";

                        echo(" | <a href='?p={$emailpage}&amp;f=email&amp;referer=" . urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>Contact Us</a>");
                        if($_ARCHON->Security->isAuthenticated())
                        {
                           echo(" | <a href='?p=core/account&amp;f=account'>My Account</a>");
                        }
/*                         if(defined('PACKAGE_COLLECTIONS'))
                        {
                           $_ARCHON->Security->Session->ResearchCart->getCart();
                           $EntryCount = $_ARCHON->Security->Session->ResearchCart->getCartCount();
                           $class = $_ARCHON->Repository->ResearchFunctionality & RESEARCH_COLLECTIONS ? '' : 'hidewhenempty';
                           $hidden = ($_ARCHON->Repository->ResearchFunctionality & RESEARCH_COLLECTIONS || $EntryCount) ? '' : "style='display:none'";

                           echo("<span id='viewcartlink' class='$class' $hidden>| <a href='?p=collections/research&amp;f=cart&amp;referer=" . urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>View Cart (<span id='cartcount'>$EntryCount</span>)</a></span>");
                        } */
                     }
?>
               </div>
		</div>
		
		<div id="headercenter">
         <div id="logosearchwrapper">
            <div id="logo"><a href="https://www.library.illinois.edu/rbx/" ><img src="<?php echo($_ARCHON->PublicInterface->ImagePath); echo(isset($_ARCHON->config->HeaderLogo) ? "/".encode($_ARCHON->config->HeaderLogo,ENCODE_HTML) : '/logo.png');?>" alt="logo" /></a> </div>
			
			<div id="headertitleblock">
				<a href='./index.php'>Manuscript Collections Database</a>
			</div>
            <div id="searchblock">
               <form action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
                  <div>
                     <input type="hidden" name="p" value="core/search" />
                     <input type="text" size="25" title="search" maxlength="150" name="q" id="qfa" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" />
                     <input type="submit" value="Search" tabindex="300" class='button' title="Search" />
                     <?php
                     if(defined('PACKAGE_COLLECTIONS') && CONFIG_COLLECTIONS_SEARCH_BOX_LISTS)
                     {
                     ?>
                        <input type="hidden" name="content" value="1" />
                     <?php
                     }
                     ?>
                  </div>
               </form>
            </div>
         </div>
		</div>
</div>

<div id="contact-info-line">
346 Main Library | 1408 West Gregory Drive | Urbana IL 61801 | 217-333-3777 | <a href="mailto:askacurator@library.illinois.edu">askacurator@library.illinois.edu</a>
</div>

         <!--<div id="researchblock">
            <?php
                     
					 
            ?>
                  </div>-->

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
                           Browse:
                        </span>
                        <span class="<?php echo($TitleClass); ?>">
                           <a href="?p=collections/collections" onclick="js_highlighttoplink(this.parentNode); return true;">Collections</a>
                        </span>
                        <!--<span class="<?php echo($DigitalLibraryClass); ?>">
                           <a href="?p=digitallibrary/digitallibrary" onclick="js_highlighttoplink(this.parentNode); return true;">Digital Content</a>
                        </span>-->
                        <span class="<?php echo($SubjectsClass); ?>">
                           <a href="?p=subjects/subjects" onclick="js_highlighttoplink(this.parentNode); return true;">Subjects</a>
                        </span>
                        <span class="<?php echo($CreatorsClass); ?>">
                           <a href="?p=creators/creators" onclick="js_highlighttoplink(this.parentNode); return true;">Creators</a>
                        </span>
                        <span class="<?php echo($ClassificationsClass); ?>">
                           <a href="?p=collections/classifications" onclick="js_highlighttoplink(this.parentNode); return true;">Record Groups</a>
                        </span>
                     </div>
                  </div>
				  
      <div id="breadcrumbblock"><span class='bold'>Location: </span><?php echo($_ARCHON->PublicInterface->createNavigation()); ?></div>
				  
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
if($_ARCHON->config->AlertNoticeRBML){
	echo("<div id='custom-message'>".encode($_ARCHON->config->AlertNoticeRBML, ENCODE_HTML)."</div>");
}

if(defined('PACKAGE_COLLECTIONS'))
{
	if($_ARCHON->QueryString && $_ARCHON->Script == 'packages/core/pub/search.php')
   {
		?>
		<script>
		window.onload = function checkArchonInfo() {
			if($('#archoninfo').length > 0){
				$(".show-if-error").hide();
			} else{
				$(".show-if-error").show();
			}
		}
		</script>
		<?php
		// add note for how to recover from the memory error (hide if footer is displaying)
		echo("<div id='custom-message' class='show-if-error'>Did your search fail? Click here to <a href='?p=core/search&q=");
		echo(encode($_ARCHON->QueryString, ENCODE_HTML));
		echo("&content=0'>try searching again in the collection summaries only</a>.</div>");
   } elseif($_ARCHON->config->CollectionDetailList and $_ARCHON->Script == 'packages/collections/pub/collections.php')
   {
      ?>
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
			<script>
			var $j = jQuery.noConflict();
         $j(document).ready(function(){
			  $j(".morelessbutton").click(function(){
					var el = $(this);
					if (el.text() == el.data("text-swap")) {
						el.text(el.data("text-original"));
					} else {
						el.data("text-original", el.text());
						el.text(el.data("text-swap"));
					}
				$j(".collDetail").toggle();
			  });
			});
	   </script>
      <?php
      if(isset($_REQUEST['char']) or isset($_REQUEST['browse']))
	   {
	   echo("<button class='morelessbutton' data-text-swap='expand descriptions'>hide descriptions</button>");
	   } 
   }
   
   
}
?>
	  
      <div id="main">