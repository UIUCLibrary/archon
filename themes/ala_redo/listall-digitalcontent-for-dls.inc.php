<?php
isset($_ARCHON) or die();


if($_REQUEST['csv']){
	$filename = ($_REQUEST['output']) ? $_REQUEST['output'] : 'dls-csv-digitalcontent';

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Metadata for digital content information in Archon, formatted for DLS import<br />");
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
	 $arrLanguages = $_ARCHON->getAllLanguages();	 
	 $GenreSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Genre/Form of Material');
	 $GeogSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Geographic Name');
	 $CorpSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Corporate Name');
	 $NameSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Personal Name');
	 $OccupationSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Occupation');
	 $TopicalSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Topical Term');

	 $arrRepositoriesForDLS = array (
		1=>"University of Illinois Archives",
		2=>"Sousa Archives and Center for American Music",
	);

	 $arrHeadersForDLS = [
		"titleForDLS",
		"dateforDLS",
		"scopeAndContentForDLS",
		"extentAndMediumForDLS",
		"identifierForDLS",
		"repositoryForDLS",
		"collectionTitleforDLS",
		"recordGroupTitleforDLS",
		"collContentStringforDLS",
		"collContentIDforDLS",
		"languageForDLS",
		"genreAccessPointsForDLS",
		"nameAccessPointsForDLS",
		"placeAccessPointsForDLS",
		"subjectAccessPointsForDLS",
		"contributorForDLS",
		"rightsStatementForDLS",
		"publicationStatusForDLS",
		"fileCountForDLS",
		"fileNameForDLS",
		"fileAccessLevelforDLS",
		"alternativeIdentifiersForDLS",
		"digitalObjectURIForDLS",
	 ];
	 
	if($_REQUEST['csv']) {
		$out = fopen('php://output', 'w');
		fputcsv($out, $arrHeadersForDLS);
	} else {
		echo("<table id='list-styled'>");
	
		echo("<tr><th>");
		echo(implode("</th><th>",$arrHeadersForDLS));
		echo("</th></tr>");
	}
	 

	 foreach($Characters as $Char) {  
		$arrDigitalContent = $_ARCHON->getDigitalContentForChar($Char);

		if(!empty($arrDigitalContent))
		{
			if(!$_REQUEST['csv']) {
				echo("; Count of Digital count: ".count($arrDigitalContent));
			}
         foreach($arrDigitalContent as $objDigitalContent)
         {
            $objDigitalContent->dbLoad();
			$objDigitalContent->dbLoadRelatedObjects();
			//var_dump($objDigitalContent);
			//break;
			if(!empty($objDigitalContent->Files)){
					/** [identifier] Archon Fields Identifier*/
					if($objDigitalContent->Identifier)
					{
						$identifierForDLS = $objDigitalContent->getString('Identifier');
					} else {
						$identifierForDLS = "";
					}

					/** [title] Archon Fields: Title; Notes: title*/
					if($objDigitalContent->Title)
					{
						$titleForDLS = $objDigitalContent->getString('Title');
					} else {
						$titleForDLS = "";
					}

					/** [extentAndMedium] Archon Fields: physical description*/
					if($objDigitalContent->PhysicalDescription)
					{
						$extentAndMediumForDLS = $objDigitalContent->getString('PhysicalDescription');
					} else {
						$extentAndMediumForDLS = "";
					}

					/**[collection or record series title and number] */
					$collectionTitleforDLS="";
					$recordGroupTitleforDLS="";
					if($objDigitalContent->Collection){
						$objCollection=$objDigitalContent->Collection;
						if($objCollection->ClassificationID) { 
							 $objCollection->Classification = New Classification($objCollection->ClassificationID);
							 $objCollection->Classification->dbLoad();

							 $collectionTitleforDLS= $objCollection->Classification->toString(LINK_NONE, true, false, true, false) . '/';

							 $recordGroupTitleforDLS= $objCollection->Classification->toString(LINK_NONE, true, false, true, false, $Delimiter = "/");
							 $recordGroupTitleforDLS .= " ".$objCollection->Classification->toString(LINK_NONE, false, true, false, true, $Delimiter = ": ");
							 $recordGroupTitleforDLS = strip_tags($recordGroupTitleforDLS);
						 }
						$collectionTitleforDLS .= strip_tags($objDigitalContent->Collection->toString(LINK_NONE, true));
					}

					/**[collection content info] */
					if($objDigitalContent->CollectionContentID){
						$collContentStringforDLS = $objDigitalContent->CollectionContent->toString(LINK_NONE, true, true, true, true, " > ");
						$collContentStringforDLS = strip_tags($collContentStringforDLS);
						$collContentIDforDLS = $objDigitalContent->CollectionContentID;
					}else {
						$collContentStringforDLS = "";
						$collContentIDforDLS = "";
					}

					/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
					if(!empty($objCollection->RepositoryID))
					{
						$repositoryForDLS = $arrRepositoriesForDLS[$objCollection->RepositoryID];
					} else {
						$repositoryForDLS = "";
					}

					/** [publicationStatus] Archon Fields: Browseable (?) (converting as follows: 1=Published, 0=Draft); Notes: Published or Draft*/
					if($objDigitalContent->Browsable)
					{
						$accessible = $objDigitalContent->getString('Browsable');
						if($accessible == 1)
						{
							$publicationStatusForDLS = "Published";
						} else {
							$publicationStatusForDLS = "Unpublished";
						}
					} else {
						$publicationStatusForDLS = "Unpublished";
					}
					/**[file names] */
					$fileNameForDLS = "";
					$fileAccessLevelforDLS ="";
					if (!empty($objDigitalContent->Files)){
						$firstFile = true;
						foreach ($objDigitalContent->Files as $objFile){
							if(!$firstFile){
								$fileNameForDLS .= "||";
								$fileAccessLevelforDLS .= "||";
							}
							$firstFile = false;
							$fileNameForDLS .= $objFile->getString('Title');

							if($objFile->DefaultAccessLevel == DIGITALLIBRARY_ACCESSLEVEL_PREVIEWONLY){
								$fileAccessLevelforDLS .= "preview only";
							}elseif($objFile->DefaultAccessLevel == DIGITALLIBRARY_ACCESSLEVEL_NONE){
								$fileAccessLevelforDLS .= "no access";
							}else {
								$fileAccessLevelforDLS .= "full access";
							}
						}
						$fileCountForDLS = count($objDigitalContent->Files);
					}
				

					/** [acquisition] Archon Fields: Contributor; Notes: */
					if($objDigitalContent->Contributor)
					{
						$contributorForDLS = $objDigitalContent->getString('Contributor');
					}else {
						$contributorForDLS ="";
					}
					
					/** [scopeAndContent] Archon Fields: Scope; Notes: scope*/
					if($objDigitalContent->Scope)
					{
						$scopeAndContentForDLS = $objDigitalContent->getString('Scope');
					}else {
						$scopeAndContentForDLS = "";
					}

					/** [reproductionConditions] Archon Fields: RightsStatement; Notes: */
					if($objDigitalContent->RightsStatement)
					{
						$rightsStatementForDLS = $objDigitalContent->getString('RightsStatement');
					} else {
						$rightsStatementForDLS = "";
					}

					/** [language] Archon Fields: Languages (stored as array); Notes: language names with || between */
					$objDigitalContent->dbLoadLanguages();
					if(!empty($objDigitalContent->Languages))
					{
						$languageForDLS = $_ARCHON->createStringFromLanguageArray($objDigitalContent->Languages, '||', LINK_TOTAL);
						$languageForDLS = strip_tags($languageForDLS);
					} else {
						$languageForDLS = "";
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

					/** [subjectAccessPoints] Archon Fields: Subjects (stored as array -- need to separate out by subject type and not include the types covered below); Notes: topical subject terms with || between*/
					$subjectAccessPointsForDLS = "";
					if(!empty($arrTopicalSubj)){
						$subjectAccessPointsForDLS = strip_tags($_ARCHON->createStringFromSubjectArray($arrTopicalSubj, "||"));
					}
					if(!empty($arrTopicalSubj) && !empty($arrOccupationSubj)){
						$subjectAccessPointsForDLS .= "||";
					}
					if(!empty($arrOccupationSubj)){
						$subjectAccessPointsForDLS .= strip_tags($_ARCHON->createStringFromSubjectArray($arrOccupationSubj, "||"));
					}

					/** [placeAccessPoints] Archon Fields: Subjects (stored as array -- need to determine which are geographic terms); Notes: geographic subject terms with || between*/
					if(!empty($arrGeogSubj)){
						$placeAccessPointsForDLS = strip_tags($_ARCHON->createStringFromSubjectArray($arrGeogSubj, "||"));
					} else {
						$placeAccessPointsForDLS = "";
					}

					/** [nameAccessPoints] Archon Fields: Subjects (stored as array -- need to determine which are name terms); Notes: name subject terms with || between*/
					$nameAccessPointsForDLS = "";
					if(!empty($arrCorpSubj)){
						$nameAccessPointsForDLS = strip_tags($_ARCHON->createStringFromSubjectArray($arrCorpSubj, "||"));
					}
					if(!empty($arrCorpSubj) && !empty($arrNameSubj)){
						$nameAccessPointsForDLS .= "||";
					}
					if(!empty($arrNameSubj)){
						$nameAccessPointsForDLS .= strip_tags($_ARCHON->createStringFromSubjectArray($arrNameSubj, "||"));
					}

					/** [genreAccessPoints] Archon Fields: Genre subjects (stored as array -- need to determine which are genre terms); Notes: material type plus genre subject terms with | between*/
					if(!empty($arrGenres)){
						$genreAccessPointsForDLS = (strip_tags($_ARCHON->createStringFromSubjectArray($arrGenres, "||")));
					} else {
						$genreAccessPointsForDLS = "";
					}

					/** [publicationNote] Archon Fields: Publisher; Notes: */
					if($objDigitalContent->Publisher)
					{
						$publisherForDLS = $objDigitalContent->getString('Publisher');
					} else {
						$publisherForDLS = "";
					}

					/** [digitalObjectURI] Archon Fields: ContentURL; Notes: only if HyperlinkURL is true?*/
					if($objDigitalContent->ContentURL){
						$digitalObjectURIForDLS = $objDigitalContent->getString('ContentURL');
					} else {
						$digitalObjectURIForDLS = "";
					}

					/** [alternativeIdentifiers] Archon Fields: ID; Notes: archon id*/
					if($objDigitalContent->ID)
					{
						$alternativeIdentifiersForDLS = "Archon ID: " . $objDigitalContent->getString('ID');
					} else {
						$alternativeIdentifiersForDLS = "";
					}

					/** [creator] Archon Fields: Creators (array - convert to text); Notes: creator name*/
					$objDigitalContent->dbLoadCreators();
					$eventActorsForDLS = "";
					if(!empty($objDigitalContent->Creators)){
						foreach($objDigitalContent->Creators as $objCreator){
							$eventActorsForDLS .= strip_tags($objCreator->toString()) . "||";
						}
						//remove the last '||' from the creator, as applicable
						if(substr($eventActorsForDLS,-1)=="||"){
							$eventActorsForDLS = substr($eventActorsForDLS,0,-1);
						}
					}

					/** [date] Archon Fields: InclusiveDates, PredominantDates; Notes: date range of digital content*/

					if($objDigitalContent->Date)
					{
						$dateForDLS = $objDigitalContent->getString('Date');
					} else {
						$dateForDLS = "";
					}
					
					$arrDataLineForDLS = [
						$titleForDLS,
						$dateForDLS,
						$scopeAndContentForDLS,
						$extentAndMediumForDLS,
						$identifierForDLS,
						$repositoryForDLS,
						$collectionTitleforDLS,
						$recordGroupTitleforDLS,
						$collContentStringforDLS,
						$collContentIDforDLS,
						$languageForDLS,
						$genreAccessPointsForDLS,
						$nameAccessPointsForDLS,
						$placeAccessPointsForDLS,
						$subjectAccessPointsForDLS,
						$contributorForDLS,
						$rightsStatementForDLS,
						$publicationStatusForDLS,
						$fileCountForDLS,
						$fileNameForDLS,
						$fileAccessLevelforDLS,
						$alternativeIdentifiersForDLS,
						$digitalObjectURIForDLS,
					];

					if($_REQUEST['csv']) {
						fputcsv($out, $arrDataLineForDLS);
					} else {
						echo("<tr><td>");
						echo(implode("</td><td>",$arrDataLineForDLS));
						echo("</td></tr>");
					}
					
				}
			}
			} 
         }

		}

	if(!$_REQUEST['csv']) {
		echo("</table>");
	}


if(!$_REQUEST['csv']){
	echo("</div>");
}

?>