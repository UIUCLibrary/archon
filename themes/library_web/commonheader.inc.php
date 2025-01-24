
<?php
/**
 * Common elements of the regular header and the finding aid header.
 */

$_ARCHON->PublicInterface->addNavigation('Holdings', 'index.php', true);
$_ARCHON->PublicInterface->addNavigation('University Archives', 'https://archives.library.illinois.edu', true);

header('Content-type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <meta name="og:site_name" content="University of Illinois Archives Holdings Database"/>
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
         jQuery(document).ready(function($) {          
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

		 <div id="sitetitleblock"><a href="https://archives.library.illinois.edu">University of Illinois Archives</a> &ndash;> <a href="index.php">Holdings Database</a></div>
			<div id="searchblock">
               <form action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
                  <div>
                     <input type="hidden" name="p" value="core/search" />
                     <input type="text" size="25" maxlength="150" name="q" id="q" title="input box for search field" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" />
                     <input type="submit" value="Search" tabindex="300" class='button' title="Search" /> <a class='bold pdfsearchlink' href='?p=core/index&amp;f=pdfsearch'>Search PDF lists</a>
                     <?php
                     if(defined('PACKAGE_COLLECTIONS') && CONFIG_COLLECTIONS_SEARCH_BOX_LISTS)
                     {
                        ?>
                        <input type="hidden" name="content" value="0" />
                        <?php
                     }
                     ?>
                  </div></form></div>

         </div>
         
			<?php
         $arrP = explode('/', $_REQUEST['p']);
         $TitleClass = $arrP[0] == 'collections' && $arrP[1] != 'classifications' ? 'currentBrowseLink' : 'browseLink';
         $ClassificationsClass = $arrP[1] == 'classifications' ? 'currentBrowseLink' : 'browseLink';
         $SubjectsClass = $arrP[0] == 'subjects' ? 'currentBrowseLink' : 'browseLink';
         $CreatorsClass = $arrP[0] == 'creators' ? 'currentBrowseLink' : 'browseLink';
         $DigitalLibraryClass = $arrP[0] == 'digitallibrary' ? 'currentBrowseLink' : 'browseLink';
         ?>

         <div id="browsebyblock">
            <div id="browseblockcontent">
            <span class="<?php echo($ClassificationsClass); ?>">
               <a href="?p=collections/classifications" onclick="js_highlighttoplink(this.parentNode); return true;">Campus Units/Record Groups</a>
            </span>

            <span class="<?php echo($TitleClass); ?>">
               <a href="?p=collections/collections" onclick="js_highlighttoplink(this.parentNode); return true;">Physical Collections: A-Z</a>
            </span>
			 <span class="<?php echo($DigitalLibraryClass); ?>">
               <a href="?p=digitallibrary/digitallibrary" onclick="js_highlighttoplink(this.parentNode); return true;">Digital Materials: A-Z</a>
            </span>
           
            <span class="<?php echo($SubjectsClass); ?>">
               <a href="?p=subjects/subjects" onclick="js_highlighttoplink(this.parentNode); return true;">Subject Headings</a>
            </span>
            <span class="<?php echo($CreatorsClass); ?>">
               <a href="?p=creators/creators" onclick="js_highlighttoplink(this.parentNode); return true;">Record Creators</a>
            </span>
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