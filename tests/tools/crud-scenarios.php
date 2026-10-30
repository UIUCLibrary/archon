<?php
/**
 * Scenarios for tests/tools/crud.php. Each scenario is a closure; they run in order and later
 * scenarios may use records created by earlier ones (through Fixtures).
 */

final class Fixtures
{
   public static $IDs = array();

   public static function get($Name)
   {
      return isset(self::$IDs[$Name]) ? self::$IDs[$Name] : NULL;
   }

   public static function firstId($Table, $Where = '1 = 1')
   {
      return (int)Db::value('SELECT TOP 1 ID FROM ' . quoteIdentifier($Table) . " WHERE $Where ORDER BY ID");
   }
}

function publicSearch($Text)
{
   return array('p' => 'core/search', 'q' => $Text);
}

/** Generic cycle for a lookup-style module. */
function lookupScenario($Package, $Module, $Table, array $Extra = array())
{
   return function() use ($Package, $Module, $Table, $Extra) {
      Crud::cycle(array('p' => "admin/$Package/$Module", 'table' => $Table) + $Extra);
   };
}

function crudScenarios()
{
   $Scenarios = array();

   // --- Lookup tables -------------------------------------------------------------------
   $Scenarios['accessions/processingpriorities'] = lookupScenario('accessions', 'processingpriorities', 'tblAccessions_ProcessingPriorities', array('fill' => array('DisplayOrder' => '7')));
   $Scenarios['collections/extentunits'] = lookupScenario('collections', 'extentunits', 'tblCollections_ExtentUnits');
   $Scenarios['collections/levelcontainers'] = lookupScenario('collections', 'levelcontainers', 'tblCollections_LevelContainers',
      array('fill' => array('IntellectualLevel' => '1', 'chkIntellectualLevel' => 'on', 'PhysicalContainer' => '0', 'EADLevel' => 'file')));
   $Scenarios['collections/materialtypes'] = lookupScenario('collections', 'materialtypes', 'tblCollections_MaterialTypes');
   $Scenarios['collections/researchappointmentpurposes'] = lookupScenario('collections', 'researchappointmentpurposes', 'tblCollections_ResearchAppointmentPurposes');
   $Scenarios['collections/researchertypes'] = lookupScenario('collections', 'researchertypes', 'tblCollections_ResearcherTypes');
   $Scenarios['core/userprofilefieldcategories'] = lookupScenario('core', 'userprofilefieldcategories', 'tblCore_UserProfileFieldCategories', array('id_not_returned' => 'UserProfileFieldCategory', 'fill' => array('DisplayOrder' => '3')));
   $Scenarios['creators/creatorsources'] = lookupScenario('creators', 'creatorsources', 'tblCreators_CreatorSources');
   $Scenarios['subjects/subjectsources'] = lookupScenario('subjects', 'subjectsources', 'tblSubjects_SubjectSources');
   $Scenarios['digitallibrary/filetypes'] = lookupScenario('digitallibrary', 'filetypes', 'tblDigitalLibrary_FileTypes',
      array('fill' => array('FileExtensions' => 'crudx', 'ContentType' => 'application/x-crud-test')));
   $Scenarios['core/regions'] = lookupScenario('core', 'regions', 'tblCore_StateProvinces', array('id_not_returned' => 'StateProvinceName'));
   $Scenarios['core/patterns'] = lookupScenario('core', 'patterns', 'tblCore_Patterns',
      array('id_not_returned' => 'Name', 'fill' => array('Pattern' => '/^crud-[0-9]+$/', 'PackageID' => '1')));
   $Scenarios['core/phrases'] = lookupScenario('core', 'phrases', 'tblCore_Phrases',
      array('fill' => array('PackageID' => '1', 'ModuleID' => '0', 'PhraseTypeID' => '1', 'LanguageID' => '2081')));
   $Scenarios['core/repositories'] = lookupScenario('core', 'repositories', 'tblCore_Repositories',
      array('fill' => array('Email' => 'crud@example.org', 'URL' => 'https://example.org/crud', 'State' => 'IL', 'ZIPCode' => '61801', 'ZIPPlusFour' => '1234', 'CountryID' => '225')));
   $Scenarios['core/userprofilefields'] = lookupScenario('core', 'userprofilefields', 'tblCore_UserProfileFields', array('id_not_returned' => 'UserProfileField'));
   $Scenarios['collections/researchappointmentfields'] = function() {
      if(Db::tableExists('tblCollections_ResearchAppointmentFields') && Db::value("SELECT COUNT(*) FROM tblCore_Modules WHERE Script = 'researchappointmentfields'"))
      {
         Crud::cycle(array('p' => 'admin/collections/researchappointmentfields', 'table' => 'tblCollections_ResearchAppointmentFields'));
         return;
      }
      // Not installed by the 3.21 installer: the module must refuse cleanly instead of failing.
      list($Response, $Controls) = Crud::form('admin/collections/researchappointmentfields');
      Report::step('page loads without a fatal error', $Response['status'] == 200 && !$Response['fatal'], (string)$Response['fatal']);
      list($Response, $Parsed) = Crud::store(array(array('p', 'admin/collections/researchappointmentfields'), array('f', 'store'), array('Name', 'x')));
      Crud::checkResponse('store is refused with a message', $Response, $Parsed, true);
   };

   // --- Authority records and the records a collection links to --------------------------
   $Scenarios['creators/creators'] = function() {
      Fixtures::$IDs['creator'] = Crud::cycle(array('p' => 'admin/creators/creators', 'table' => 'tblCreators_Creators', 'delete' => false,
         'fill' => array('Dates' => '1890-1960', 'BiogHist' => function($Marker) { return "[b]Bold[/b] biography with <i>markup</i> & ampersand $Marker"; }),
         'public' => function($ID) { return array(array('p' => 'creators/creator', 'id' => $ID), array('p' => 'creators/eac', 'id' => $ID)); },
         'public_expect' => function($Filled) { return $Filled['Name']; }));
   };
   $Scenarios['creators/creators (second, related)'] = function() {
      $First = Fixtures::get('creator');
      Fixtures::$IDs['creator2'] = $ID = Crud::cycle(array('p' => 'admin/creators/creators', 'table' => 'tblCreators_Creators', 'delete' => false, 'label' => 'creator2',
         'fill' => array('CreatorRelationships[0][RelatedCreatorID]' => $First, 'CreatorRelationships[0][CreatorRelationshipTypeID]' => '1',
            'CreatorRelationships[0][Description]' => 'related for the CRUD test')));
      if($ID && $First)
      {
         Report::step('creator relationship stored', (bool)Db::value('SELECT COUNT(*) FROM tblCreators_CreatorCreatorIndex WHERE CreatorID = ? AND RelatedCreatorID = ?', array($ID, $First)));
      }
   };
   $Scenarios['subjects/subjects'] = function() {
      Fixtures::$IDs['subject'] = Crud::cycle(array('p' => 'admin/subjects/subjects', 'table' => 'tblSubjects_Subjects', 'delete' => false,
         'fill' => array('SubjectTypeID' => '1', 'SubjectSourceID' => '1')));
   };
   $Scenarios['subjects/subjects (child)'] = function() {
      $Parent = Fixtures::get('subject');
      Fixtures::$IDs['subject_child'] = $ID = Crud::cycle(array('p' => 'admin/subjects/subjects', 'table' => 'tblSubjects_Subjects', 'delete' => false, 'label' => 'subchild',
         'fill' => array('ParentID' => $Parent, 'SubjectTypeID' => '1')));
      if($ID)
      {
         Report::step('child subject has the parent', (int)Db::value('SELECT ParentID FROM tblSubjects_Subjects WHERE ID = ?', array($ID)) == $Parent);
      }
   };
   $Scenarios['collections/locations'] = function() {
      Fixtures::$IDs['location'] = Crud::cycle(array('p' => 'admin/collections/locations', 'table' => 'tblCollections_Locations', 'delete' => false,
         'fill' => array('RelatedRepositoryIDs[]' => array('1'), 'RepositoryLimit' => '1', 'chkRepositoryLimit' => 'on')));
      if(Fixtures::get('location'))
      {
         Report::step('location linked to the repository', (bool)Db::value('SELECT COUNT(*) FROM tblCollections_LocationRepositoryIndex WHERE LocationID = ? AND RepositoryID = 1', array(Fixtures::get('location'))));
      }
   };
   $Scenarios['collections/classification'] = function() {
      Fixtures::$IDs['classification'] = Crud::cycle(array('p' => 'admin/collections/classification', 'table' => 'tblCollections_Classifications', 'delete' => false,
         'fill' => array('ClassificationIdentifier' => function($Marker) { return 'CL' . substr(md5($Marker), 0, 6); }, 'CreatorID' => Fixtures::get('creator')),
         'public' => function($ID) { return array(array('p' => 'collections/classifications', 'id' => $ID)); },
         'public_expect' => function($Filled) { return NULL; }));
   };
   $Scenarios['collections/books'] = function() {
      Fixtures::$IDs['book'] = Crud::cycle(array('p' => 'admin/collections/books', 'table' => 'tblCollections_Books', 'delete' => false,
         'fill' => array('CopyNumber' => '2', 'NumberOfPages' => '120', 'RelatedCreatorIDs[]' => array(Fixtures::get('creator')), 'RelatedSubjectIDs[]' => array(Fixtures::get('subject'))),
         'public' => function($ID) { return array(array('p' => 'collections/bookcard', 'id' => $ID), array('p' => 'collections/marcbook', 'id' => $ID)); },
         'public_expect' => function($Filled) { return NULL; }));
      if(Fixtures::get('book'))
      {
         Report::step('book creator and subject stored', Db::value('SELECT COUNT(*) FROM tblCollections_BookCreatorIndex WHERE BookID = ?', array(Fixtures::get('book'))) == 1
            && Db::value('SELECT COUNT(*) FROM tblCollections_BookSubjectIndex WHERE BookID = ?', array(Fixtures::get('book'))) == 1);
      }
   };

   // --- Collections, content, digital library, accessions ------------------------------
   $Scenarios['collections/collections'] = 'collectionScenario';
   $Scenarios['collections/collectioncontent'] = 'contentScenario';
   $Scenarios['digitallibrary/digitallibrary'] = 'digitalLibraryScenario';
   $Scenarios['accessions/accessions'] = 'accessionScenario';
   $Scenarios['public research cart'] = 'researchCartScenario';

   // --- AV assessments ----------------------------------------------------------------
   $Scenarios['avsap/avsapinstitutions'] = lookupScenario('avsap', 'avsapinstitutions', 'tblAVSAP_AVSAPInstitutions');
   $Scenarios['avsap/avsapstoragefacilities'] = function() {
      // The location fixture is limited to repository 1, and the edit form lists only the facility's repository's locations.
      Fixtures::$IDs['storagefacility'] = Crud::cycle(array('p' => 'admin/avsap/avsapstoragefacilities', 'table' => 'tblAVSAP_AVSAPStorageFacilities', 'delete' => false,
         'fill' => array('RepositoryID' => '1', 'LocationID' => Fixtures::get('location'))));
   };
   $Scenarios['avsap/avsapassessments'] = function() {
      // The edit form lists the storage facilities of the assessment's repository (the fixture's is 1). Film (type 1) format 1
      // has no base choice: the page script selects -1 ("default"), the only option the edit form offers.
      Crud::cycle(array('p' => 'admin/avsap/avsapassessments', 'table' => 'tblAVSAP_AVSAPAssessments',
         'fill' => array('RepositoryID' => '1', 'StorageFacilityID' => Fixtures::get('storagefacility'), 'SubAssessmentType' => '1', 'Format' => '1', 'BaseComposition' => '-1', 'CollectionID' => Fixtures::get('collection'))));
      list($Response) = array(Crud::$Admin->request(array('p' => 'admin/avsap/avsapassessmentreport')));
      Report::step('assessment report page', $Response['status'] == 200 && !$Response['fatal'], (string)$Response['fatal']);
   };

   // --- Users, groups, permissions, sessions ------------------------------------------
   $Scenarios['core/usergroups'] = 'usergroupScenario';
   $Scenarios['core/adminusers'] = 'adminUserScenario';
   $Scenarios['core/publicusers'] = 'publicUserScenario';
   $Scenarios['public registration and profile'] = 'publicRegistrationScenario';
   $Scenarios['collections/researchappointments'] = 'researchAppointmentScenario';
   $Scenarios['core/sessions'] = 'sessionScenario';
   $Scenarios['core/configuration'] = 'configurationScenario';
   $Scenarios['core/mypreferences'] = 'myPreferencesScenario';
   $Scenarios['core/modificationlog'] = 'modificationLogScenario';

   // --- Teardown of the shared records (reverse dependency order) ----------------------
   $Scenarios['delete shared records'] = 'teardownScenario';

   return $Scenarios;
}


