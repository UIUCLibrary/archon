<?php

abstract class Accessions_Archon
{

   /**
    * Retrieves all Processing Priorities from the database
    *
    * The returned array of ProcessingPriority objects
    * is sorted by ProcessingPriority and has IDs as keys.
    *
    * @return ProcessingPriority[]
    */
   public function getAllProcessingPriorities()
   {
      return $this->loadTable("tblAccessions_ProcessingPriorities", "ProcessingPriority", "DisplayOrder, ProcessingPriority");
   }

   /**
    * Searches the ProcessingPriority database
    *
    * @param string $SearchQuery
    * @param integer $SearchFlags
    * @param integer $Limit[optional]
    * @param integer $Offset[optional]
    * @return ProcessingPriority[]
    */
   public function searchAccessions($SearchQuery, $SearchFlags = SEARCH_ACCESSIONS, $ClassificationID = 0, $CollectionID = 0, $SubjectID = 0, $CreatorID = 0, $Limit = CONFIG_CORE_SEARCH_RESULTS_LIMIT, $Offset = 0)
   {
      $arrAccessions = array();
      $arrPrepQueries = array();

      if(!$this->Security->verifyPermissions(MODULE_ACCESSIONS, READ))
      {
         $SearchFlags &= ~ (SEARCH_DISABLED_ACCESSIONS);
      }

      if(!($SearchFlags & SEARCH_ACCESSIONS))
      {
         return $arrAccessions;
      }

      $enabledquery = " AND (";
      if($SearchFlags & SEARCH_ENABLED_ACCESSIONS)
      {
         $enabledquery .= "tblAccessions_Accessions.Enabled = '1'";

         if($SearchFlags & SEARCH_DISABLED_ACCESSIONS)
         {
            $enabledquery .= " OR tblAccessions_Accessions.Enabled = '0'";
         }
      }
      else
      {
         $enabledquery = "tblAccessions_Accessions.Enabled = '0'";
      }
      $enabledquery .= ")";
      $enabledtypes = array();
      $enabledvars = array();

      if((is_natural($Offset) && $Offset > 0) && (is_natural($Limit) && $Limit > 0))
      {
         $limitparams = array($Limit, $Offset);
      }
      elseif(is_natural($Offset) && $Offset > 0)
      {
         $limitparams = array(4294967295, $Offset);
      }
      elseif(is_natural($Limit) && $Limit > 0)
      {
         $limitparams = array($Limit);
      }
      else
      {
         $limitparams = array(4294967295);
      }


      if($SubjectID && is_natural($SubjectID))
      {
         $arrIndexSearch['Subject'] = array($SubjectID => NULL);
      }
      elseif($CreatorID && is_natural($CreatorID))
      {
         $arrIndexSearch['Creator'] = array($CreatorID => NULL);
      }
      elseif($ClassificationID && is_natural($ClassificationID))
      {
         $arrIndexSearch['Classification'] = array($ClassificationID => NULL);
      }
      elseif($CollectionID && is_natural($CollectionID))
      {
         $arrIndexSearch['Collection'] = array($CollectionID => NULL);
      }
      else
      {
         $arrWords = $this->createSearchWordArray($SearchQuery);

         $textquery = '';
         $texttypes = array();
         $textvars = array();

         if(!empty($arrWords))
         {
            $i = 0;
            foreach($arrWords as $word)
            {
               $i++;
               if($word[0] == "-")
               {
                  $word = encoding_substr($word, 1, encoding_strlen($word) - 1);
                  $textquery .= "(tblAccessions_Accessions.Title NOT LIKE ? AND tblAccessions_Accessions.AccessionDate NOT LIKE ? AND tblAccessions_Accessions.ScopeContent NOT LIKE ? AND tblAccessions_Accessions.PhysicalDescription NOT LIKE ? AND tblAccessions_Accessions.Identifier NOT LIKE ? AND tblAccessions_Accessions.Donor NOT LIKE ? AND tblAccessions_Accessions.Comments NOT LIKE ?)";
                  array_push($texttypes, 'text', 'text', 'text', 'text', 'text', 'text', 'text');
                  array_push($textvars, "%$word%", "%$word%", "%$word%", "%$word%", "%$word%", "%$word%", "%$word%");
               }
               else
               {
                  $textquery .= "(tblAccessions_Accessions.Title LIKE ? OR tblAccessions_Accessions.AccessionDate LIKE ? OR tblAccessions_Accessions.ScopeContent LIKE ? OR tblAccessions_Accessions.PhysicalDescription LIKE ? OR tblAccessions_Accessions.Identifier LIKE ? OR tblAccessions_Accessions.Donor LIKE ? OR tblAccessions_Accessions.Comments LIKE ?)";
                  array_push($texttypes, 'text', 'text', 'text', 'text', 'text', 'text', 'text');
                  array_push($textvars, "%$word%", "%$word%", "%$word%", "%$word%", "%$word%", "%$word%", "%$word%");
               }

               if($i < count($arrWords))
               {
                  $textquery .= " AND ";
               }
            }
         }
         else
         {
//                $textquery = "tblAccessions_Accessions.Title LIKE '%%'";
            $textquery = "1=1";
         }

         // If our query is just a number, try to match it
         // directly to an ID from the table.
         if(is_natural($SearchQuery) && $SearchQuery > 0)
         {
            $textquery .= " OR ID = ?";
            $texttypes[] = 'integer';
            $textvars[] = $SearchQuery;
         }

         if($textquery || $enabledquery)
         {
            $wherequery = "WHERE $textquery $enabledquery";
            $wheretypes = array_merge($texttypes, $enabledtypes);
            $wherevars = array_merge($textvars, $enabledvars);
         }
         else
         {
            $wherequery = '';
            $wheretypes = array();
            $wherevars = array();
         }

         $prepQuery->query = "SELECT tblAccessions_Accessions.* FROM tblAccessions_Accessions $wherequery ORDER BY tblAccessions_Accessions.Title, tblAccessions_Accessions.Identifier";
         $prepQuery->types = $wheretypes;
         $prepQuery->vars = $wherevars;
         $arrPrepQueries[] = $prepQuery;

         if(defined('PACKAGE_SUBJECTS') && ($SearchFlags & SEARCH_SUBJECTS) && ($SearchFlags & SEARCH_RELATED))
         {
            $arrIndexSearch['Subject'] = $this->searchSubjects($SearchQuery);
         }

         if(defined('PACKAGE_CREATORS') && ($SearchFlags & SEARCH_CREATORS) && ($SearchFlags & SEARCH_RELATED))
         {
            $arrIndexSearch['Creator'] = $this->searchCreators($SearchQuery);
         }
      }

      if(!empty($arrIndexSearch))
      {
         foreach($arrIndexSearch as $Type => $arrObjects)
         {
            if(!empty($arrObjects))
            {
               foreach($arrObjects as $ID => $junk)
               {
                  if($Type != 'Classification')
                  {
                     $prepQuery->query = "SELECT tblAccessions_Accessions.* FROM tblAccessions_Accessions JOIN {$this->mdb2->quoteIdentifier("tblAccessions_Accession{$Type}Index")} ON {$this->mdb2->quoteIdentifier("tblAccessions_Accession{$Type}Index")}.AccessionID = tblAccessions_Accessions.ID WHERE {$this->mdb2->quoteIdentifier("tblAccessions_Accession{$Type}Index")}.{$this->mdb2->quoteIdentifier("{$Type}ID")} = ?$enabledquery ORDER BY tblAccessions_Accessions.Title, tblAccessions_Accessions.Identifier";
                     $prepQuery->types = array_merge(array('integer'), $enabledtypes);
                     $prepQuery->vars = array_merge(array($ID), $enabledvars);
                     $arrPrepQueries[] = $prepQuery;

                     // $arrQueries[] = "SELECT tblAccessions_Accessions.* FROM tblAccessions_Accessions JOIN tblAccessions_{$Type}Index ON tblAccessions_{$Type}Index.AccessionID = tblAccessions_Accessions.ID WHERE tblAccessions_{$Type}Index.{$Type}ID = '$ID'$enabledquery ORDER BY tblAccessions_Accessions.Title $limitquery";
                  }
                  else
                  {
                     $prepQuery->query = "SELECT tblAccessions_Accessions.* FROM tblAccessions_Accessions JOIN tblAccessions_AccessionCollectionIndex ON tblAccessions_AccessionCollectionIndex.AccessionID = tblAccessions_Accessions.ID WHERE tblAccessions_AccessionCollectionIndex.ClassificationID = ?$enabledquery ORDER BY tblAccessions_Accessions.Title, tblAccessions_Accessions.Identifier";
                     $prepQuery->types = array_merge(array('integer'), $enabledtypes);
                     $prepQuery->vars = array_merge(array($ID), $enabledvars);
                     $arrPrepQueries[] = $prepQuery;

                     // $arrQueries[] = "SELECT tblAccessions_Accessions.* FROM tblAccessions_Accessions JOIN tblAccessions_AccessionCollectionIndex ON tblAccessions_AccessionCollectionIndex.AccessionID = tblAccessions_Accessions.ID WHERE tblAccessions_AccessionCollectionIndex.ClassificationID = '$ID'$enabledquery ORDER BY tblAccessions_Accessions.Title $limitquery";
                  }
               }
            }
         }
      }


      if(!empty($arrPrepQueries))
      {
         foreach($arrPrepQueries as $prepQuery)
         {
            if($prepQuery->query)
            {
               call_user_func_array(array($this->mdb2, 'setLimit'), $limitparams);
               $prep = $this->mdb2->prepare($prepQuery->query, $prepQuery->types, MDB2_PREPARE_RESULT);
               $result = $prep->execute($prepQuery->vars);
               if(pear_isError($result))
               {
                  trigger_error($result->getMessage(), E_USER_ERROR);
               }

               while($row = $result->fetchRow())
               {
                  $arrAccessions[$row['ID']] = New Accession($row);
               }
               $result->free();
               $prep->free();
            }
         }
      }

      return $arrAccessions;
   }

