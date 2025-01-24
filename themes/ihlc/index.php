<?php
/**
 * Main page for default template
 *
 * @package Archon
 * @author Chris Rishel
 */

isset($_ARCHON) or die();

if($_REQUEST['f'] == 'pdfsearch')
{
    require("pdfsearch.inc.php");
    return;
} 
elseif ($_REQUEST['f'] == 'searchmultiple') {
	require("searchmultiple.inc.php");
	return;
} 
elseif ($_REQUEST['f'] == 'searchprimo') {
	require("searchprimo.inc.php");
	return;
}
elseif ($_REQUEST['f'] == 'privacy') {
	require("privacy.inc.php");
	return;
} 
elseif ($_REQUEST['f'] == 'listall')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall.inc.php");
		return;
	}
} elseif ($_REQUEST['f'] == 'listall-locations')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-locations.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'listall-with-subj')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-with-subj.inc.php");
		return;
	}
}	elseif ($_REQUEST['f'] == 'list-subjects')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("list-subjects.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'openidentifiers')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("openidentifiers.inc.php");
		return;
	}
} elseif($_REQUEST['f'] == 'barcodelookup')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("barcodelookup.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'identifiersearch')
{
	require("identifiersearch.inc.php");
	return;
}elseif ($_REQUEST['f'] == 'chstm-list')
{
	require("chstm-list.inc.php");
	return;
}elseif ($_REQUEST['f'] == 'chstm-table')
{
	require("chstm-table.inc.php");
	return;
}elseif ($_REQUEST['f'] == 'lincoln-table')
{
	require("lincoln-table.inc.php");
	return;
}elseif ($_REQUEST['f'] == 'listall-for-atom')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-for-atom.inc.php");
	}
	return;
}elseif ($_REQUEST['f'] == 'listall-locations-for-atom')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-locations-for-atom.inc.php");
	}
	return;
}elseif ($_REQUEST['f'] == 'listall-creators-for-atom')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-creators-for-atom.inc.php");
	}
	return;
}elseif ($_REQUEST['f'] == 'listall-creator-relations-for-atom')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-creator-relations-for-atom.inc.php");
	}
	return;
}elseif ($_REQUEST['f'] == 'listall-digitalcontent-for-atom')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-digitalcontent-for-atom.inc.php");
	}
	return;
}elseif ($_REQUEST['f'] == 'listall-digitalcontent-for-dls')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-digitalcontent-for-dls.inc.php");
	}
	return;
}
//notice
if($_ARCHON->config->AlertNoticeIHLC) {
 
 if(strtotime($_ARCHON->config->AlertNoticeEndDate)>time()) {
	 echo("<p style='border: 2px solid red; font-weight: bold; text-align: center; padding: 10px;'>");
	 echo($_ARCHON->config->AlertNoticeIHLC);
	 echo("</p>");
 }
}


//$_ARCHON->PublicInterface->Title.=": Holdings Database";

echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "</h1>\n");

?>



<div id='themeindex' class='bground'>

<div >

<div <?php 
	// for testing, hide from users not logged in
	if(!$_ARCHON->Security->isAuthenticated()) 
	{ 
		echo("style='display:none;'");
	} else {
		echo("style='display:none;'");
	}
?> class='bground textcontainer box1'>
<h2>Browse Manuscript Collections by title</h2>
<?php
$arrCollectionCount = $_ARCHON->countCollections(true, false, $_SESSION['Archon_RepositoryID']);
echo("<div class='a-z'>");
//$href = 'index.php'; //"?p={$_REQUEST['p']}&amp;char=";
//echo("<form action='$href'>");
//echo("<input type='hidden' id='collquery' name='p' value='collections/collections'/>");
//echo("<select name='char'>");
      if(!empty($arrCollectionCount['#']))
      {
         $href = "?p=collections/collections&amp;char=" . urlencode('#');
         
         echo("<a href='$href'>-#-</a>");
		 //$urlvalue=urlencode('#');
		 //echo("<option value='$urlvalue'>- # -</option>");
      }
      else
      {
         echo("-#-");
		 //echo("<option value='' disabled>- # -</option>");
      }

      for($i = 65; $i < 91; $i++)
      {
         $char = chr($i);

         if(!empty($arrCollectionCount[encoding_strtolower($char)]))
         {
            $href = "index.php?p=collections/collections&amp;char=$char";
            
            echo("<a href='$href'>-$char-</a>");
			//echo("<option value='$char'>- $char -</option>");
         }
         else
         {
            echo("-$char-");
			//echo("<option value='' disabled>- $char -</option>");
         }
      }
	  $strViewAll = $objViewAllPhrase ? $objViewAllPhrase->getPhraseValue(ENCODE_HTML) : 'View All';
      echo("<a href='?p=collections/collections&amp;browse'>{$strViewAll}</a>");
	  //echo("<option value=''>$strViewAll</option>");*/