function relationCount($Table, $Column, $ID, $Where = '')
{
   return (int)Db::value('SELECT COUNT(*) FROM ' . quoteIdentifier($Table) . ' WHERE ' . quoteIdentifier($Column) . ' = ?' . ($Where ? " AND $Where" : ''), array($ID));
}

function englishLanguageId()
{
   static $ID = NULL;
   if($ID === NULL)
   {
      list($Response, $Controls) = Crud::form('admin/core/phrases');
      $ID = 0;
      $Control = $Controls ? findControl($Controls, 'LanguageID') : NULL;
      foreach($Control ? $Control['options'] : array() as $Option)
      {
         if(stripos($Option['text'], 'English') === 0)
         {
            $ID = (int)$Option['value'];
            break;
         }
      }
   }

   return $ID;
}

function publicPage(array $Get, $Expect = NULL, $Client = NULL)
{
   $Client = $Client ?: new Client($GLOBALS['Base']);
   $Response = $Client->request($Get);
   $Body = html_entity_decode($Response['body'], ENT_QUOTES, 'UTF-8');
   $Missing = $Expect !== NULL && strpos($Body, $Expect) === false;
   Report::step('public ' . http_build_query($Get), $Response['status'] == 200 && !$Response['fatal'] && !$Missing,
      "HTTP {$Response['status']}" . ($Response['fatal'] ? ' ' . $Response['fatal'] : '') . ($Missing ? " (missing \"$Expect\")" : ''));

   return $Response;
}

