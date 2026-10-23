<?php
/**
 * Builds a synthetic Archon 3.21 data set for one production install profile on top of a fresh install.
 *
 * Record counts, identifier and date shapes, tree sizes and edge cases follow
 * tests/research/live-survey/summary.md; all text is generated (SampleText) and no live record is copied.
 * The output is deterministic for a given profile, size and seed.
 */
class SampleGenerator
{
   const ENGLISH = 2081;
   const COUNTRY_US = 226;

   /** Standard-size record counts per profile; "large" approximates the live installs. */
   public static $Profiles = array(
      'archives' => array(
         'repositories' => array(
            array('Name' => 'Sample University Archives', 'Code' => 'UA', 'Email' => 'archives@example.edu', 'Share' => 0.86),
            array('Name' => 'Sample Center for American Music', 'Code' => 'SCAM', 'Email' => 'music@example.edu', 'Share' => 0.14),
         ),
         'standard' => array('collections' => 420, 'creators' => 320, 'subjects' => 2000, 'digital' => 400, 'accessions' => 60, 'locations' => 40, 'books' => 0, 'researchers' => 25),
         'large' => array('collections' => 7300, 'creators' => 3000, 'subjects' => 9000, 'digital' => 2500, 'accessions' => 300, 'locations' => 120, 'books' => 0, 'researchers' => 120),
         'classifications' => 'record-series', 'top_classifications' => 20,
         'config' => array('collections' => array('Classification Identifier Minimum Length' => '1', 'Collection Identifier Minimum Length' => '1')),
         'trees' => array('deep' => 9300, 'music' => 6500),
      ),
      'ihlc' => array(
         'repositories' => array(array('Name' => 'Sample Illinois History and Lincoln Collections', 'Code' => 'IHLC', 'Email' => 'ihlc@example.edu', 'Share' => 1)),
         'standard' => array('collections' => 240, 'creators' => 220, 'subjects' => 700, 'digital' => 60, 'accessions' => 15, 'locations' => 20, 'books' => 0, 'researchers' => 15),
         'large' => array('collections' => 1200, 'creators' => 900, 'subjects' => 2500, 'digital' => 300, 'accessions' => 60, 'locations' => 40, 'books' => 0, 'researchers' => 40),
         'classifications' => 'none', 'top_classifications' => 0,
         'config' => array('collections' => array('Classification Identifier Minimum Length' => '2', 'Collection Identifier Minimum Length' => '3')),
         'trees' => array('pdf-guides' => 0),
      ),
      'rbml' => array(
         'repositories' => array(array('Name' => 'Sample Rare Book & Manuscript Library', 'Code' => 'RBML', 'Email' => 'rbml@example.edu', 'Share' => 1)),
         'standard' => array('collections' => 130, 'creators' => 140, 'subjects' => 200, 'digital' => 150, 'accessions' => 15, 'locations' => 20, 'books' => 40, 'researchers' => 15),
         'large' => array('collections' => 320, 'creators' => 350, 'subjects' => 500, 'digital' => 600, 'accessions' => 40, 'locations' => 40, 'books' => 150, 'researchers' => 40),
         'classifications' => 'rbml', 'top_classifications' => 2,
         'config' => array('collections' => array('Classification Identifier Minimum Length' => '2', 'Collection Identifier Minimum Length' => '1')),
         'trees' => array('alphanumeric' => 1850),
      ),
      'ala' => array(
         'repositories' => array(array('Name' => 'Sample American Library Association Archives', 'Code' => 'ALA', 'Email' => 'ala@example.edu', 'Share' => 1)),
         'standard' => array('collections' => 260, 'creators' => 160, 'subjects' => 900, 'digital' => 150, 'accessions' => 15, 'locations' => 20, 'books' => 0, 'researchers' => 15),
         'large' => array('collections' => 1700, 'creators' => 700, 'subjects' => 3000, 'digital' => 600, 'accessions' => 60, 'locations' => 40, 'books' => 0, 'researchers' => 40),
         'classifications' => 'record-series', 'top_classifications' => 12,
         'config' => array('collections' => array('Classification Identifier Minimum Length' => '1', 'Collection Identifier Minimum Length' => '1')),
         'trees' => array('pdf-guides' => 0),
      ),
   );

   /** Level/container types added to the installer's Series, Box, Folder, Sub-Series and Item. */
   private static $ExtraLevels = array(
      // name => array(IntellectualLevel, PhysicalContainer, EADLevel, GlobalNumbering)
      'File' => array(1, 0, 'file', 0),
      'Reel' => array(0, 1, '', 0),
      'Volume' => array(1, 1, 'item', 0),
      'Oversize Folder' => array(1, 1, 'file', 1),
      'Steamer Trunk' => array(0, 1, '', 1),
      'Oversize Portfolio Case' => array(0, 1, '', 1),
      'Furniture Piece' => array(1, 1, 'item', 1),
   );

   /** EAD element IDs of the user fields used on content (packages/collections EAD elements). */
   private static $UserFieldElements = array('Note' => 19, 'Other Information' => 12, 'Physical Description' => 4, 'Material Specific Details' => 18,
      'Dimensions' => 21, 'Physical Facet' => 23, 'Use Restrictions' => 17, 'Access Restrictions' => 1);

   private static $Languages = array(2099 => 'French', 2114 => 'German', 2372 => 'Spanish', 2166 => 'Italian', 2315 => 'Polish', 2167 => 'Japanese',
      2330 => 'Russian', 2211 => 'Latin', 2059 => 'Czech', 2035 => 'Chinese', 2379 => 'Swedish', 2072 => 'Dutch', 2134 => 'Hebrew', 2441 => 'Yiddish');

   public $Log;
   private $Db;
   private $Name;
   private $Profile;
   private $Size;
   private $Count;
   private $Scale;
   private $Secret;
   private $Queue = array();
   private $Next = array();
   private $Level = array();
   private $Unit = array();
   private $Material = array();
   private $Priority = array();
   private $Purpose = array();
   private $Repositories = array();
   private $Locations = array();
   private $Classifications = array();
   private $ClassificationCounters = array();
   private $Creators = array();
   private $PopularCreator = 0;
   private $Subjects = array();
   private $Collections = array();
   private $Content = array();
   private $Researchers = array();
   private $AdminID = 1;
   private $Now;

   public function __construct(SampleDatabase $Db, $Profile, $Size, $Secret)
   {
      if(!isset(self::$Profiles[$Profile]))
      {
         throw new InvalidArgumentException("unknown profile '$Profile' (" . implode(', ', array_keys(self::$Profiles)) . ')');
      }
      if(!in_array($Size, array('small', 'standard', 'large'), true))
      {
         throw new InvalidArgumentException("unknown size '$Size' (small, standard, large)");
      }
      $this->Db = $Db;
      $this->Name = $Profile;
      $this->Profile = self::$Profiles[$Profile];
      $this->Size = $Size;
      $this->Secret = $Secret;
      $this->Now = 1767225600; // 2026-01-01, fixed so that the data set is reproducible
      if($Size == 'large')
      {
         $this->Count = $this->Profile['large'];
         $this->Scale = 1.0;
      }
      else
      {
         $this->Scale = $Size == 'small' ? 0.1 : 1.0;
         $this->Count = array();
         foreach($this->Profile['standard'] as $Key => $Value)
         {
            $this->Count[$Key] = $Size == 'small' ? max($Value ? 4 : 0, (int)round($Value * 0.12)) : $Value;
         }
      }
      $this->Log = function($Message) { echo $Message . "\n"; };
   }

   private function log($Message)
   {
      call_user_func($this->Log, $Message);
   }

   public function run()
   {
      if((int)$this->Db->value('SELECT COUNT(*) FROM tblCollections_Collections') > 0)
      {
         throw new RuntimeException('the database already has collections; sample data is only added to a fresh installation');
      }
      $this->AdminID = (int)$this->Db->value('SELECT ISNULL(MIN(ID), 1) FROM tblCore_Users WHERE IsAdminUser = 1');

      $this->lookups();
      $this->repositories();
      $this->configuration();
      $this->users();
      $this->locations();
      $this->classifications();
      $this->creators();
      $this->subjects();
      $this->collections();
      $this->digitalContent();
      $this->accessions();
      $this->books();
      $this->research();
      $this->flush();
      $this->encodingSamples();
   }

   /* ------------------------------------------------------------ helpers */

   private function id($Table)
   {
      if(!isset($this->Next[$Table]))
      {
         $this->Next[$Table] = $this->Db->nextId($Table);
      }
      return $this->Next[$Table]++;
   }

   private function add($Table, array $Row)
   {
      if(!isset($Row['ID']))
      {
         $Row = array('ID' => $this->id($Table)) + $Row;
      }
      $this->Queue[$Table][] = $Row;
      $Limit = $Table == 'tblDigitalLibrary_Files' ? 10 : 2000;
      if(count($this->Queue[$Table]) >= $Limit)
      {
         $this->flushTable($Table);
      }
      return $Row['ID'];
   }

   private function flushTable($Table)
   {
      if(!empty($this->Queue[$Table]))
      {
         $this->Db->insert($Table, $this->Queue[$Table]);
         $this->Queue[$Table] = array();
      }
   }

