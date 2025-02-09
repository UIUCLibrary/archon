<?php
error_reporting(0);
header('Content-Type: application/json');

isset($_ARCHON) or die();

$session= $_SERVER['HTTP_SESSION'];
if ($_ARCHON->Security->Session->verifysession($session)){

    if ($_REQUEST['cid']){
	
            if (isset($_REQUEST['batch_start'])){
                $start = ( $_REQUEST['batch_start'] < 1 ? 1: $_REQUEST['batch_start']);
                $arrout=loadCollectionContent($start);
                header('HTTP/1.0 200 Created');
								
				if (empty($arrout[0])) {
					exit ("No matching record(s) found for cid=".$_REQUEST['cid']." and batch_start=".$_REQUEST['batch_start']);
				}
				
                //Creators
                $arrCollectionContentCreator = getCollectionContentcreators();

                foreach ($arrCollectionContentCreator as $CollectionContentRelatedObject)
                {
                    extract($CollectionContentRelatedObject);
                    // Multi array special process
                    if(isset($arrout['0'][$CollectionContentID])){
                        $arrout['0'][$CollectionContentID]->Creators[] = $CollectionContentRelatedObject['CreatorID'];
                    }
                }

                //Subjects
                $arrCollectionContentSubjects= getCollectionContentSubjects();

                foreach ($arrCollectionContentSubjects as $CollectionContentRelatedObject)
                {
                    extract($CollectionContentRelatedObject);
                    if (isset($arrout['0'][$CollectionContentID])){
                        $arrout['0'][$CollectionContentRelatedObject['CollectionContentID']]->Subjects[] = $CollectionContentRelatedObject['SubjectID'];
                    }
                }
				clean_up($arrout);
				$arrout = objectToArray($arrout); 
				if ($_ARCHON->db->ServerType == 'MSSQL'AND !$_ARCHON->config->DatabaseEncodingUTF8) {array_walk_recursive($arrout, 'myutf8_encode');}  //fix unicode for MSSQL migrations; function will incorrectly transform mysql unicode
				echo  $_ARCHON->bbcode_to_html(json_encode($arrout[0]));  // at some point, the object was nested with first element of a parent array, so don't encod the second, null object.-++----
            }
            else
            {
				header('HTTP/1.0 400 Bad Request');
                echo "batch_start Not found! Please enter a batch_start and resubmit the request.";
            }
        }
        else
        {
			header('HTTP/1.0 400 Bad Request');
            echo "cid  Not found! and  Please enter a collection ID and resubmit the request.";

        }
} else {
	header('HTTP/1.0 401 Unauthorized');
    echo "Please submit your admin credentials to p=core/authenticate";
}

//FUNCTIONS

function loadCollectionContent($start){

    global $_ARCHON;

    $cid = $_REQUEST['cid'];
    $objCollection = New Collection($_REQUEST['cid']);
    $objCollection->dbLoadContentjson($start-1);  //parameter gives start row for result set of up to 100 rows

    //$arrContent[]= array_slice($objCollection->Content,$start-1,100,true);
    $arrContent[]=$objCollection->Content; 
    //var_dump ($arrContent);
    //exit;
    $arrDisplay[]= array();

    foreach ($arrContent as $contentObj)
    {
        if ($contentObj['ParentID'] == 0)
        {// Top Node Loaded first
           array_unshift($arrDisplay,$contentObj);
        }
        else
        {//if parent loaded the Add
            if($arrDisplay[$contentObj->ParentID]){
                array_push($arrDisplay,$contentObj);
            }
            else{//if no parent find parent added parent add node remove parent from heap to avoid duplication
                array_push($arrDisplay,$arrContent[$contentObj->ParentID]);
                array_push($arrDisplay,$contentObj);
                unset($arrContent[$contentObj->ParentID]); //remove the already added parent  to stop duplication.
            }
        }
    }

return ($arrDisplay);

}
function getCollectionContentcreators()
{
    global $_ARCHON;

    $query = "SELECT CollectionContentID,CreatorID FROM tblCollections_CollectionContentCreatorIndex";
    $result = $_ARCHON->mdb2->query($query);

    if(pear_isError($result))
    {
        trigger_error($result->getMessage(), E_USER_ERROR);
    }

    while($row = $result->fetchRow())
    {
        $arrCollectionContentCreators [] = $row;
    }

    $result->free();

    return $arrCollectionContentCreators;

}

function getCollectionContentSubjects()
{
    global $_ARCHON;


    $query = "SELECT DISTINCT CollectionContentID,SubjectID FROM tblCollections_CollectionContentSubjectIndex";
    $result = $_ARCHON->mdb2->query($query);


    if(pear_isError($result))
    {
        trigger_error($result->getMessage(), E_USER_ERROR);
    }

    while($row = $result->fetchRow())
    {
        $arrCollectionContentSubjects [] = $row;

    }

    $result->free();

    return $arrCollectionContentSubjects;
}

