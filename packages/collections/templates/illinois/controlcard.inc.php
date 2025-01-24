<?php
/**
 * Control Card template for "illinois" templateset
 *
 * The variable:
 *
 *  $objCollection
 *
 * is an instance of a Collection object, with its properties
 * already loaded when this template is referenced.
 *
 * Refer to the Collection class definition in lib/collection.inc.php
 * for available properties and methods.
 *
 * The Archon API is also available through the variable:
 *
 *  $_ARCHON
 *
 * Refer to the Archon class definition in lib/archon.inc.php
 * for available properties and methods.
 *
 * @package Archon
 * @author Chris Rishel, Chris Prom, Paul Sorensen
 */
isset($_ARCHON) or die();

echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "</h1>\n");
//echo '<pre>'; var_dump($objCollection); echo '</pre>';

include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/requestprep.inc.php");
?>
  
<div id='ccardleft'>
   <div id="ccardpublic" class='mdround'>
      <?php
      if($objCollection->Title)
      {
         ?>
         <div class='ccardcontent'><span class='ccardlabel'>Title:</span> <?php echo($objCollection->toString()); ?></div>
         <?php
      }

      if($objCollection->Classification)
      {
         ?>
         <div class='ccardcontent'><span class='ccardlabel'>Series Number:</span> <?php echo($objCollection->Classification->toString(LINK_NONE, true, false, true, false)); ?>/<?php echo($objCollection->getString('CollectionIdentifier')); ?></div>
         <?php
      }
      if($objCollection->AcquisitionDate || $objCollection->AccrualInfo)
      {
         ?>
         <div class='ccardcontent'><span class='ccardlabel'>Acquired:</span>
         <?php
         if($objCollection->AcquisitionDate)
         {
            echo($objCollection->getString('AcquisitionDateMonth') . '/' . $objCollection->getString('AcquisitionDateDay') . '/' . $objCollection->getString('AcquisitionDateYear') . ".  ");
         }
         if($objCollection->AccrualInfo)
         {
            echo($objCollection->getString('AccrualInfo'));
         }
         ?>
         </div>
            <?php
         }
         if($objCollection->Extent)
         {
            ?>
         <div class='ccardcontent'><span class='ccardlabel'>Volume:</span> <?php echo(preg_replace('/\.(\d)0/', ".$1", $objCollection->getString('Extent'))) . " " . $objCollection->getString('ExtentUnit'); ?>
         </div>
   <?php
}

if($objCollection->AltExtentStatement)
{
   ?>

         <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('CollectionAltExtent'); return false;"><img id='CollectionAltExtentImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' />
   <?php
   echo ("More Extent Information");
   ?>
               </a></span>
            <div class='ccardshowlist' style='display:none' id='CollectionAltExtentResults'>
   <?php echo($objCollection->getString('AltExtentStatement')); ?>
            </div>
         </div>
   <?php
}

if($objCollection->PredominantDates)
{
   ?>
         <div class='ccardcontent'><span class='ccardlabel'>Predominant Dates:</span> <?php echo($objCollection->getString('PredominantDates')); ?></div>
         <?php
      }

      if($objCollection->Arrangement)
      {
         ?>

         <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('CollectionArrangement'); return false;"><img id='CollectionArrangementImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' />
   <?php
   echo ("Arrangement");
   ?>
               </a></span>
            <div class='ccardshowlist' style='display:none' id='CollectionArrangementResults'>
   <?php echo($objCollection->getString('Arrangement')); ?>
            </div>
         </div>
   <?php
}

if($objCollection->Abstract)
{
   ?>

         <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('CollectionAbstract'); return false;"><img id='CollectionAbstractImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' />
   <?php
   echo ("Abstract");
   ?>
               </a></span>
            <div class='ccardshowlist' style='display:none' id='CollectionAbstractResults'>
   <?php echo($objCollection->getString('Abstract')); ?>
            </div>
         </div>
   <?php
}


if(!empty($objCollection->Creators))
{
   ?>
         <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('creators'); return false;"><img id='creatorsImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon'/> Creator(s)</a></span><br/>
            <div class='ccardshowlist' style="display: none;" id="creatorsResults"><?php echo($_ARCHON->createStringFromDigitalContentArray($objCollection->Creators, "<br/>\n", LINK_TOTAL)); ?></div></div>

   <?php
}