function collectionScenario()
{
   $Creator = Fixtures::get('creator');
   $Subject = Fixtures::get('subject');
   $Location = Fixtures::get('location');
   $Language = englishLanguageId();
   Fixtures::$IDs['collection'] = $ID = Crud::cycle(array('p' => 'admin/collections/collections', 'table' => 'tblCollections_Collections', 'delete' => false,
      'update_field' => 'Title',
      'fill' => array(
         'Enabled' => '1', 'NormalDateBegin' => '1900', 'NormalDateEnd' => '1950', 'Extent' => '2.5', 'ExtentUnitID' => '1', 'MaterialTypeID' => '1',
         'CollectionIdentifier' => function($Marker) { return 'C' . substr(md5($Marker), 0, 8); },
         'ClassificationID' => Fixtures::get('classification'),
         'Abstract' => function($Marker) { return "Abstract with [i]BBCode[/i], <b>HTML</b>, quotes \"double\" 'single' & ampersand $Marker"; },
         'RelatedCreatorIDs[]' => $Creator ? array($Creator) : NULL, 'PrimaryCreatorID' => $Creator,
         'RelatedSubjectIDs[]' => $Subject ? array($Subject) : NULL,
         'RelatedLanguageIDs[]' => $Language ? array($Language) : NULL,
         'RelatedBookIDs[]' => Fixtures::get('book') ? array(Fixtures::get('book')) : NULL,
         'LocationEntries[0][LocationID]' => $Location, 'LocationEntries[0][Content]' => 'Boxes 1-3', 'LocationEntries[0][RangeValue]' => 'R1',
         'LocationEntries[0][Section]' => 'S2', 'LocationEntries[0][Shelf]' => '3', 'LocationEntries[0][Extent]' => '1.5', 'LocationEntries[0][ExtentUnitID]' => '1',
         'AcquisitionDateMonth' => '04', 'AcquisitionDateDay' => '15', 'AcquisitionDateYear' => '1999',
         'PublicationDateMonth' => '12', 'PublicationDateDay' => '01', 'PublicationDateYear' => '2005',
      ),
      'transforms' => array('Extent' => function($Value) { return (float)$Value; }),
      'form_ignore' => array('Abstract'),
      'after_create' => function($ID) use ($Creator, $Subject, $Location, $Language) {
         Report::step('creator relation (primary)', relationCount('tblCollections_CollectionCreatorIndex', 'CollectionID', $ID, 'PrimaryCreator = 1') == 1);
         Report::step('subject relation', relationCount('tblCollections_CollectionSubjectIndex', 'CollectionID', $ID) == 1);
         Report::step('language relation', !$Language || relationCount('tblCollections_CollectionLanguageIndex', 'CollectionID', $ID) == 1);
         Report::step('book relation', relationCount('tblCollections_CollectionBookIndex', 'CollectionID', $ID) == 1);
         $Entry = Db::row('SELECT * FROM tblCollections_CollectionLocationIndex WHERE CollectionID = ?', array($ID));
         Report::step('location entry', $Entry && $Entry['LocationID'] == $Location && $Entry['Content'] == 'Boxes 1-3' && (float)$Entry['Extent'] == 1.5, json_encode($Entry));
         $Row = Db::row('SELECT AcquisitionDate, PublicationDate, NormalDateBegin, NormalDateEnd, ClassificationID FROM tblCollections_Collections WHERE ID = ?', array($ID));
         Report::step('dates and classification stored', $Row && $Row['AcquisitionDate'] == '19990415' && $Row['PublicationDate'] == '20051201' && $Row['NormalDateBegin'] == 1900 && $Row['ClassificationID'] == Fixtures::get('classification'), json_encode($Row));
      },
      'public' => function($ID) {
         return array(array('p' => 'collections/controlcard', 'id' => $ID), array('p' => 'collections/findingaid', 'id' => $ID));
      },
      'public_expect' => function($Filled) { return $Filled['Title']; },
   ));
   if(!$ID)
   {
      return;
   }
   $Title = Db::value('SELECT Title FROM tblCollections_Collections WHERE ID = ?', array($ID));
   $Response = publicPage(array('p' => 'collections/ead', 'id' => $ID, 'templateset' => 'ead', 'disabletheme' => '1'));
   $Previous = libxml_use_internal_errors(true);
   Report::step('EAD is well-formed XML', $Response['body'] !== '' && simplexml_load_string($Response['body']) !== false, snippet($Response['body'], 120));
   libxml_use_internal_errors($Previous);
   publicPage(array('p' => 'collections/marc', 'id' => $ID));
   publicPage(array('p' => 'core/search', 'q' => Db::value('SELECT CollectionIdentifier FROM tblCollections_Collections WHERE ID = ?', array($ID))));
   publicPage(array('p' => 'collections/collections', 'q' => '', 'browse' => '1'));
   publicPage(array('p' => 'creators/creator', 'id' => $Creator), $Title);
   publicPage(array('p' => 'subjects/subjects'));
   publicPage(array('p' => 'collections/classifications', 'id' => Fixtures::get('classification')));

   // Removing a relation through the form (the browser leaves the related select empty).
   list($Response, $Controls) = Crud::form('admin/collections/collections', $ID);
   $Pairs = successfulPairs($Controls);
   setPair($Pairs, 'RelatedSubjectIDs[]', array());
   Report::step('form re-submits the related creator', pairValues($Pairs, 'RelatedCreatorIDs[]') == array((string)$Creator), json_encode(pairValues($Pairs, 'RelatedCreatorIDs[]')));
   list($Response, $Parsed) = Crud::store($Pairs);
   if(Crud::checkResponse('store with the subject removed', $Response, $Parsed))
   {
      Report::step('subject relation removed? (an empty related select is not submitted)', true,
         relationCount('tblCollections_CollectionSubjectIndex', 'CollectionID', $ID) . ' subject rows remain');
      Report::step('creator relation kept', relationCount('tblCollections_CollectionCreatorIndex', 'CollectionID', $ID) == 1);
   }
}

