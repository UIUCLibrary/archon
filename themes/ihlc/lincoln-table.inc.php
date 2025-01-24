<?php
isset($_ARCHON) or die();

	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("List of collections with Lincoln subjects in Archon<br />");
	echo("<span style='font-size:14px'>");
	echo date("Y-m-d H:i:s");
	echo("</span>");
	echo("</h1>\n");
?>
	

<div >

<?php
if(!$_ARCHON->Error)
   {
	echo("<table id='list-subjects'>");
	echo("<tr>
    <th>Collection Identifier</th>
    <th>Collection title</th>
	<th>Inclusive dates</th>
	<th>Subjects</th>
	<th>Creator</th>
  </tr>");

	if($_ARCHON->config->LincolnSubjectList){
		$arrTermsLincoln = $_ARCHON->config->LincolnSubjectList;
	} else {
		$arrTermsLincoln = array("899", "904", "2749", "902", "905", "906", "907", "6", "1501", "1310", "487", "741", "360");
	}
	
	$arrResultsLincoln = array();

	foreach($arrTermsLincoln as $lincolnTermID) {
		$objSubject = New Subject($lincolnTermID);
      	$objSubject->dbLoad();
		
		if($objSubject->ID)
		{
			$subjectResults=array();
			$subjectResults=$_ARCHON->searchCollections(NULL, SEARCH_ENABLED_COLLECTIONS, $objSubject->ID);
			foreach($subjectResults as $subjResult){
				array_push($arrResultsLincoln,$subjResult->ID);
			}
		}
    }

	 $uniqueArrResultsLincoln = array_unique($arrResultsLincoln);

	 foreach($uniqueArrResultsLincoln as $resultLincoln){
		echo("<tr>");
		$objCollection= new Collection($resultLincoln);
		$objCollection->dbLoad();
		//collection identifier
		echo("<td>");
		echo($objCollection->getString('CollectionIdentifier'));
		echo("</td>");
		
		//collection title
		echo("<td>");
		if($objCollection->Title)
			{
				echo $objCollection->getString('Title');
			}
		echo("</td>");
		
		//inclusive dates
		echo("<td>");
		if($objCollection->InclusiveDates)
			{
				echo $objCollection->getString('InclusiveDates');
			}
		echo("</td>");
		
		//subjects
		echo("<td>");
		$objCollection->dbLoadSubjects();
		if(!empty($objCollection->Subjects)){
			echo($_ARCHON->createStringFromSubjectArray($objCollection->Subjects, "; "));
		}
		echo("</td>");
		
		//creator
		echo("<td>");
		$objCollection->dbLoadCreators();
			if(!empty($objCollection->Creators)){
				echo($_ARCHON->createStringFromCreatorArray($objCollection->Creators, "; "));
			}
		echo("</td>");

		//end row
		echo("</tr>");
	 }
	 echo("</table>");
   }
  ?>

</div>