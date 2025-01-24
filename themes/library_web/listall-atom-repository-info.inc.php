<?php
$arrRepositoriesForAtoM = array (
    1=>"University of Illinois Archives",
    2=>"Sousa Archives and Center for American Music",
);
 
 $unitSourcePrefix = "UA";
 $atomImportNested = false;
 $atomImportClassificationAsGenre = true;
 $defaultRepositoryForAtom = $arrRepositoriesForAtoM[1];


//for collection level records in Archon
$atomLevelDescription ="Record Series";

$arrExtentConversionForAtomBoxType = [
    'cubic feet' => 'box',
    'cubic foot' => 'box',
    'Cubic Feet' => 'box',
];
?>