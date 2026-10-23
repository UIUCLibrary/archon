<?php
/**
 * Deterministic synthetic text for the sample data (seeded with mt_srand() by generate.php).
 *
 * Everything here is invented from word lists and templates. The shapes (lengths, date formats,
 * punctuation, encoding oddities) follow tests/research/live-survey/summary.md; no live text is copied.
 */
class SampleText
{
   public static $Surnames = array('Abernathy', 'Albright', 'Alcott', 'Ambrose', 'Ashby', 'Baird', 'Barlow', 'Beaumont', 'Bellamy', 'Bertram', 'Blackwood', 'Bramwell',
      'Brennan', 'Briggs', 'Calloway', 'Carver', 'Castellano', 'Chamberlin', 'Chandler', 'Corbett', 'Crandall', 'Dalton', 'Delacroix', 'Donnelly', 'Draper', 'Dunbar',
      'Eastman', 'Ellery', 'Emmerich', 'Fairbanks', 'Falkner', 'Fenwick', 'Fitzgerald', 'Fletcher', 'Galloway', 'Garrick', 'Gilchrist', 'Goodwin', 'Grayson', 'Halloran',
      'Hartwell', 'Haskins', 'Hawthorne', 'Hennessey', 'Holloway', 'Huxley', 'Iverson', 'Jaworski', 'Jennings', 'Kavanagh', 'Kellerman', 'Kincaid', 'Kowalczyk', 'Lachance',
      'Langford', 'Lindqvist', 'Lockwood', 'Lowell', 'Mabry', 'Macintyre', 'Marchetti', 'Merriweather', 'Middleton', 'Montague', 'Moreau', 'Nakamura', 'Navarro', 'Nesbitt',
      'Novak', 'Oakley', "O'Rourke", 'Ostrowski', 'Pemberton', 'Pendleton', 'Petrov', 'Prescott', 'Quimby', 'Radcliffe', 'Ramsey', 'Ravenscroft', 'Reinholt', 'Rosales',
      'Sandoval', 'Schuyler', 'Sedgwick', 'Shackleford', 'Sinclair', 'Soderberg', 'Stanhope', 'Sterling', 'Szymanski', 'Tanaka', 'Thackeray', 'Thornbury', 'Townsend',
      'Underwood', 'Vandermeer', 'Varga', 'Wainwright', 'Wakefield', 'Whitcomb', 'Whitfield', 'Winslow', 'Yamamoto', 'Yardley', 'Zellweger', 'Zimmerman', 'Brückner',
      'Ødegaard', 'Lefèvre', 'Muñoz', 'Kodaly-Nemeth', 'Guille-Allès', 'Dvořák', 'Søndergaard', 'Çelik', 'Esterházy', 'Mæland');
   public static $GivenNames = array('Abigail', 'Adelaide', 'Albert', 'Alice', 'Ambrose', 'Amelia', 'Arthur', 'Beatrice', 'Benjamin', 'Bernard', 'Carl', 'Caroline', 'Catherine',
      'Charles', 'Clara', 'Cornelius', 'Daniel', 'Dorothy', 'Edgar', 'Edith', 'Edmund', 'Eleanor', 'Elijah', 'Eliza', 'Emil', 'Emma', 'Ernest', 'Esther', 'Eugene', 'Evelyn',
      'Florence', 'Frances', 'Frederick', 'George', 'Gertrude', 'Grace', 'Harold', 'Harriet', 'Helen', 'Henry', 'Herbert', 'Ida', 'Irene', 'Isaac', 'Jacob', 'James',
      'Jane', 'Josephine', 'Julia', 'Karl', 'Katherine', 'Lillian', 'Louis', 'Lucy', 'Mabel', 'Margaret', 'Marian', 'Martha', 'Matthias', 'Mildred', 'Nathaniel', 'Nora',
      'Oscar', 'Otto', 'Pauline', 'Philip', 'Rachel', 'Raymond', 'Rose', 'Ruth', 'Samuel', 'Sarah', 'Silas', 'Sophia', 'Theodore', 'Thomas', 'Ursula', 'Victor',
      'Viola', 'Walter', 'Wilhelmina', 'William', 'Winifred', 'Zelda', 'José', 'Zoë', 'Renée', 'Søren', 'Jürgen', 'Hiroshi', 'Mei', 'Rinshirō', 'Zoltán', 'Agnieszka');
   public static $Fields = array('Agriculture', 'Agricultural Engineering', 'Animal Science', 'Anthropology', 'Architecture', 'Astronomy', 'Bacteriology', 'Botany', 'Ceramic Engineering',
      'Chemistry', 'Civil Engineering', 'Classics', 'Dairy Science', 'Economics', 'Education', 'Electrical Engineering', 'English', 'Entomology', 'Forestry', 'French',
      'Geography', 'Geology', 'Germanic Languages', 'History', 'Home Economics', 'Horticulture', 'Journalism', 'Landscape Architecture', 'Library Science', 'Linguistics',
      'Mathematics', 'Mechanical Engineering', 'Music', 'Philosophy', 'Physical Education', 'Physics', 'Physiology', 'Political Science', 'Psychology', 'Sociology',
      'Speech and Hearing Science', 'Theatre', 'Urban Planning', 'Veterinary Medicine', 'Zoology', 'Aeronautical Engineering', 'Art and Design', 'Labor Relations');
   public static $Places = array('Urbana', 'Champaign', 'Springfield', 'Peoria', 'Chicago', 'Galesburg', 'Danville', 'Decatur', 'Bloomington', 'Rockford', 'Cairo', 'Quincy',
      'Moline', 'Kankakee', 'Joliet', 'Carbondale', 'Evanston', 'Ottawa', 'Alton', 'Jacksonville', 'Mattoon', 'Effingham', 'Rantoul', 'Monticello', 'Tuscola', 'Paxton',
      'Mahomet', 'Savoy', 'Vandalia', 'Kaskaskia', 'New Salem', 'Nauvoo', 'Galena', 'Shawneetown', 'Belleville', 'Edwardsville', 'Macomb', 'Normal', 'Charleston', 'DeKalb');
   public static $Counties = array('Champaign', 'Vermilion', 'Piatt', 'Douglas', 'Coles', 'McLean', 'Sangamon', 'Adams', 'Cook', 'Peoria', 'Macon', 'Ford', 'Iroquois', 'Kane',
      'Knox', 'Madison', 'Menard', 'Morgan', 'Ogle', 'Pike', 'Randolph', 'Shelby', 'Tazewell', 'Union', 'Wabash');
   public static $Countries = array('Argentina', 'Australia', 'Brazil', 'Canada', 'China', 'Egypt', 'France', 'Germany', 'Greece', 'India', 'Ireland', 'Italy', 'Japan',
      'Kenya', 'Mexico', 'Netherlands', 'Norway', 'Peru', 'Poland', 'Russia', 'South Africa', 'Spain', 'Sweden', 'Turkey', 'United Kingdom');
   public static $Forms = array('Correspondence', 'Photographs', 'Scrapbooks', 'Diaries', 'Sheet music', 'Programs', 'Posters', 'Clippings', 'Minutes', 'Reports',
      'Audiotapes', 'Motion pictures', 'Maps', 'Ledgers', 'Account books', 'Speeches', 'Lecture notes', 'Oral histories', 'Newsletters', 'Pamphlets', 'Blueprints',
      'Lantern slides', 'Postcards', 'Sound recordings', 'Manuscripts', 'Broadsides', 'Yearbooks', 'Memorabilia', 'Videocassettes', 'Glass plate negatives');
   public static $Topics = array('student life', 'campus planning', 'agricultural extension', 'band music', 'athletics', 'women in science', 'labor history', 'civil rights',
      'rural electrification', 'soil conservation', 'public health', 'radio broadcasting', 'teacher education', 'veterans', 'international students', 'architecture',
      'library history', 'Lincoln family', 'prairie ecology', 'railroads', 'temperance', 'suffrage', 'the Civil War', 'immigration', 'county fairs', 'Olympic Games',
      'intellectual freedom', 'book publishing', 'children\'s literature', 'school libraries', 'printing history', 'cartography', 'opera', 'jazz', 'church music');
   public static $Activities = array('the administration of', 'research on', 'teaching of', 'publications about', 'correspondence concerning', 'committee work on',
      'public lectures about', 'field work in', 'fundraising for', 'the organization of', 'surveys of', 'photographs documenting', 'recordings of', 'planning for');
   public static $Occupations = array('Architects', 'Bandmasters', 'Chemists', 'Composers', 'Editors', 'Engineers', 'Farmers', 'Librarians', 'Lawyers', 'Physicians',
      'Photographers', 'Publishers', 'Teachers', 'Women college teachers', 'Surveyors', 'Missionaries', 'Coaches (Athletics)', 'Journalists', 'Actors', 'Booksellers');
   public static $Functions = array('Accreditation', 'Admissions', 'Alumni relations', 'Budgeting', 'Commencement', 'Curriculum planning', 'Fundraising', 'Grant administration',
      'Personnel management', 'Recruiting', 'Student discipline', 'Policy making');
   public static $Greek = array('Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi', 'Rho', 'Sigma', 'Tau', 'Phi', 'Chi', 'Psi', 'Omega');
   public static $Months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
   public static $Words = array('annual', 'reports', 'records', 'committee', 'files', 'notes', 'drafts', 'proofs', 'meeting', 'agenda', 'program', 'survey', 'study',
      'project', 'plans', 'sketches', 'memoranda', 'subject', 'general', 'personal', 'official', 'printed', 'material', 'press', 'releases', 'guest', 'concert',
      'tour', 'season', 'convention', 'conference', 'workshop', 'exhibit', 'catalog', 'inventory', 'budget', 'accounts', 'receipts', 'contracts', 'awards');

