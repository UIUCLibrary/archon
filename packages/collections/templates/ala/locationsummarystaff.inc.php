<?php
if(!empty($objCollection->LocationEntries) && !empty($_ARCHON->config->RequestLinkLocationList) && !empty($_ARCHON->config->PublicLocationInfoList))
{
      if(!empty($objCollection->LocationEntries))
      {
                  //create list of location codes
                  $uniqueLocations = array_unique($objCollection->LocationEntries);
                  $locationCodes = array();
                  foreach($uniqueLocations as $uloc){
                     $loccode = $_ARCHON->config->RequestLinkLocationList[$uloc->LocationID];
                     $locationCodes[] = $loccode;
                  }
                  $uniqueLocationCodes = array_unique($locationCodes);
                  if($_ARCHON->config->DisplayLocationCodesListForStaff){
                     echo("<p><strong>Location codes for this record series:</strong></p>");
                     echo("<ul>");            
                     foreach($uniqueLocationCodes as $ucode){
                        echo("<li>".$ucode."</li>");
                     }    
                     echo("</ul>");
                  }
                  ?>
                  
      <p class='ccardlabel' id='requestlocations'>Locations for this record series:</p>

<?php
         //create location summary array that collapses lines and sums them together
         //if the location, range, and section all match
         //[0] is content, [1] is location, [2] is range, [3] is section,
         //[4] is shelf or summary note, [5] is extent, [6] is extent unit
         $LocationSummaryArray =array();

         foreach ($objCollection->LocationEntries as $loc)
         {
            $currentContent=$loc->getString('Content');

            if($loc->LocationID && !$loc->Location)
            {
               $loc->Location = New Location($loc->LocationID);
               $loc->Location->dbLoad();
            }
            
            $currentLocation = $loc->Location->toString();

            if(isset($loc->RangeValue)){
               $currentRangeValue = $loc->getString('RangeValue');
            }

            if(isset($loc->Section)){
               $currentSection = $loc->getString('Section');
            }

            if(isset($loc->Shelf)){
                 $currentShelf = $loc->getString('Shelf');
            }

            if(isset($loc->Extent)){
                 $currentExtentValue = $loc->getString('Extent');
            }

            if($loc->ExtentUnitID){
               if(!$loc->ExtentUnit)
               {
                  $loc->ExtentUnit = New ExtentUnit($loc->ExtentUnitID);
                  $loc->ExtentUnit->dbLoad();
               }
               $currentExtentUnit = $loc->ExtentUnit->toString();
            }

            if(empty($LocationSummaryArray)){
               $LocationSummaryArray[$currentContent] = [$currentContent,$currentLocation, $currentRangeValue,$currentSection, $currentShelf, $currentExtentValue,$currentExtentUnit,1];
               $prevContent = $currentContent;
            } else {
               if($compareLocation == $currentLocation && $compareRangeValue == $currentRangeValue && $compareSection == $currentSection){
                  $LocationSummaryArray[$prevContent][0]=$prevContent." to ".$currentContent;
                  $LocationSummaryArray[$prevContent][4]="multiple";//set shelf (barcode) value
                  if($LocationSummaryArray[$prevContent][6]==$currentExtentUnit){
                     $LocationSummaryArray[$prevContent][5]+=$currentExtentValue;
                  } else {
                     $LocationSummaryArray[$prevContent][5]="error calculating";//set extent value
                     $LocationSummaryArray[$prevContent][6]="multiple";//set extent unit
                  }
                  $LocationSummaryArray[$prevContent][7]++;
               } else {
                  $LocationSummaryArray[$currentContent] = [$currentContent,$currentLocation, $currentRangeValue,$currentSection, $currentShelf, $currentExtentValue,$currentExtentUnit,1];
                  $prevContent = $currentContent;
               }
            }

            $compareLocation = $currentLocation;
            $compareRangeValue = $currentRangeValue;
            $compareSection = $currentSection;
            $compareShelf = $currentShelf;
            $compareExtentUnit = $currentExtentUnit;

			}

?>
            <table id='locationsummarytable' border='1'>
              <thread><tr>
                 <th>Content</th>
                 <th>Location</th>
                 <th>Range</th>
                 <th>Section</th>
                 <th>Shelf/Barcode</th>
                 <th>Extent</th>
              </tr></thread>
              <tbody class='locationTableBody'>
        <?php 
               foreach ($LocationSummaryArray as $sumloc){
                  echo("<tr>");
                  echo("<td>".$sumloc[0]);//content
                  if($sumloc[7]>1){
                     echo("<br /> (".$sumloc[7]." location entries)</td>");
                  } else {
                     echo("</td>");
                  }
                  echo("<td>".$sumloc[1]."</td>");//location
                  echo("<td>".$sumloc[2]."</td>");//range
                  echo("<td>".$sumloc[3]."</td>");//section
                  echo("<td>".$sumloc[4]."</td>");//shelf or note
                  echo("<td>".$sumloc[5]." ".$sumloc[6]."</td>");//extent
                  echo("</tr>");
               } 
        ?>
                 </td></tbody>
              </tr>
           </table>
<?php
      }
   }
?>