   private function flush()
   {
      foreach(array_keys($this->Queue) as $Table)
      {
         $this->flushTable($Table);
      }
   }

   /** Optional text: present with probability $Probability, otherwise NULL or (a quarter of the time) ''. */
   private function maybe($Probability, $Value)
   {
      if(SampleText::chance($Probability))
      {
         return is_callable($Value) ? $Value() : $Value;
      }
      return SampleText::chance(0.25) ? '' : NULL;
   }

   private function scaled($Number, $Minimum = 1)
   {
      return max($Minimum, (int)round($Number * $this->Scale));
   }

   private function repository()
   {
      $Roll = mt_rand() / mt_getrandmax();
      foreach($this->Repositories as $ID => $Share)
      {
         if(($Roll -= $Share) <= 0)
         {
            return $ID;
         }
      }
      return key($this->Repositories);
   }

   private function extentUnit()
   {
      $Units = array('Cubic Feet' => 45, 'Linear Feet' => 15, 'Items' => 12, 'Boxes' => 6, 'Folders' => 5, 'Photographs' => 3, 'Letters' => 2,
         'Box(es)' => 3, 'Item(s)' => 3, 'cubic feet' => 2, 'item' => 1, 'Pamphlet Binder' => 1, 'Volumes' => 2);
      $Roll = mt_rand(1, array_sum($Units));
      foreach($Units as $Unit => $Weight)
      {
         if(($Roll -= $Weight) <= 0)
         {
            return $this->Unit[$Unit];
         }
      }
      return $this->Unit['Cubic Feet'];
   }

   private function languages()
   {
      $Roll = mt_rand(1, 100);
      if($Roll <= 85)
      {
         return array(self::ENGLISH);
      }
      $Other = array_rand(self::$Languages);
      return $Roll <= 95 ? array(self::ENGLISH, $Other) : array($Other);
   }

   private function passwordHash()
   {
      $Salt = '';
      $Characters = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
      for($i = 0; $i < 8; $i++)
      {
         $Salt .= $Characters[mt_rand(0, 63)];
      }
      return crypt($this->Secret, '$1$' . $Salt . '$');
   }

   /* ------------------------------------------------------------ lookups and setup */

   private function lookups()
   {
      foreach($this->Db->query('SELECT ID, LevelContainer FROM tblCollections_LevelContainers') as $Row)
      {
         $this->Level[$Row['LevelContainer']] = (int)$Row['ID'];
      }
      foreach(self::$ExtraLevels as $Name => $Definition)
      {
         if(!isset($this->Level[$Name]))
         {
            $this->Level[$Name] = $this->add('tblCollections_LevelContainers', array('LevelContainer' => $Name, 'IntellectualLevel' => $Definition[0],
               'PhysicalContainer' => $Definition[1], 'EADLevel' => $Definition[2], 'PrimaryEADLevel' => 0, 'GlobalNumbering' => $Definition[3]));
         }
      }

      foreach($this->Db->query('SELECT ID, ExtentUnit FROM tblCollections_ExtentUnits') as $Row)
      {
         $this->Unit[$Row['ExtentUnit']] = (int)$Row['ID'];
      }
      foreach(array('Box(es)', 'Item(s)', 'Pamphlet Binder', 'cubic feet', 'item', 'Volumes', 'Reels', 'Gigabytes') as $Unit)
      {
         if(!isset($this->Unit[$Unit]))
         {
            $this->Unit[$Unit] = $this->add('tblCollections_ExtentUnits', array('ExtentUnit' => $Unit));
         }
      }

      foreach($this->Db->query('SELECT ID, MaterialType FROM tblCollections_MaterialTypes') as $Row)
      {
         $this->Material[$Row['MaterialType']] = (int)$Row['ID'];
      }
      foreach(array('Audiovisual Materials', 'Photographs', 'Sheet Music', 'Artificial Collection', 'Organizational Records') as $Type)
      {
         if(!isset($this->Material[$Type]))
         {
            $this->Material[$Type] = $this->add('tblCollections_MaterialTypes', array('MaterialType' => $Type));
         }
      }

      foreach($this->Db->query('SELECT ID FROM tblAccessions_ProcessingPriorities') as $Row)
      {
         $this->Priority[] = (int)$Row['ID'];
      }
      foreach(array('Academic research', 'Genealogy', 'Publication or broadcast', 'Class assignment', 'Exhibit or loan', 'Other') as $Purpose)
      {
         $this->Purpose[] = $this->add('tblCollections_ResearchAppointmentPurposes', array('ResearchAppointmentPurpose' => $Purpose));
      }
      foreach(array('Faculty', 'Graduate student', 'Undergraduate student', 'Independent scholar', 'Genealogist', 'Staff') as $Type)
      {
         $this->add('tblCollections_ResearcherTypes', array('ResearcherType' => $Type));
      }
      $this->flush();
   }

   private function repositories()
   {
      $First = (int)$this->Db->value('SELECT MIN(ID) FROM tblCore_Repositories');
      $Columns = $this->Db->columns('tblCore_Repositories');
      foreach($this->Profile['repositories'] as $Index => $Repository)
      {
         $Values = array('Name' => $Repository['Name'], 'Code' => $Repository['Code'], 'Administrator' => implode(' ', array_reverse(SampleText::person())),
            'Address' => (100 + 10 * $Index) . ' Sample Hall', 'Address2' => $Index ? 'Room ' . mt_rand(100, 450) : NULL, 'City' => 'Urbana', 'State' => 'IL',
            'CountryID' => self::COUNTRY_US, 'ZIPCode' => '61801', 'Phone' => '217-555-01' . sprintf('%02d', $Index * 10 + 1), 'Email' => $Repository['Email'],
            'URL' => 'https://example.edu/' . strtolower($Repository['Code']) . '/', 'EmailSignature' => $Repository['Name'] . "\nhttps://example.edu/");
         if($Index == 0 && $First)
         {
            $Sets = array();
            $Params = array();
            foreach($Values as $Column => $Value)
            {
               if(isset($Columns[$Column]))
               {
                  $Sets[] = "$Column = ?";
                  $Params[] = $Value;
               }
            }
            $Params[] = $First;
            $this->Db->query('UPDATE tblCore_Repositories SET ' . implode(', ', $Sets) . ' WHERE ID = ?', $Params);
            $this->Repositories[$First] = $Repository['Share'];
         }
         else
         {
            $Values['ResearchFunctionality'] = (int)$this->Db->value('SELECT ISNULL(MAX(ResearchFunctionality), 0) FROM tblCore_Repositories');
            $Values['StateProvinceID'] = 0;
            $this->Repositories[$this->add('tblCore_Repositories', $Values)] = $Repository['Share'];
         }
      }
      $this->flush();
   }

   private function configuration()
   {
      foreach($this->Profile['config'] as $Package => $Directives)
      {
         foreach($Directives as $Directive => $Value)
         {
            $this->Db->query('UPDATE tblCore_Configuration SET Value = ? WHERE Directive = ? AND PackageID = (SELECT ID FROM tblCore_Packages WHERE APRCode = ?)',
               array($Value, $Directive, $Package));
         }
      }
   }

   private function users()
   {
      $Accounts = array(
         // login => array(first, last, staff usergroup or 0 for a public (researcher) account)
         'teststaff' => array('Staff', 'Tester', 'Power Users'),
         'testreader' => array('Reader', 'Tester', 'Read-Only Users'),
         'testresearcher' => array('Researcher', 'Tester', NULL),
      );
      $Groups = array();
      foreach($this->Db->query('SELECT ID, Usergroup FROM tblCore_Usergroups') as $Row)
      {
         $Groups[$Row['Usergroup']] = (int)$Row['ID'];
      }
      foreach($Accounts as $Login => $Account)
      {
         $ID = $this->add('tblCore_Users', array('Login' => $Login, 'Email' => "$Login@example.edu", 'PasswordHash' => $this->passwordHash(),
            'FirstName' => $Account[0], 'LastName' => $Account[1], 'DisplayName' => "{$Account[0]} {$Account[1]}", 'IsAdminUser' => $Account[2] ? 1 : 0,
            'RegisterTime' => $this->Now - 86400 * 400, 'Pending' => 0, 'LanguageID' => self::ENGLISH, 'CountryID' => self::COUNTRY_US,
            'RepositoryLimit' => 0, 'Locked' => 0));
         if($Account[2] && isset($Groups[$Account[2]]))
         {
            $this->add('tblCore_UserUsergroupIndex', array('UserID' => $ID, 'UsergroupID' => $Groups[$Account[2]]));
         }
         if(!$Account[2])
         {
            $this->Researchers[] = $ID;
         }
      }
      for($i = 1; $i < $this->Count['researchers']; $i++)
      {
         list($Last, $First) = SampleText::person();
         $Login = strtolower(preg_replace('/[^A-Za-z]/', '', $First . substr($Last, 0, 1))) . $i;
         $this->Researchers[] = $this->add('tblCore_Users', array('Login' => $Login, 'Email' => "$Login@example.org",
            'PasswordHash' => '$1$' . substr(md5($Login), 0, 8) . '$' . substr(md5($Login . 'x'), 0, 22),
            'FirstName' => $First, 'LastName' => $Last, 'DisplayName' => SampleText::chance(0.3) ? "$First $Last" : NULL, 'IsAdminUser' => 0,
            'RegisterTime' => $this->Now - mt_rand(1, 3000) * 86400, 'Pending' => SampleText::chance(0.1) ? 1 : 0, 'LanguageID' => self::ENGLISH,
            'CountryID' => SampleText::chance(0.9) ? self::COUNTRY_US : 0, 'RepositoryLimit' => 0, 'Locked' => SampleText::chance(0.05) ? 1 : 0));
      }
      $this->flush();
   }

