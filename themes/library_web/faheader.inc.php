<?php
/**
 * Header file for default theme finding aid output
 *
 * @package Archon
 * @author Chris Rishel, Chris Prom, Kyle Fox, Paul Sorensen
 */
isset($_ARCHON) or die();

// *** This is now a configuration directive. Please set in the Configuration Manager ***
//$_ARCHON->PublicInterface->EscapeXML = false;


$_ARCHON->PublicInterface->Header->OnLoad .= "externalLinks();";

if($_ARCHON->Error)
{
   $_ARCHON->PublicInterface->Header->OnLoad .= " alert('" . encode(str_replace(';', "\n", $_ARCHON->processPhrase($_ARCHON->Error)), ENCODE_JAVASCRIPT) . "');";
}

if(!empty($objCollection->Subjects))
{
   $GenreSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Genre/Form of Material');

   foreach($objCollection->Subjects as $objSubject)
   {
      if($objSubject->SubjectTypeID == $GenreSubjectTypeID)
      {
         $arrGenres[$objSubject->ID] = $objSubject;
      }
      else
      {
         $arrSubjects[$objSubject->ID] = $objSubject;
      }
   }
}

if(defined('PACKAGE_COLLECTIONS'))
{
   if($objCollection->Repository)
   {
      $RepositoryName = $objCollection->Repository;
   }
   elseif($objDigitalContent->Collection->Repository)
   {
      $RepositoryName = $objDigitalContent->Collection->Repository;
   }
   else
   {
      $RepositoryName = $_ARCHON->Repository ? $_ARCHON->Repository->getString('Name') : '';
   }
   $_ARCHON->PublicInterface->Title = $_ARCHON->PublicInterface->Title ? 'Finding Aid for ' . $_ARCHON->PublicInterface->Title . ' | ' . $RepositoryName : $RepositoryName;
}
else
{
   $_ARCHON->PublicInterface->Title = $_ARCHON->PublicInterface->Title ? $_ARCHON->PublicInterface->Title . ' - ' . 'Archon' : 'Archon';
}

require('commonheader.inc.php');
?>           

                  <!-- Begin Navigation Bar for Finding Aid View -->

                  <div id='left'>
                     <div id='fanavbox'>
                        <p class='bold' style='text-align:center'><?php echo($objCollection->getString('Title')); ?></p>
                        <p><a href="#" tabindex="300">Overview</a></p>
