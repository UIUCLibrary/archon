<?php
//echo("<span class='ccardlabel' id='requestlocations'>Locations for this collection:</span><br/>");
if(!empty($objCollection->LocationEntries))
  {
   if(count($objCollection->LocationEntries)>1){
      $multipleLocationEntries=true;
   }else {
      $multipleLocationEntries=false;
   }

   if(!empty($_ARCHON->config->RequestLinkLocationList) && !empty($_ARCHON->config->PublicLocationInfoList)){
         //create list of locations
         $uniqueLocations = array_unique($objCollection->LocationEntries);
         $locationCodes = array();
         foreach($uniqueLocations as $uloc){
            $loccode = $_ARCHON->config->RequestLinkLocationList[$uloc->LocationID];
            $locationCodes[] = $loccode;
         }
         $uniqueLocationCodes = array_unique($locationCodes);
         $selectLocation = "<label for='locations'>Filter by location:</label><select name='locations' id='locations' aria-describedby='location-table-note'><option selected value> </option>";
         echo("<ul class='locationsummary'>");            
            foreach($uniqueLocationCodes as $ucode){
               $loctext = $_ARCHON->config->PublicLocationInfoList[$ucode];
               if ($loctext)
               {
                  echo("<li>".$loctext."</li>");
                  $selectLocation .= "<option value='".$ucode."'>".$loctext."</option>";
               }
                     
               else
               {
               echo("<li>Unidentified location.</li>");
               }
            }    
         echo("</ul>");
         if($multipleLocationEntries && $requestLink){
               //the select location feature only works in the modal, so it needs to be hidden from the public if it appears in the control card sidebar table and not the modal (as is the case for the staff only optoin)
               if(!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ)) {
                  echo($selectLocation."</select><br /><br />");
               }

         }

      }//end if for the custom public locations info
      
      if($multipleLocationEntries && $requestLink){
            ?>
            <label for="filterBy">Filter by box: </label><input class="locationFilter" id="filterBy" type="text" aria-describedby="location-table-note">
      <?php
      echo("<p id='location-table-note'><i>Rows will be filtered from the table below as selections are made</i></p>");
         }//end if for filter by box
      }//end if for location entries in the collection object
         ?>


         <table id='locationtable' border='1' style='margin-left:0'>
            <thread><tr>

               <th style='width:350px'>Service Location</th>
               <th style='width:150px'>Boxes</th>
               <?php 
               if(!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ)){
                  echo("<th style='width:150px'>Request</th>");
               }
               ?>
            </tr></thread>


                  <tbody class='locationTableBody'>

   <?php
      if(!empty($objCollection->LocationEntries))
      {
                  $numberRequestableBoxes = 0;
                  foreach ($objCollection->LocationEntries as $loc)
                  {
                     //test if the location entry is a item/container with a barcode
                     $requestableBox = false;
                     if($loc->Shelf){
                        $testBarcode = trim((string) $loc->Shelf);
                        
                        if(is_numeric($testBarcode) AND strlen($testBarcode)==14){
                           $requestableBox = true;
                           $numberRequestableBoxes++;
                        }
                     }
                     //option to override in config file
                     if($_ARCHON->config->RequestBoxesWithoutBarcodes){
                        $requestableBox = true;
                     }
                     //only generate if there is a barcode for the box or if this is overridden
                     if($requestableBox){
                        echo("<tr>");
                        if(!empty($_ARCHON->config->RequestLinkLocationList) && !empty($_ARCHON->config->PublicLocationInfoList)){
                           $locationcode = $_ARCHON->config->RequestLinkLocationList[$loc->LocationID];
                           $locationtext = $_ARCHON->config->PublicLocationInfoList[$locationcode];
                        }elseif(isset($_ARCHON->config->RequestDefaultLocation)){
                           $locationcode = $_ARCHON->config->RequestDefaultLocation;
                           $locationtext = $_ARCHON->Repository->Name . (isset($_ARCHON->Repository->Address) ? "<br />". $_ARCHON->Repository->Address : "");
                        }

                        if ($locationtext)
                        {
                           echo("<td>".$locationtext."</td>");
                        }
                        
                        else
                        {
                           echo("<td>Location information not found. Please contact us for assistance.</td>");
                        }
                  
                        echo ("<td>". $loc->Content);
                      

/** Add request links to each row with the container info.**/
                     if(!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ)){
                        if(!$_ARCHON->config->RequestHasConsistentLocation and $requestLink){
                        echo("<td>");
                        include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/requestlinkforboxes.inc.php");
                        }
                        echo("</td>");
                     }
/**end section for request links **/

					      echo("</td></tr>");
                  }
					}
               //if there are more lines of the location table than requestable boxes
               $numberNonRequestableBoxes = count($objCollection->LocationEntries) - $numberRequestableBoxes;
               if(($numberRequestableBoxes < 1) OR ($numberNonRequestableBoxes > 0)){
                  echo ('</tbody></table>');
                  echo ("<table id='locationtable' border='1' style='margin-left:0'><tbody>");
                        echo("<tr>");
                        $locationcode = $_ARCHON->config->RequestDefaultLocation;
                        $locationtext = $_ARCHON->Repository->Name . (isset($_ARCHON->Repository->Address) ? "<br />". $_ARCHON->Repository->Address : "");
                        
                        
                        if ($locationtext)
                        {
                           echo("<td style='width:350px'>".$locationtext."</td>");
                        }
                        else
                        {
                           echo("<td style='width:350px'>Location information not found. Please contact us for assistance.</td>");
                        }
                        
                  
                        echo ("<td style='width:150px'>");
                        //do we need a message here?
                        if($numberRequestableBoxes > 0) {
                           //echo("Box not listed above<br />");
                           echo("Other<br />");
                        } else {
                           echo("");
                        }
                     
                     
                     if(!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ)){
                        if(!$_ARCHON->config->RequestHasConsistentLocation and $requestLink){
                           echo("<td style='width:150px'>");
                           echo("<a href='" . $requestLink . "' target='_blank' class='request-button'>");
			                  //echo($_ARCHON->config->RequestLinkText ? $_ARCHON->config->RequestLinkText : "Submit request");
                           echo("Request other content not listed");
                           echo("</td>");
                        }
                     }
                     
					      echo("</td></tr>");
               }
            echo ('</tbody></table>');
         }

         else
         {
            //echo("<div id='ccardstaff'><span class='ccardlabel' id='requestlocations'>Service Location:</span><br/>Please <a href='mailto:ihlc@library.illinois.edu'>contact the IHLC</a> for assistance. </span></div>");
            $locationtext = $_ARCHON->Repository->Name . (isset($_ARCHON->Repository->Address) ? "<br />". $_ARCHON->Repository->Address : "");
            echo("<td>".$locationtext."</td>");
            echo("<td></td>");//to skip boxes column of table
            echo("<td>");
            if(!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ)){
               if(!$_ARCHON->config->RequestHasConsistentLocation and $requestLink){
                  //echo("<br/>");
                  echo("<a href='" . $requestLink . "' target='_blank'>");
                  echo($_ARCHON->config->RequestLinkText ? $_ARCHON->config->RequestLinkText : "Submit request");
               }
            }
            echo("</td>");
            
            echo("</tr>");
            echo ('</tbody></table>');

         }
		 ?>