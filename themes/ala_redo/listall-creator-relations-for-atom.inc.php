<?php
isset($_ARCHON) or die();

require 'vendor/autoload.php';

require 'listall-atom-repository-info.inc.php';

if($_REQUEST['csv']){
	if($_REQUEST['output']){
		$filename = $_REQUEST['output'];
	} else {
		$filename = 'atom-csv-creator-rel-'.strtolower($unitSourcePrefix);

		if($_REQUEST['startletter'] && $_REQUEST['endletter']){
			$filename .= "_". strtolower($_REQUEST['startletter'])."-".strtolower($_REQUEST['endletter']);
		}
		if($_REQUEST['archonid']){
			$filename .= "_". strtolower($_REQUEST['archonid']);
		}
	}

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Full list of creator relationship information in Archon, formatted for AtoM CSV import<br />");
	echo("<span style='font-size:14px'>");
	echo date("Y-m-d H:i:s");
	echo("<br /><a href=https://".($_SERVER['HTTP_HOST']). ($_SERVER['REQUEST_URI'])."&disabletheme=true&csv=true>Download CSV</a>");
	echo("</span>");
	echo("</h1>\n");
	echo("<div >");
}

if(!$_ARCHON->Error)
   {
	$listAllCharStart = $_REQUEST['startletter'];
	$listAllCharEnd = $_REQUEST['endletter'];
	if(strlen($listAllCharStart) == 1 && ctype_alpha($listAllCharStart) && strlen($listAllCharEnd) == 1 && ctype_alpha($listAllCharEnd)){
		$startingMessage = "Start Letter {$listAllCharStart}; End Letter {$listAllCharEnd}";
	    $Characters = range($listAllCharStart, $listAllCharEnd);
	}else{
		$startingMessage = "No valid URL parameters -- titles starting with numbers only";
	   	$Characters[] = "#";
	}

	if(!$_REQUEST['csv']){
		echo($startingMessage);
	}

	 $arrHeadersForAtoM = [
		"subjectAuthorizedFormOfName",
		"relationType",
		"objectAuthorizedFormOfName",
		"description",
		"date",
		"startDate",
		"endDate",
		"culture",		
	 ];
	 
	if($_REQUEST['csv']) {
		$out = fopen('php://output', 'w');
		fputcsv($out, $arrHeadersForAtoM);
	} else {
		echo("<table id='list-styled'>");
	
		echo("<tr><th>");
		echo(implode("</th><th>",$arrHeadersForAtoM));
		echo("</th></tr>");
	}

	$arrCreatorRelations = [
		'identity'=>'identity',
		'hierarchical-parent'=> 'is the subsidiary of',
		'hierarchical-child'=> 'is the parent organization of',
		'temporal-earlier'=> 'is the successor of',
		'temporal-later'=> 'is the predecessor of',
		'family'=> 'family',
		'associative'=> 'associative',
	];
	 

	 foreach($Characters as $Char) {  
		 $arrCreators = $_ARCHON->getCreatorsForChar($Char);

		if(!empty($arrCreators))
		{

         foreach($arrCreators as $objCreator)
         {
			$objCreator->dbLoadRelatedCreators(true);

			foreach($objCreator->CreatorRelationships as $objCreatorRelationship){

				$subjectAuthorizedFormOfNameForAtoM="";
				$relationTypeForAtoM="";
				$objectAuthorizedFormOfNameForAtoM="";
				$descriptionForAtoM="";
				$dateForAtoM="";
				$startDateForAtoM="";
				$endDateForAtoM="";
				$cultureForAtoM="";


				/** [subjectAuthorizedFormOfName] Archon Fields: Name; Notes: */
				$subjectAuthorizedFormOfNameForAtoM=strip_tags($objCreator->toString());

				/** [relationType] Archon Fields: CreatorRelationships:CreatorRelationshipTypeID; Notes: convert to name*/
				if($objCreatorRelationship->CreatorRelationshipType){
					$relationTypeForAtoM=$objCreatorRelationship->CreatorRelationshipType->getString('CreatorRelationshipType');
				}
				if($arrCreatorRelations[$relationTypeForAtoM]){
					$relationTypeForAtoM= $arrCreatorRelations[$relationTypeForAtoM];
				}

				/** [objectAuthorizedFormOfName] Archon Fields: CreatorRelationships:RelatedCreatorID; Notes: convert to name*/
				if($objCreatorRelationship->RelatedCreator){
					$objectAuthorizedFormOfNameForAtoM=strip_tags($objCreatorRelationship->RelatedCreator->toString());
				}

				/** [description] Archon Fields: CreatorRelationships:Description; Notes: */
				if($objCreatorRelationship->Description){
					$descriptionForAtoM=$objCreatorRelationship->getString("Description");
				}

				/** [date] Archon Fields: ; Notes: */

				/** [startDate] Archon Fields: ; Notes: */

				/** [endDate] Archon Fields: ; Notes: */

				/** [culture] Archon Fields: ; Notes: en (for english)*/
				$cultureForAtoM="en";

				$arrDataLineForAtoM = [
					$subjectAuthorizedFormOfNameForAtoM,
					$relationTypeForAtoM,
					$objectAuthorizedFormOfNameForAtoM,
					$descriptionForAtoM,
					$dateForAtoM,
					$startDateForAtoM,
					$endDateForAtoM,
					$cultureForAtoM,
				];

				if(!$_REQUEST['striptags']){
					foreach($arrDataLineForAtoM as $keyAtoM => $dataPointForAtoM){
						//only remove the odd tabs that Archon adds to successive paragraphs
						$editedValueForAtoM = str_replace("</p><p>\t","</p><p>",$dataPointForAtoM);
	
						$markdown = new HTML_To_Markdown($editedValueForAtoM);
						$editedValueForAtoM = $markdown->output();
						$arrDataLineForAtoM[$keyAtoM] = $editedValueForAtoM;
					}
				} else {
					foreach($arrDataLineForAtoM as $keyAtoM => $dataPointForAtoM){
						//AtoM doesn't like importing html paragraph or line breaks; try new lines instead
						$editedValueForAtoM = str_replace("</p><p>\t","\n\n",$dataPointForAtoM);
						$editedValueForAtoM = str_replace("</p><p>","\n\n",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("<br/>","\n",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("<br />","\n",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("</p>","",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("<p>","",$editedValueForAtoM);
						$editedValueForAtoM = strip_tags($editedValueForAtoM);
						$arrDataLineForAtoM[$keyAtoM] = $editedValueForAtoM;
					}
				}
				
				if($_REQUEST['csv']) {
					fputcsv($out, $arrDataLineForAtoM);
				} else {
					echo("<tr><td>");
					echo(implode("</td><td>",$arrDataLineForAtoM));
					echo("</td></tr>");
				}
					
			}
		}

         //echo("</div>\n");
		}
		//echo("</table>");
	 }
	if(!$_REQUEST['csv']) {
		echo("</table>");
	}
}

if(!$_REQUEST['csv']){
	echo("</div>");
}

?>