<?php if($objCollection->Abstract)
                     {
 ?><p><a href="#abstract" tabindex="400">Abstract</a></p><?php } ?>
            <?php
                     if($objCollection->Scope)
                     {
            ?><p><a href="#scopecontent" tabindex="400">Scope and Contents</a></p><?php } ?>
            <?php
                     if($objCollection->PrimaryCreator->BiogHist)
                     {
            ?><p><a href="#bioghist" tabindex="500"><?php
                        if(trim($objCollection->PrimaryCreator->CreatorType) == "Corporate Name")
                        {
                           echo ("Historical Note");
                        }
                        elseif(trim($objCollection->PrimaryCreator->CreatorType) == "Family Name")
                        {
                           echo ("Family History");
                        }
                        else
                        {
                           echo ("Biographical Note");
                        }
            ?>
                     </a></p>
            <?php
                     }
            ?>
            <?php
                     if(!empty($arrSubjects))
                     {
            ?> <p><a href="#subjects" tabindex="600">Subject Terms</a></p><?php } ?>
            <?php
                     if(!empty($objCollection->AccessRestrictions) || !empty($objCollection->UseRestrictions) || !empty($objCollection->PhysicalAccessNote) || !empty($objCollection->TechnicalAccessNote) || !empty($objCollection->AcquisitionSource) || !empty($objCollection->AcquisitionMethod) || !empty($objCollection->AppraisalInformation) || !empty($objCollection->CustodialHistory) || !empty($objCollection->OrigCopiesNote) || !empty($objCollection->OrigCopiesURL) || !empty($objCollection->RelatedMaterials) || !empty($objCollection->RelatedMaterialsURL) || !empty($objCollection->RelatedPublications) || !empty($objCollection->PreferredCitation) || !empty($objCollection->ProcessingInfo) || !empty($objCollection->RevisionHistory))
                     {
            ?> <p><a href="#admininfo" tabindex="700">Administrative Information</a></p> <?php } ?>
            <?php
                     if(!empty($objCollection->Content))
                     {
            ?> <p><a href="#boxfolder" tabindex="800">Detailed Description</a></p><?php
                     

                     foreach($objCollection->Content as $ID => $objContent)
                     {
                        if(!$objContent->ParentID)
                        {
                           if($objContent->enabled())
                           {
                              if(trim($objContent->Title))
                              {
                                 echo("<p class='faitemcontent'><a href='?p=collections/findingaid&amp;id=$objCollection->ID&amp;q=$_ARCHON->QueryStringURL&amp;rootcontentid=$ID#id$ID'>" . $objContent->getString('Title') . "</a></p>\n");
                              }
                              else
                              {
                                 $LevelContainerString = $objContent->LevelContainer ? $objContent->LevelContainer->getString('LevelContainer') : '';
                                 echo("<p class='faitemcontent'><a href='?p=collections/findingaid&amp;id=$objCollection->ID&amp;q=$_ARCHON->QueryStringURL&amp;rootcontentid=$ID#id$ID'>{$LevelContainerString} " . $objContent->getString('LevelContainerIdentifier', 0, false) . "</a></p>\n");
                              }
                           }
                           else
                           {
                              
                              $objInfoRestrictedPhrase = Phrase::getPhrase('informationrestricted', PACKAGE_CORE, 0, PHRASETYPE_PUBLIC);
                              $strInfoRestricted = $objInfoRestrictedPhrase ? $objInfoRestrictedPhrase->getPhraseValue(ENCODE_HTML) : 'Information restricted, please contact us for additional information.';
                              echo("<p class='faitemcontent'>{$strInfoRestricted}</p>\n");
                           }
                        }
                     }
            ?>
                     <form action="index.php" accept-charset="UTF-8" method="get" onsubmit="if(!this.q.value) { alert('Please enter search terms.'); return false; } else { return true; }">
                        <div id="fasearchblock">
                           <input type="hidden" name="p" value="core/search" />
                           <input type="hidden" name="flags" value="<?php echo(SEARCH_COLLECTIONCONTENT); ?>" />
                           <input type="hidden" name="collectionid" value="<?php echo($objCollection->ID); ?>" />
                           <input type="hidden" name="content" value="1" />
                           <input type="text" size="20" maxlength="150" name="q" id="q2" value="<?php echo(encode($_ARCHON->QueryString, ENCODE_HTML)); ?>" tabindex="100" /><br/>
                           <input type="submit" value="Search Box List" tabindex="200" class='button' title="Search Box List" />
                        </div>
                     </form>
            <?php
                  }//end if statement for if collection content is not empty

                  if($objCollection->OtherURL){

                     if(strtolower(substr($objCollection->getString('OtherURL'),-3))=="pdf"){
                        echo( '<p><a href="#pdf-fa" tabindex="800">PDF Box/Folder List</a></p>');
                     }

                  }
                     
                     if(defined('PACKAGE_COLLECTIONS'))
                     {
                        echo("<hr/><p class='center' style='font-weight:bold'>");
                        if ($objCollection->RepositoryID == 2)
                        {
                           $emailmailto = "sousa@illinois.edu";
                           $emailsubject="Reference inquiry for Sousa Archives";
                           $emailbody="%0D---Please type your message above this line---%0DThis email was sent from: https://".urlencode($_SERVER['HTTP_HOST']). urlencode($_SERVER['REQUEST_URI']);
                           
                        }
                        else 
                        {
                           $emailmailto = "illiarch@illinois.edu";
                           $emailsubject="Inquiry: University Archives Finding Aid";
                           $emailReferralPage = "https://".urlencode($_SERVER['HTTP_HOST']). urlencode($_SERVER['REQUEST_URI']);
                           $emailbody="%0D---Please type your message above this line---%0DReferral page: ".$emailReferralPage;
                        }
                        if($objCollection->Classification) {
                           $emailsubject .= " (RS ".$objCollection->Classification->toString(LINK_NONE, true, false, true, false);
                           $emailsubject .= "/".$objCollection->getString('CollectionIdentifier').")";
                        }
                        if($objCollection->RepositoryID == 2){
                           echo("<a href='mailto:".$emailmailto."?subject=". $emailsubject .'&body='.$emailbody."'>");
                        } else {
                           echo("<a href='https://archives.library.illinois.edu/email-ahx.php?this_page=".$emailReferralPage."' target='_blank'>");
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
               
                           echo("</a>");        
                        
                        echo("</p>");
                     }
            ?>

         </div>
      </div>
      <script type="text/javascript">
         /* <![CDATA[ */
         if ($.browser.msie && parseInt($.browser.version, 10) <= 8){
            $('#searchblock').corner({
               tl: { radius: 5 },
               tr: { radius: 5 },
               bl: { radius: 5 },
               br: { radius: 5 },
               autoPad: true
            });
            $('#browsebyblock').corner({
               tl: { radius: 10 },
               tr: { radius: 0 },
               bl: { radius: 0 },
               br: { radius: 0 },
               autoPad: true
            });
            $('#left').corner({
               tl: { radius: 10 },
               tr: { radius: 10 },
               bl: { radius: 10 },
               br: { radius: 10 },
               autoPad: true
            });
         }
         /* ]]> */
      </script>
      <div id="famain">