   private function locations()
   {
      for($i = 1; $i <= $this->Count['locations']; $i++)
      {
         switch($i % 5)
         {
            case 0: $Name = 'Off-site storage, Bay ' . mt_rand(1, 40); break;
            case 1: $Name = 'Room ' . mt_rand(10, 450) . ', Range ' . mt_rand(1, 60); break;
            case 2: $Name = 'Annex ' . chr(64 + mt_rand(1, 6)) . '-' . sprintf('%02d', mt_rand(1, 30)); break;
            case 3: $Name = 'Stacks ' . mt_rand(1, 9) . ', Map case ' . mt_rand(1, 20); break;
            default: $Name = 'Vault ' . mt_rand(1, 4);
         }
         $Limited = count($this->Repositories) > 1 && SampleText::chance(0.3);
         $ID = $this->add('tblCollections_Locations', array('Location' => $Name . " (#$i)", 'Description' => $this->maybe(0.3, 'Climate controlled'), 'RepositoryLimit' => $Limited ? 1 : 0));
         if($Limited)
         {
            $this->add('tblCollections_LocationRepositoryIndex', array('LocationID' => $ID, 'RepositoryID' => $this->repository()));
         }
         $this->Locations[] = $ID;
      }
      $this->flush();
   }

   private function classifications()
   {
      $Style = $this->Profile['classifications'];
      if($Style == 'none')
      {
         return;
      }
      if($Style == 'rbml')
      {
         foreach(array('1' => 'Manuscripts', '2' => 'Named and Subject Collections') as $Identifier => $Title)
         {
            $this->Classifications[] = $this->add('tblCollections_Classifications', array('ClassificationIdentifier' => $Identifier, 'Title' => $Title,
               'Description' => NULL, 'ParentID' => 0, 'CreatorID' => 0));
         }
         $this->flush();
         return;
      }

      $Top = $this->Size == 'large' ? ($this->Name == 'archives' ? 66 : 40) : $this->scaled($this->Profile['top_classifications'], 4);
      $Numbers = $this->Name == 'archives' ? array_values(array_diff(range(0, 67), array(42, 47))) : range(1, 99);
      $Numbers = array_slice(SampleText::shuffled($Numbers), 0, $Top);
      sort($Numbers);
      foreach($Numbers as $Number)
      {
         $Field = SampleText::pick(SampleText::$Fields);
         $Title = $this->Name == 'ala' ? SampleText::pick(array('Office for ', 'Division of ', 'Committee on ', 'Round Table on ')) . $Field
            : SampleText::pick(array('College of ', 'School of ', 'Office of ', 'Institute of ')) . $Field;
         $Parent = $this->add('tblCollections_Classifications', array('ClassificationIdentifier' => (string)$Number, 'Title' => $Title,
            'Description' => $this->maybe(0.2, function() { return SampleText::sentence(); }), 'ParentID' => 0, 'CreatorID' => 0));
         $Children = mt_rand(2, $this->Size == 'small' ? 3 : 8);
         for($c = 1; $c <= $Children; $c++)
         {
            $Child = $this->add('tblCollections_Classifications', array('ClassificationIdentifier' => (string)$c,
               'Title' => SampleText::pick(array('Department of ', 'Office of ', 'Program in ', '')) . SampleText::pick(SampleText::$Fields)
                  . SampleText::pick(array('', '', ' Alumni', ' Student Organizations', ' Faculty Papers')),
               'Description' => NULL, 'ParentID' => $Parent, 'CreatorID' => 0));
            $this->Classifications[] = $Child;
            if(SampleText::chance(0.15))
            {
               for($g = 1, $Count = mt_rand(1, 3); $g <= $Count; $g++)
               {
                  $this->Classifications[] = $this->add('tblCollections_Classifications', array('ClassificationIdentifier' => (string)$g,
                     'Title' => ucfirst(SampleText::pick(SampleText::$Topics)), 'Description' => NULL, 'ParentID' => $Child, 'CreatorID' => 0));
               }
            }
         }
      }
      $this->flush();
   }

   /* ------------------------------------------------------------ creators and subjects */

   private function creatorName($Type)
   {
      if($Type == 22)
      {
         return array(SampleText::corporateName(), NULL);
      }
      list($Last, $First) = SampleText::person();
      if($Type == 20)
      {
         return array("$Last family", NULL);
      }
      $Dates = SampleText::chance(0.5) ? SampleText::personDates() : NULL;
      return array("$Last, $First", $Dates);
   }

   private function creators()
   {
      $Count = $this->Count['creators'];
      $Repositories = array_keys($this->Repositories);
      for($i = 0; $i < $Count; $i++)
      {
         $Roll = mt_rand(1, 100);
         $Type = $Roll <= 73 ? 19 : ($Roll <= 93 ? 22 : ($Roll <= 98 ? 20 : 21));
         if($i == 0)
         {
            $Type = 22;
         }
         list($Name, $Dates) = $this->creatorName($Type);
         // A fifth of the dated names carry their dates in the name itself, as LCNAF headings do.
         $InName = $Dates !== NULL && SampleText::chance(0.2);
         $Biography = $this->maybe(0.3, function() { return SampleText::text(mt_rand(300, 3000)); });
         $ID = $this->add('tblCreators_Creators', array(
            'Name' => $i == 0 ? 'Sample University. Board of Trustees' : ($InName ? "$Name, $Dates" : $Name),
            'NameFullerForm' => $Type == 19 ? $this->maybe(0.05, function() use ($Name) { return $Name . ' ' . SampleText::pick(SampleText::$GivenNames); }) : NULL,
            'NameVariants' => $this->maybe(0.1, function() { return implode('; ', array_map(function($p) { return "{$p[0]}, {$p[1]}"; }, array(SampleText::person(), SampleText::person()))); }),
            'CreatorTypeID' => $Type,
            'Dates' => $InName ? NULL : $Dates,
            'LCNAFDates' => $this->maybe(0.1, function() { return SampleText::personDates(); }),
            'LCNAFCompliant' => SampleText::chance(0.4) ? 1 : 0,
            'BiogHistAuthor' => is_string($Biography) && $Biography !== '' && SampleText::chance(0.2) ? implode(' ', array_reverse(SampleText::person())) : NULL,
            'BiogHist' => $Biography,
            'Sources' => $this->maybe(0.1, function() { return 'Obituary, ' . SampleText::pick(SampleText::$Places) . ' Daily News, ' . mt_rand(1900, 2010) . '.'; }),
            'LanguageID' => SampleText::chance(0.7) ? self::ENGLISH : NULL,
            'RepositoryID' => SampleText::chance(0.5) ? SampleText::pick($Repositories) : 0,
            'ScriptID' => 0,
            'Identifier' => $this->maybe(0.05, function() { return 'n ' . mt_rand(50, 2019) . sprintf('%06d', mt_rand(0, 999999)); }),
            'CreatorSourceID' => SampleText::chance(0.6) ? 1 : 2,
         ));
         $this->Creators[] = $ID;
      }
      $this->PopularCreator = $this->Creators[0];

      // Related creators: mostly "associative" (type 7).
      foreach($this->Creators as $ID)
      {
         if(SampleText::chance(0.1))
         {
            foreach(SampleText::pickSome($this->Creators, mt_rand(1, 2)) as $Related)
            {
               if($Related != $ID)
               {
                  $this->add('tblCreators_CreatorCreatorIndex', array('CreatorID' => $ID, 'RelatedCreatorID' => $Related,
                     'CreatorRelationshipTypeID' => SampleText::chance(0.7) ? 7 : mt_rand(1, 6), 'Description' => $this->maybe(0.2, 'Colleagues and correspondents.')));
               }
            }
         }
      }
      $this->flush();
      $this->log(sprintf('  creators: %d', count($this->Creators)));
   }

   private function subjectTerm($Type)
   {
      switch($Type)
      {
         case 2: return (string)(mt_rand(185, 201) * 10) . 's';
         case 3: return SampleText::corporateName();
         case 4: return SampleText::pick(SampleText::$Functions);
         case 5: return SampleText::pick(SampleText::$Forms);
         case 6: return SampleText::chance(0.5) ? SampleText::pick(SampleText::$Places) . ' (Ill.)' : (SampleText::chance(0.5) ? SampleText::pick(SampleText::$Counties) . ' County (Ill.)' : SampleText::pick(SampleText::$Countries));
         case 7: return SampleText::pick(SampleText::$Occupations);
         case 8:
            list($Last, $First) = SampleText::person();
            return "$Last, $First" . (SampleText::chance(0.4) ? ', ' . SampleText::personDates() : '');
         case 9: return ucfirst(SampleText::pick(SampleText::$Words)) . ' ' . SampleText::pick(SampleText::$Words) . ' (' . SampleText::pick(array('Periodical', 'Newsletter', 'Motion picture', 'Radio program')) . ')';
         case 10: return SampleText::pick(SampleText::$Surnames) . ' family';
         default: return ucfirst(SampleText::pick(SampleText::$Topics)) . (SampleText::chance(0.3) ? ' -- ' . SampleText::pick(array('History', 'Study and teaching', 'Societies, etc.', 'Archives', 'Pictorial works')) : '');
      }
   }