   public static function chance($Probability)
   {
      return mt_rand() / mt_getrandmax() < $Probability;
   }

   public static function between($Min, $Max)
   {
      return mt_rand($Min, $Max);
   }

   public static function pick($List)
   {
      return $List[mt_rand(0, count($List) - 1)];
   }

   public static function pickSome($List, $Count)
   {
      $Count = min($Count, count($List));
      $Picked = array();
      while(count($Picked) < $Count)
      {
         $Picked[self::pick($List)] = true;
      }
      return array_keys($Picked);
   }

   public static function shuffled($List)
   {
      for($i = count($List) - 1; $i > 0; $i--)
      {
         $j = mt_rand(0, $i);
         $Tmp = $List[$i];
         $List[$i] = $List[$j];
         $List[$j] = $Tmp;
      }
      return $List;
   }

   public static function person()
   {
      $Given = self::pick(self::$GivenNames);
      $Middle = self::chance(0.4) ? ' ' . chr(mt_rand(65, 90)) . '.' : '';
      return array(self::pick(self::$Surnames), $Given . $Middle);
   }

   public static function personDates()
   {
      $Born = mt_rand(1790, 1960);
      switch(mt_rand(0, 9))
      {
         case 0: return 'b. ' . $Born;
         case 1: return 'fl. ' . ($Born + 30);
         case 2: return 'ca. ' . $Born . '-' . ($Born + mt_rand(40, 90));
         case 3: return $Born . '-' . ($Born + mt_rand(40, 90)) . '?';
         case 4: return $Born . '-';
         case 5: return (int)($Born / 100 + 1) . 'th century';
         default: return $Born . '-' . min(2024, $Born + mt_rand(30, 95));
      }
   }