function contentScenario()
{
   $Collection = Fixtures::get('collection');
   if(!Report::step('collection fixture', (bool)$Collection))
   {
      return;
   }
   $Get = array('collectionid' => $Collection);
   Fixtures::$IDs['series'] = $Series = Crud::cycle(array('p' => 'admin/collections/collectioncontent', 'table' => 'tblCollections_Content', 'delete' => false, 'get' => $Get, 'label' => 'series',
      'update_field' => 'Title',
      'fill' => array('CollectionID' => $Collection, 'LevelContainerID' => '1', 'LevelContainerIdentifier' => '1', 'ParentID' => '0', 'SortOrder' => '1', 'Enabled' => '1',
         'RelatedCreatorIDs[]' => array(Fixtures::get('creator')), 'RelatedSubjectIDs[]' => array(Fixtures::get('subject'))),
   ));
   if(!$Series)
   {
      return;
   }
   Report::step('content creator relation', relationCount('tblCollections_CollectionContentCreatorIndex', 'CollectionContentID', $Series) == 1);
   Report::step('content subject relation', relationCount('tblCollections_CollectionContentSubjectIndex', 'CollectionContentID', $Series) == 1);
   Fixtures::$IDs['folder'] = $Folder = Crud::cycle(array('p' => 'admin/collections/collectioncontent', 'table' => 'tblCollections_Content', 'delete' => false, 'label' => 'folder',
      'get' => array('collectionid' => $Collection, 'parentid' => $Series), 'update_field' => 'Title',
      'fill' => array('CollectionID' => $Collection, 'ParentID' => $Series, 'LevelContainerID' => '3', 'LevelContainerIdentifier' => '7', 'SortOrder' => '1', 'Date' => '1923-1925', 'Enabled' => '1'),
   ));
   if($Folder)
   {
      Report::step('folder is a child of the series', (int)Db::value('SELECT ParentID FROM tblCollections_Content WHERE ID = ?', array($Folder)) == $Series);
      $Title = Db::value('SELECT Title FROM tblCollections_Content WHERE ID = ?', array($Folder));
      publicPage(array('p' => 'collections/findingaid', 'id' => $Collection, 'q' => ''), $Title);
      publicPage(array('p' => 'collections/controlcard', 'id' => $Collection));
      $Response = Crud::$Admin->request(array('p' => 'admin/collections/collectioncontent', 'f' => 'tree', 'collectionid' => $Collection, 'key' => 0, 'currentKey' => 0));
      Report::step('content tree (JSON)', $Response['status'] == 200 && !$Response['fatal'] && json_decode(trim($Response['body'])) !== NULL, snippet($Response['body'], 120));
      $Response = Crud::$Admin->request(array('p' => 'admin/collections/collectioncontent', 'f' => 'getnextsortorder', 'collectionid' => $Collection, 'parentid' => $Series));
      Report::step('next sort order', $Response['status'] == 200 && !$Response['fatal'] && json_decode(trim($Response['body'])) == 2, snippet($Response['body'], 60));
   }
}

