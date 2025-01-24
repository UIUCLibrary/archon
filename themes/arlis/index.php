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
//$_ARCHON->PublicInterface->Title.=": Holdings and Image/Document Database";

echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "</h1>\n");

?>


<div style="width:75%; margin:1em auto; padding:1em; background-color:#F9F9FA; border:#ddd 1px solid; font-size:small" class="bground">
<h2 style='margin-top:.1em'>Search the ARLIS/NA Archives</h2>
		<div style='text-align:center'>
		<div id ="multisearch">
		 <form method="get" id="multisearchbox" action='index.php' accept-charset="UTF-8" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
		 
		 <input type="hidden" id="hiddenquery" name="p" value="core/search"/>
     <input type="hidden" name="setrepositoryid" value="4"/>
		 
		 <input type="text" size="40" maxlength="150" name="q" id="q" class="searchinput" style='font-size:1.5em' value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="0" autofocus/>
		 
		 <input type="submit" value="Search" tabindex="0" class='button' title="Search" />
		
		</div>
		</form></div>
  <p style='text-align:center'>Search here for keywords (names, places, subjects, topics) in our database of archival record series.</p>
</div>
<div id='themeindex' class='bground' >
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
      <li>Use double quotes around your search query. (e.g "Art Documentation")</li>
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
<div style="width:50%; margin:1em auto; padding:1em; background-color:#F9F9FA; border:#ddd 1px solid; font-size:small" class="bground">
    <h2 style='margin-top:.1em'>Browse by Record Series</h2>
    <div>
      <div class='listitem'>85 50 <a href='?p=collections/classifications&amp;id=3825'>ARLIS/Executive Board, Officers, and Executive Director</a></div>
      <div class='listitem'>85 51 <a href='?p=collections/classifications&amp;id=3827'>ARLIS/Committees</a></div>
      <div class='listitem'>85 52 <a href='?p=collections/classifications&amp;id=3828'>ARLIS/Discussion Groups</a></div>
      <div class='listitem'>85 53 <a href='?p=collections/classifications&amp;id=3829'>ARLIS/Divisions</a></div>
      <div class='listitem'>85 54 <a href='?p=collections/classifications&amp;id=3830'>ARLIS/Sections</a></div>
      <div class='listitem'>85 55 <a href='?p=collections/classifications&amp;id=3831'>ARLIS/Roundtables</a></div>
      <div class='listitem'>85 56 <a href='?p=collections/classifications&amp;id=3832'>ARLIS/Special Interest Groups (SIG) and Type of Library (TOL)</a></div>
      <div class='listitem'>85 57 <a href='?p=collections/classifications&amp;id=3826'>ARLIS/Chapters</a></div>
      <div class='listitem'>85 59 <a href='?p=collections/classifications&amp;id=3835'>ARLIS/Personal Members</a></div>
    </div>
    </div>