if($objCollection->PrimaryCreator->BiogHist)
{
   ?>

         <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('BiogHist'); return false;"><img id='BiogHistImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon'/>

   <?php
   if(trim($objCollection->PrimaryCreator->CreatorType->CreatorType) == "Family Name")
   {
      echo ("Family History");
   }
   elseif(trim($objCollection->PrimaryCreator->CreatorType->CreatorType) == "Corporate Name")
   {
      echo ("Administrative History of Creating Unit");
   }
   else
   {
      echo ("Biographical Note");
   }
   ?>
               </a></span><br/>
            <div class='ccardshowlist' style='display:none' id='BiogHistResults'>

   <?php
   echo($objCollection->PrimaryCreator->getString('BiogHist'));
   if($objCollection->PrimaryCreator->Sources)
   {
      echo("<br/><br/><span class='bold'>Sources:</span><br/>" . $objCollection->PrimaryCreator->getString('Sources'));
   }
   ?>
            </div>

         </div>
   <?php
}


if($objCollection->AccessRestrictions)
{
   ?>

         <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('restriction'); return false;"><img id='restrictionImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon'/> Access Restrictions</a></span><br/>
            <div class='ccardshowlist' style="display: none;" id="restrictionResults"><?php echo($objCollection->getString('AccessRestrictions')); ?></div>
         </div>
   <?php
}

if(!empty($objCollection->Subjects))
{
   $GenreSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Genre/Form of Material');

   foreach($objCollection->Subjects as $objSubject)  //filters out papers as genre since it is so common
   {
      if($objSubject->SubjectTypeID == $GenreSubjectTypeID && preg_replace("/<a.*<\/a>/", "", $objSubject) != "Papers")
      {
         $arrGenres[$objSubject->ID] = $objSubject;
      }
      elseif($objSubject != "Papers")
      {
         $arrSubjects[$objSubject->ID] = $objSubject;
      }
   }

   if(!empty($arrSubjects))
   {
      ?>
            <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('subjects'); return false;"><img id='subjectsImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon'/> Subjects</a> <span style='font-size:80%'>(links to similar materials)</span></span><br/>
               <div class='ccardshowlist' style='display: none' id='subjectsResults'><?php echo($_ARCHON->createStringFromSubjectArray($arrSubjects, "<br/>\n", LINK_TOTAL)); ?></div>
            </div>
      <?php
   }
   if(!empty($arrGenres))
   {
      ?>
            <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('genres'); return false;"><img id='genresImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon'/> Special Formats</a> <span style='font-size:80%'>(links to similar genres)</span></span><br/>
               <div class='ccardshowlist' style='display: none' id='genresResults'><?php echo($_ARCHON->createStringFromSubjectArray($arrGenres, "<br/>\n", LINK_TOTAL)); ?></div>
            </div>
      <?php
   }
}

if(!empty($objCollection->Languages))  //show only non-english
{
   if(isset($objCollection->Languages[2081])){
      unset($objCollection->Languages[2081]);
   }
   
   if(!empty($objCollection->Languages))
   {
      ?>
            <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('langs'); return false;"><img id='langsImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon'/> Special Languages</a><span style='font-size:80%'> (non-English)</span></span><br/>
               <div class='ccardshowlist' style='display: none' id='langsResults'><?php echo($_ARCHON->createStringFromLanguageArray($objCollection->Languages, "<br/>\n", LINK_TOTAL)); ?></div>
            </div>
            <?php
         }
      }

      if($objCollection->Books)
      {
         ?>
         <div class='ccardcontent'><span class='bold'><a href='#' onclick="toggleDisplay('LinkedBooks'); return false;"><img id='LinkedBooksImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' /> Books </a></span><br/>
            <div class='ccardshowlist' style='display: none' id='LinkedBooksResults'><?php echo($_ARCHON->createStringFromBookArray($objCollection->Books, "<br/>\n", LINK_TOTAL)); ?></div>
         </div>

         <?php
      }


