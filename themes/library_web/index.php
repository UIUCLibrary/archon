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
}elseif ($_REQUEST['f'] == 'chstm-list')
{
	require("chstm-list.inc.php");
	return;
}elseif ($_REQUEST['f'] == 'chstm-table')
{
	require("chstm-table.inc.php");
	return;
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
		require("listall-locations-a-o.inc.php");
		return;
	}
}elseif ($_REQUEST['f'] == 'listall-locations-p-z')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-locations-p-z.inc.php");
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
}elseif ($_REQUEST['f'] == 'listall-digitalcontent-index')
{
	if(!$_ARCHON->Security->userHasAdministrativeAccess()) {
		echo("<p>This page is admin access only. You must login to proceed.</p>");
		return;
	} else {
		require("listall-digitalcontent-index.inc.php");
	}
	return;
}
//$_ARCHON->PublicInterface->Title.=": Holdings and Image/Document Database";

//echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "</h1>\n");

?>


<div id='themeindex' class='bground'>

<div style="width:75%; margin:1em auto; padding:1em; background-color:#F9F9FA; border:#ddd 1px solid; font-size:small" class="bground">
<h2 style='margin-top:.1em'>Search Our Archives</h2>
		<div style='text-align:center'>
		<div id ="multisearch">
		 <form method="get" id="multisearchbox" action='index.php' accept-charset="UTF-8" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
		 
		 <input type="hidden" id="hiddenquery" name="p" value="core/search"/>
		 
		 <input type="text" size="40" maxlength="150" name="q" id="q" class="searchinput" style='font-size:1.5em' value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" autofocus/>
		 
		 <input type="submit" value="Search" tabindex="300" class='button' title="Search" />
		
		<p><a href='?p=core/index&amp;f=pdfsearch'>Search PDF lists</a></p>
		
		</div>
		</form></div>
	<p>This database contains descriptions of all materials held by the University of Illinois Archives, incuding non-current University records, faculty and alumni papers, and materials from the Sousa Archives and Center for American Music, the Advertising Council Archives, the American Society of Quality, and other institutions whose archives we hold on contract for their research value.  The American Library Association Archives are described in a <a href="../ala/">separate database.</a><br/><br/>Materials are arranged according to the principle of provenance (files from one creating office or person are not mixed with those from another) and they are also indexed by subject.  The Archives are available for use during the University Archives' normal business hours.  We can be contacted at <a href="mailto:illiarch@illinois.edu">illiarch@illinois.edu</a> or (217) 333-0798.</p>
	</div>
</div>

<div style="margin:1em auto; padding:1em; background-color:#F9F9FA; border:#ddd 1px solid; width:90%; margin:1em auto; clear:left; font-size:small" class="bground">
<h2 style='margin-top:.1em'>Search Tips</h2>
<ul>
  <li style='margin-bottom:4px'>The search engine looks for every term you submit, no matter how short.</li>
  <li style='margin-bottom:4px'>The search engine finds descriptions of our paper-based holdings <span class='bold'>and</span> items in our online archives.</li>
  <li style='margin-bottom:4px'>To look for phrases, use double quotes, e.g "Festival of Contemporary Arts"</li>
  <li style='margin-bottom:4px'>Record Series shortcut: e.g. '26/4/1' finds our <a href='?p=collections/controlcard&amp;id=2563'>Alumni File</a>.</li>
  <li style='margin-bottom:4px'>To search deep into box/folders lists that are not in the database, follow the <a href='?p=core/index&amp;f=pdfsearch'>Deep Search</a> link.</li>
    <li style='margin-bottom:4px'>Limiting 'Hits':
    <ul>
      <li class='back'>Use a minus sign, e.g. 'bass -fish' finds bass guitars but not bass fishing.</li>
      <li class='back'>Browse by subject, name, or campus unit.</li>
      <li class='back'>Call or <a href='mailto:illiarch@illinois.edu'>email</a> the archives.  We're here to help!</li>
    </ul>
  </li>
</ul>
</div>


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