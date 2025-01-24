<?php
        //include file to generate collection or record series stub record for parent id feature to work
        //uses $objCollection from other file
        
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
						$parentIdForAtoM = $unitSourcePrefix."-c-".$objCollection->ClassificationID;
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

                    /** [levelOfDescription] Archon Fields: ; Notes: collection for ihlc and rbml, record series for archives and ala*/
			        $levelOfDescriptionForAtoM = $atomLevelDescription;

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

					/** [culture] Archon Fields: ; Notes: en (for english)*/
					$cultureForAtoM="en";

                    include 'listall-atom-info-obj-line.inc.php';
                    ?>