   private function subjects()
   {
      $Count = $this->Count['subjects'];
      $Types = array(1 => 46, 8 => 14, 3 => 12, 6 => 10, 5 => 8, 7 => 3, 10 => 3, 2 => 2, 4 => 1, 9 => 1);
      $Seen = array();
      $Parents = array();
      for($i = 0; count($this->Subjects) < $Count && $i < $Count * 3; $i++)
      {
         $Roll = mt_rand(1, 100);
         $Type = 1;
         foreach($Types as $Candidate => $Weight)
         {
            if(($Roll -= $Weight) <= 0)
            {
               $Type = $Candidate;
               break;
            }
         }
         $Term = $this->subjectTerm($Type);
         // Depth: 92% top level, about 6% second, 1.5% third, the rest fourth.
         $Parent = 0;
         $Depth = mt_rand(1, 1000);
         if($Parents && $Depth > 920)
         {
            $Parent = SampleText::pick($Parents);
            $Term = SampleText::pick(array('History', 'Biography', 'Correspondence', 'Photographs', 'Societies, etc.', 'Study and teaching', '20th century', 'Pictorial works')) . (SampleText::chance(0.3) ? ' ' . mt_rand(1, 99) : '');
         }
         $Key = "$Parent|$Type|" . strtolower($Term);
         if(isset($Seen[$Key]))
         {
            continue;
         }
         $Seen[$Key] = true;
         $ID = $this->add('tblSubjects_Subjects', array('Subject' => $Term, 'SubjectTypeID' => $Type,
            'SubjectSourceID' => $Type == 8 || $Type == 3 ? (SampleText::chance(0.6) ? 2 : 5) : (SampleText::chance(0.7) ? 1 : (SampleText::chance(0.6) ? 5 : 3)),
            'ParentID' => $Parent, 'LastModified' => $this->Now - mt_rand(0, 4000) * 86400, 'ModifiedByID' => $this->AdminID,
            'Description' => $this->maybe(0.03, function() { return SampleText::sentence(); }),
            'Identifier' => $this->maybe(0.05, function() { return 'sh' . mt_rand(85000000, 2019009999); })));
         $this->Subjects[] = $ID;
         if($Parent == 0 || $Depth > 985)
         {
            $Parents[] = $ID;
         }
      }

      // One parent heading with two real children, and many flat headings that only look like children.
      $Parent = $this->add('tblSubjects_Subjects', array('Subject' => 'Sample University', 'SubjectTypeID' => 3, 'SubjectSourceID' => 5, 'ParentID' => 0,
         'LastModified' => $this->Now, 'ModifiedByID' => $this->AdminID));
      foreach(array('Students', 'Buildings') as $Child)
      {
         $this->Subjects[] = $this->add('tblSubjects_Subjects', array('Subject' => $Child, 'SubjectTypeID' => 3, 'SubjectSourceID' => 5, 'ParentID' => $Parent,
            'LastModified' => $this->Now, 'ModifiedByID' => $this->AdminID));
      }
      $Flat = $this->Size == 'small' ? 15 : 150;
      for($i = 1; $i <= $Flat; $i++)
      {
         $this->Subjects[] = $this->add('tblSubjects_Subjects', array('Subject' => 'Sample University -- ' . SampleText::pick(SampleText::$Fields) . " -- $i",
            'SubjectTypeID' => 3, 'SubjectSourceID' => 5, 'ParentID' => 0, 'LastModified' => $this->Now, 'ModifiedByID' => $this->AdminID));
      }
      // A no-break space, and Latin-1 accents.
      $this->Subjects[] = $this->add('tblSubjects_Subjects', array('Subject' => "World War,\u{00A0}1939-1945", 'SubjectTypeID' => 1, 'SubjectSourceID' => 1,
         'ParentID' => 0, 'LastModified' => $this->Now, 'ModifiedByID' => $this->AdminID));
      $this->Subjects[] = $this->add('tblSubjects_Subjects', array('Subject' => 'Café society -- Montréal (Québec)', 'SubjectTypeID' => 1, 'SubjectSourceID' => 5,
         'ParentID' => 0, 'LastModified' => $this->Now, 'ModifiedByID' => $this->AdminID));
      $this->flush();
      $this->log(sprintf('  subjects: %d', count($this->Subjects)));
   }

   /* ------------------------------------------------------------ collections */

   private function collectionIdentifier($ClassificationID, $Index)
   {
      switch($this->Name)
      {
         case 'rbml':
            $Roll = mt_rand(1, 100);
            if($Index < 3)
            {
               return array('Sutton', 'Asian', 'Baskette')[$Index];
            }
            if($Roll <= 8)
            {
               return sprintf('PRE-1650 MS %04d', $Index);
            }
            if($Roll <= 20)
            {
               return sprintf('POST-1650 MS %04d', $Index);
            }
            return sprintf('MSS%05d', $Index);
         case 'ihlc':
            return (string)($Index + 1);
         default:
            $Key = (int)$ClassificationID;
            $this->ClassificationCounters[$Key] = isset($this->ClassificationCounters[$Key]) ? $this->ClassificationCounters[$Key] + 1 : 1;
            return (string)$this->ClassificationCounters[$Key];
      }
   }

   /** array(title, sort title, creator name or NULL, dates) */
   private function collectionTitle()
   {
      $Dates = SampleText::dates();
      $Roll = mt_rand(1, 100);
      $Creator = NULL;
      if($Roll <= 45)
      {
         list($Last, $First) = SampleText::person();
         $Title = "$First $Last " . SampleText::pick(array('Papers', 'Papers', 'Papers', 'Collection', 'Correspondence', 'Diaries', 'Photographs', 'Scrapbooks'));
         $SortTitle = "$Last, $First " . substr($Title, strlen("$First $Last "));
         $Creator = "$Last, $First";
      }
      elseif($Roll <= 75)
      {
         $Corporate = SampleText::corporateName();
         $Title = SampleText::chance(0.5) ? "$Corporate Records" : SampleText::pick(array('Records of the ', 'Minutes of the ', 'Publications of the ')) . $Corporate;
         $SortTitle = $Title;
         $Creator = $Corporate;
      }
      elseif($Roll <= 90)
      {
         $Title = SampleText::pick(SampleText::$Forms) . ' ' . SampleText::pick(array('on ', 'about ', 'relating to ', 'of ')) . SampleText::pick(SampleText::$Topics);
         $SortTitle = $Title;
      }
      else
      {
         $Title = ucfirst(SampleText::pick(SampleText::$Topics)) . ' Collection';
         $SortTitle = $Title;
      }
      // Archives titles usually carry the dates; the other installs keep them in InclusiveDates only.
      if($this->Name == 'archives' && $Dates[0] !== '' && SampleText::chance(0.7))
      {
         $Title .= ', ' . $Dates[0];
      }
      return array($Title, $SortTitle, $Creator, $Dates);
   }

