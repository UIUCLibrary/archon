<?php
isset($_ARCHON) or die();

require 'vendor/autoload.php';

require 'listall-atom-repository-info.inc.php';

if($_REQUEST['csv']){
	$filename = ($_REQUEST['output']) ? $_REQUEST['output'] : 'atom-csv-locations';

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Full list of collection location information in Archon, formatted for AtoM CSV import<br />");
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

	 $count = 0;
	 $publiccount = 0;
	 $arrExtentUnits = $_ARCHON->getAllExtentUnits();

	 $arrHeadersForAtoM = [
		"legacyId",
		"parentId",
		"qubitParentSlug",
		"accessionNumber",
		"identifier",
		"title",
		"levelOfDescription",
		"extentAndMedium",
		"repository",
		"archivalHistory",
		"acquisition",
		"scopeAndContent",
		"appraisal",
		"accruals",
		"arrangement",
		"accessConditions",
		"reproductionConditions",
		"language",
		"script",
		"languageNote",
		"physicalCharacteristics",
		"findingAids",
		"locationOfOriginals",
		"locationOfCopies",
		"relatedUnitsOfDescription",
		"publicationNote",
		"digitalObjectPath",
		"digitalObjectURI",
		"generalNote",
		"subjectAccessPoints",
		"placeAccessPoints",
		"nameAccessPoints",
		"genreAccessPoints",
		"descriptionIdentifier",
		"institutionIdentifier",
		"rules",
		"descriptionStatus",
		"levelOfDetail",
		"revisionHistory",
		"languageOfDescription",
		"scriptOfDescription",
		"sources",
		"archivistNote",
		"publicationStatus",
		"physicalObjectName",
		"physicalObjectLocation",
		"physicalObjectType",
		"alternativeIdentifiers",
		"alternativeIdentifierLabels",
		"eventDates",
		"eventTypes",
		"eventStartDates",
		"eventEndDates",
		"eventActors",
		"eventActorHistories",
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
	 

	 foreach($Characters as $Char) {  
         //echo("<table>");
		 $arrCollections = $_ARCHON->getCollectionsForChar($Char, false, $_SESSION['Archon_RepositoryID'], array('ID', 'Title', 'SortTitle', 'ClassificationID', 'InclusiveDates', 'CollectionIdentifier', 'RepositoryID', 'Extent', 'ExtentUnitID', 'Enabled'));
         
		

		if(!empty($arrCollections))
		{
         //echo("<div class='listitemhead bold'>$Char</div><br/><br/>\n<div id='listitemwrapper' class='bground'><div class='listitemcover'></div>\n");

         foreach($arrCollections as $objCollection)
         {
            $objCollection->dbLoadLocationEntries();
			if(!empty($objCollection->LocationEntries)){
				foreach($objCollection->LocationEntries as $objLocationEntry){
					
					$legacyIdForAtoM="";
					$parentIdForAtoM="";
					$qubitParentSlugForAtoM="";
					$accessionNumberForAtoM="";
					$identifierForAtoM="";
					$titleForAtoM="";
					$levelOfDescriptionForAtoM="";
					$extentAndMediumForAtoM="";
					$repositoryForAtoM="";
					$archivalHistoryForAtoM="";
					$acquisitionForAtoM="";
					$scopeAndContentForAtoM="";
					$appraisalForAtoM="";
					$accrualsForAtoM="";
					$arrangementForAtoM="";
					$accessConditionsForAtoM="";
					$reproductionConditionsForAtoM="";
					$languageForAtoM="";
					$scriptForAtoM="";
					$languageNoteForAtoM="";
					$physicalCharacteristicsForAtoM="";
					$findingAidsForAtoM="";
					$locationOfOriginalsForAtoM="";
					$locationOfCopiesForAtoM="";
					$relatedUnitsOfDescriptionForAtoM="";
					$publicationNoteForAtoM="";
					$digitalObjectPathForAtoM="";
					$digitalObjectURIForAtoM="";
					$generalNoteForAtoM="";
					$subjectAccessPointsForAtoM="";
					$placeAccessPointsForAtoM="";
					$nameAccessPointsForAtoM="";
					$genreAccessPointsForAtoM="";
					$descriptionIdentifierForAtoM="";
					$institutionIdentifierForAtoM="";
					$rulesForAtoM="";
					$descriptionStatusForAtoM="";
					$levelOfDetailForAtoM="";
					$revisionHistoryForAtoM="";
					$languageOfDescriptionForAtoM="";
					$scriptOfDescriptionForAtoM="";
					$sourcesForAtoM="";
					$archivistNoteForAtoM="";
					$publicationStatusForAtoM="";
					$physicalObjectNameForAtoM="";
					$physicalObjectLocationForAtoM="";
					$physicalObjectTypeForAtoM="";
					$alternativeIdentifiersForAtoM="";
					$alternativeIdentifierLabelsForAtoM="";
					$eventDatesForAtoM="";
					$eventTypesForAtoM="";
					$eventStartDatesForAtoM="";
					$eventEndDatesForAtoM="";
					$eventActorsForAtoM="";
					$eventActorHistoriesForAtoM="";
					$cultureForAtoM="";
					
					/** [legacyId] Archon Fields: ID (plus prefix); Notes: archon id prefixed with ala, archives, ihlc, or rbml*/
					if($objCollection->getString('ID'))
					{
						$legacyIdForAtoM = $unitSourcePrefix."-a-".$objCollection->getString('ID');
					}

					/** [parentId] Archon Fields: ParentID (collection content) or ClassificationID (collection); Notes: archon id of parent prefixed with ala, archives, ihlc, or rbml*/
					if($atomImportNested && $objCollection->ClassificationID)
					{
						$parentIdForAtoM = $unitSourcePrefix."-a-".$objCollection->ClassificationID;
					}
					
					/** [qubitParentSlug] Archon Fields: ; Notes: only used after imported into atom (use legacy or this atom url slug)*/

					$qubitParentSlugForAtoM="";
					
					/** [accessionNumber] Archon Fields: ; Notes: */
					$accessionNumberForAtoM="";

					/** [identifier] Archon Fields: CollectionIdentifier; Notes: collection identifier or record series number*/
					if(!$atomImportNested && $objCollection->ClassificationID)
					{
						$objCollection->Classification = New Classification($objCollection->ClassificationID);
						$identifierForAtoM = $objCollection->Classification->toString(LINK_NONE, true, false, true, false) . '/';
						$identifierForAtoM = str_replace("/","-",$identifierForAtoM);
					}
					if($objCollection->CollectionIdentifier)
					{
						$identifierForAtoM .= $objCollection->getString('CollectionIdentifier');
					}

					/** [title] Archon Fields: Title; Notes: title*/
					if($objCollection->Title)
					{
						$titleForAtoM = $objCollection->getString('Title');
					}

					/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
					if(!empty($objCollection->RepositoryID))
					{
						$repositoryForAtoM = $arrRepositoriesForAtoM[$objCollection->RepositoryID];
					}

					/** [publicationStatus] Archon Fields: Enabled (converting as follows: 1=Published, 0=Draft); Notes: Published or Draft*/
					if($objCollection->Enabled)
					{
						$accessible = $objCollection->getString('Enabled');
						if($accessible == 1)
						{
							$publicationStatusForAtoM = "Published";
							$publiccount++;
						} else {
							$publicationStatusForAtoM = "Draft";
						}
					} else {
						$publicationStatusForAtoM = "Draft";
					}

					/** [physicalObjectName] Archon Fields: (note: these are the terms from the json) Locations:Content, Locations:Extent, Locations:ExtentUnitID (convert to text), Locations:Shelf (if barcode); Notes: LocationContent, extent and extent unit in parenthesis?, and Barcode (if in shelf field)*/
					if($objLocationEntry->Content){
						$physicalObjectNameForAtoM = $objLocationEntry->getString('Content');
						
						//for IHLC, convert single dashes for unnumbered single containers
						if($unitSourcePrefix == "IHLC" && $physicalObjectNameForAtoM == "-"){
							$physicalObjectNameForAtoM = "All";
						}
					}

					if($objLocationEntry->Extent && $objLocationEntry->ExtentUnit){
						$physicalObjectNameForAtoM .= " (". $objLocationEntry->getString('Extent') ." ". $objLocationEntry->getString('ExtentUnit').")";
					}

					if($objLocationEntry->Shelf){
						$boxHasBarcode=false;
						$testBoxBarcode = trim((string) $objLocationEntry->Shelf);
				
						if(is_numeric($testBoxBarcode) AND strlen($testBoxBarcode)==14){
							$boxHasBarcode=true;
						}

						if($boxHasBarcode){
							$physicalObjectNameForAtoM .= "; Barcode: ".$objLocationEntry->getString('Shelf');
						}
					}

					/** [physicalObjectLocation] Archon Fields: (note: these are the terms from the json) Locations:Location, Locations:Range, Locations:Section, Locations:Shelf (if not barcode); Notes: Location, Range, Section, Shelf (if not holding barcode)*/
					if($objLocationEntry->Location){
						$physicalObjectLocationForAtoM = $objLocationEntry->getString('Location');
					}
					if($objLocationEntry->RangeValue){
						$physicalObjectLocationForAtoM .= " --> " . $objLocationEntry->getString('RangeValue');
					}
					if($objLocationEntry->Section){
						$physicalObjectLocationForAtoM .= " --> " . $objLocationEntry->getString('Section');
					}
					if($objLocationEntry->Shelf && !$boxHasBarcode){
						$physicalObjectLocationForAtoM .= "; ".$objLocationEntry->getString('Shelf');
					}

					/** [physicalObjectType] Archon Fields: ; Notes: options defined in AtoM taxonomy -- indicates type of box; would we want to use extent and extent unit here as a type of box?*/
					if($objLocationEntry->ExtentUnit){
						$physicalObjectTypeForAtoM = $objLocationEntry->getString('ExtentUnit');
					}
					//use the extent unit for the box type unless it has a conversion specified
					if($arrExtentConversionForAtomBoxType[$physicalObjectTypeForAtoM]){
						$physicalObjectTypeForAtoM = $arrExtentConversionForAtomBoxType[$physicalObjectTypeForAtoM];
					}
					$physicalObjectTypeForAtoM = ucfirst($physicalObjectTypeForAtoM);

					/** [culture] Archon Fields: ; Notes: en (for english)*/
					$cultureForAtoM="en";
					
					include 'listall-atom-info-obj-line.inc.php';
					
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