<?php
$arrRepositoriesForAtoM = array (
    1=>"American Library Association Archives",
    3=>"American Association of Law Libraries Archives",
    4=>"Art Libraries Society of North America Archives",
);
 
 $unitSourcePrefix = "ALAA";
 $atomImportClassificationAsGenre = true;
 $atomImportNested = false;

 $defaultRepositoryForAtom = $arrRepositoriesForAtoM[1];


//for collection level records in Archon
$atomLevelDescription ="Record Series";

$arrExtentConversionForAtomBoxType = [
    'cubic feet' => 'box',
    'cubic foot' => 'box',
    'Cubic Feet' => 'box',
];
?>