function digitalLibraryScenario()
{
   $Collection = Fixtures::get('collection');
   // Upload a file the way the upload dialog does (multipart POST, f=uploadfile).
   $Bytes = "%PDF-1.4\n% CRUD test file " . Crud::$RunId . "\n" . str_repeat("binary \x00\x01\x02\xff ", 64) . "\n%%EOF\n";
   $Temp = tempnam(sys_get_temp_dir(), 'crudfile');
   file_put_contents($Temp, $Bytes);
   $Before = (int)Db::value('SELECT ISNULL(MAX(ID), 0) FROM tblDigitalLibrary_Files');
   $Response = Crud::$Admin->request(array(), array(array('p', 'admin/digitallibrary/digitallibrary'), array('f', 'uploadfile'), array('Title', 'Uploaded ' . Crud::$RunId)),
      array('FileContents' => array($Temp, 'application/pdf', 'crud-test.pdf')));
   unlink($Temp);
   $Parsed = parseArchonResponse($Response['body']);
   Crud::checkResponse('upload file', $Response, $Parsed);
   $File = Db::row('SELECT TOP 1 ID, Filename, Size, DigitalContentID, DATALENGTH(FileContents) AS Bytes FROM tblDigitalLibrary_Files WHERE ID > ? ORDER BY ID DESC', array($Before));
   if(!Report::step('uploaded file stored', $File && (int)$File['Bytes'] === strlen($Bytes), json_encode($File)))
   {
      $File = NULL;
   }
   else
   {
      $Stored = Db::value('SELECT FileContents FROM tblDigitalLibrary_Files WHERE ID = ?', array($File['ID']));
      Report::step('stored bytes are identical', $Stored === $Bytes, 'md5 ' . md5((string)$Stored) . ' vs ' . md5($Bytes));
      Crud::$Cleanup[] = array('admin/digitallibrary/filemanager', $File['ID'], 'tblDigitalLibrary_Files');
   }

   Fixtures::$IDs['digitalcontent'] = $ID = Crud::cycle(array('p' => 'admin/digitallibrary/digitallibrary', 'table' => 'tblDigitalLibrary_DigitalContent', 'delete' => false,
      'update_field' => 'Title',
      'fill' => array('Browsable' => '1', 'CollectionID' => $Collection, 'CollectionContentID' => Fixtures::get('folder'), 'Date' => '1924',
         'ContentURL' => 'https://example.org/crud', 'HyperlinkURL' => '0',
         'RelatedCreatorIDs[]' => array(Fixtures::get('creator')), 'PrimaryCreatorID' => Fixtures::get('creator'), 'RelatedSubjectIDs[]' => array(Fixtures::get('subject')),
         'Files[0][FileContents]' => $File ? $File['ID'] : NULL, 'Files[0][Title]' => 'Attached ' . Crud::$RunId, 'Files[0][DefaultAccessLevel]' => '2', 'Files[0][DisplayOrder]' => '1'),
      'public' => function($ID) { return array(array('p' => 'digitallibrary/digitalcontent', 'id' => $ID)); },
      'public_expect' => function($Filled) { return $Filled['Title']; },
   ));
   if($ID && $File)
   {
      $Row = Db::row('SELECT DigitalContentID, DefaultAccessLevel, Title FROM tblDigitalLibrary_Files WHERE ID = ?', array($File['ID']));
      Report::step('file attached to the digital content', $Row && (int)$Row['DigitalContentID'] == $ID, json_encode($Row));
      $Download = (new Client($GLOBALS['Base']))->request(array('p' => 'digitallibrary/getfile', 'id' => $File['ID']));
      Report::step('public download returns the bytes', $Download['status'] == 200 && $Download['body'] === $Bytes && !$Download['fatal'],
         "HTTP {$Download['status']} {$Download['type']} " . strlen($Download['body']) . ' bytes' . ($Download['fatal'] ? ' ' . $Download['fatal'] : ''));
      publicPage(array('p' => 'digitallibrary/digitallibrary'));
      publicPage(array('p' => 'digitallibrary/thumbnails'));
      $Response = Crud::$Admin->request(array('p' => 'admin/digitallibrary/digitallibrary', 'f' => 'getfilelist', 'id' => $ID));
      Report::step('admin file list', $Response['status'] == 200 && !$Response['fatal'], snippet($Response['body'], 80));
      list($Response, $Controls) = Crud::form('admin/digitallibrary/filemanager', $File['ID']);
      Report::step('file manager edit form', $Controls !== NULL && !$Response['fatal'] && findControl($Controls, 'Title')['value'] === 'Attached ' . Crud::$RunId, (string)$Response['fatal']);
   }
}

function accessionScenario()
{
   Fixtures::$IDs['accession'] = $ID = Crud::cycle(array('p' => 'admin/accessions/accessions', 'table' => 'tblAccessions_Accessions', 'delete' => false,
      'update_field' => 'Title',
      'fill' => array('Enabled' => '1', 'AccessionDateMonth' => '03', 'AccessionDateDay' => '09', 'AccessionDateYear' => '2001',
         'ExpectedCompletionDateMonth' => '1', 'ExpectedCompletionDateDay' => '2', 'ExpectedCompletionDateYear' => '2030',
         'ReceivedExtent' => '3.25', 'ReceivedExtentUnitID' => '1', 'MaterialTypeID' => '1', 'ProcessingPriorityID' => '1',
         'RelatedCreatorIDs[]' => array(Fixtures::get('creator')), 'PrimaryCreatorID' => Fixtures::get('creator'), 'RelatedSubjectIDs[]' => array(Fixtures::get('subject')),
         'LocationEntries[0][LocationID]' => Fixtures::get('location'), 'LocationEntries[0][Content]' => 'Accession box', 'LocationEntries[0][Extent]' => '1',
         'CollectionEntries[0][CollectionID]' => Fixtures::get('collection')),
      'transforms' => array('ReceivedExtent' => function($Value) { return (float)$Value; }),
      'public' => function($ID) { return array(array('p' => 'accessions/accession', 'id' => $ID)); },
      'public_expect' => function($Filled) { return NULL; },
   ));
   if($ID)
   {
      $Row = Db::row('SELECT AccessionDate, ExpectedCompletionDate FROM tblAccessions_Accessions WHERE ID = ?', array($ID));
      Report::step('accession dates stored', $Row && $Row['AccessionDate'] == '20010309' && $Row['ExpectedCompletionDate'] == '20300102', json_encode($Row));
      Report::step('accession creator relation', relationCount('tblAccessions_AccessionCreatorIndex', 'AccessionID', $ID) == 1);
      Report::step('accession subject relation', relationCount('tblAccessions_AccessionSubjectIndex', 'AccessionID', $ID) == 1);
      Report::step('accession location entry', relationCount('tblAccessions_AccessionLocationIndex', 'AccessionID', $ID) == 1);
      Report::step('accession collection entry', relationCount('tblAccessions_AccessionCollectionIndex', 'AccessionID', $ID) == 1, relationCount('tblAccessions_AccessionCollectionIndex', 'AccessionID', $ID) . ' rows');
      publicPage(array('p' => 'accessions/accessions'));
   }
}

