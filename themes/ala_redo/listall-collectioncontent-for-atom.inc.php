<?php
isset($_ARCHON) or die();

require 'vendor/autoload.php';

require 'listall-atom-repository-info.inc.php';

if($_REQUEST['csv']){
	if($_REQUEST['output']){
		$filename = $_REQUEST['output'];
	} else {
		$filename = 'atom-csv-collectioncontent-'.strtolower($unitSourcePrefix);

		if($_REQUEST['startletter'] && $_REQUEST['endletter']){
			$filename .= "_". strtolower($_REQUEST['startletter'])."-".strtolower($_REQUEST['endletter']);
		}
		if($_REQUEST['archonid']){
			$filename .= "_". strtolower($_REQUEST['archonid']);
		}
	};

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Full list of collection content information in Archon, formatted for AtoM CSV import<br />");
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

	if($_REQUEST['archonid']){
		//if there is an archonid specified we won't use the character array
		$Characters = array(1);
		$chosenArchonCollectionID = $_REQUEST['archonid'];
		$startingMessage = "Archon ID: " . $chosenArchonCollectionID;
	}

	if(!$_REQUEST['csv']){
		echo($startingMessage);
	}

	 $count = 0;
	 $publiccount = 0;
	 $arrExtentUnits = $_ARCHON->getAllExtentUnits();
	 $arrLevelContainers = $_ARCHON->getAllLevelContainers();

	 $GenreSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Genre/Form of Material');
	 $GeogSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Geographic Name');
	 $CorpSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Corporate Name');
	 $NameSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Personal Name');
	 $OccupationSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Occupation');
	 $TopicalSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Topical Term');

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
		 if($chosenArchonCollectionID){
			$chosenArchonCollection = new Collection($chosenArchonCollectionID);
			$chosenArchonCollection->dbLoad();
			$arrCollections = array ($chosenArchonCollection);
		 } else {
		 	$arrCollections = $_ARCHON->getCollectionsForChar($Char, false, $_SESSION['Archon_RepositoryID'], array('ID', 'Title', 'SortTitle', 'ClassificationID', 'InclusiveDates', 'CollectionIdentifier', 'RepositoryID', 'Extent', 'ExtentUnitID', 'Enabled'));
		 }
		

		if(!empty($arrCollections))
		{

         foreach($arrCollections as $objCollection)
         {
			$objCollection->dbLoadAll();

			if(!empty($objCollection->Content)){

				include 'listall-atom-stub-coll-record.inc.php';	

				foreach($objCollection->Content as $objCollectionContent){
					
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
					if($objCollectionContent->ID)
					{
						$legacyIdForAtoM = $unitSourcePrefix."-c-".$objCollectionContent->getString('ID');
					}

					/** [parentId] Archon Fields: ParentID (collection content); Notes: archon id of parent prefixed with ala, archives, ihlc, or rbml*/

					if($objCollectionContent->ParentID)
					{
						$parentIdForAtoM = $unitSourcePrefix."-c-".$objCollectionContent->getString('ParentID');
					} elseif ($objCollectionContent->CollectionID) {
						$parentIdForAtoM = $unitSourcePrefix."-a-".$objCollectionContent->getString('CollectionID');
					}
					
					/** [qubitParentSlug] Archon Fields: ; Notes: only used after imported into atom (use legacy or this atom url slug)*/

					$qubitParentSlugForAtoM="";
					
					/** [accessionNumber] Archon Fields: ; Notes: */
					$accessionNumberForAtoM="";

					/** [identifier] Archon Fields Identifier*/
					if($objCollectionContent->LevelContainerIdentifier)
					{
						$identifierForAtoM = $objCollectionContent->getString('LevelContainerIdentifier');
					}

					/** [title] Archon Fields: Title; Notes: title*/
					if($objCollectionContent->Title)
					{
						$titleForAtoM = $objCollectionContent->getString('Title');
					} elseif ($objCollectionContent->LevelContainerID && $objCollectionContent->LevelContainerIdentifier){
						$titleForAtoM = $arrLevelContainers[$objCollectionContent->getString('LevelContainerID')] ." ". $objCollectionContent->getString('LevelContainerIdentifier');
					}

					/** [levelOfDescription] Archon Fields:  */
					if($objCollectionContent->LevelContainerID)
					{
						$levelOfDescriptionForAtoM = $arrLevelContainers[$objCollectionContent->getString('LevelContainerID')];
					}
					 

					/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
					if(!empty($objCollection->RepositoryID))
					{
						$repositoryForAtoM = $arrRepositoriesForAtoM[$objCollection->RepositoryID];
					}

					/** [publicationStatus] Archon Fields: Browseable (?) (converting as follows: 1=Published, 0=Draft); Notes: Published or Draft*/
					if($objCollectionContent->Enabled)
					{
						$accessible = $objCollectionContent->getString('Enabled');
						if($accessible == 1)
						{
							$publicationStatusForAtoM = "Published";
						} else {
							$publicationStatusForAtoM = "Draft";
						}
					} else {
						$publicationStatusForAtoM = "Draft";
					}

					
					
					/** [scopeAndContent] Archon Fields: Scope; Notes: scope*/
					if($objCollectionContent->Description)
					{
						$scopeAndContentForAtoM = $objCollectionContent->getString('Description');
					}

					/** [arrangement] Archon Fields: SortOrder; Notes: not sure about retaining this (hopefully it will all migrate in the right order?), but to keep it for now with a note*/

					if($objCollectionContent->SortOrder)
					{
						$rrangementForAtoM = "Sort order: " . $objCollectionContent->getString('SortOrder');
					}


					/** [generalNote] Archon Fields: Notes; Notes: */
					if($objCollectionContent->Note)
					{
						$generalNoteForAtoM = $objCollectionContent->getString('Note');
					}

					/*set up for sorting subject terms into categories */
					$objCollectionContent->dbLoadSubjects();
					$arrGenres=array();
					$arrGeogSubj=array();
					$arrCorpSubj=array();
					$arrNameSubj=array();
					$arrOccupationSubj=array();
					$arrTopicalSubj=array();
					
					if(!empty($objCollectionContent->Subjects)){	
						foreach($objCollectionContent->Subjects as $objSubject){
						if($objSubject->SubjectTypeID == $GenreSubjectTypeID)
						{
							$arrGenres[$objSubject->ID] = $objSubject;
						} 
						elseif($objSubject->SubjectTypeID == $GeogSubjectTypeID) 
						{
							$arrGeogSubj[$objSubject->ID] = $objSubject;
						} 
						elseif($objSubject->SubjectTypeID == $CorpSubjectTypeID)
						{
							$arrCorpSubj[$objSubject->ID] = $objSubject;
						} 
						elseif($objSubject->SubjectTypeID == $NameSubjectTypeID)
						{
							$arrNameSubj[$objSubject->ID] = $objSubject;
						}
						elseif($objSubject->SubjectTypeID == $OccupationSubjectTypeID)
						{
							$arrOccupationSubj[$objSubject->ID] = $objSubject;
						}
						elseif($objSubject->SubjectTypeID == $TopicalSubjectTypeID)
						{
							$arrTopicalSubj[$objSubject->ID] = $objSubject;
						}
						}
					}	

					/** [subjectAccessPoints] Archon Fields: Subjects (stored as array -- need to separate out by subject type and not include the types covered below); Notes: topical subject terms with | between*/
					if(!empty($arrTopicalSubj)){
						$subjectAccessPointsForAtoM = strip_tags($_ARCHON->createStringFromSubjectArray($arrTopicalSubj, "|"));
					}
					if(!empty($arrTopicalSubj) && !empty($arrOccupationSubj)){
						$subjectAccessPointsForAtoM .= "|";
					}
					if(!empty($arrOccupationSubj)){
						$subjectAccessPointsForAtoM .= strip_tags($_ARCHON->createStringFromSubjectArray($arrOccupationSubj, "|"));
					}

					/** [placeAccessPoints] Archon Fields: Subjects (stored as array -- need to determine which are geographic terms); Notes: geographic subject terms with | between*/
					if(!empty($arrGeogSubj)){
						$placeAccessPointsForAtoM = strip_tags($_ARCHON->createStringFromSubjectArray($arrGeogSubj, "|"));
					}

					/** [nameAccessPoints] Archon Fields: Subjects (stored as array -- need to determine which are name terms); Notes: name subject terms with | between*/
					if(!empty($arrCorpSubj)){
						$nameAccessPointsForAtoM = strip_tags($_ARCHON->createStringFromSubjectArray($arrCorpSubj, "|"));
					}
					if(!empty($arrCorpSubj) && !empty($arrNameSubj)){
						$nameAccessPointsForAtoM .= "|";
					}
					if(!empty($arrNameSubj)){
						$nameAccessPointsForAtoM .= strip_tags($_ARCHON->createStringFromSubjectArray($arrNameSubj, "|"));
					}

					/** [genreAccessPoints] Archon Fields: MaterialType, Subjects (stored as array -- need to determine which are genre terms); Notes: material type plus genre subject terms with | between*/
					if($objCollection->MaterialType){
						$genreAccessPointsForAtoM = $objCollection->MaterialType->toString();
					}
					if($objCollection->MaterialType && !empty($arrGenres)){
						$genreAccessPointsForAtoM = "|";
					}
					if(!empty($arrGenres)){
						$genreAccessPointsForAtoM .= (strip_tags($_ARCHON->createStringFromSubjectArray($arrGenres, "|")));
					}


					/** [alternativeIdentifiers] Archon Fields: ID; Notes: archon id*/
					if($objCollectionContent->ID)
					{
						$alternativeIdentifiersForAtoM = $objCollectionContent->getString('ID');
					}

					/** [alternativeIdentifierLabels] Archon Fields: ; Notes: "ArchonID"*/
					$alternativeIdentifierLabelsForAtoM = "Archon ID";

					/** [eventActors] Archon Fields: Creators (array - convert to text); Notes: creator name*/
					$objCollectionContent->dbLoadCreators();
					if(!empty($objCollectionContent->Creators)){
						foreach($objCollectionContent->Creators as $objCreator){
							$eventActorsForAtoM .= strip_tags($objCreator->toString()) . "|";

							//add type creation without date information to make this a creator
							$eventDatesForAtoM .= "NULL|";
							$eventTypesForAtoM .= "Creation|";
						}
						//remove the last '|' from the creator, as applicable
						if(substr($eventActorsForAtoM,-1)=="|"){
							$eventActorsForAtoM = substr($eventActorsForAtoM,0,-1);
						}

					}

					/** [eventDates] Archon Fields: InclusiveDates, PredominantDates; Notes: date range of collection; can separate multiple types (inclusive, predominant?) with a |*/
					if($objCollectionContent->Date)
					{
						$eventDatesForAtoM .= $objCollectionContent->getString('Date');
					}
					//remove the last '|' from the date, as applicable
					if(substr($eventDatesForAtoM,-1)=="|"){
						$eventDatesForAtoM = substr($eventDatesForAtoM,0,-1);
					}

					/** [eventTypes] Archon Fields: ; Notes: in same order as above with | as needed (e.g. inclusive, predominant?)*/
					if($objCollectionContent->Date)
					{
						$eventTypesForAtoM .= "Inclusive Dates";
					}
					//remove the last '|' from the event type, as applicable
					if(substr($eventTypesForAtoM,-1)=="|"){
						$eventTypesForAtoM = substr($eventTypesForAtoM,0,-1);
					}
					
					/** [eventStartDates] Archon Fields: NormalDateBegin; Notes: normal date start*/


					/** [eventEndDates] Archon Fields: NormalDateEnd; Notes: normal date end*/


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