   public static function corporateName()
   {
      switch(mt_rand(0, 7))
      {
         case 0: return 'University of Illinois at Urbana-Champaign. Department of ' . self::pick(self::$Fields);
         case 1: return self::pick(self::$Greek) . ' ' . self::pick(self::$Greek) . ' ' . self::pick(self::$Greek) . '. ' . self::pick(self::$Greek) . ' Chapter (University of Illinois)';
         case 2: return self::pick(self::$Places) . ' ' . self::pick(array('Woman\'s Club', 'Historical Society', 'Public Library', 'Board of Education', 'Choral Society', 'Chamber of Commerce'));
         case 3: return 'Illinois ' . self::pick(array('State Teachers Association', 'Library Association', 'Farm Bureau', 'Bandmasters Association', 'Press Association', 'Academy of Science'));
         case 4: return self::pick(self::$Fields) . ' Club (University of Illinois)';
         case 5: return 'Office and Professional Employees International Union, Local ' . mt_rand(2, 400);
         case 6: return 'Committee on ' . ucfirst(self::pick(self::$Topics));
         default: return self::pick(self::$Surnames) . ' & ' . self::pick(self::$Surnames) . ' ' . self::pick(array('Company', 'Publishing Co.', 'Music Company', 'Architects', 'Printers'));
      }
   }