   /**
    * Searches the ProcessingPriority database
    *
    * @param string $SearchQuery
    * @param integer $Limit[optional]
    * @param integer $Offset[optional]
    * @return ProcessingPriority[]
    */
   public function searchProcessingPriorities($SearchQuery, $Limit = CONFIG_CORE_SEARCH_RESULTS_LIMIT, $Offset = 0)
   {
      return $this->searchTable($SearchQuery, 'tblAccessions_ProcessingPriorities', 'ProcessingPriority', 'ProcessingPriority', 'DisplayOrder, ProcessingPriority', NULL, array(), array(), NULL, array(), array(), $Limit, $Offset);
   }

   /**
    * Retrieves an array of Accession objects that begin with
    * the character specified by $Char
    *
    * @param string $Char
    * @param integer $RepositoryID[optional]
    * @return Collection[]
    */
   public function getAccessionsForChar($Char, $RepositoryID = 0, $Fields = array())
   {
      if(!$this->Security->verifyPermissions(MODULE_ACCESSIONS, READ))
      {
         $ExcludeDisabledAccessions = true;
      }

      if(!$Char)
      {
         $this->declareError("Could not get Accessions: Character not defined.");
         return false;
      }

      $arrAccessions = array();

      $andTypes = array();
      $andVars = array();
      if($ExcludeDisabledAccessions)
      {
         $andquery = " AND Enabled = '1'";
      }

      if(!is_array($RepositoryID) && is_natural($RepositoryID) && $RepositoryID > 0)
      {
         $andquery .= " AND (tblAccessions_Accessions.RepositoryID = ?)";
         array_push($andTypes, 'integer');
         array_push($andVars, $RepositoryID);
      }
      elseif($RepositoryID && is_array($RepositoryID) && !empty($RepositoryID))
      {
         $andquery .= " AND RepositoryID IN (";
         $andquery .= implode(', ', array_fill(0, count($RepositoryID), '?'));
         $andquery .= ")";

         $andTypes = array_merge($andTypes, array_fill(0, count($RepositoryID), 'integer'));
         $andVars = array_merge($andVars, $RepositoryID);
      }


//      if($RepositoryID && is_natural($RepositoryID))
//      {
//         $andquery .= " AND RepositoryID = ?";
//         array_push($andTypes, 'integer');
//         array_push($andVars, $RepositoryID);
//      }

      if(!empty($Fields) && is_array($Fields))
      {
         $tmpAccession = new Accession();
         $badFields = array_diff($Fields, array_keys(get_object_vars($tmpAccession)));
         if(!empty($badFields))
         {
            $this->declareError("Could not load Accessions: Field(s) '" . implode(',', $badFields) . "' do not exist in Class Accession.");
            return false;
         }

         $selectFields = implode(',', $Fields);
      }


      $selectFields = ($selectFields) ? $selectFields : '*';

      if($Char == '#')
      {
         $query = "SELECT {$selectFields} FROM tblAccessions_Accessions WHERE (Title LIKE '0%' OR Title LIKE '1%' OR Title LIKE '2%' OR Title LIKE '3%' OR Title LIKE '4%' OR Title LIKE '5%' OR Title LIKE '6%' OR Title LIKE '7%' OR Title LIKE '8%' OR Title LIKE '9%') $andquery ORDER BY Title";
      }
      else
      {
         $query = "SELECT {$selectFields} FROM tblAccessions_Accessions WHERE Title LIKE '{$this->mdb2->escape($Char, true)}%' $andquery ORDER BY Title";
      }

      $prep = $this->mdb2->prepare($query, $andTypes, MDB2_PREPARE_RESULT);
      $result = $prep->execute($andVars);
      if(pear_isError($result))
      {
         trigger_error($result->getMessage(), E_USER_ERROR);
      }

      while($row = $result->fetchRow())
      {
         $arrAccessions[$row['ID']] = New Accession($row);
      }
      $result->free();
      $prep->free();

      return $arrAccessions;
   }