   private function collections()
   {
      $Count = $this->Count['collections'];
      $Materials = array_values($this->Material);
      $Units = array_values($this->Unit);
      $Special = array();
      foreach($this->Profile['trees'] as $Shape => $Items)
      {
         if($Items)
         {
            $Special[$Shape] = $this->scaled($Items, 150);
         }
      }

      for($i = 0; $i < $Count; $i++)
      {
         $Classification = $this->Classifications ? ($this->Name == 'rbml' ? ($i % 4 == 3 ? $this->Classifications[1] : $this->Classifications[0]) : SampleText::pick($this->Classifications)) : 0;
         $Repository = $this->repository();
         list($Title, $SortTitle, $CreatorName, $Dates) = $this->collectionTitle();
         $Biography = $this->maybe(0.5, function() { return SampleText::text(mt_rand(300, 5000)); });
         $Row = array(
            'Enabled' => 1,
            'RepositoryID' => $Repository,
            'ClassificationID' => $Classification,
            'CollectionIdentifier' => $this->collectionIdentifier($Classification, $i),
            'Title' => $Title,
            'SortTitle' => $SortTitle,
            'InclusiveDates' => $Dates[0] === '' ? $this->maybe(0, '') : $Dates[0],
            'PredominantDates' => $this->maybe(0.3, function() { $d = SampleText::dates(); return $d[0]; }),
            'NormalDateBegin' => $Dates[1],
            'NormalDateEnd' => $Dates[2],
            'FindingAidAuthor' => $this->maybe(0.4, function() { return implode(' ', array_reverse(SampleText::person())); }),
            'Extent' => round(mt_rand(1, 15000) / 100, mt_rand(0, 2)),
            'ExtentUnitID' => $this->extentUnit(),
            'Scope' => $this->maybe(0.85, function() { return SampleText::text(mt_rand(200, 3000)); }),
            'Abstract' => $this->maybe(0.6, function() { return SampleText::paragraph(2, 4); }),
            'Arrangement' => $this->maybe(0.4, function() { return SampleText::sentence(); }),
            'MaterialTypeID' => SampleText::chance(0.9) ? SampleText::pick($Materials) : 0,
            'AltExtentStatement' => $this->maybe(0.1, function() { return mt_rand(1, 40) . ' ' . SampleText::pick(array('boxes', 'folders', 'reels of microfilm', 'audio cassettes')); }),
            'AccessRestrictions' => $this->maybe(0.3, function() { return SampleText::pick(array('Open for research.', 'Student records are closed for 75 years from the date of creation.', 'Some materials are stored off site; please contact staff two business days in advance.')); }),
            'UseRestrictions' => $this->maybe(0.2, 'Copyright is retained by the creators of the materials or their heirs.'),
            'PhysicalAccess' => $this->maybe(0.05, 'Fragile; use photocopies when available.'),
            'TechnicalAccess' => $this->maybe(0.05, 'Audio recordings require playback equipment; contact staff.'),
            'AcquisitionSource' => $this->maybe(0.4, function() { return implode(' ', array_reverse(SampleText::person())); }),
            'AcquisitionMethod' => $this->maybe(0.3, function() { return SampleText::pick(array('Gift', 'Transfer', 'Purchase', 'Deposit')); }),
            'AcquisitionDate' => $this->maybe(0.4, function() { return SampleText::acquisitionDate(); }),
            'AppraisalInfo' => $this->maybe(0.1, function() { return SampleText::sentence(); }),
            'AccrualInfo' => $this->maybe(0.1, 'Additions are expected.'),
            'CustodialHistory' => $this->maybe(0.1, function() { return SampleText::sentence(); }),
            'OrigCopiesNote' => $this->maybe(0.05, 'Originals are held by the creator.'),
            'OrigCopiesURL' => $this->maybe(0.03, 'https://example.org/originals/' . $i),
            'RelatedMaterials' => $this->maybe(0.2, function() { return SampleText::sentence(); }),
            'RelatedMaterialsURL' => $this->maybe(0.05, 'https://example.org/related/' . $i),
            'RelatedPublications' => $this->maybe(0.1, function() { return SampleText::sentence(); }),
            'SeparatedMaterials' => $this->maybe(0.05, 'Photographs were transferred to the photographic files.'),
            'PreferredCitation' => $this->maybe(0.1, 'Sample citation: [Item], [Collection], Sample Archives.'),
            'OtherNote' => $this->maybe(0.1, function() { return SampleText::sentence(); }),
            'OtherURL' => $this->maybe(0.03, 'https://example.org/other/' . $i),
            'DescriptiveRulesID' => SampleText::chance(0.6) ? 1 : (SampleText::chance(0.5) ? mt_rand(2, 4) : 0),
            'ProcessingInfo' => $this->maybe(0.2, function() { return 'Processed by ' . implode(' ', array_reverse(SampleText::person())) . ' in ' . mt_rand(1970, 2023) . '.'; }),
            'RevisionHistory' => $this->maybe(0.1, function() { return 'Finding aid revised ' . mt_rand(1995, 2023) . '.'; }),
            'PublicationDate' => $this->maybe(0.2, function() { return SampleText::acquisitionDate(); }),
            'PublicationNote' => $this->maybe(0.05, 'Encoded in EAD by the sample processing team.'),
            'FindingLanguageID' => SampleText::chance(0.8) ? self::ENGLISH : 0,
            'BiogHistAuthor' => is_string($Biography) && $Biography !== '' && SampleText::chance(0.2) ? implode(' ', array_reverse(SampleText::person())) : NULL,
            'BiogHist' => $Biography,
         );
         $this->collection($Row, $CreatorName);
      }
      $this->edgeCases();

      // Content trees. The special trees go to the first collections of the right repository, the rest at random.
      $Collections = $this->Collections;
      foreach($Special as $Shape => $Items)
      {
         $Target = $Shape == 'music' && count($this->Repositories) > 1 ? $this->collectionInRepository(array_keys($this->Repositories)[1]) : array_shift($Collections);
         $Created = $this->tree($Target, $Shape, $Items);
         $this->log(sprintf('  %s tree: %d items in collection %d', $Shape, $Created, $Target));
         $Collections = array_values(array_diff($Collections, array($Target)));
      }
      $Total = 0;
      foreach($Collections as $CollectionID)
      {
         $Roll = mt_rand(1, 100);
         if($Roll <= 55)
         {
            continue;
         }
         $Total += $Roll <= 85 ? $this->tree($CollectionID, 'box-folder', mt_rand(5, 40)) : $this->tree($CollectionID, 'series', mt_rand(40, 250));
      }
      $this->flush();
      $this->log(sprintf('  collections: %d (%d generic content items)', count($this->Collections), $Total));
   }

   private $CollectionRepository = array();
   private $EncodingCollections = array();

   private function collectionInRepository($RepositoryID)
   {
      foreach($this->Collections as $CollectionID)
      {
         if($this->CollectionRepository[$CollectionID] == $RepositoryID)
         {
            return $CollectionID;
         }
      }
      return $this->Collections[0];
   }

   /** Adds a collection with its creator, subject, language and location index rows. */
   private function collection(array $Row, $CreatorName = NULL, $Indexes = true)
   {
      $ID = $this->add('tblCollections_Collections', $Row);
      $this->Collections[] = $ID;
      $this->CollectionRepository[$ID] = $Row['RepositoryID'];
      if(!$Indexes)
      {
         return $ID;
      }

      $Creators = array();
      if(SampleText::chance(0.85))
      {
         $Creators[] = count($this->Collections) % 25 == 1 ? $this->PopularCreator : SampleText::pick($this->Creators);
      }
      if(SampleText::chance(0.2))
      {
         $Creators = array_merge($Creators, SampleText::pickSome($this->Creators, mt_rand(1, 4)));
      }
      foreach(array_values(array_unique($Creators)) as $Index => $CreatorID)
      {
         $this->add('tblCollections_CollectionCreatorIndex', array('CollectionID' => $ID, 'CreatorID' => $CreatorID, 'PrimaryCreator' => $Index == 0 ? 1 : 0));
      }
      $SubjectCount = min(count($this->Subjects), SampleText::chance(0.1) ? 0 : (SampleText::chance(0.1) ? mt_rand(10, 25) : mt_rand(1, 8)));
      foreach(SampleText::pickSome($this->Subjects, $SubjectCount) as $SubjectID)
      {
         $this->add('tblCollections_CollectionSubjectIndex', array('CollectionID' => $ID, 'SubjectID' => $SubjectID));
      }
      foreach($this->languages() as $LanguageID)
      {
         $this->add('tblCollections_CollectionLanguageIndex', array('CollectionID' => $ID, 'LanguageID' => $LanguageID));
      }
      if($this->Locations && SampleText::chance(0.7))
      {
         $Box = 1;
         foreach(SampleText::pickSome($this->Locations, mt_rand(1, 3)) as $LocationID)
         {
            $Boxes = mt_rand(1, 30);
            $this->add('tblCollections_CollectionLocationIndex', array('CollectionID' => $ID, 'LocationID' => $LocationID,
               'Content' => $Boxes == 1 ? "Box $Box" : 'Boxes ' . $Box . '-' . ($Box + $Boxes - 1), 'RangeValue' => (string)mt_rand(1, 60),
               'Section' => (string)mt_rand(1, 12), 'Shelf' => (string)mt_rand(1, 8), 'Extent' => round($Boxes * 0.4, 2), 'ExtentUnitID' => $this->Unit['Cubic Feet']));
            $Box += $Boxes;
         }
      }
      return $ID;
   }

   /* ------------------------------------------------------------ content trees */

   private $TreeRows = array();
   private $Siblings = array();

   private function node($CollectionID, $ParentID, $Level, $Identifier, $Title, array $Extra = array())
   {
      $ID = $this->id('tblCollections_Content');
      $Key = $ParentID ? $ParentID : 0;
      $this->Siblings[$Key] = isset($this->Siblings[$Key]) ? $this->Siblings[$Key] + 1 : 1;
      $Row = array_merge(array('ID' => $ID, 'CollectionID' => $CollectionID, 'LevelContainerID' => $this->Level[$Level],
         'LevelContainerIdentifier' => (string)$Identifier, 'Title' => $Title, 'PrivateTitle' => NULL, 'Date' => NULL, 'Description' => NULL,
         'RootContentID' => $ParentID ? $this->TreeRows[$ParentID]['RootContentID'] : $ID, 'ParentID' => $ParentID, 'ContainsContent' => 0,
         'SortOrder' => $this->Siblings[$Key], 'Enabled' => 1), $Extra);
      if($ParentID)
      {
         $this->TreeRows[$ParentID]['ContainsContent'] = 1;
         if(!$this->TreeRows[$ParentID]['Enabled'])
         {
            $Row['Enabled'] = 0;
         }
      }
      $this->TreeRows[$ID] = $Row;
      return $ID;
   }

   private function userField($ContentID, $Title, $Value)
   {
      $Element = isset(self::$UserFieldElements[$Title]) ? self::$UserFieldElements[$Title] : 12;
      $this->add('tblCollections_UserFields', array('ContentID' => $ContentID, 'Title' => $Title, 'Value' => $Value, 'EADElementID' => $Element));
   }

