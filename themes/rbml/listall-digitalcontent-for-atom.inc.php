<?php
isset($_ARCHON) or die();

require 'vendor/autoload.php';

require 'listall-atom-repository-info.inc.php';

if($_REQUEST['csv']){
	$filename = ($_REQUEST['output']) ? $_REQUEST['output'] : 'atom-csv-digitalcontent';

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Full list of digital content information in Archon, formatted for AtoM CSV import<br />");
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
            $objCollection->dbLoadDigitalContent();

			if(!empty($objCollection->DigitalContent)){

				include 'listall-atom-stub-coll-record.inc.php';	

				foreach($objCollection->DigitalContent as $objDigitalContent){

					if($objDigitalContent->CollectionContentID){
						$attachCollectionContent = true;
					}else {
						$attachCollectionContent = false;
					}
					
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
					if($objDigitalContent->ID)
					{
						$legacyIdForAtoM = $unitSourcePrefix."-d-".$objDigitalContent->getString('ID');
					}

					/** [parentId] Archon Fields: Collection or record series legacy id (or collection content id)*/
					if($attachCollectionContent){
						$parentIdForAtoM = $unitSourcePrefix."-a-".$objDigitalContent->getString('CollectionContentID');
					} elseif($objDigitalContent->CollectionID) {
						$parentIdForAtoM = $unitSourcePrefix."-a-".$objDigitalContent->getString('CollectionID');
					}
					
					/** [qubitParentSlug] Archon Fields: ; Notes: only used after imported into atom (use legacy or this atom url slug)*/

					$qubitParentSlugForAtoM="";
					
					/** [accessionNumber] Archon Fields: ; Notes: */
					$accessionNumberForAtoM="";

					/** [identifier] Archon Fields Identifier*/
					if($objDigitalContent->Identifier)
					{
						$identifierForAtoM = $objDigitalContent->getString('Identifier');
					}

					/** [title] Archon Fields: Title; Notes: title*/
					if($objDigitalContent->Title)
					{
						$titleForAtoM = $objDigitalContent->getString('Title');
					}

					/** [levelOfDescription] Archon Fields: ; Notes: "Part" */
					$levelOfDescriptionForAtoM = 'Part';

					/** [extentAndMedium] Archon Fields: physical description*/
					if($objDigitalContent->PhysicalDescription)
					{
						$extentAndMediumForAtoM = $objDigitalContent->getString('PhysicalDescription');
					}
					 

					/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
					if(!empty($objCollection->RepositoryID))
					{
						$repositoryForAtoM = $arrRepositoriesForAtoM[$objCollection->RepositoryID];
					}

					/** [publicationStatus] Archon Fields: Browseable (?) (converting as follows: 1=Published, 0=Draft); Notes: Published or Draft*/
					if($objDigitalContent->Browsable)
					{
						$accessible = $objDigitalContent->getString('Browsable');
						if($accessible == 1)
						{
							$publicationStatusForAtoM = "Published";
						} else {
							$publicationStatusForAtoM = "Draft";
						}
					} else {
						$publicationStatusForAtoM = "Draft";
					}

					/** [acquisition] Archon Fields: Contributor; Notes: */
					if($objDigitalContent->Contributor)
					{
						$acquisitionForAtoM = $objDigitalContent->getString('Contributor');
					}
					
					/** [scopeAndContent] Archon Fields: Scope; Notes: scope*/
					if($objDigitalContent->Scope)
					{
						$scopeAndContentForAtoM = $objDigitalContent->getString('Scope');
					}

					/** [reproductionConditions] Archon Fields: RightsStatement; Notes: */
					if($objDigitalContent->RightsStatement)
					{
						$reproductionConditionsForAtoM = $objDigitalContent->getString('RightsStatement');
					}

					/** [language] Archon Fields: Languages (stored as array; will need to iterate to get the codes AtoM wants); Notes: language codes with | between */
					$objDigitalContent->dbLoadLanguages();
					if(!empty($objDigitalContent->Languages) && $atomLanguageCodesArray)
					{
						$collLanguageArr = array();
						foreach ($objDigitalContent->Languages as $objLanguage) {
							$collLanguageArr[]=$atomLanguageCodesArray[$objLanguage->getString('LanguageShort', 0, false)];
						}
						$languageForAtoM = implode($collLanguageArr,"|");
					}

					/*set up for sorting subject terms into categories */
					$objDigitalContent->dbLoadSubjects();
					$arrGenres=array();
					$arrGeogSubj=array();
					$arrCorpSubj=array();
					$arrNameSubj=array();
					$arrOccupationSubj=array();
					$arrTopicalSubj=array();
					
					if(!empty($objDigitalContent->Subjects)){	
						foreach($objDigitalContent->Subjects as $objSubject){
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

					/** [publicationNote] Archon Fields: Publisher; Notes: */
					if($objDigitalContent->Publisher)
					{
						$reproductionConditionsForAtoM = $objDigitalContent->getString('Publisher');
					}

					/** [digitalObjectURI] Archon Fields: ContentURL; Notes: only if HyperlinkURL is true?*/
					if($objDigitalContent->ContentURL){
						$digitalObjectURIForAtoM = $objDigitalContent->getString('ContentURL');
					}

					/** [alternativeIdentifiers] Archon Fields: ID; Notes: archon id*/
					if($objDigitalContent->ID)
					{
						$alternativeIdentifiersForAtoM = $objDigitalContent->getString('ID');
					}

					/** [alternativeIdentifierLabels] Archon Fields: ; Notes: "ArchonID"*/
					$alternativeIdentifierLabelsForAtoM = "Archon ID";

					/** [eventActors] Archon Fields: Creators (array - convert to text); Notes: creator name*/
					$objDigitalContent->dbLoadCreators();
					if(!empty($objDigitalContent->Creators)){
						foreach($objDigitalContent->Creators as $objCreator){
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

					/** [eventActorHistories] Archon Fields*/
					
				

					/** [eventDates] Archon Fields: InclusiveDates, PredominantDates; Notes: date range of collection; can separate multiple types (inclusive, predominant?) with a |*/
					if($objDigitalContent->Date)
					{
						$eventDatesForAtoM .= $objDigitalContent->getString('Date');
					}
					//remove the last '|' from the date, as applicable
					if(substr($eventDatesForAtoM,-1)=="|"){
						$eventDatesForAtoM = substr($eventDatesForAtoM,0,-1);
					}

					/** [eventTypes] Archon Fields: ; Notes: in same order as above with | as needed (e.g. inclusive, predominant?)*/
					if($objDigitalContent->Date)
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