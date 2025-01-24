<?php

	echo("<table id='aeon-bulk-upload' class='simple-table'>");
	echo("<tr>
    <th>Site</th>
    <th>SubLocation</th>
    <th>DocumentType</th>
	<th>ItemTitle</th>
    <th>CallNumber</th>
	<th>ItemVolume</th>");
	//rbml fields
	/* 
	echo("<th>ItemSubTitle</th>
	<th>ItemIssue</th>");
	*/
	echo("<th>ItemNumber</th>");
	echo("<th>ItemInfo4</th>
	<th>Location</th>");
	echo("<th>ItemISxN</th>");
	echo("<th>SpecialRequest</th>");

	//archives and ala fields
	//echo("<th>Transaction.CustomFields.ResearchPurpose</th>");
	echo("<th>ItemCitation</th>");
  	echo("</tr>");

	if(!empty($objCollection->LocationEntries)){
		foreach($objCollection->LocationEntries as $objLocationEntry){
			
			if($objLocationEntry->Shelf){
				$boxHasBarcode=false;
				$testBoxBarcode = trim((string) $objLocationEntry->Shelf);
				
				if(is_numeric($testBoxBarcode) AND strlen($testBoxBarcode)==14){
					$boxHasBarcode=true;
				}
			}
			
			echo("<tr>");
			
			//site
			if($_ARCHON->config->AlternativeSiteForRepository[$objCollection->RepositoryID]){
				$requestSiteValue = $_ARCHON->config->AlternativeSiteForRepository[$objCollection->RepositoryID];
			} elseif($_ARCHON->config->AlternativeSiteForLocation[$requestBoxLocationCode]){
				$requestSiteValue = $_ARCHON->config->AlternativeSiteForLocation[$requestBoxLocationCode];
			} elseif($_ARCHON->config->RequestLinkSiteValue){
				$requestSiteValue = $_ARCHON->config->RequestLinkSiteValue;
			} else {
				$requestSiteValue = "ILLINOISSHARED";
			}

			echo("<td>".$requestSiteValue."</td>");
			
			//sublocation (repository)
			echo("<td>".$objCollection->Repository->getString('Name')."</td>");
			
			//document type
			echo("<td>".$requestMaterialType."</td>");
			
			//title
			echo("<td>".urldecode($requestTitle)."</td>");
			
			//call number
			echo("<td>".$requestIdentifier."</td>");
			
			//box number
			if($boxHasBarcode){
				echo("<td>".$objLocationEntry->getString('Content')."</td>");
			} else {
				echo("<td></td>");
			}

			//item number (barcode)
			if($boxHasBarcode){
				echo("<td>".$objLocationEntry->getString('Shelf')."</td>");
			} else {
				echo("<td></td>");
			}
			
			//item info4 (collection extent)
			echo("<td>".$requestExtent."</td>");
			
			//location (location code)
			echo("<td>");
			if($_ARCHON->config->RequestHasConsistentLocation) {
				if($_ARCHON->config->RequestDefaultLocation){
					echo($_ARCHON->config->RequestDefaultLocation);
				}
			}elseif(!empty($_ARCHON->config->RequestLinkLocationList)){
				echo($_ARCHON->config->RequestLinkLocationList[$objLocationEntry->LocationID]);
			}
			echo("</td>");
			
			//item isxn (box range or number)
			echo("<td>".$objLocationEntry->getString('Content')."</td>");
			
			//special request (restrictions)
			echo("<td>".$requestRestrictions."</td>");
			
			//item edition (research purpose)
			//echo("<td></td>");
			
			//item citation (research topic)
			echo("<td></td>");
			echo("</tr>\n");
		}
	}
	echo("</tbody></table>");

?>