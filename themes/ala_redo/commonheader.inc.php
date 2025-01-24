
<?php
/**
 * Common elements of the regular header and the finding aid header.
 */

$_ARCHON->PublicInterface->addNavigation('The American Library Association Archives:', 'http://www.library.illinois.edu/archives/ala/', true);

?>

<!DOCTYPE html>
<html lang="en">
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <meta name="og:site_name" content="American Library Association Archives Holdings Database"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?php echo(strip_tags($_ARCHON->PublicInterface->Title)); ?></title>
      <link rel="stylesheet" type="text/css" href="themes/<?php echo($_ARCHON->PublicInterface->Theme); ?>/style.css?v=20240717" />
      <link rel="stylesheet" type="text/css" href="<?php echo($_ARCHON->PublicInterface->ThemeJavascriptPath); ?>/cluetip/jquery.cluetip.css" />
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
               expandPrefix:     '. . . ',         // text to come before the expand link
               expandText:         '[read more]',  //text to use for expand link
               expandEffect:     'fadeIn',         // or slideDown
               expandSpeed:      700,              // in milliseconds
               collapseTimer:    0,                // milliseconds before auto collapse; default is 0 (don't re-collape)
               userCollapseText: '[collapse]'      // text for collaspe link
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
         $k("#stafflocationtable .staffBoxFilter").on("keyup", function() {
            var value = $k(this).val().toLowerCase();
            $k("#stafflocationtable .locationTableBody tr").filter(function() {
               var $h = $(this).children().first();
               $k(this).toggle($k($h).text().toLowerCase().indexOf(value) > -1)
               });
         });
         $k("#stafflocationtable .staffLocationFilter").on("keyup", function() {
            var value = $k(this).val().toLowerCase();
            $k("#stafflocationtable .locationTableBody tr").filter(function() {
               $k(this).toggle($k(this).text().toLowerCase().indexOf(value) > -1)
               });
         });
      });
      </script>

      <?php if($_ARCHON->PublicInterface->Header->Message && $_ARCHON->PublicInterface->Header->Message != $_ARCHON->Error)
      {
         $message = $_ARCHON->PublicInterface->Header->Message;
      } ?>
   </head>
   <body>
      <?php
      $_ARCHON->PublicInterface->outputGoogleAnalyticsCode();

      if($message)
      {
         echo("<div class='message'>".encode($message, ENCODE_HTML)."</div>\n");
      }
      
         $arrP = explode('/', $_REQUEST['p']);
         $TitleClass = $arrP[0] == 'collections' && $arrP[1] != 'classifications' ? 'currentBrowseLink' : 'browseLink';
         $ClassificationsClass = $arrP[1] == 'classifications' ? 'currentBrowseLink' : 'browseLink';
         $SubjectsClass = $arrP[0] == 'subjects' ? 'currentBrowseLink' : 'browseLink';
         $CreatorsClass = $arrP[0] == 'creators' ? 'currentBrowseLink' : 'browseLink';
         $DigitalLibraryClass = $arrP[0] == 'digitallibrary' ? 'currentBrowseLink' : 'browseLink';
         ?>
        
    <div id="top">
			
				<div id="logo"><a href="index.php" ><img src="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/ala_logo.gif" alt="logo" /></a>
				</div>
				<div id="browsebyblock">
				 <span class="<?php echo($TitleClass); ?>">
				   <a href="?p=collections/collections" onclick="js_highlighttoplink(this.parentNode); return true;">Collections</a>
				</span>
				<span class="<?php echo($DigitalLibraryClass); ?>">
				   <a href="?p=digitallibrary/digitallibrary" onclick="js_highlighttoplink(this.parentNode); return true;">Digital Content</a>
				</span>
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

 			
 		

         <div id="researchblock">

            <?php


            if($_ARCHON->Security->isAuthenticated())
            {
               echo("<span class='bold'>Welcome, ". $_ARCHON->Security->Session->User->toString(). "</span><br/>");
               $logoutURI = preg_replace('/(&|\\?)f=([\\w])*/', '', $_SERVER['REQUEST_URI']);
               $Logout = (encoding_strpos($logoutURI, '?') !== false) ? '&amp;f=logout' : '?f=logout';
               $strLogout = encode($logoutURI, ENCODE_HTML) . $Logout;
               echo("<a href='$strLogout'>Logout</a> | <a href='?p=core/account&amp;f=account'>My Account</a>");
            }
            else
            {
               echo("<a href='#' onclick='$(window).scrollTo(\"#archoninfo\"); if($(\"#userlogin\").is(\":visible\")) $(\"#loginlink\").html(\"Log In\"); else $(\"#loginlink\").html(\"Hide\"); $(\"#userlogin\").slideToggle(\"normal\"); $(\"#ArchonLoginField\").focus(); return false;'>Log In</a>");
            }

            if(!$_ARCHON->Security->userHasAdministrativeAccess())
            {

               echo(" | <a href='http://archives.library.illinois.edu/ala/contact-us/'>Contact Us</a>");

               if(defined('PACKAGE_COLLECTIONS'))
               {
                  $_ARCHON->Security->Session->ResearchCart->getCart();
                  $EntryCount = $_ARCHON->Security->Session->ResearchCart->getCartCount();

                  echo("<span id='viewcartlink' | <a href='?p=collections/research&amp;f=cart&amp;referer=" . urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>View Cart (<span id='cartcount'>$EntryCount</span>)</a></span>");

               }
            }


            ?>
         </div>


		<div id="searchblock">
				   <form action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
					  <div>
						 <input type="hidden" name="p" value="core/search" />
						 <input type="text" size="25" title="search" maxlength="150" name="q" id="qfa" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" />
						 <input type="submit" value="Search" tabindex="300" class='button' title="Search" />  <a class='bold pdfsearchlink' href='?p=core/index&amp;f=pdfsearch'>Search PDF lists</a>
						 <?php
					 
						 if(defined('PACKAGE_COLLECTIONS') && CONFIG_COLLECTIONS_SEARCH_BOX_LISTS)
						 {
							?>
						 <input type="hidden" name="content" value="1" />
						  <input type="hidden" name="repositoryid" value="0" />
							<?php
						 }
						 ?>
					  </div>
				   </form>
		</div>

	

      
      </div>
      <div id="breadcrumbblock"><?php echo($_ARCHON->PublicInterface->createNavigation());?></div>
      <div id="breadcrumbclearblock">.</div>