   private function folder($CollectionID, $ParentID, $Number, $NoteChance = 0.1)
   {
      $Dates = SampleText::dates();
      $ID = $this->node($CollectionID, $ParentID, 'Folder', $Number, SampleText::folderTitle(),
         array('Date' => SampleText::chance(0.5) ? $Dates[0] : $this->maybe(0, ''), 'Description' => $this->maybe(0.05, function() { return SampleText::sentence(); })));
      if(SampleText::chance($NoteChance))
      {
         $this->userField($ID, SampleText::pick(array('Note', 'Note', 'Physical Description', 'Other Information', 'Dimensions')), SampleText::sentence());
      }
      return $ID;
   }

   /** Builds a content tree of about $Items nodes and queues it; returns the number of nodes. */
   private function tree($CollectionID, $Shape, $Items)
   {
      $this->TreeRows = array();
      $this->Siblings = array();
      $Method = 'tree' . str_replace(' ', '', ucwords(str_replace('-', ' ', $Shape)));
      $this->$Method($CollectionID, $Items);
      $Sample = array();
      foreach($this->TreeRows as $ID => $Row)
      {
         $this->add('tblCollections_Content', $Row);
         if($Row['Enabled'] && count($Sample) < 40 && SampleText::chance(0.3))
         {
            $Sample[] = $ID;
         }
      }
      $this->Content[$CollectionID] = $Sample;
      // A few content-level creator and subject links.
      foreach(SampleText::pickSome(array_keys($this->TreeRows), min(3, (int)(count($this->TreeRows) / 50))) as $ContentID)
      {
         $this->add('tblCollections_CollectionContentCreatorIndex', array('CollectionContentID' => $ContentID, 'CreatorID' => SampleText::pick($this->Creators)));
         $this->add('tblCollections_CollectionContentSubjectIndex', array('CollectionContentID' => $ContentID, 'SubjectID' => SampleText::pick($this->Subjects)));
      }
      $Count = count($this->TreeRows);
      $this->TreeRows = array();
      return $Count;
   }

   private function treeBoxFolder($CollectionID, $Items, $ParentID = 0, &$Box = 0)
   {
      $Created = 0;
      while($Created < $Items)
      {
         $Box++;
         $BoxID = $this->node($CollectionID, $ParentID, 'Box', $Box, SampleText::chance(0.3) ? SampleText::folderTitle() : $this->maybe(0, ''));
         $Created++;
         for($f = 1, $n = mt_rand(3, 20); $f <= $n && $Created < $Items; $f++, $Created++)
         {
            $this->folder($CollectionID, $BoxID, $f);
         }
      }
      return $Created;
   }

   private function treeSeries($CollectionID, $Items)
   {
      $Series = mt_rand(2, 8);
      $Box = 0;
      $Created = 0;
      for($s = 1; $s <= $Series; $s++)
      {
         $SeriesID = $this->node($CollectionID, 0, 'Series', $s, SampleText::chance(0.5) ? SampleText::pick(SampleText::$Forms) : "Series $s: " . ucfirst(SampleText::pick(SampleText::$Topics)),
            array('Date' => SampleText::dates()[0], 'Description' => $this->maybe(0.5, function() { return SampleText::paragraph(1, 3); })));
         $Created++;
         $Budget = max(2, (int)(($Items - $Created) / ($Series - $s + 1)));
         if(SampleText::chance(0.3))
         {
            for($u = 1, $Subs = mt_rand(2, 4); $u <= $Subs; $u++)
            {
               $SubID = $this->node($CollectionID, $SeriesID, 'Sub-Series', $u, ucfirst(SampleText::pick(SampleText::$Topics)));
               $Created += 1 + $this->treeBoxFolder($CollectionID, max(2, (int)($Budget / $Subs) - 1), $SubID, $Box);
            }
         }
         else
         {
            $Created += $this->treeBoxFolder($CollectionID, $Budget - 1, $SeriesID, $Box);
         }
      }
      return $Created;
   }

   /** Six levels deep (Series/Sub-Series/Sub-Series/Box/Folder/Item), one restricted series, notes with HTML. */
   private function treeDeep($CollectionID, $Items)
   {
      $Created = 0;
      $Box = 0;
      $Note = function() {
         return SampleText::chance(0.3) ? '<p>' . SampleText::sentence() . '</p><p>' . SampleText::sentence() . '</p>' : SampleText::sentence();
      };
      for($s = 1; $Created < $Items; $s++)
      {
         $Restricted = $s == 3;
         $SeriesID = $this->node($CollectionID, 0, 'Series', $s, $Restricted ? 'Personnel files (restricted)' : SampleText::pick(SampleText::$Forms) . ', ' . SampleText::dates()[0],
            array('Enabled' => $Restricted ? 0 : 1, 'Description' => SampleText::paragraph(1, 2)));
         $Created++;
         for($u = 1, $Subs = mt_rand(2, 4); $u <= $Subs && $Created < $Items; $u++)
         {
            $SubID = $this->node($CollectionID, $SeriesID, 'Sub-Series', $u, ucfirst(SampleText::pick(SampleText::$Topics)));
            $Created++;
            for($v = 1, $Subs2 = mt_rand(1, 3); $v <= $Subs2 && $Created < $Items; $v++)
            {
               $Sub2ID = $this->node($CollectionID, $SubID, 'Sub-Series', "$u.$v", SampleText::pick(SampleText::$Places) . ' ' . SampleText::pick(array('office', 'branch', 'chapter')));
               $Created++;
               for($b = 0, $Boxes = mt_rand(2, 6); $b < $Boxes && $Created < $Items; $b++)
               {
                  $Box++;
                  $BoxID = $this->node($CollectionID, $Sub2ID, 'Box', $Box, $this->maybe(0, ''));
                  $Created++;
                  for($f = 1, $Folders = mt_rand(4, 12); $f <= $Folders && $Created < $Items; $f++)
                  {
                     $FolderID = $this->folder($CollectionID, $BoxID, $f, 0.3);
                     $Created++;
                     if(SampleText::chance(0.4))
                     {
                        for($t = 1, $Pieces = mt_rand(1, 5); $t <= $Pieces && $Created < $Items; $t++, $Created++)
                        {
                           $ItemID = $this->node($CollectionID, $FolderID, 'Item', $t, SampleText::folderTitle(), array('Date' => SampleText::dates()[0]));
                           if(SampleText::chance(0.3))
                           {
                              $this->userField($ItemID, 'Note', $Note());
                           }
                        }
                     }
                  }
               }
            }
         }
      }
      return $Created;
   }

   /** A band-library style tree: music folders with composer and microfilm notes, unusual containers. */
   private function treeMusic($CollectionID, $Items)
   {
      $Created = 0;
      $SeriesNames = array('Music', 'Programs', 'Scrapbooks', 'Photographs', 'Memorabilia', 'Audio recordings');
      $Box = 0;
      foreach($SeriesNames as $s => $Name)
      {
         $SeriesID = $this->node($CollectionID, 0, 'Series', $s + 1, $Name, array('Description' => SampleText::paragraph(1, 2)));
         $Created++;
         $Budget = $Name == 'Music' ? (int)($Items * 0.7) : (int)($Items * 0.06);
         switch($Name)
         {
            case 'Memorabilia':
               $this->node($CollectionID, $SeriesID, 'Steamer Trunk', 1, 'Steamer trunk used on the 1911 world tour');
               for($p = 1; $p <= 3; $p++)
               {
                  $this->node($CollectionID, $SeriesID, 'Oversize Portfolio Case', $p, 'Posters and certificates');
               }
               $this->node($CollectionID, $SeriesID, 'Furniture Piece', 1, 'Music stand');
               $Created += 5;
               $Created += $this->treeBoxFolder($CollectionID, max(2, $Budget - 5), $SeriesID, $Box);
               break;
            case 'Audio recordings':
               for($r = 1; $r <= max(3, $Budget); $r++, $Created++)
               {
                  $this->node($CollectionID, $SeriesID, 'Reel', $r, SampleText::pick(SampleText::$Places) . ' concert, ' . mt_rand(1950, 1990));
               }
               break;
            case 'Music':
               for($Done = 0; $Done < $Budget; )
               {
                  $Box++;
                  $BoxID = $this->node($CollectionID, $SeriesID, 'Box', $Box, $this->maybe(0, ''));
                  $Done++;
                  for($f = 1, $n = mt_rand(10, 30); $f <= $n && $Done < $Budget; $f++, $Done++)
                  {
                     list($Last, $First) = SampleText::person();
                     $FolderID = $this->node($CollectionID, $BoxID, 'Folder', $f, ucfirst(SampleText::pick(SampleText::$Words)) . ' ' . SampleText::pick(array('March', 'Waltz', 'Overture', 'Galop', 'Serenade', 'Polka', 'Fantasia')));
                     $this->userField($FolderID, 'Composer/Arranger', "$First $Last");
                     if(SampleText::chance(0.4))
                     {
                        $this->userField($FolderID, 'Microfilm', 'Reel ' . mt_rand(1, 120) . ', frames ' . mt_rand(1, 400));
                     }
                  }
               }
               $Created += $Done;
               break;
            default:
               $Created += $this->treeBoxFolder($CollectionID, max(2, $Budget), $SeriesID, $Box);
         }
      }
      return $Created;
   }

