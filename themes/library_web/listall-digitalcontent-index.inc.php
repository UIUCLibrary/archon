<?php
isset($_ARCHON) or die();


if($_REQUEST['csv']){
	$filename = ($_REQUEST['output']) ? $_REQUEST['output'] : 'dls-csv-digitalcontent-index';

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Index of digital content information in Archon with download links<br />");
	echo("<span style='font-size:14px'>");
	echo date("Y-m-d H:i:s");
	//echo("<br /><a href=https://".($_SERVER['HTTP_HOST']). ($_SERVER['REQUEST_URI'])."&disabletheme=true&csv=true>Download CSV</a>");
	echo("</span>");
	echo("</h1>\n");
	echo("<div >");
}

if(!$_ARCHON->Error)
   {
	$requestBatchSize = $_REQUEST['batchsize'];
	if(ctype_digit($requestBatchSize) && $requestBatchSize > 0){
		$batchSize = $requestBatchSize;
	} else {
		$batchSize = 250;
	}

	$digitalcontentArrayAll = $_ARCHON->getAllDigitalContent(false, false);
	$countDigitalContent = count($digitalcontentArrayAll);
	$batchStart = 1;
	$batchEnd = $batchStart + $batchSize -1;
	echo("<h2>Batches</h2><table id='list-styled'>");
	echo("<tr><th>Range</th><th>DLS table link</th><th>DLS csv download link</th></tr>");
	while ($batchEnd < $countDigitalContent){
		echo("<tr><td>{$batchStart} to {$batchEnd}</td>");
		echo("<td><a href='?f=listall-digitalcontent-for-dls&start={$batchStart}&end={$batchEnd}'>View DLS table for {$batchStart} to {$batchEnd}</a></td>");
		echo("<td><a href='?f=listall-digitalcontent-for-dls&start={$batchStart}&end={$batchEnd}&disabletheme=true&csv=true&output=dls-csv-digitalcontent_{$batchStart}-{$batchEnd}'>Download csv for {$batchStart} to {$batchEnd}</a></td></tr>");
		$batchStart += $batchSize;
		$batchEnd += $batchSize;
	}
	echo("<tr><td>{$batchStart} to {$countDigitalContent}</td>");
	echo("<td><a href='?f=listall-digitalcontent-for-dls&start={$batchStart}&end={$batchEnd}'>View DLS table for {$batchStart} to {$countDigitalContent}</a></td>");
	echo("<td><a href='?f=listall-digitalcontent-for-dls&start={$batchStart}&end={$countDigitalContent}&disabletheme=true&csv=true&output=dls-csv-digitalcontent_{$batchStart}-{$countDigitalContent}'>Download csv for {$batchStart} to {$countDigitalContent}</a></td></tr>");
	echo("</table>");

	$digitalcontentIndexArray = $_ARCHON->getAllDigitalContent(true, false);
	$indexKeys = array_keys($digitalcontentIndexArray);
	echo("<h2>Index</h2><table id='list-styled'>");
	echo("<tr><th>Key</th><th>Count</th><th>Example</th><th>DLS table link (only works for letters)</th></tr>");
	foreach($indexKeys as $digitalcontentKey){
		echo("<tr><td>" . $digitalcontentKey . "</td><td>" . count($digitalcontentIndexArray[$digitalcontentKey])."</td>");
		echo("<td>".current($digitalcontentIndexArray[$digitalcontentKey])->toString(LINK_TOTAL)."</td>");
		echo("<td><a href='?f=listall-digitalcontent-for-dls&startletter={$digitalcontentKey}&endletter={$digitalcontentKey}'>View DLS table for {$digitalcontentKey}</a></td>");
		echo("</tr>");
	}
	echo("</table>");
}
    ?>