   /**
    * A date string and its normal begin/end years: array(display, begin, end).
    */
   public static function dates($From = 1840, $To = 2022)
   {
      $Begin = mt_rand($From, $To - 1);
      $End = min($To, $Begin + mt_rand(0, 70));
      $Short = function($Year) { return substr((string)$Year, 2); };
      switch(mt_rand(0, 19))
      {
         case 0: return array($Begin . '-', $Begin, NULL);
         case 1: return array("circa $Begin-$End", $Begin, $End);
         case 2: return array("ca. $Begin-$End", $Begin, $End);
         case 3: return array((int)($Begin / 10) * 10 . 's', (int)($Begin / 10) * 10, (int)($Begin / 10) * 10 + 9);
         case 4: return array('undated', NULL, NULL);
         case 5: return array("$Begin-undated", $Begin, NULL);
         case 6: return array(self::pick(self::$Months) . ' ' . mt_rand(1, 28) . ', ' . $Begin, $Begin, $Begin);
         case 7: return array(self::pick(self::$Months) . ' ' . $Begin, $Begin, $Begin);
         case 8:
            $Mid = min($End, $Begin + mt_rand(1, 5));
            return array("$Begin-" . $Short($Mid) . ', ' . min($To, $Mid + 2) . '-' . $Short($End), $Begin, $End);
         case 9: return array((int)($Begin / 10) * 10 . 's?-' . $End, (int)($Begin / 10) * 10, $End);
         case 10: return array("$Begin-circa $End", $Begin, $End);
         case 11: return array("ca. $Begin-$End, and " . min($To, $End + mt_rand(5, 30)), $Begin, min($To, $End + 30));
         case 12: return array('', NULL, NULL);
         case 13: return array($Begin . '-' . $Short($End), $Begin, $End);
         case 14: return array("$Begin", $Begin, $Begin);
         default: return array("$Begin-$End", $Begin, $End);
      }
   }

   /**
    * AcquisitionDate is varchar(8) YYYYMMDD in the schema, but live data also has zero months/days.
    */
   public static function acquisitionDate()
   {
      $Year = mt_rand(1920, 2023);
      switch(mt_rand(0, 9))
      {
         case 0: return sprintf('%04d0000', $Year);
         case 1: return sprintf('%04d%02d00', $Year, mt_rand(1, 12));
         default: return sprintf('%04d%02d%02d', $Year, mt_rand(1, 12), mt_rand(1, 28));
      }
   }