   /** RBML style: alphanumeric box and folder identifiers and shelf locations in the description. */
   private function treeAlphanumeric($CollectionID, $Items)
   {
      $Created = 0;
      for($b = 1; $Created < $Items; $b++)
      {
         $Identifier = $b % 7 == 0 ? 'OS-' . ($b / 7) : ($b % 5 == 0 ? ($b - 1) . 'a' : (string)$b);
         $BoxID = $this->node($CollectionID, 0, 'Box', $Identifier, $this->maybe(0.2, function() { return SampleText::folderTitle(); }),
            array('Description' => sprintf('Location: C%02d.%02d.%02d', mt_rand(1, 20), mt_rand(1, 12), mt_rand(1, 8))));
         $Created++;
         for($f = 1, $n = mt_rand(5, 25); $f <= $n && $Created < $Items; $f++, $Created++)
         {
            $Identifier = SampleText::chance(0.1) ? $f . '-' . ($f + mt_rand(1, 3)) : (SampleText::chance(0.1) ? $f . chr(mt_rand(97, 100)) : (string)$f);
            $this->node($CollectionID, $BoxID, 'Folder', $Identifier, SampleText::folderTitle(), array('Date' => SampleText::dates(1450, 1950)[0]));
         }
      }
      return $Created;
   }

   /* ------------------------------------------------------------ edge cases */

   private function baseCollection($Title, array $Values = array())
   {
      $Repository = key($this->Repositories);
      return array_merge(array('Enabled' => 1, 'RepositoryID' => $Repository, 'ClassificationID' => $this->Classifications ? $this->Classifications[0] : 0,
         'CollectionIdentifier' => $this->collectionIdentifier($this->Classifications ? $this->Classifications[0] : 0, count($this->Collections)),
         'Title' => $Title, 'SortTitle' => $Title, 'InclusiveDates' => '1900-1950', 'NormalDateBegin' => 1900, 'NormalDateEnd' => 1950,
         'Extent' => 1.5, 'ExtentUnitID' => $this->Unit['Cubic Feet'], 'Scope' => SampleText::paragraph(), 'FindingLanguageID' => self::ENGLISH), $Values);
   }

   private function edgeCases()
   {
      $this->collection($this->baseCollection('Sample Unpublished Collection (disabled)', array('Enabled' => 0)));
      $this->collection($this->baseCollection('Duplicate entry created by mistake -test'));
      $Teaser = SampleText::paragraph(4, 6);
      $this->collection($this->baseCollection('Sample Alumni Association Records', array('Abstract' => $Teaser)));
      $this->collection($this->baseCollection('Sample Alumni Association Publications', array('Abstract' => $Teaser, 'Scope' => '')));
      $this->collection($this->baseCollection('Sample Collection Without Extent', array('Extent' => 0.0, 'ExtentUnitID' => 0, 'InclusiveDates' => NULL,
         'NormalDateBegin' => NULL, 'NormalDateEnd' => NULL, 'Scope' => NULL)), NULL, false);
      $this->collection($this->baseCollection('CafÃ© Society Records', array('Scope' => 'Mojibake that was saved twice: CafÃ© and SÃ£o Paulo.')));
      $this->collection($this->baseCollection('Smith & Jones "Big Band" <Orchestra> Records', array('Scope' => 'Markup-like text: <b>bold</b> & ampersands; 5 < 6 > 4.')));
      $this->collection($this->baseCollection(str_repeat('Very Long Title ', 9) . 'Papers', array('CollectionIdentifier' => '')));
      $this->collection($this->baseCollection('Café Müller Señora Ærø Œuvre Papers', array('Scope' => 'Windows-1252 characters: “quotes”, ‘single’, en – dash, em — dash, € and ….')));
      $this->EncodingCollections[] = $this->collection($this->baseCollection('Encoding sample (title replaced with raw UTF-8 bytes)'));
      $this->EncodingCollections[] = $this->collection($this->baseCollection('Encoding Sample Papers (scope in raw UTF-8 bytes)'));
      $this->flush();
   }

   /* ------------------------------------------------------------ digital library */

   private function digitalContent()
   {
      $Count = $this->Count['digital'];
      if(!$Count)
      {
         return;
      }
      $Gallery = $this->Collections[min(count($this->Collections) - 1, 5)];
      $Files = 0;
      for($i = 0; $i < $Count; $i++)
      {
         $CollectionID = $i < 12 ? $Gallery : (SampleText::chance(0.7) ? SampleText::pick($this->Collections) : 0);
         $ContentID = $CollectionID && !empty($this->Content[$CollectionID]) && SampleText::chance(0.3) ? SampleText::pick($this->Content[$CollectionID]) : 0;
         $Roll = mt_rand(1, 100);
         $Kind = $Roll <= 40 ? 'jpeg' : ($Roll <= 55 ? 'pdf' : ($Roll <= 90 ? 'url' : 'metadata'));
         $Dates = SampleText::dates();
         $Title = $Kind == 'jpeg' ? SampleText::pick(SampleText::$Places) . ' ' . SampleText::pick(array('band', 'campus', 'parade', 'library', 'concert hall', 'farm')) . ', ' . ($Dates[1] ?: 'undated')
            : SampleText::folderTitle();
         $ID = $this->add('tblDigitalLibrary_DigitalContent', array('Browsable' => SampleText::chance(0.9) ? 1 : 0, 'Title' => $Title,
            'CollectionID' => $CollectionID, 'CollectionContentID' => $ContentID, 'Identifier' => $this->maybe(0.6, (string)mt_rand(1, 99999)),
            'Scope' => $this->maybe(0.5, function() { return SampleText::paragraph(1, 3); }),
            'PhysicalDescription' => $this->maybe(0.3, function() { return mt_rand(1, 20) . ' ' . SampleText::pick(array('photographs', 'pages', 'leaves', 'items')); }),
            'Date' => $Dates[0], 'Publisher' => $this->maybe(0.1, function() { return SampleText::corporateName(); }),
            'Contributor' => $this->maybe(0.1, function() { return implode(' ', array_reverse(SampleText::person())); }),
            'RightsStatement' => $this->maybe(0.4, 'This item may be protected by copyright; contact the archives for permission to publish.'),
            'ContentURL' => $Kind == 'url' ? 'https://example.org/digital/' . $i . (SampleText::chance(0.3) ? '?view=full&page=1' : '') : NULL,
            'HyperlinkURL' => $Kind == 'url' ? 1 : 0));
         foreach(SampleText::pickSome($this->Creators, mt_rand(0, 2)) as $Index => $CreatorID)
         {
            $this->add('tblDigitalLibrary_DigitalContentCreatorIndex', array('DigitalContentID' => $ID, 'CreatorID' => $CreatorID, 'PrimaryCreator' => $Index == 0 ? 1 : 0));
         }
         foreach(SampleText::pickSome($this->Subjects, mt_rand(0, 4)) as $SubjectID)
         {
            $this->add('tblDigitalLibrary_DigitalContentSubjectIndex', array('DigitalContentID' => $ID, 'SubjectID' => $SubjectID));
         }
         if(SampleText::chance(0.5))
         {
            $this->add('tblDigitalLibrary_DigitalContentLanguageIndex', array('DigitalContentID' => $ID, 'LanguageID' => self::ENGLISH));
         }
         if($Kind == 'jpeg' || $Kind == 'pdf')
         {
            for($f = 1, $n = SampleText::chance(0.15) ? mt_rand(2, 3) : 1; $f <= $n; $f++)
            {
               if($Kind == 'jpeg')
               {
                  list($Contents, $Long, $Short) = SampleFiles::jpeg("Sample image $ID-$f", $Title);
                  $Type = 2;
                  $Filename = sprintf('sample-%05d-%d.jpg', $ID, $f);
               }
               else
               {
                  $Contents = SampleFiles::pdf($Title, array(SampleText::sentence(), SampleText::sentence(), SampleText::sentence()));
                  $Long = substr($Contents, 0, 2048 * 1024);
                  $Short = substr($Contents, 0, 512 * 1024);
                  $Type = 14;
                  $Filename = sprintf('sample-%05d-%d.pdf', $ID, $f);
               }
               $Roll = mt_rand(1, 100);
               $this->add('tblDigitalLibrary_Files', array('DefaultAccessLevel' => $Roll <= 85 ? 2 : ($Roll <= 95 ? 1 : 0), 'DigitalContentID' => $ID,
                  'Title' => $n > 1 ? "Page $f" : $this->maybe(0.3, $Title), 'Filename' => $Filename, 'FileTypeID' => $Type,
                  'FileContents' => $Contents, 'FilePreviewLong' => $Long, 'FilePreviewShort' => $Short, 'Size' => strlen($Contents), 'DisplayOrder' => $f));
               $Files++;
            }
         }
      }
      $this->flush();
      $this->log(sprintf('  digital content: %d (%d files)', $Count, $Files));
   }

   /* ------------------------------------------------------------ accessions, books, research */