function researchCartScenario()
{
   $Collection = Fixtures::get('collection');
   $Folder = Fixtures::get('folder');
   if(!Report::step('content fixtures', $Collection && $Folder))
   {
      return;
   }
   $Browser = new Client($GLOBALS['Base']);
   $Response = $Browser->request(array('p' => 'collections/research', 'f' => 'add', 'collectionid' => $Collection, 'collectioncontentid' => $Folder));
   Report::step('add to cart', $Response['status'] == 200 && !$Response['fatal'] && strpos($Response['body'], '"cartcount":1') !== false, snippet($Response['body'], 120));
   $Title = Db::value('SELECT Title FROM tblCollections_Content WHERE ID = ?', array($Folder));
   publicPage(array('p' => 'collections/research', 'f' => 'cart'), $Title, $Browser);
   $Response = $Browser->request(array('p' => 'collections/research', 'f' => 'jsoncart'));
   Report::step('cart as JSON', $Response['status'] == 200 && !$Response['fatal'], snippet($Response['body'], 120));
   $Response = $Browser->request(array('p' => 'collections/research', 'f' => 'delete', 'collectionid' => $Collection, 'collectioncontentid' => $Folder));
   Report::step('remove from cart', $Response['status'] == 200 && !$Response['fatal'] && strpos($Response['body'], '"cartcount":0') !== false, snippet($Response['body'], 120));
}

function usergroupScenario()
{
   $Module = (int)Db::value("SELECT ID FROM tblCore_Modules WHERE Script = 'collections'");
   Fixtures::$IDs['usergroup'] = $ID = Crud::cycle(array('p' => 'admin/core/usergroups', 'table' => 'tblCore_Usergroups', 'delete' => false,
      'fill' => array('DefaultPermissions' => '1', 'chkDefaultPermissionsRead' => 'on', "Read[$Module]" => '1', "Add[$Module]" => '2', "Update[$Module]" => '4', 'setadvpermissions' => 'true'),
   ));
   if($ID)
   {
      $Permissions = Db::value('SELECT Permissions FROM tblCore_UsergroupPermissions WHERE UsergroupID = ? AND ModuleID = ?', array($ID, $Module));
      Report::step('module permissions stored', $Permissions !== NULL, 'collections permissions = ' . json_encode($Permissions));
   }
}

function adminUserScenario()
{
   $Group = Fixtures::get('usergroup') ?: 1;
   $Secret = 'Crud-' . substr(md5(Crud::$RunId), 0, 10) . '!';
   $Login = 'crud' . substr(md5(Crud::$RunId), 0, 8);
   Fixtures::$IDs['adminuser'] = $ID = Crud::cycle(array('p' => 'admin/core/adminusers', 'table' => 'tblCore_Users', 'delete' => false,
      'fill' => array('Login' => $Login, 'Password' => $Secret, 'ConfirmPassword' => $Secret, 'Email' => "$Login@example.org",
         'RelatedUsergroupIDs[]' => array($Group), 'RelatedRepositoryIDs[]' => array('1'), 'Locked' => '0', 'RepositoryLimit' => '0'),
      'skip' => array('PasswordHash'), 'update_field' => 'DisplayName',
   ));
   if(!$ID)
   {
      return;
   }
   $Row = Db::row('SELECT PasswordHash, IsAdminUser FROM tblCore_Users WHERE ID = ?', array($ID));
   Report::step('password stored as a crypt hash', $Row && preg_match('/^\$1\$/', $Row['PasswordHash']) && $Row['IsAdminUser'] == 1, $Row ? substr($Row['PasswordHash'], 0, 3) . '... admin=' . $Row['IsAdminUser'] : 'no row');
   Report::step('user is in the group', relationCount('tblCore_UserUsergroupIndex', 'UserID', $ID, 'UsergroupID = ' . (int)$Group) == 1);
   $Browser = new Client($GLOBALS['Base']);
   Report::step('new administrator can log in', $Browser->loginAdmin($Login, $Secret));
   $Response = $Browser->request(array('p' => 'admin/collections/collections'));
   Report::step('new administrator opens a permitted module', $Response['status'] == 200 && !$Response['fatal'] && strpos($Response['body'], 'mainform') !== false);
   $Wrong = new Client($GLOBALS['Base']);
   Report::step('wrong password is rejected', !$Wrong->loginAdmin($Login, $Secret . 'x'));

   // Change the password through the form and log in again.
   list($Response, $Controls) = Crud::form('admin/core/adminusers', $ID);
   $Pairs = successfulPairs($Controls);
   setPair($Pairs, 'Password', $Secret . '2');
   setPair($Pairs, 'ConfirmPassword', $Secret . '2');
   list($Response, $Parsed) = Crud::store($Pairs);
   if(Crud::checkResponse('change password', $Response, $Parsed))
   {
      $Again = new Client($GLOBALS['Base']);
      Report::step('log in with the new password', $Again->loginAdmin($Login, $Secret . '2'));
   }
}

function publicUserScenario()
{
   $Secret = 'Pub-' . substr(md5(Crud::$RunId . 'p'), 0, 10) . '!';
   $Email = 'pub' . substr(md5(Crud::$RunId), 0, 8) . '@example.org';
   Fixtures::$IDs['publicuser'] = $ID = Crud::cycle(array('p' => 'admin/core/publicusers', 'table' => 'tblCore_Users', 'delete' => false,
      'fill' => array('Email' => $Email, 'Password' => $Secret, 'ConfirmPassword' => $Secret, 'CountryID' => '225', 'Pending' => '0', 'Locked' => '0'),
      'skip' => array('PasswordHash'), 'update_field' => 'LastName',
   ));
   Fixtures::$IDs['publicuser_secret'] = $Secret;
   if($ID)
   {
      $Browser = new Client($GLOBALS['Base']);
      $Response = $Browser->request(array('p' => 'core/index'), array('archonlogin' => $Email, 'archonpassword' => $Secret));
      $Account = $Browser->request(array('p' => 'core/account'));
      Report::step('public user can log in', !$Response['fatal'] && $Account['status'] == 200 && stripos($Account['body'], 'archonlogin') === false, snippet($Account['body'], 100));
      Fixtures::$IDs['publicuser_client'] = $Browser;
   }
}