//echo("</select><input type='submit' value='Go'></form>");
//$strViewAll = $objViewAllPhrase ? $objViewAllPhrase->getPhraseValue(ENCODE_HTML) : 'View All';
//echo("<a href='?p={$_REQUEST['p']}'>{$strViewAll}</a>");
echo("</div>");
?>
</div>


	<div class="bground textcontainer box1"><label for='mainsearch'><h2>Search Manuscript Collection Descriptions</h2></label>
		<p>Search our collection descriptions for keywords, such as names, places, formats, or topics here. <br />To search by collection number, please use the <a href="#identifiersearch">identifier search box</a> at the bottom of the page.</p>
		<div id ="multisearch">
		 <form method="get" id="multisearchbox" action='index.php' accept-charset="UTF-8" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
		 
		 <input type="hidden" id="hiddenquery" name="p" value="core/search"/>
		 
		 <input type="text" size="40" maxlength="150" name="q" id="mainsearch" class="searchinput" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="0" autofocus/>
		 
		 <input type="submit" value="Find" tabindex="0" class='button' title="Search" />

		<br/>
		
		<div id="multisearchoptions">
		<input type="radio" class="radio_option" id="opt1" name="multisearch" onclick="document.getElementById('multisearchbox').action='index.php'; document.getElementById('hiddenquery').name='p'; document.getElementById('hiddenquery').value='core/search';" checked="checked"/> <label for="opt1">Search all collection and creator summaries in this database</label>
		<br />- or -<br />
		<input type="radio" name="multisearch" id="opt2" onclick="document.getElementById('multisearchbox').action='https://www.google.com/search'; document.getElementById('hiddenquery').name='hq'; document.getElementById('hiddenquery').value='site:www.library.illinois.edu/ihx/inventories OR site:files.archon.library.illinois.edu/ihlcsfa';"/><label for="opt2">Use Google to search detailed folder and box lists for collections</label>
		
		</div>
		</form></div>
		</div>
		
	</div>
	<div class="bground textcontainer box2"><h2>About the database</h2>
	<p>This database contains <span class="keyphrase">descriptions of manuscript materials</span> held by the Illinois History and Lincoln Collections (IHLC) at the University of Illinois Library. The database includes descriptions for a substantial portion of the collections, but is not yet comprehensive.</p>
	<p><span class="keyphrase">Manuscript collections</span> range from single letters and diaries to organizational records occupying fifty or more document cartons. Please see the <a href="http://www.library.illinois.edu/ihx/collections">Overview of Collections</a> on the IHLC website for more detail on the types of the collections and the various topics they cover.</p>
	<p>The database provides <span class="keyphrase">searchable summary information</span> about these collections including descriptions of the scope and content of materials (noting the names of key people, organizations, and places, as well as topics and types of documents), as well as dates and acquisition information.</p>
	<p>In addition, <span class="keyphrase">detailed lists of collection contents</span> (box and folder lists) have been prepared for several hundred collections and are uploaded as PDF files. These inventories are NOT included in the general database search. These must be searched separately using the Google custom search function set up above.</p>
	<p>Please <strong><a href="mailto:ihlc@library.illinois.edu">contact us</a></strong> if you have any questions or if you would like to inquire about unprocessed collections without descriptions in this database.</p></div>

	<div class="bground textcontainer box3"><h2>Search tips</h2>
	<h3>Database searching</h3>
	<ul>
	<li>Note that the database will only return results that include all of your search terms</li>
	<li>Use double quotes to search for a phrase (for example, "Religious Society of Friends")</li>
	<li>A maximum of 100 results will be returned in any given search. To limit your search by excluding a term, put a minus sign before the particular word (for example, diary -microfilm will exclude collections that contain microfilm)</li>
	</ul>
	<h3>Searching PDFs with Google</h3>
	<ul>
	<li>Remember that not every collection will have a detailed inventory; the database search must be used to locate these collections of manuscript materials</li>
	<li>After locating an item of interest, download the PDF inventory and use the "find" option (typically ctrl-F) to locate your search terms within the document</li>
	<li>Note that some PDFs are scanned copies of paper files and while these are also searchable, there may be some errors in the computer-generated text searched for these inventories</li>
	</ul>
	<h3>Searching for archival materials in additional units of the University of Illinois Library</h3>
	<ul>
	<li>This database contains only items found in the IHLC. To search additional archival repositories at the University of Illinois Library, please use the <a href='?f=searchmultiple'>multiple repository search page</a>. 
	</ul>
	</div>
</div>


<div style="clear:both;"></div>
<div class="bground textcontainer box4"><label for='identifier'><h2>Look Up Collection by Identifier</h2></label>
	<p>If you know the collection identifier number, you may look up the corresponding collection record in the database here.<p>
	<form id="identifiersearch" action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
        <div>
            <input type="hidden" name="f" value="identifiersearch" />
			<input class="searchinput" type="text" size="15" maxlength="10" name="q" id="identifier" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="0" />
            <input type="submit" value="Find" tabindex="0" class='button' title="Search" /> 
        </div>
	</form>
</div>
<?php
if($_ARCHON->Security->userHasAdministrativeAccess()) {
?>
	<div class="bground textcontainer box4"><label for='barcode'><h2>Look Up by Barcode</h2></label>
		<form id="barcodelookup" action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.barcode.value) { alert('Please enter barcode value.'); return false; } else { return true; }">
			<div>
				<input type="hidden" name="f" value="barcodelookup" />
				<input class="searchinput" type="text" size="15" maxlength="20" name="barcode" id="barcode" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="0" />
				<input type="submit" value="Lookup" tabindex="0" class='button' title="Lookup" /> 
			</div>
		</form>
	</div>
<?php
}
?>
<!--
<div style='border:red 1px solid; text-align:center; float:left; font-size:small'>
<img src="<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/memstad.gif" alt="Photo of Memorial Stadium, circa 1925"><br/>Memorial Stadium, circa 1925
</div>
-->


</div>
