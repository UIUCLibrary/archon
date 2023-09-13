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
                        if($_ARCHON->config->RequestLinkText) {
                           echo($_ARCHON->config->RequestLinkText);
                        }else {
                           echo("Submit request");
                        }
                        echo("</a>");
					       }
                     }
?>