   public static function sentence()
   {
      $Year = mt_rand(1850, 2015);
      switch(mt_rand(0, 13))
      {
         case 0: return 'The ' . self::pick(array('papers', 'records', 'files', 'materials')) . ' document ' . self::pick(self::$Activities) . ' ' . self::pick(self::$Topics) . ' between ' . $Year . ' and ' . ($Year + mt_rand(2, 40)) . '.';
         case 1: return 'Included are ' . strtolower(self::pick(self::$Forms)) . ', ' . strtolower(self::pick(self::$Forms)) . ' and ' . strtolower(self::pick(self::$Forms)) . ' relating to ' . self::pick(self::$Topics) . '.';
         case 2: return 'Correspondents include ' . implode(', ', array_map(function($p) { return $p[1] . ' ' . $p[0]; }, array(self::person(), self::person()))) . ' and ' . implode(' ', array_reverse(self::person())) . '.';
         case 3: return 'Material from ' . $Year . ' to ' . ($Year + mt_rand(1, 9)) . ' reflects the department\'s growth during the ' . self::pick(array('war years', 'Depression', 'postwar expansion', 'centennial', 'reorganization')) . '.';
         case 4: return 'Born in ' . self::pick(self::$Places) . ', Illinois, in ' . $Year . ', ' . self::pick(array('she', 'he')) . ' studied ' . strtolower(self::pick(self::$Fields)) . ' and later taught at the University.';
         case 5: return 'The ' . self::pick(array('series', 'collection', 'subseries')) . ' is arranged ' . self::pick(array('chronologically', 'alphabetically by correspondent', 'by format and then chronologically', 'in original order', 'by subject')) . '.';
         case 6: return 'A ' . self::pick(array('folder list', 'box list', 'partial inventory', 'card index')) . ' prepared in ' . $Year . ' accompanies the ' . self::pick(array('records', 'collection', 'series')) . '.';
         case 7: return 'Researchers should note that ' . self::pick(array('some photographs are fragile', 'audio recordings require advance notice', 'student records are restricted for 75 years', 'oversize items are stored separately')) . '.';
         case 8: return 'Topics include ' . self::pick(self::$Topics) . ', ' . self::pick(self::$Topics) . ', and ' . self::pick(self::$Topics) . '.';
         case 9: return 'In ' . $Year . ' the ' . self::pick(array('office', 'committee', 'society', 'department', 'band')) . ' moved to ' . self::pick(self::$Places) . ', where it ' . self::pick(array('expanded its programs', 'published an annual report', 'hosted a national convention', 'merged with a related group')) . '.';
         case 10: return 'The ' . strtolower(self::pick(self::$Forms)) . ' (' . mt_rand(2, 300) . ' items) were ' . self::pick(array('transferred', 'donated', 'purchased', 'deposited')) . ' in ' . $Year . '.';
         case 11: return 'See also the ' . self::pick(self::$Surnames) . ' Papers and the records of the ' . self::pick(self::$Fields) . ' Department.';
         case 12: return ucfirst(self::pick(self::$Words)) . ' ' . self::pick(self::$Words) . ' and ' . self::pick(self::$Words) . ' ' . self::pick(self::$Words) . ' for ' . $Year . '-' . ($Year + 1) . ' are complete.';
         default: return 'Records of ' . self::pick(self::$Activities) . ' ' . self::pick(self::$Topics) . ' make up the bulk of the ' . self::pick(array('collection', 'series', 'material')) . '.';
      }
   }

   public static function paragraph($Min = 3, $Max = 7)
   {
      $Sentences = array();
      for($i = mt_rand($Min, $Max); $i > 0; $i--)
      {
         $Sentences[] = self::sentence();
      }
      return implode(' ', $Sentences);
   }

   /**
    * Text of roughly $Length characters in paragraphs separated by blank lines (Archon stores raw text).
    */
   public static function text($Length)
   {
      $Paragraphs = array();
      $Total = 0;
      while($Total < $Length)
      {
         $Paragraph = self::paragraph();
         $Paragraphs[] = $Paragraph;
         $Total += strlen($Paragraph) + 2;
      }
      return implode("\n\n", $Paragraphs);
   }

   public static function folderTitle()
   {
      switch(mt_rand(0, 9))
      {
         case 0: return ucfirst(self::pick(self::$Words)) . ' ' . self::pick(self::$Words);
         case 1: return self::pick(self::$Surnames) . ', ' . self::pick(self::$GivenNames);
         case 2: return self::pick(self::$Forms) . ', ' . mt_rand(1880, 2010);
         case 3: return ucfirst(self::pick(self::$Topics));
         case 4: return self::pick(self::$Places) . ' ' . self::pick(array('concert', 'tour', 'meeting', 'convention', 'office'));
         case 5: return self::pick(self::$Months) . '-' . self::pick(self::$Months) . ' ' . mt_rand(1890, 2005);
         case 6: return 'Correspondence, ' . chr(mt_rand(65, 77)) . '-' . chr(mt_rand(78, 90));
         default: return ucfirst(self::pick(self::$Words)) . ' ' . self::pick(self::$Words) . ', ' . mt_rand(1880, 2010) . '-' . mt_rand(10, 99);
      }
   }
}
