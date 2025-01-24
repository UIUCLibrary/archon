<?php
isset($_ARCHON) or die();

require 'vendor/autoload.php';

require 'listall-atom-repository-info.inc.php';

if($_REQUEST['csv']){
	$filename = ($_REQUEST['output']) ? $_REQUEST['output'] : 'atom-csv-creators';

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Full list of creator information in Archon, formatted for AtoM CSV import<br />");
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

	 $arrCreatorTypes = $_ARCHON->getAllCreatorTypes();
	 $arrCreatorSources = $_ARCHON->getAllCreatorSources();

	 $arrHeadersForAtoM = [
		"culture",
		"typeOfEntity",
		"authorizedFormOfName",
		"parallelFormsOfName",
		"standardizedFormsOfName",
		"otherFormsOfName",
		"corporateBodyIdentifiers",
		"datesOfExistence",
		"history",
		"places",
		"legalStatus",
		"functions",
		"mandates",
		"internalStructures",
		"generalContext",
		"descriptionIdentifier",
		"institutionIdentifier",
		"rules",
		"status",
		"levelOfDetail",
		"revisionHistory",
		"sources",
		"maintenanceNotes",
		"actorOccupations",
		"actorOccupationNotes",
		"subjectAccessPoints",
		"placeAccessPoints",
		"digitalObjectURI",
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
	 

	 foreach($Characters as $Char) {  
		 $arrCreators = $_ARCHON->getCreatorsForChar($Char);

		if(!empty($arrCreators))
		{

         foreach($arrCreators as $objCreator)
         {
			//$objCreator->dbLoadRelatedObjects();

            $cultureForAtoM="";
			$typeOfEntityForAtoM="";
			$authorizedFormOfNameForAtoM="";
			$parallelFormsOfNameForAtoM="";
			$standardizedFormsOfNameForAtoM="";
			$otherFormsOfNameForAtoM="";
			$corporateBodyIdentifiersForAtoM="";
			$datesOfExistenceForAtoM="";
			$historyForAtoM="";
			$placesForAtoM="";
			$legalStatusForAtoM="";
			$functionsForAtoM="";
			$mandatesForAtoM="";
			$internalStructuresForAtoM="";
			$generalContextForAtoM="";
			$descriptionIdentifierForAtoM="";
			$institutionIdentifierForAtoM="";
			$rulesForAtoM="";
			$statusForAtoM="";
			$levelOfDetailForAtoM="";
			$revisionHistoryForAtoM="";
			$sourcesForAtoM="";
			$maintenanceNotesForAtoM="";
			$actorOccupationsForAtoM="";
			$actorOccupationNotesForAtoM="";
			$subjectAccessPointsForAtoM="";
			$placeAccessPointsForAtoM="";
			$digitalObjectPathForAtoM="";
			$digitalObjectURIForAtoM="";

			/** [culture] Archon Fields: ; Notes: en*/
			$cultureForAtoM="en";

			/** [typeOfEntity] Archon Fields: CreatorTypeID; Notes: convert to text and then to Corporate body, Family or Person*/
			if($objCreator->CreatorTypeID){
				$typeOfEntityForAtoM = $arrCreatorTypes[$objCreator->CreatorTypeID]->getString('CreatorType');
			}
			
			//convert to AtoM terms
			if($typeOfEntityForAtoM == "Personal Name"){
				$typeOfEntityForAtoM = "Person";
			}
			if($typeOfEntityForAtoM == "Family Name"){
				$typeOfEntityForAtoM = "Family";
			}
			if($typeOfEntityForAtoM == "Corporate Name"){
				$typeOfEntityForAtoM = "Corporate body";
			}

			/** [authorizedFormOfName] Archon Fields: Name; Notes: */
			if($objCreator->Name){
				$authorizedFormOfNameForAtoM=strip_tags($objCreator->toString());
			}

			/** [parallelFormsOfName] Archon Fields: NameFullerForm; Notes: */
			if($objCreator->NameFullerForm){
				$parallelFormsOfNameForAtoM=$objCreator->NameFullerForm;
			}
			/** [standardizedFormsOfName] Archon Fields: ; Notes: */
			$standardizedFormsOfNameForAtoM="";

			/** [otherFormsOfName] Archon Fields: NameVariants; Notes: */
			if($objCreator->NameVariants){
				$otherFormsOfNameForAtoM=$objCreator->NameVariants;
			}
			/** [corporateBodyIdentifiers] Archon Fields: ; Notes: */
			$corporateBodyIdentifiersForAtoM="";

			/** [datesOfExistence] Archon Fields: Dates; Notes: */
			if($objCreator->Dates){
				$datesOfExistenceForAtoM=$objCreator->Dates;
			}
			/** [history] Archon Fields: BiogHist; BiogHistAuthor; Notes: */
			if($objCreator->BiogHist){
				$historyForAtoM=$objCreator->getString('BiogHist');
			}
			if($objCreator->BiogHistAuthor){
				$historyForAtoM .= "(by ". $objCreator->getString('BiogHistAuthor') .")";
			}
			/** [places] Archon Fields: ; Notes: */
			$placesForAtoM="";

			/** [legalStatus] Archon Fields: ; Notes: */
			$legalStatusForAtoM="";

			/** [functions] Archon Fields: ; Notes: */
			$functionsForAtoM="";

			/** [mandates] Archon Fields: ; Notes: */
			$mandatesForAtoM="";

			/** [internalStructures] Archon Fields: ; Notes: */
			$internalStructuresForAtoM="";

			/** [generalContext] Archon Fields: ; Notes: */
			$generalContextForAtoM="";

			/** [descriptionIdentifier] Archon Fields: Identifier; Notes: if empty, and a local source, use local_[archonid]*/
			if($objCreator->Identifier){
				$descriptionIdentifierForAtoM=$objCreator->getString('Identifier');
			}
			/** [institutionIdentifier] Archon Fields: RepositoryID; Notes: convert to text*/
			if($objCreator->RepositoryID){
				$institutionIdentifierForAtoM=$arrRepositoriesForAtoM[$objCreator->RepositoryID];
			}
			/** [rules] Archon Fields: ; Notes: */
			$rulesForAtoM="";

			/** [status] Archon Fields: ; Notes: */
			$statusForAtoM="";

			/** [levelOfDetail] Archon Fields: ; Notes: */
			$levelOfDetailForAtoM="";

			/** [revisionHistory] Archon Fields: ID; Notes: add note about importing from Archon and give the id?*/
			$revisionHistoryForAtoM="Imported from ". $unitSourcePrefix . " Archon with ID ".$objCreator->ID;

			/** [sources] Archon Fields: CreatorSourceID; Sources; Notes: convert to text*/
			if($objCreator->CreatorSourceID){
				$sourcesForAtoM = $arrCreatorSources[$objCreator->CreatorSourceID];
			}
			if($objCreator->CreatorSourceID && $objCreator->Sources){
				$sourcesForAtoM .= "; ";
			}
			if($objCreator->Sources){
				$sourcesForAtoM .= $objCreator->getString('Sources');
			}
			/** [maintenanceNotes] Archon Fields: LanguageID, ScriptID (for the lack of anywhere else to put it); Notes: */
			$maintenanceNotesForAtoM="";

			/** [actorOccupations] Archon Fields: ; Notes: */
			$actorOccupationsForAtoM="";

			/** [actorOccupationNotes] Archon Fields: ; Notes: */
			$actorOccupationNotesForAtoM="";

			/** [subjectAccessPoints] Archon Fields: ; Notes: */
			$subjectAccessPointsForAtoM="";

			/** [placeAccessPoints] Archon Fields: ; Notes: */
			$placeAccessPointsForAtoM="";

			/** [digitalObjectURI] Archon Fields: ; Notes: */
			$digitalObjectURIForAtoM="";

					
					$arrDataLineForAtoM = [
						$cultureForAtoM,
						$typeOfEntityForAtoM,
						$authorizedFormOfNameForAtoM,
						$parallelFormsOfNameForAtoM,
						$standardizedFormsOfNameForAtoM,
						$otherFormsOfNameForAtoM,
						$corporateBodyIdentifiersForAtoM,
						$datesOfExistenceForAtoM,
						$historyForAtoM,
						$placesForAtoM,
						$legalStatusForAtoM,
						$functionsForAtoM,
						$mandatesForAtoM,
						$internalStructuresForAtoM,
						$generalContextForAtoM,
						$descriptionIdentifierForAtoM,
						$institutionIdentifierForAtoM,
						$rulesForAtoM,
						$statusForAtoM,
						$levelOfDetailForAtoM,
						$revisionHistoryForAtoM,
						$sourcesForAtoM,
						$maintenanceNotesForAtoM,
						$actorOccupationsForAtoM,
						$actorOccupationNotesForAtoM,
						$subjectAccessPointsForAtoM,
						$placeAccessPointsForAtoM,
						$digitalObjectURIForAtoM,
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