   private function accessions()
   {
      $Units = array($this->Unit['Cubic Feet'], $this->Unit['Linear Feet'], $this->Unit['Items'], $this->Unit['Boxes']);
      for($i = 0; $i < $this->Count['accessions']; $i++)
      {
         $Date = SampleText::acquisitionDate();
         $Dates = SampleText::dates();
         $Received = round(mt_rand(1, 4000) / 100, 2);
         $ID = $this->add('tblAccessions_Accessions', array('Enabled' => SampleText::chance(0.95) ? 1 : 0, 'AccessionDate' => $Date,
            'Title' => SampleText::chance(0.5) ? implode(' ', array_reverse(SampleText::person())) . ' Papers' : SampleText::corporateName() . ' Records',
            'Identifier' => substr($Date, 0, 4) . '-' . sprintf('%04d', $i + 1), 'InclusiveDates' => $Dates[0],
            'ReceivedExtent' => $Received, 'ReceivedExtentUnitID' => SampleText::pick($Units),
            'UnprocessedExtent' => SampleText::chance(0.6) ? round($Received * mt_rand(0, 100) / 100, 2) : NULL, 'UnprocessedExtentUnitID' => SampleText::pick($Units),
            'MaterialTypeID' => SampleText::pick(array_values($this->Material)), 'ProcessingPriorityID' => $this->Priority ? SampleText::pick($this->Priority) : 0,
            'ExpectedCompletionDate' => $this->maybe(0.2, function() { return SampleText::acquisitionDate(); }),
            'Donor' => $this->maybe(0.7, function() { return implode(' ', array_reverse(SampleText::person())); }),
            'DonorContactInformation' => $this->maybe(0.3, '555-0100, donor@example.org'), 'DonorNotes' => $this->maybe(0.2, function() { return SampleText::sentence(); }),
            'PhysicalDescription' => $this->maybe(0.4, function() { return SampleText::sentence(); }),
            'ScopeContent' => $this->maybe(0.6, function() { return SampleText::paragraph(); }), 'Comments' => $this->maybe(0.2, function() { return SampleText::sentence(); })));
         if(SampleText::chance(0.7))
         {
            $CollectionID = SampleText::pick($this->Collections);
            $this->add('tblAccessions_AccessionCollectionIndex', array('AccessionID' => $ID, 'ClassificationID' => 0, 'CollectionID' => $CollectionID, 'PrimaryCollection' => 1));
         }
         foreach(SampleText::pickSome($this->Creators, mt_rand(0, 2)) as $Index => $CreatorID)
         {
            $this->add('tblAccessions_AccessionCreatorIndex', array('AccessionID' => $ID, 'CreatorID' => $CreatorID, 'PrimaryCreator' => $Index == 0 ? 1 : 0));
         }
         foreach(SampleText::pickSome($this->Subjects, mt_rand(0, 3)) as $SubjectID)
         {
            $this->add('tblAccessions_AccessionSubjectIndex', array('AccessionID' => $ID, 'SubjectID' => $SubjectID));
         }
         if($this->Locations && SampleText::chance(0.6))
         {
            $this->add('tblAccessions_AccessionLocationIndex', array('AccessionID' => $ID, 'LocationID' => SampleText::pick($this->Locations),
               'Content' => 'Boxes 1-' . mt_rand(2, 12), 'RangeValue' => (string)mt_rand(1, 60), 'Section' => (string)mt_rand(1, 12), 'Shelf' => (string)mt_rand(1, 8),
               'Extent' => $Received, 'ExtentUnitID' => $this->Unit['Cubic Feet']));
         }
      }
      $this->flush();
   }

   private function books()
   {
      for($i = 0; $i < $this->Count['books']; $i++)
      {
         $Year = mt_rand(1480, 1900);
         $ID = $this->add('tblCollections_Books', array('Title' => ucfirst(SampleText::pick(SampleText::$Topics)) . ': ' . SampleText::pick(array('a treatise', 'an account', 'observations', 'letters', 'a history')),
            'Edition' => $this->maybe(0.3, SampleText::pick(array('1st ed.', '2nd ed.', 'New ed.'))), 'CopyNumber' => SampleText::chance(0.2) ? mt_rand(1, 3) : NULL,
            'PublicationDate' => (string)$Year, 'PlaceOfPublication' => SampleText::pick(array('London', 'Paris', 'Venice', 'Leipzig', 'Philadelphia', 'Boston')),
            'Publisher' => $this->maybe(0.7, function() { return SampleText::pick(SampleText::$Surnames) . ' & Sons'; }), 'Description' => $this->maybe(0.5, function() { return SampleText::sentence(); }),
            'Notes' => $this->maybe(0.3, function() { return SampleText::sentence(); }), 'NumberOfPages' => mt_rand(20, 900), 'Series' => $this->maybe(0.1, 'Sample Classics')));
         $this->add('tblCollections_CollectionBookIndex', array('CollectionID' => SampleText::pick($this->Collections), 'BookID' => $ID));
         $this->add('tblCollections_BookCreatorIndex', array('BookID' => $ID, 'CreatorID' => SampleText::pick($this->Creators), 'PrimaryCreator' => 1));
         foreach(SampleText::pickSome($this->Subjects, mt_rand(0, 3)) as $SubjectID)
         {
            $this->add('tblCollections_BookSubjectIndex', array('BookID' => $ID, 'SubjectID' => $SubjectID));
         }
         $this->add('tblCollections_BookLanguageIndex', array('BookID' => $ID, 'LanguageID' => SampleText::chance(0.6) ? self::ENGLISH : array_rand(self::$Languages)));
      }
      $this->flush();
   }

   private function research()
   {
      $Staff = (int)$this->Db->value("SELECT ISNULL(MIN(ID), 1) FROM tblCore_Users WHERE Login = 'teststaff'");
      foreach($this->Researchers as $Index => $ResearcherID)
      {
         if($Index > 0 && !SampleText::chance(0.5))
         {
            continue;
         }
         foreach(SampleText::pickSome($this->Collections, mt_rand(1, 5)) as $CollectionID)
         {
            $ContentID = !empty($this->Content[$CollectionID]) && SampleText::chance(0.5) ? SampleText::pick($this->Content[$CollectionID]) : 0;
            $this->add('tblCollections_ResearchCarts', array('ResearcherID' => $ResearcherID, 'CollectionID' => $CollectionID, 'CollectionContentID' => $ContentID));
         }
         if($Index == 0 || SampleText::chance(0.5))
         {
            $Arrival = $this->Now - mt_rand(-30, 700) * 86400 + 9 * 3600;
            $ID = $this->add('tblCollections_ResearchAppointments', array('SubmitTime' => $Arrival - mt_rand(2, 20) * 86400, 'ArrivalTime' => $Arrival,
               'DepartureTime' => $Arrival + mt_rand(2, 7) * 3600, 'ResearcherID' => $ResearcherID, 'AppointmentPurposeID' => SampleText::pick($this->Purpose),
               'Topic' => ucfirst(SampleText::pick(SampleText::$Topics)), 'ResearcherComments' => $this->maybe(0.4, function() { return SampleText::sentence(); }),
               'ArchivistComments' => $this->maybe(0.2, 'Pulled in advance.')));
            foreach(SampleText::pickSome($this->Collections, mt_rand(1, 3)) as $CollectionID)
            {
               $Retrieved = $Arrival < $this->Now && SampleText::chance(0.7);
               $this->add('tblCollections_ResearchAppointmentMaterialsIndex', array('AppointmentID' => $ID, 'CollectionID' => $CollectionID,
                  'CollectionContentID' => !empty($this->Content[$CollectionID]) ? SampleText::pick($this->Content[$CollectionID]) : 0,
                  'RetrievalTime' => $Retrieved ? $Arrival + 600 : 0, 'RetrievalUserID' => $Retrieved ? $Staff : 0,
                  'ReturnTime' => $Retrieved ? $Arrival + 3 * 3600 : 0, 'ReturnUserID' => $Retrieved ? $Staff : 0));
            }
         }
      }
      $this->flush();
   }

   /**
    * Text written as raw UTF-8 bytes into varchar columns (what a client without a character set conversion stores),
    * including a zero-width space at the start of a note. Whether production has such rows is an open question.
    */
   private function encodingSamples()
   {
      $Samples = array(
         array('tblCollections_Collections', 'Title', 'Ñandú Society Records — 東京 branch', 150),
         array('tblCollections_Collections', 'Scope', "\u{200B}Zero-width space at the start; Łódź, Dvořák, Ελληνικά.", 0),
      );
      $Collections = $this->EncodingCollections;
      foreach($Samples as $Index => $Sample)
      {
         list($Table, $Column, $Text, $Length) = $Sample;
         $Type = $Length ? "varchar($Length)" : 'varchar(max)';
         $this->Db->query("UPDATE $Table SET $Column = CONVERT($Type, 0x" . bin2hex($Text) . ") WHERE ID = ?", array($Collections[$Index]));
      }
      if(!empty($this->Content))
      {
         $CollectionID = array_keys($this->Content)[0];
         if($this->Content[$CollectionID])
         {
            $this->Db->query("UPDATE tblCollections_Content SET Description = CONVERT(varchar(max), 0x" . bin2hex("\u{200B}Note with a leading zero-width space") . ") WHERE ID = ?",
               array($this->Content[$CollectionID][0]));
         }
      }
   }
}
