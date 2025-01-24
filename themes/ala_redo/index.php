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
}elseif($_REQUEST['f'] == 'barcodelookup')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("barcodelookup.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'listall')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'listall-locations')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-locations.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'listall-locations-a-o')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-locations.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'listall-locations-p-z')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-locations.inc.php");
		return;
	}
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
}elseif ($_REQUEST['f'] == 'listall-recordgroup-for-atom')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-recordgroup-for-atom.inc.php");
	}
	return;
}elseif ($_REQUEST['f'] == 'listall-recordgroup-for-atom-locations')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-recordgroup-for-atom-locations.inc.php");
	}
	return;
}elseif ($_REQUEST['f'] == 'listall-collectioncontent-for-atom')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-collectioncontent-for-atom.inc.php");
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


echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "</h1>\n");

?>

<div class="bground textcontainer box1"><h2>Search Descriptions of Archival Record Series</h2>
		<p>Search our archival record series descriptions for keywords, such as names, places, formats, or topics here. </p>
		<div id ="multisearch">
		 <form method="get" id="multisearchbox" action='index.php' accept-charset="UTF-8" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
		 
		 <input type="hidden" id="hiddenquery" name="p" value="core/search"/>
		 
		 <input type="text" size="40" maxlength="150" name="q" id="q" class="searchinput" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" autofocus/>
		 
		 <input type="submit" value="Find" tabindex="300" class='button' title="Search" />

		<br/>
		
		<div id="multisearchoptions">
		<input type="radio" class="radio_option" id="opt1" name="multisearch" onclick="document.getElementById('multisearchbox').action='index.php'; document.getElementById('hiddenquery').name='p'; document.getElementById('hiddenquery').value='core/search';" checked="checked"/> <label for="opt1">Search all record series and creator summaries in this database</label>
		<br />- or -<br />
		<input type="radio" name="multisearch" id="opt2" onclick="document.getElementById('multisearchbox').action='https://www.google.com/search'; document.getElementById('hiddenquery').name='hq'; document.getElementById('hiddenquery').value='site:files.archon.library.illinois.edu/alasfa/ OR site:archives.library.illinois.edu/alasfa/';"/><label for="opt2">Use Google to search detailed folder and box lists for records series</label>
		
		</div>
		</form></div>
		</div>
		
	</div>

<div id='themeindex' class='bground'>
<dl>
  <dt class='index'>Default Behaviors</dt>
  <dd class='index'>
    <ul>
      <li>The search engine looks for records containing every term you submit.</li>
    </ul>
  </dd>
  <dt class='index'>Search By Phrase</dt>
  <dd class='index'>
    <ul>
      <li>Use double quotes around your search query.  (e.g "Festival of Contemporary Arts")</li>
    </ul>
  </dd>
  <dt class='index'>Narrow Your Search Results</dt>
  <dd class='index'>
    <ul>
      <li>Use a minus sign before a term you want to omit from your results.  (e.g. 'bass -fish' finds bass guitars but not bass fishing.)</li>
      <li>Browse by collection title, subject, name, or classification.</li>
    </ul>
  </dd>
</dl>
</div>

<?php
/*add search by barcode for logged in users */
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