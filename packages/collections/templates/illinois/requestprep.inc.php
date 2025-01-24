<?php
/** Gather text to build a request link **/

	$requestTitle = ($objCollection->Title) ? $objCollection->Title : "";
	$requestDates = ($objCollection->InclusiveDates) ? ", ".$objCollection->InclusiveDates : "";
	$requestTitle = urlencode($requestTitle . $requestDates);

	$requestIdentifier = ($objCollection->Classification) ? $objCollection->Classification->toString(LINK_NONE, true, false, true, false)."/".$objCollection->getString('CollectionIdentifier') : $objCollection->getString('CollectionIdentifier');
	$requestIdentifier = "RS ".$requestIdentifier;

	$requestExtent = ($objCollection->Extent) ? preg_replace('/\.(\d)0/', ".$1", $objCollection->getString('Extent')) . " " . $objCollection->getString('ExtentUnit') : "";
	$requestExtent .= ($objCollection->AltExtentStatement) ? "; ".$objCollection->AltExtentStatement : "";
	
	$requestRestrictions = ($objCollection->AccessRestrictions) ? "Access restrictions: " . strip_tags($objCollection->getString('AccessRestrictions')) : "";
	
	if($objCollection->MaterialType){
		$requestMaterialType = ($_ARCHON->config->RequestMaterialTypeList[$objCollection->MaterialType]) ? $_ARCHON->config->RequestMaterialTypeList[$objCollection->MaterialType] : $objCollection->MaterialType;
	}

	$requestSite = $_ARCHON->config->RequestLinkSiteValue;
	if($_ARCHON->config->AlternativeSiteForRepository[$objCollection->RepositoryID]){
		$requestSite = $_ARCHON->config->AlternativeSiteForRepository[$objCollection->RepositoryID];
	}


/*Build reading room request link */
if($_ARCHON->config->AddRequestLink and $_ARCHON->config->RequestURL and !$_ARCHON->config->ExcludeRequestLink[$objCollection->RepositoryID]) 
{
	$requestBaseLink =	$_ARCHON->config->RequestURL;//defined in config file

	//concatenate the field names (URL parameters) and metadata from the collection to form the request link
	$requestLink = $requestBaseLink;
	if($_ARCHON->config->RequestVarTitle){
		$requestLink .= $_ARCHON->config->RequestVarTitle . $requestTitle;
	}
	if($_ARCHON->config->RequestVarIdentifier) {
		$requestLink .= $_ARCHON->config->RequestVarIdentifier . $requestIdentifier;
	}
	if($_ARCHON->config->RequestVarDates) {
		$requestLink .= $_ARCHON->config->RequestVarDates;
		$requestLink .= ($objCollection->InclusiveDates) ? $objCollection->InclusiveDates : "";
	}
	if($_ARCHON->config->RequestVarExtent) {
		$requestLink .= $_ARCHON->config->RequestVarExtent . $requestExtent;
	}
	
	if($_ARCHON->config->RequestVarRestrictions) {
		$requestLink .= $_ARCHON->config->RequestVarRestrictions . $requestRestrictions;
	}
	
	if($_ARCHON->config->RequestVarMaterialType) {
		$requestLink .= $_ARCHON->config->RequestVarMaterialType . $requestMaterialType;
	}
	
	if($_ARCHON->config->RequestHasConsistentLocation) {
		if($_ARCHON->config->RequestVarLocation and $_ARCHON->config->RequestDefaultLocation){
			$requestLink .= $_ARCHON->config->RequestVarLocation . $_ARCHON->config->RequestDefaultLocation;
		}
	}

	//save a version of the request link before adding the site code for use with box requests
	$requestLinkNoSiteCode = $requestLink;

	if($_ARCHON->config->RequestVarSite){
		$requestLink .= $_ARCHON->config->RequestVarSite . $requestSite;
	}
}

?>