<?php
if(!empty($objCollection->LocationEntries) && !empty($_ARCHON->config->RequestLinkLocationList) && !empty($_ARCHON->config->PublicLocationInfoList))
               {
                  ?>
      <span class='ccardlabel' id='requestlocations'>Locations for this record series:</span><br/>

         <?php
         //create list of locations
         $uniqueLocations = array_unique($objCollection->LocationEntries);
         $locationCodes = array();
         foreach($uniqueLocations as $uloc){
            $loccode = $_ARCHON->config->RequestLinkLocationList[$uloc->LocationID];
            $locationCodes[] = $loccode;
         }
         $uniqueLocationCodes = array_unique($locationCodes);
         $selectLocation = "<label for='locations'>Filter by location: </label><select name='locations' id='locations' aria-describedby='location-table-note'><option selected value>All locations</option>";
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
         if(count($objCollection->LocationEntries)>1){
            if($requestLink){
               //the select location feature only works in the modal, so it needs to be hidden from the public if it appears in the control card sidebar table and not the modal (as is the case for the staff only option)
               if(!$_ARCHON->config->ExcludeRequestLink[$objCollection->RepositoryID] AND !$_ARCHON->config->ExcludePublicRequestLink[$objCollection->RepositoryID] AND (!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ))) {
                  echo($selectLocation."</select><br /><br />");
               }
         ?>
               <label for="filterBy">Filter by box: </label><input class="locationFilter" id="filterBy" type="text" aria-describedby="location-table-note">
         <?php
            echo("<p id='location-table-note'><i>Rows will be filtered from the table below as selections are made</i></p>"); 
             }
         }
         ?>
         <table id='locationtable' border='1' style='margin-left:0'>
            <thread><tr>

               <th style='width:400px'>Service Location</th>
               <th style='width:100px'>Boxes</th>
               <?php 
               if(!$_ARCHON->config->ExcludeRequestLink[$objCollection->RepositoryID] AND !$_ARCHON->config->ExcludePublicRequestLink[$objCollection->RepositoryID] AND (!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ))){
                  echo("<th style='width:150px'>Request</th>");
               }
               ?>
            </tr></thread>


                  <tbody class='locationTableBody'>
                  <?php
                  foreach ($objCollection->LocationEntries as $loc)
                  {
                     echo("<tr>");
                     $locationcode = $_ARCHON->config->RequestLinkLocationList[$loc->LocationID];
                     $locationtext = $_ARCHON->config->PublicLocationInfoList[$locationcode];

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
            echo ('</tbody></table>');
         }

         else
         {
            echo("<div id='ccardstaff'><span class='ccardlabel' id='requestlocations'>Service Location:</span><br/>Please <a href='http://archives.library.illinois.edu/ala/contact-us/'>contact the Archives</a> for assistance. </span></div>");

         }
		 ?>