<?php
$arrRepositoriesForAtoM = array (
    3=>"Rare Book and Manuscript Library",
);
 
 $unitSourcePrefix = "RBML";
 $atomImportNested = false;
 $defaultRepositoryForAtom = $arrRepositoriesForAtoM[3];


//for collection level records in Archon
$atomLevelDescription ="Collection";

$arrExtentConversionForAtomBoxType = [
    'cubic feet' => 'box',
    'cubic foot' => 'box',
    'Cubic Feet' => 'box',
    'Linear Feet'=> 'box',
    'Boxes'=> 'box',
];
?>