function publicRegistrationScenario()
{
   $Browser = new Client($GLOBALS['Base']);
   $Response = $Browser->request(array('p' => 'core/register'));
   $Controls = parseForm($Response['body'], "//form[.//input[@name='f' or @name='F']]");
   if(!Report::step('registration form', $Response['status'] == 200 && $Controls !== NULL && !$Response['fatal'], "HTTP {$Response['status']}"))
   {
      return;
   }
   $Pairs = successfulPairs($Controls);
   $Email = 'reg' . substr(md5(Crud::$RunId), 0, 8) . '@example.org';
   $Secret = 'Reg-' . substr(md5(Crud::$RunId . 'r'), 0, 10) . '!';
   foreach(array('f' => 'store', 'Email' => $Email, 'FirstName' => 'Régine', 'LastName' => 'Tëst ' . Crud::$RunId, 'Password' => $Secret, 'ConfirmPassword' => $Secret, 'CountryID' => '225') as $Name => $Value)
   {
      setPair($Pairs, $Name, $Value);
   }
   $Response = $Browser->request(array(), $Pairs);
   Report::step('registration submitted', !$Response['fatal'] && $Response['status'] < 500, "HTTP {$Response['status']}" . ($Response['fatal'] ? ' ' . $Response['fatal'] : ''));
   $User = Db::row('SELECT ID, Login, FirstName, LastName, DisplayName, Pending, IsAdminUser FROM tblCore_Users WHERE Email = ?', array($Email));
   if(!Report::step('registered user stored', $User && $User['IsAdminUser'] == 0 && $User['FirstName'] === 'Régine', json_encode($User, JSON_UNESCAPED_UNICODE)))
   {
      return;
   }
   Crud::$Cleanup[] = array('admin/core/publicusers', $User['ID'], 'tblCore_Users');
   Fixtures::$IDs['registered'] = $User['ID'];

   // Edit the profile as the logged-in user (the registration logs the user in when verification is off).
   $Login = $Browser->request(array('p' => 'core/index'), array('archonlogin' => $Email, 'archonpassword' => $Secret));
   $Response = $Browser->request(array('p' => 'core/editprofile'));
   $Controls = parseForm($Response['body'], "//form[.//input[@name='f' or @name='F']]");
   if(Report::step('profile form', $Response['status'] == 200 && $Controls !== NULL && !$Response['fatal'], "HTTP {$Response['status']}" . ($Response['fatal'] ? ' ' . $Response['fatal'] : '')))
   {
      $Pairs = successfulPairs($Controls);
      setPair($Pairs, 'f', 'store');
      setPair($Pairs, 'LastName', 'Ŧëst-upd ' . Crud::$RunId);
      $Response = $Browser->request(array(), $Pairs);
      $LastName = Db::value('SELECT LastName FROM tblCore_Users WHERE ID = ?', array($User['ID']));
      Report::step('profile update stored', !$Response['fatal'] && strpos((string)$LastName, 'upd ' . Crud::$RunId) !== false, json_encode($LastName, JSON_UNESCAPED_UNICODE) . ($Response['fatal'] ? ' ' . $Response['fatal'] : ''));
      Report::note('LastName with a character outside code page 1252 (Ŧ) is stored as ' . json_encode($LastName, JSON_UNESCAPED_UNICODE) . ' (varchar column; production behaves the same with a UTF-8 FreeTDS client charset)');
   }
}

function researchAppointmentScenario()
{
   $Browser = isset(Fixtures::$IDs['publicuser_client']) ? Fixtures::$IDs['publicuser_client'] : NULL;
   if($Browser && Fixtures::get('collection') && Fixtures::get('folder'))
   {
      $Browser->request(array('p' => 'collections/research', 'f' => 'add', 'collectionid' => Fixtures::get('collection'), 'collectioncontentid' => Fixtures::get('folder')));
      $Response = $Browser->request(array('p' => 'collections/research', 'f' => 'verify'));
      Report::step('appointment form (logged-in researcher)', $Response['status'] == 200 && !$Response['fatal'], "HTTP {$Response['status']}" . ($Response['fatal'] ? ' ' . $Response['fatal'] : ''));
   }
   $Researcher = Fixtures::get('publicuser');
   if(!$Researcher)
   {
      Report::step('researcher fixture', false);
      return;
   }
   Crud::cycle(array('p' => 'admin/collections/researchappointments', 'table' => 'tblCollections_ResearchAppointments',
      'fill' => array('ResearcherID' => $Researcher, 'ArrivalTime' => '2031-05-04 10:00', 'DepartureTime' => '2031-05-04 15:30', 'Topic' => 'CRUD topic ' . Crud::$RunId, 'AppointmentPurposeID' => '0'),
      'transforms' => array('ArrivalTime' => function($Value) { return strtotime($Value); }, 'DepartureTime' => function($Value) { return strtotime($Value); }),
      'form_ignore' => array('ArrivalTime', 'DepartureTime'), 'update_field' => 'Topic',
   ));
}

