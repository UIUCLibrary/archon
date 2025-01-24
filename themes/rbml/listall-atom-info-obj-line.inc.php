<?php

$arrDataLineForAtoM = [
    $legacyIdForAtoM,
    $parentIdForAtoM,
    $qubitParentSlugForAtoM,
    $accessionNumberForAtoM,
    $identifierForAtoM,
    $titleForAtoM,
    $levelOfDescriptionForAtoM,
    $extentAndMediumForAtoM,
    $repositoryForAtoM,
    $archivalHistoryForAtoM,
    $acquisitionForAtoM,
    $scopeAndContentForAtoM,
    $appraisalForAtoM,
    $accrualsForAtoM,
    $arrangementForAtoM,
    $accessConditionsForAtoM,
    $reproductionConditionsForAtoM,
    $languageForAtoM,
    $scriptForAtoM,
    $languageNoteForAtoM,
    $physicalCharacteristicsForAtoM,
    $findingAidsForAtoM,
    $locationOfOriginalsForAtoM,
    $locationOfCopiesForAtoM,
    $relatedUnitsOfDescriptionForAtoM,
    $publicationNoteForAtoM,
    $digitalObjectPathForAtoM,
    $digitalObjectURIForAtoM,
    $generalNoteForAtoM,
    $subjectAccessPointsForAtoM,
    $placeAccessPointsForAtoM,
    $nameAccessPointsForAtoM,
    $genreAccessPointsForAtoM,
    $descriptionIdentifierForAtoM,
    $institutionIdentifierForAtoM,
    $rulesForAtoM,
    $descriptionStatusForAtoM,
    $levelOfDetailForAtoM,
    $revisionHistoryForAtoM,
    $languageOfDescriptionForAtoM,
    $scriptOfDescriptionForAtoM,
    $sourcesForAtoM,
    $archivistNoteForAtoM,
    $publicationStatusForAtoM,
    $physicalObjectNameForAtoM,
    $physicalObjectLocationForAtoM,
    $physicalObjectTypeForAtoM,
    $alternativeIdentifiersForAtoM,
    $alternativeIdentifierLabelsForAtoM,
    $eventDatesForAtoM,
    $eventTypesForAtoM,
    $eventStartDatesForAtoM,
    $eventEndDatesForAtoM,
    $eventActorsForAtoM,
    $eventActorHistoriesForAtoM,
    $cultureForAtoM,
];

if(!$_REQUEST['striptags']){
    foreach($arrDataLineForAtoM as $keyAtoM => $dataPointForAtoM){
        //only remove the odd tabs that Archon adds to successive paragraphs
        $editedValueForAtoM = str_replace("</p><p>\t","</p><p>",$dataPointForAtoM);

        $markdown = new HTML_To_Markdown($editedValueForAtoM);
        $editedValueForAtoM = $markdown->output();
        
        //convert underlines into italics
        $editedValueForAtoM = preg_replace('/<\s*span\s+style="text-decoration:underline"\s*>([^<]*?)<\/span>/', '_$1_', $editedValueForAtoM);
        //strip any remaining unconverted tags
        $editedValueForAtoM = strip_tags($editedValueForAtoM);

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
?>