   /**
    * Returns the number of Accessions in the database
    *
    * If $Alphabetical is set to true, an array will be returned with keys of
    * a-z, #, and * each holding the count for Accession Title starting
    * with that character.  # represents all collections starting with a number,
    * and * holds the total count of all collections.
    *
    * @param boolean $Alphabetical[optional]
    * @param boolean $ExcludeDisabledAccessions[optional]
    * @param integer $RepositoryID[optional]
    * @return integer|Array
    */
   public function countAccessions($Alphabetical = false, $ExcludeDisabledAccessions = false, $RepositoryID = 0)
   {
      if(!$this->Security->verifyPermissions(MODULE_ACCESSIONS, READ))
      {
         $ExcludeDisabledAccessions = true;
      }

      if($ExcludeDisabledAccessions)
      {
         $Conditions = "Enabled = '1'";
      }

      if($RepositoryID && !is_array($RepositoryID) && is_natural($RepositoryID))
      {
         $Conditions .= $Conditions ? " AND RepositoryID = ?" : "RepositoryID = ?";
         $ConditionsTypes = array('integer');
         $ConditionsVars = array($RepositoryID);
      }
      elseif($RepositoryID && is_array($RepositoryID) && !empty($RepositoryID))
      {
         $Conditions .= $Conditions ? " AND RepositoryID IN (" : "RepositoryID IN (";
         $Conditions .= implode(', ', array_fill(0, count($RepositoryID), '?'));
         $Conditions .= ")";

         $ConditionsTypes = array_fill(0, count($RepositoryID), 'integer');
         $ConditionsVars = $RepositoryID;
      }
      else
      {
         $ConditionsTypes = array();
         $ConditionsVars = array();
      }

      if($Alphabetical)
      {
         if($Conditions)
         {
            $Conditions = 'AND ' . $Conditions;
         }

         $arrIndex = array();
         $sum = 0;

         $prep = $this->mdb2->prepare("SELECT ID FROM tblAccessions_Accessions WHERE (Title LIKE '0%' OR Title LIKE '1%' OR Title LIKE '2%' OR Title LIKE '3%' OR Title LIKE '4%' OR Title LIKE '5%' OR Title LIKE '6%' OR Title LIKE '7%' OR Title LIKE '8%' OR Title LIKE '9%') $Conditions", $ConditionTypes, MDB2_PREPARE_RESULT);
         $result = $prep->execute($ConditionsVars);
         if(pear_isError($result))
         {
            trigger_error($result->getMessage(), E_USER_ERROR);
         }

         $arrIndex['#'] = $result->numRows();
         $sum += $arrIndex['#'];

         $result->free();
         $prep->free();

         $prep = $this->mdb2->prepare("SELECT ID FROM tblAccessions_Accessions WHERE Title LIKE ? $Conditions", array_merge(array('text'), $ConditionsTypes), MDB2_PREPARE_RESULT);
         for($i = 65; $i < 91; $i++)
         {
            $char = chr($i);

            $result = $prep->execute(array_merge(array("$char%"), $ConditionsVars));
            if(pear_isError($result))
            {
               trigger_error($result->getMessage(), E_USER_ERROR);
            }

            $arrIndex[$char] = $result->numRows();
            $arrIndex[encoding_strtolower($char)] = & $arrIndex[$char];
            $sum += $arrIndex[$char];

            $result->free();
         }
         $prep->free();

         $arrIndex['*'] = $sum;

         return $arrIndex;
      }
      else
      {
         if($Conditions)
         {
            $Conditions = 'WHERE ' . $Conditions;
         }

         $prep = $this->mdb2->prepare("SELECT ID FROM tblAccessions_Accessions $Conditions", $ConditionsTypes, MDB2_PREPARE_RESULT);
         $result = $prep->execute($ConditionsVars);
         if(pear_isError($result))
         {
            trigger_error($result->getMessage(), E_USER_ERROR);
         }

         $returnVal = $result->numRows();
         $result->free();
         $prep->free();

         return $returnVal;
      }
   }

}


$_ARCHON->mixClasses('Archon', 'Accessions_Archon');
?>
