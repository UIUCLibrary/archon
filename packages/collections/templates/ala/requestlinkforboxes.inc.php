<?php
/** Add request links to each row with the container info.**/
                     if(!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ)){
                        if(!$_ARCHON->config->RequestHasConsistentLocation and $requestLink){
                        echo("<a href='" . $requestLink);
                        
                        //add on the location code based on the location array, or the default if location id not in the array
                        if($_ARCHON->config->RequestVarLocation){
                           if($_ARCHON->config->RequestLinkLocationList[$loc->LocationID]) {
                              echo($_ARCHON->config->RequestVarLocation . $_ARCHON->config->RequestLinkLocationList[$loc->LocationID]);
                           } elseif($_ARCHON->config->RequestDefaultLocation) {
                              echo($_ARCHON->config->RequestVarLocation .  $_ARCHON->config->RequestDefaultLocation);
                           }
                        }
                        
                        //send info about which boxes the request was made from
                        if($_ARCHON->config->RequestVarBoxes){
                           echo($_ARCHON->config->RequestVarBoxes . $loc->Content);
                           //also send the extent here
                           if(isset($loc->Extent) && $loc->Extent > 0){
                              echo(" (".preg_replace('/\.(\d)0/', ".$1", $loc->getString('Extent')));
                              if($loc->ExtentUnitID){
                                 if(!$loc->ExtentUnit)
                                 {
                                    $loc->ExtentUnit = New ExtentUnit($loc->ExtentUnitID);
                                    $loc->ExtentUnit->dbLoad();
                                 }
                                 echo(" ".strtolower($loc->ExtentUnit->toString()). ")");
                              }
                           }
                        }

                        //if the shelf value is a barcode, then send the box number and barcode through the link too
                        if($loc->Shelf){
                           $testBarcode = trim((string) $loc->Shelf);
                           
                           if(is_numeric($testBarcode) AND strlen($testBarcode)==14){
                              if($_ARCHON->config->RequestVarBox){
                                 echo($_ARCHON->config->RequestVarBox . $loc->Content);
                              }
                              if($_ARCHON->config->RequestVarBarcode){
                                 echo($_ARCHON->config->RequestVarBarcode . $loc->Shelf);
                              }
                           }
                         }
                        
                        echo("' target='_blank' class='request-button'>");
                        //echo($_ARCHON->config->RequestLinkText ? $_ARCHON->config->RequestLinkText : "Submit request");
                        $firstCharContent = substr($loc->Content,1);
                        if(is_numeric($loc->Content) || is_numeric($firstCharContent)){
                           echo("Request Box ");
                        } else {
                           echo("Request ");
                        }
                        echo(($loc->Content));
                        echo("</a>");
					       }
                     }
?>