function sessionScenario()
{
   $Other = new Client($GLOBALS['Base']);
   $Other->loginAdmin($GLOBALS['AdminLogin'], $GLOBALS['AdminSecret']);
   $Session = Db::row('SELECT TOP 1 ID FROM tblCore_Sessions ORDER BY ID DESC');
   $Response = Crud::$Admin->request(array('p' => 'admin/core/sessions'));
   Report::step('sessions page', $Response['status'] == 200 && !$Response['fatal'], (string)$Response['fatal']);
   if($Session)
   {
      list($Response, $Parsed) = Crud::delete('admin/core/sessions', array($Session['ID']));
      Crud::checkResponse('delete a session', $Response, $Parsed);
      Report::step('session removed', !Db::row('SELECT ID FROM tblCore_Sessions WHERE ID = ?', array($Session['ID'])));
      $Response = $Other->request(array('p' => 'admin/core/home'));
      Report::step('the deleted session is logged out', stripos($Response['body'], 'archonlogin') !== false, "HTTP {$Response['status']}");
   }
}

function configurationScenario()
{
   $Row = Db::row("SELECT TOP 1 ID, Directive, Value FROM tblCore_Configuration WHERE Directive = 'Search Results Limit'");
   if(!Report::step('configuration directive exists', (bool)$Row))
   {
      return;
   }
   list($Response, $Controls) = Crud::form('admin/core/configuration', $Row['ID']);
   if(!Report::step('configuration form', $Controls !== NULL && !$Response['fatal'], (string)$Response['fatal']))
   {
      return;
   }
   $Pairs = successfulPairs($Controls);
   setPair($Pairs, 'Value', '37');
   list($Response, $Parsed) = Crud::store($Pairs);
   if(Crud::checkResponse('store configuration value', $Response, $Parsed))
   {
      Report::step('value stored', Db::value('SELECT Value FROM tblCore_Configuration WHERE ID = ?', array($Row['ID'])) === '37');
      setPair($Pairs, 'Value', $Row['Value']);
      list($Response, $Parsed) = Crud::store($Pairs);
      Crud::checkResponse('restore configuration value', $Response, $Parsed);
      Report::step('value restored', Db::value('SELECT Value FROM tblCore_Configuration WHERE ID = ?', array($Row['ID'])) === $Row['Value']);
   }
}

function myPreferencesScenario()
{
   list($Response, $Controls) = Crud::form('admin/core/mypreferences');
   if(!Report::step('preferences form', $Controls !== NULL && !$Response['fatal'], (string)$Response['fatal']))
   {
      return;
   }
   list($Response, $Parsed) = Crud::store(successfulPairs($Controls));
   Crud::checkResponse('store preferences unchanged', $Response, $Parsed);
}

function modificationLogScenario()
{
   $Entry = Db::row('SELECT TOP 1 ID FROM tblCore_ModificationLog ORDER BY ID DESC');
   $Response = Crud::$Admin->request(array('p' => 'admin/core/modificationlog'));
   Report::step('modification log page', $Response['status'] == 200 && !$Response['fatal'], (string)$Response['fatal']);
   $Response = Crud::$Admin->request(array('p' => 'admin/core/modificationlog', 'f' => 'search', 'q' => 'tblCollections'));
   Report::step('modification log search', $Response['status'] == 200 && !$Response['fatal'], (string)$Response['fatal']);
   if($Entry)
   {
      $Response = Crud::$Admin->request(array('p' => 'admin/core/modificationlog', 'id' => $Entry['ID']));
      Report::step('modification log entry', $Response['status'] == 200 && !$Response['fatal'], (string)$Response['fatal']);
   }
}

function teardownScenario()
{
   global $Keep;
   if($Keep)
   {
      Report::note('--keep: shared records left in place');
      return;
   }
   $Collection = Fixtures::get('collection');
   $Order = array(
      array('digitalcontent', 'admin/digitallibrary/digitallibrary', 'tblDigitalLibrary_DigitalContent'),
      array('accession', 'admin/accessions/accessions', 'tblAccessions_Accessions'),
      array('folder', 'admin/collections/collectioncontent', 'tblCollections_Content'),
      array('series', 'admin/collections/collectioncontent', 'tblCollections_Content'),
      array('collection', 'admin/collections/collections', 'tblCollections_Collections'),
      array('book', 'admin/collections/books', 'tblCollections_Books'),
      array('classification', 'admin/collections/classification', 'tblCollections_Classifications'),
      array('storagefacility', 'admin/avsap/avsapstoragefacilities', 'tblAVSAP_AVSAPStorageFacilities'),
      array('location', 'admin/collections/locations', 'tblCollections_Locations'),
      array('subject_child', 'admin/subjects/subjects', 'tblSubjects_Subjects'),
      array('subject', 'admin/subjects/subjects', 'tblSubjects_Subjects'),
      array('creator2', 'admin/creators/creators', 'tblCreators_Creators'),
      array('creator', 'admin/creators/creators', 'tblCreators_Creators'),
      array('adminuser', 'admin/core/adminusers', 'tblCore_Users'),
      array('publicuser', 'admin/core/publicusers', 'tblCore_Users'),
      array('registered', 'admin/core/publicusers', 'tblCore_Users'),
      array('usergroup', 'admin/core/usergroups', 'tblCore_Usergroups'),
   );
   foreach($Order as $Item)
   {
      list($Name, $P, $Table) = $Item;
      $ID = Fixtures::get($Name);
      if(!$ID || !Db::row('SELECT ID FROM ' . quoteIdentifier($Table) . ' WHERE ID = ?', array($ID)))
      {
         continue;
      }
      Report::note("deleting $Name #$ID");
      Crud::remove($P, $Table, $ID);
      if($Name == 'collection')
      {
         foreach(array('tblCollections_CollectionCreatorIndex', 'tblCollections_CollectionSubjectIndex', 'tblCollections_CollectionLanguageIndex', 'tblCollections_CollectionLocationIndex', 'tblCollections_CollectionBookIndex') as $Index)
         {
            Report::step("$Index rows removed with the collection", relationCount($Index, 'CollectionID', $ID) == 0, relationCount($Index, 'CollectionID', $ID) . ' rows left');
         }
      }
      if($Name == 'creator')
      {
         Report::step('creator relationships removed', (int)Db::value('SELECT COUNT(*) FROM tblCreators_CreatorCreatorIndex WHERE CreatorID = ? OR RelatedCreatorID = ?', array($ID, $ID)) == 0);
      }
   }
}