function clean_up($CollectionContent) {

    array_walk_recursive ($CollectionContent, 'normalize');	
    return $CollectionContent;
}

function normalize($item,$key){

	$item->ID = strval($item->ID);
	$item->CollectionID = strval($item->CollectionID);
	$item->RootContentID = strval($item->RootContentID);
	$item->ParentID = strval($item->ParentID);
	$item->Enabled = strval($item->Enabled);
	$item->ContainsContent = strval($item->ContainsContent);
	$item->SortOrder = strval($item->SortOrder);
	$item->ParentID = strval($item->ParentID);
	
	if (isset($item->Subjects)){
        foreach ($item->Subjects as &$subject){  
            $subject = strval($subject);
         }
        } 
        
    if (isset($item->Creators)){
        foreach ($item->Creators as &$creator){  
            $creator = strval($creator);
         }
        } 

	if (isset($item->LevelContainer)) {
 		if ($item->LevelContainer->IntellectualLevel == "1" && $item->LevelContainer->PhysicalContainer== "0") {
		$item->ContentType = "1";
		}
		
		elseif($item->LevelContainer->IntellectualLevel == "0" && $item->LevelContainer->PhysicalContainer== "1") {
		$item->ContentType = "2";
		}
		
		else  {
		$item->ContentType = "3";
		}

		
		if ($item->LevelContainer->IntellectualLevel == "0" && $item->LevelContainer->PhysicalContainer == "1")  {  // second test allows us to account for case where both are false in the else clause below
			$item->UniqueID = "" ;
			$item->EADLevel = "";
      		$item->OtherLevel = "";
      		$item->ContainerTypeID = $item->LevelContainerID;
			//$item->ContainerType = $item->LevelContainer->LevelContainer;  //don't need the string value
			$item->ContainerIndicator = $item->LevelContainerIdentifier ;
		}
		
		else			//expose ONLY levels of description if intellectual is true and physical is false, otherwise, expose everything
		{
			$item->UniqueID = $item->LevelContainer->LevelContainer . " " . $item->LevelContainerIdentifier ; 
      		$temp = strtolower($item->LevelContainer->EADLevel);
      	
      		if ($temp == "")  {  //normalize the EAD levels recorded by users, where possible.
      			$item->EADLevel = "other level";
      			$item->OtherLevel = "undefined";
      			}
      		elseif ($temp <> "series" && $temp <> "file" && $temp <> "subseries" && $temp <> "item" && $temp <> "class" && $temp <> "collection" && $temp <> "fonds" && $temp <> "recordgrp" && $temp <> "subfonds" && $temp <> "subgrp" && $temp <> "other level" && $temp <> "other_unmapped") {
      			$item->EADLevel = "otherlevel";
      			$item->OtherLevel = strtolower($item->LevelContainer->EADLevel);
      			}  	
      		else {
      			$item->EADLevel = strtolower($item->LevelContainer->EADLevel);
      			$item->OtherLevel = ""; 
				}
		
			if (($item->LevelContainer->IntellectualLevel == "0" && $item->LevelContainer->PhysicalContainer == "0") || $item->LevelContainer->PhysicalContainer == "1") {  // plop in containter type in case of user error in not marking either physical or intellectual
				$item->ContainerTypeID = $item->LevelContainerID ;
				//$item->ContainerType = $item->LevelContainer->LevelContainer ;  //don't need the string value
				$item->ContainerIndicator = $item->LevelContainerIdentifier ;				
			}
			else  //leave containter type id and indicator empty ONLY if intellectual is true and physical is false
			{
				$item->ContainerTypeID = "" ;
				//$item->ContainerType = "" ;   //don't need the string value
				$item->ContainerIndicator ="" ;
			}
		}	
	}
	
	unset($item->LevelContainer);
    unset($item->Collection);
    unset($item->LevelContainerIdentifier);
    unset($item->LevelContainerID);
    unset($item->Parent);
    unset($item->Content);
    unset($item->DigitalContent);
    unset($item->ToStringFields); 
     if (isset($item->UserFields)){
         foreach ($item->UserFields as $UserField){
         	  $UserField->ID = strval($UserField->ID);
              unset($UserField->ContentID);
              unset($UserField->ToStringFields);
              unset($UserField->Content);	     
              $UserField->NoteType = $UserField->EADElement->EADTag;
              $UserField->Label = $UserField->Title;
              $UserField->Content = $UserField->Value;
        	  unset($UserField->EADElementID);
        	  unset($UserField->EADElement);
        	  unset($UserField->Title);
        	  unset($UserField->Value);
         }
    $item->Notes = $item->UserFields; 
    } 
	unset($item->UserFields);
}

function objectToArray( $object ) {
    if( !is_object( $object ) && !is_array( $object ) ) {
        return $object;
    }
    if( is_object( $object ) ) {
        $object = (array) $object;
    }
    return array_map( 'objectToArray', $object );
}

function myutf8_encode (&$value) {
	$value = utf8_encode($value);
}

?>