if(!empty($objCollection->BiogHist) || !empty($objCollection->UseRestrictions) || !empty($objCollection->PhysicalAccess) || !empty($objCollection->TechnicalAccess) || !empty($objCollection->PhysicalAccessNote) || !empty($objCollection->TechnicalAccessNote) || (!empty($objCollection->AcquisitionSource) and $_ARCHON->Security->userHasAdministrativeAccess()) || (!empty($objCollection->AcquisitionMethod) and $_ARCHON->Security->userHasAdministrativeAccess()) || (!empty($objCollection->AppraisalInformation) and $_ARCHON->Security->userHasAdministrativeAccess()) || !empty($objCollection->OrigCopiesNote) || !empty($objCollection->OrigCopiesURL) || !empty($objCollection->RelatedMaterials) || !empty($objCollection->RelatedMaterialsURL) || !empty($objCollection->RelatedPublications) || !empty($objCollection->PreferredCitation) || !empty($objCollection->ProcessingInfo) || !empty($objCollection->RevisionHistory))
			
 			
//admin info exists
      {
         ?>

         <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('otherinformation'); return false;"><img id='otherinformationImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon'/> Administrative Information</a></span><br/>
            <div class='ccardshowlist' style='display:none' id='otherinformationResults'>

         <?php
         if($objCollection->BiogHist)
         {
            ?>

                  <div class='ccardcontent'><span class='ccardlabel'>Adiminsrative/Biographical History:</span>
                  <?php
                  echo($objCollection->getString('BiogHist'));
                  if($objCollection->BiogHistAuthor)
                  {
                     echo(" <span class='bold'> Note Author:</span> " . $objCollection->getString('BiogHistAuthor'));
                  }
                  ?>
                  </div>

                     <?php
                  }



                  if($objCollection->UseRestrictions)
                  {
                     ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Rights:</span> <?php echo($objCollection->getString('UseRestrictions')); ?></div>
                  <?php
               }

               if($objCollection->PhysicalAccess)
               {
                  ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Access Notes: </span><?php echo($objCollection->getString('PhysicalAccess')); ?></div>
                  <?php
               }

               if($objCollection->TechnicalAccess)
               {
                  ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Technical Notes: </span><?php echo($objCollection->getString('TechnicalAccess')); ?></div>

                  <?php
               }
				echo ($objCollection->AcquisitionSource);
               if ($objCollection->AcquisitionSource and $_ARCHON->Security->userHasAdministrativeAccess() || $objCollection->AcquisitionMethod)
               {
                  ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Acquisition Note: </span>
                  <?php
                  if($objCollection->AcquisitionSource && $_ARCHON->Security->userHasAdministrativeAccess())
                  {
                     echo("&nbsp;<em>Source:</em> " . $objCollection->getString('AcquisitionSource') . ".<br/>");
                  }
                  if($objCollection->AcquisitionMethod)
                  {
                     echo($objCollection->getString('AcquisitionMethod'));
                  }
                  ?>
                  </div>
                     <?php
              }
	
                  if($objCollection->AppraisalInfo and $_ARCHON->Security->userHasAdministrativeAccess())
                  {
                     ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Appraisal Notes:</span> <?php echo($objCollection->getString('AppraisalInfo')); ?></div>
                  <?php
               }

               if($objCollection->OrigCopiesNote || $objCollection->OrigCopiesURL)
               {
                  ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Other Formats:</span>      
                  <?php
                  if($objCollection->OrigCopiesNote)
                  {
                     echo($objCollection->getString('OrigCopiesNote'));
                  }
                  if($objCollection->OrigCopiesURL)
                  {
                     echo("<br/>For more information please see <a href='{$objCollection->getString('OrigCopiesURL')}'>{$objCollection->getString('OrigCopiesURL')}</a>.");
                  }
                  ?>
                  </div>
                     <?php
                  }

                  if($objCollection->RelatedMaterials || $objCollection->RelatedMaterialsURL)
                  {
                     ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Related Materials:</span>      
                  <?php
                  if($objCollection->RelatedMaterials)
                  {
                     echo($objCollection->getString('RelatedMaterials'));
                  }
                  if($objCollection->RelatedMaterialsURL)
                  {
                     echo("<br/>For more information please see <a href='{$objCollection->getString('RelatedMaterialsURL')}'>{$objCollection->getString('RelatedMaterialsURL')}</a>.");
                  }
                  ?>
                  </div>
                     <?php
                  }


                  if($objCollection->RelatedPublications)
                  {
                     ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Related Publications:</span> <?php echo($objCollection->getString('RelatedPublications')); ?></div>
                  <?php
               }


               if($objCollection->PreferredCitation)
               {
                  ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Preferred Citation:</span> <?php echo($objCollection->getString('PreferredCitation')); ?></div>
                  <?php
               }


               if($objCollection->ProcessingInfo)
               {
                  ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Processing Note</span>: <?php echo($objCollection->getString('ProcessingInfo')); ?></div>
                  <?php
               }


               if($objCollection->RevisionHistory)
               {
                  ?>
                  <div class='ccardcontent'><span class='ccardlabel'>Finding Aid Revisions:</span> <?php echo($objCollection->getString('RevisionHistory')); ?></div>
                  <?php
               }
               echo("</div>");  // ending ccardshowlist
               echo("</div>");  // ending admininfo content
            }

            if(!empty($arrDisplayAccessions))
            {
               ?>
               <div class='ccardcontent'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('accessions'); return false;"><img id='subjectsImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' /> Unprocessed Materials<?php
            if($_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ))
            {
               echo (" and Processed Accessions");
            }
               ?></a></span><br/>

               <?php
               echo ("<div class='ccardshowlist' style='display: none' id='accessionsResults'>");

               foreach($arrDisplayAccessions as $objAccession)
               {
                  echo($objAccession->toString(LINK_EACH) . "<br/>\n");
                  $ResultCount++;
               }
               ?>

               </div>
            </div>
                  <?php
               }



               echo("</div>"); //ending public div



               if($_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ))
               {
                  echo("<div id='ccardstaff' class='mdround'><div class='ccardstafflabel'>Staff Information</div>");
                  //use the staff location summary table if it isn't a repository excluded from the request link system
                  //use the staff location table if the repository is excluded from the request link system
                  if(!empty($objCollection->LocationEntries))
                  {
                     if(!$_ARCHON->config->ExcludeRequestLink[$objCollection->RepositoryID] AND !$_ARCHON->config->ExcludePublicRequestLink[$objCollection->RepositoryID]AND (!$_ARCHON->config->StaffOnlyRequestLink OR $_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ))){
                        include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/locationsummarystaff.inc.php");
                        echo('<a href="#stafflocationtable">View full location list</a>');
                     } else {
                        include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/stafflocationtable.inc.php");
                     }
                  }
                  else
                  {
                  ?>
                           <p>No locations are listed for this record series.</p>
                  <?php
                  }
               ?>
            <div class="ccardcontents"><br/><span class='ccardlabel'>Show this record as:</span><br/><br/>
               <a href='?p=collections/ead&amp;id=<?php echo($objCollection->ID); ?>&amp;templateset=ead&amp;disabletheme=1&amp;output=<?php echo(formatFileName($objCollection->getString('SortTitle', 0, false, false))); ?>'>EAD</a><br/>
               <a href='?p=collections/marc&amp;id=<?php echo($objCollection->ID); ?>'>MARC</a><br/>
               <a href='?p=collections/controlcard&amp;id=<?php echo($objCollection->ID); ?>&amp;templateset=kardexcontrolcard&amp;disabletheme=1'>5 by 8 Kardex</a><br/>
               <a href='?p=collections/controlcard&amp;id=<?php echo($objCollection->ID); ?>&amp;templateset=draftcontrolcard&amp;disabletheme=1'>Review copy/draft</a>
            </div>
         </div>   <!--end ccardstaffdiv -->

            <?php
         }
         else            //user is not authenticated
         {
            echo("<div id='ccardstaff' class='mdround'>");
			//include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/openlocationtable.inc.php");
         
         //use the location summary if it isn't a repository excluded from the request link system
         //use the open location table (which also includes a summary) if the repository is excluded from the request link system or if it is a staff only request link
         if(!$_ARCHON->config->ExcludeRequestLink[$objCollection->RepositoryID] AND !$_ARCHON->config->ExcludePublicRequestLink[$objCollection->RepositoryID] AND!$_ARCHON->config->StaffOnlyRequestLink){
            include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/locationsummary.inc.php");
         } else {
            include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/openlocationtable.inc.php");
         }

         echo("</div>");
   
   ?>

         </div> <!--end ccardstaffdiv -->
               <?php
            }


    echo("</div>"); //ending left div

	echo("<div id='ccardprintcontact' class='smround'>");
   
   /**add request button, or launch a modal with the location table if variable locations */
   //if it is a staff-only link, show this only if someone is logged in (note that repository restriction is addressed in the code for the modal and link itself)
			if(!$_ARCHON->config->StaffOnlyRequestLink AND !$_ARCHON->config->ExcludePublicRequestLink[$objCollection->RepositoryID]){
            include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/requestlink.inc.php");
         } elseif($_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ)){
            include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/requestlink.inc.php");
         }

         if ($objCollection->RepositoryID == 2)
         {
            $emailmailto = "sousa@illinois.edu";
            $emailsubject="Reference inquiry for Sousa Archives";
            $emailbody="%0D---Please type your message above this line---%0DThis email was sent from: https://".urlencode($_SERVER['HTTP_HOST']). urlencode($_SERVER['REQUEST_URI']);
         }
         else 
         {
            $emailmailto = "illiarch@illinois.edu";
            $emailsubject="Inquiry: University Archives";
            $emailReferralPage = "https://".urlencode($_SERVER['HTTP_HOST']). urlencode($_SERVER['REQUEST_URI']);
            $emailbody="%0D---Please type your message above this line---%0DReferral page: ".$emailReferralPage;
         }
         if($objCollection->Classification) {
            $emailsubject .= " (RS ".$objCollection->Classification->toString(LINK_NONE, true, false, true, false);
            $emailsubject .= "/".$objCollection->getString('CollectionIdentifier').")";
         }
         if ($objCollection->RepositoryID == 2) {
            echo("<a href='mailto:".$emailmailto."?subject=". $emailsubject .'&body='.$emailbody."'><img src='" . $_ARCHON->PublicInterface->ImagePath . "/email.png' alt='email' /> ");
         } else {
            echo("<a href='https://archives.library.illinois.edu/email-ahx.php?this_page=".$emailReferralPage."' target='_blank'><img src='" . $_ARCHON->PublicInterface->ImagePath . "/email.png' alt='email' /> ");
         }

         echo("Email us about these ");

					
			if($objCollection->MaterialType == 'Official Records--Non-University' || $objCollection->MaterialType == 'Official Records')
			{
				echo ('records');
			}
            elseif($objCollection->MaterialType == 'Publications')
            {
               echo ('publications');
            }
            else
            {
               echo ('papers');
            }

            echo("</a> | <a href='?p=collections/controlcard&amp;id=" . $objCollection->ID . "&amp;templateset=print&amp;disabletheme=1'><img src='" . $_ARCHON->PublicInterface->ImagePath . "/printer.png' alt='printer' /> Print this information</a></div>");  //ending printcontact div


            if($objCollection->Scope || !empty($objCollection->Content) || ($objCollection->DigitalContent || $containsImages) || !empty($objCollection->OtherURL))
            {
               ?>
         <div id="ccardscope" class="mdround">
         <?php
         if($objCollection->Scope)
         {
            ?>
               <div class='ccardcontent expandable' style='padding-left:.2em'><span class='ccardlabel'>Description:</span> <?php 
               if($_ARCHON->config->DatabaseEncodingUTF8){
                  echo ($objCollection->getString('Scope')); 
               }else {
                  echo (utf8_encode($objCollection->getString('Scope')));
               }
                  ?></div>
            <?php
         }
				 
				 if (!empty($objCollection->AccessRestrictions))
					{
				    ?>
							<div class='ccardcontent expandablesmall' style="padding-left:.2em" id="AccessRestrictResults"><span class='ccardlabel'>Access Restriction:</span> <?php echo($objCollection->getString('AccessRestrictions')); ?></div>
				     <?php	
					}
					if (!empty($objCollection->UseRestrictions))
					{
				    ?>
							<div class='ccardcontent expandablesmall' style="padding-left:.2em" id="UseRestrictResults"><span class='ccardlabel'>Rights Statement:</span> <?php echo($objCollection->getString('UseRestrictions')); ?></div>
				     <?php
					}				
				 
         if($objCollection->DigitalContent || $containsImages)
         {
            ?>

               <div class='ccardcontent' style='padding-left:.2em'><span class='ccardlabel'><a href='#' onclick="toggleDisplay('digitalcontent'); return false;"><img id='digitalcontentImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' /> Online Images / Records</a></span><br/>
                  <div class='ccardshowlist' style="display: none;" id="digitalcontentResults">
            <?php
            if($containsImages)
            {
               echo("<span class='bold'><a href='index.php?p=digitallibrary/thumbnails&amp;collectionid={$objCollection->ID}'>Images</a></span> (browse thumbnails)<br/>\n\n");
            }
            if($objCollection->DigitalContent)
            {
               echo("<br/><span class='bold'>Documents and Files:</span><br/></br>&nbsp;" . $_ARCHON->createStringFromDigitalContentArray($objCollection->DigitalContent, "<br/>\n&nbsp;", LINK_TOTAL, false));
            }
            ?>
                  </div>
               </div>
               <?php
            }
            if(!empty($objCollection->Content))
            {
               ?>
               <div class='ccardcontent'><span class='ccardlabel'><a href='?p=collections/findingaid&amp;id=<?php echo($objCollection->ID); ?>&amp;q=<?php echo($_ARCHON->QueryStringURL); ?>'>Detailed Description</a></span><br/>
      <?php
      $DisableTheme = $_ARCHON->PublicInterface->DisableTheme;
      $_ARCHON->PublicInterface->DisableTheme = true;

      foreach($objCollection->Content as $ID => $objContent)
      {
         if(!$objContent->ParentID)
         {
            if($objContent->enabled())
            {
               echo("<span class='ccardserieslist'><a href='?p=collections/findingaid&amp;id=$objCollection->ID&amp;q=$_ARCHON->QueryStringURL&amp;rootcontentid=$ID#id$ID'>" . $objContent->toString() . "</a></span><br/>\n");
            }
            else
            {

               $objInfoRestrictedPhrase = Phrase::getPhrase('informationrestricted', PACKAGE_CORE, 0, PHRASETYPE_PUBLIC);
               $strInfoRestricted = $objInfoRestrictedPhrase ? $objInfoRestrictedPhrase->getPhraseValue(ENCODE_HTML) : 'Information restricted, please contact us for additional information.';
               echo("<span class='ccardserieslist'>{$strInfoRestricted}</span><br/>\n");
            }
         }
      }

      $_ARCHON->PublicInterface->DisableTheme = $DisableTheme;
      ?>
               </div>
                  <?php
               }


               if(!empty($objCollection->OtherURL))
               {
                  $onclick = ($_ARCHON->config->GACode && $_ARCHON->config->GACollectionsURL) ? "onclick='javascript: pageTracker._trackPageview(\"{$_ARCHON->config->GACollectionsURL}\");'" : "";
   
                  if(strtolower(substr($objCollection->getString('OtherURL'),-3))=="pdf"){
                     echo("<br /><strong><a href='?p=collections/findingaid&amp;id=".$objCollection->ID."#pdf-fa'>View finding aid and PDF box/folder list</a></strong>");
                  } else {
                     echo("<div class='ccardcontent'><span class='ccardlabel'>Other URL:</span> <a href='");
                     echo($objCollection->getString('OtherURL')); ?>' <?php if(!$_ARCHON->Security->userHasAdministrativeAccess()) {echo($onclick);} ?> target="_blank"><?php echo($objCollection->getString('OtherURL')); 
                     echo("</a></div>");
                  }
               }
                echo ("<div style='clear:both'><pre>");
//var_dump ($objCollection);
echo ("</pre></div>");

               ?>
         </div> <!--end ccard scope -->
               <?php
                  if($_ARCHON->Security->verifyPermissions(MODULE_COLLECTIONS, READ) AND !$_ARCHON->config->ExcludeRequestLink[$objCollection->RepositoryID]){
                     ?>
                     <div style='clear:both;margin-left:1em;'>
                     <p><strong>Additional Data Tables for Staff Use (hidden if not logged in)</strong></p><p>Copy these tables to Excel as needed to create bulk upload files.</strong></p>
                     <a href='#' onclick="toggleDisplay('locationupdate'); return false;"><img id='locationupdateImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' /> <caption>Bulk update table for location entries</caption></a><br/>
                     <div style="display: none;" id="locationupdateResults">
                     <?php
                     include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/locationupdatetable.inc.php");
                     echo("</div>");
                     if($requestLink) {
                        ?>
                        <br />
                        <a href='#' onclick="toggleDisplay('bulkrequest'); return false;"><img id='bulkrequestImage' src='<?php echo($_ARCHON->PublicInterface->ImagePath); ?>/plus.gif' alt='expand icon' /> <caption>Bulk request table, staff upload to Aeon (delete rows not needed)</caption></a><br/>
                        <div style="display: none;" id="bulkrequestResults">
                        <?php
                        include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/requestbulktable.inc.php");
                        echo("</div>");
                     }
                     echo("</div>");
                     
                     if(!$_ARCHON->config->ExcludeRequestLink[$objCollection->RepositoryID]){     
                        echo("<div id='stafflocationtable' style='margin:1em;border-top:1px solid black'>");
                        echo("<p><strong>Filter full list of storage locations below:</strong></p>");
                        include("packages/collections/templates/{$_ARCHON->PublicInterface->TemplateSet}/stafflocationtable.inc.php");
                     }
            }
         }
            ?>