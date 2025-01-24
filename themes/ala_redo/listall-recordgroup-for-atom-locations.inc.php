<?php
isset($_ARCHON) or die();

require 'vendor/autoload.php';

require 'listall-atom-repository-info.inc.php';

if($_REQUEST['csv']){
	if($_REQUEST['output']){
		$filename = $_REQUEST['output'];
	} else {
		$filename = 'atom-csv-recordgroup-'.strtolower($unitSourcePrefix);

		if($_REQUEST['rg']){
			$filename .= "_rg". strtolower($_REQUEST['rg']);
		}
	}

	header('Content-type: text/xml; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'.csv"');

	$_ARCHON->PublicInterface->DisableTheme = true;
} else {
	echo("<h1 id='titleheader'>" . strip_tags($_ARCHON->PublicInterface->Title) . "<br />");
	echo("Full list of locations for records series by record group in Archon, formatted for AtoM CSV import<br />");
	echo("<span style='font-size:14px'>");
	echo date("Y-m-d H:i:s");
	echo("<br /><a href=https://".($_SERVER['HTTP_HOST']). ($_SERVER['REQUEST_URI'])."&disabletheme=true&csv=true>Download CSV</a>");
	echo("</span>");
	echo("</h1>\n");
	echo("<div >");
}

if(!$_ARCHON->Error)
   {
    
	 //$Characters = range('A','Z');
	//$Characters[] = "#";

	 $selectedRecordGroup = $_REQUEST['rg'];

	 if(strlen($selectedRecordGroup)>0 && strlen($selectedRecordGroup)<3){
		$startingMessage = "Record Group {$selectedRecordGroup}";
	}else{
		$startingMessage = "No valid URL parameters -- examining Record Group 1";
		$selectedRecordGroup = 1;
	}

	if(!$_REQUEST['csv']){
		echo($startingMessage);
	}
	 
	 
	 $count = 0;
	 $publiccount = 0;
	 $arrExtentUnits = $_ARCHON->getAllExtentUnits();
	 $arrLanguages = $_ARCHON->getAllLanguages();
	 $arrDescriptiveRules = $_ARCHON->getAllDescriptiveRules();
	 
	 
	 $GenreSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Genre/Form of Material');
	 $GeogSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Geographic Name');
	 $CorpSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Corporate Name');
	 $NameSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Personal Name');
	 $OccupationSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Occupation');
	 $TopicalSubjectTypeID = $_ARCHON->getSubjectTypeIDFromString('Topical Term');


	 $atomLanguageCodesArray = [
        'aar' => 'aa', // Afar
        'abk' => 'ab', // Abkhazian
        'afr' => 'af', // Afrikaans
        'aka' => 'ak', // Akan
        'alb' => 'sq', // Albanian
        'amh' => 'am', // Amharic
        'ara' => 'ar', // Arabic
        'arg' => 'an', // Aragonese
        'arm' => 'hy', // Armenian
        'asm' => 'as', // Assamese
        'ava' => 'av', // Avaric
        'ave' => 'ae', // Avestan
        'aym' => 'ay', // Aymara
        'aze' => 'az', // Azerbaijani
        'bak' => 'ba', // Bashkir
        'bam' => 'bm', // Bambara
        'baq' => 'eu', // Basque
        'bel' => 'be', // Belarusian
        'ben' => 'bn', // Bengali
        'bih' => 'bh', // Bihari languages
        'bis' => 'bi', // Bislama
        'bos' => 'bs', // Bosnian
        'bre' => 'br', // Breton
        'bul' => 'bg', // Bulgarian
        'bur' => 'my', // Burmese
        'cat' => 'ca', // Catalan; Valencian
        'cha' => 'ch', // Chamorro
        'che' => 'ce', // Chechen
        'chi' => 'zh', // Chinese
        'chu' => 'cu', // Church Slavic; Old Slavonic; Church Slavonic; Old
        // Bulgarian; Old Church Slavonic
        'chv' => 'cv', // Chuvash
        'cor' => 'kw', // Cornish
        'cos' => 'co', // Corsican
        'cre' => 'cr', // Cree
        'cze' => 'cs', // Czech
        'dan' => 'da', // Danish
        'div' => 'dv', // Divehi; Dhivehi; Maldivian
        'dut' => 'nl', // Dutch; Flemish
        'dzo' => 'dz', // Dzongkha
        'eng' => 'en', // English
        'epo' => 'eo', // Esperanto
        'est' => 'et', // Estonian
        'ewe' => 'ee', // Ewe
        'fao' => 'fo', // Faroese
        'fij' => 'fj', // Fijian
        'fin' => 'fi', // Finnish
        'fre' => 'fr', // French
        'fry' => 'fy', // Western Frisian
        'ful' => 'ff', // Fulah
        'geo' => 'ka', // Georgian
        'ger' => 'de', // German
        'gla' => 'gd', // Gaelic; Scottish Gaelic
        'gle' => 'ga', // Irish
        'glg' => 'gl', // Galician
        'glv' => 'gv', // Manx
        'gre' => 'el', // Greek, Modern (1453-)
        'grn' => 'gn', // Guarani
        'guj' => 'gu', // Gujarati
        'hat' => 'ht', // Haitian; Haitian Creole
        'hau' => 'ha', // Hausa
        'heb' => 'he', // Hebrew
        'her' => 'hz', // Herero
        'hin' => 'hi', // Hindi
        'hmo' => 'ho', // Hiri Motu
        'hrv' => 'hr', // Croatian
        'hun' => 'hu', // Hungarian
        'ibo' => 'ig', // Igbo
        'ice' => 'is', // Icelandic
        'ido' => 'io', // Ido
        'iii' => 'ii', // Sichuan Yi; Nuosu
        'iku' => 'iu', // Inuktitut
        'ile' => 'ie', // Interlingue; Occidental
        'ina' => 'ia', // Interlingua (International Auxiliary Language
        // Association)
        'ind' => 'id', // Indonesian
        'ipk' => 'ik', // Inupiaq
        'ita' => 'it', // Italian
        'jav' => 'jv', // Javanese
        'jpn' => 'ja', // Japanese
        'kal' => 'kl', // Kalaallisut; Greenlandic
        'kan' => 'kn', // Kannada
        'kas' => 'ks', // Kashmiri
        'kau' => 'kr', // Kanuri
        'kaz' => 'kk', // Kazakh
        'khm' => 'km', // Central Khmer
        'kik' => 'ki', // Kikuyu; Gikuyu
        'kin' => 'rw', // Kinyarwanda
        'kir' => 'ky', // Kirghiz; Kyrgyz
        'kom' => 'kv', // Komi
        'kon' => 'kg', // Kongo
        'kor' => 'ko', // Korean
        'kua' => 'kj', // Kuanyama; Kwanyama
        'kur' => 'ku', // Kurdish
        'lao' => 'lo', // Lao
        'lat' => 'la', // Latin
        'lav' => 'lv', // Latvian
        'lim' => 'li', // Limburgan; Limburger; Limburgish
        'lin' => 'ln', // Lingala
        'lit' => 'lt', // Lithuanian
        'ltz' => 'lb', // Luxembourgish; Letzeburgesch
        'lub' => 'lu', // Luba-Katanga
        'lug' => 'lg', // Ganda
        'mac' => 'mk', // Macedonian
        'mah' => 'mh', // Marshallese
        'mal' => 'ml', // Malayalam
        'mao' => 'mi', // Maori
        'mar' => 'mr', // Marathi
        'may' => 'ms', // Malay
        'mlg' => 'mg', // Malagasy
        'mlt' => 'mt', // Maltese
        'mon' => 'mn', // Mongolian
        'nau' => 'na', // Nauru
        'nav' => 'nv', // Navajo; Navaho
        'nbl' => 'nr', // Ndebele, South; South Ndebele
        'nde' => 'nd', // Ndebele, North; North Ndebele
        'ndo' => 'ng', // Ndonga
        'nep' => 'ne', // Nepali
        'nno' => 'nn', // Norwegian Nynorsk; Nynorsk, Norwegian
        'nob' => 'nb', // Bokmål, Norwegian; Norwegian Bokmål
        'nor' => 'no', // Norwegian
        'nya' => 'ny', // Chichewa; Chewa; Nyanja
        'oci' => 'oc', // Occitan (post 1500); Provençal
        'oji' => 'oj', // Ojibwa
        'ori' => 'or', // Oriya
        'orm' => 'om', // Oromo
        'oss' => 'os', // Ossetian; Ossetic
        'pan' => 'pa', // Panjabi; Punjabi
        'per' => 'fa', // Persian
        'pli' => 'pi', // Pali
        'pol' => 'pl', // Polish
        'por' => 'pt', // Portuguese
        'pus' => 'ps', // Pushto; Pashto
        'que' => 'qu', // Quechua
        'roh' => 'rm', // Romansh
        'rum' => 'ro', // Romanian; Moldavian; Moldovan
        'run' => 'rn', // Rundi
        'rus' => 'ru', // Russian
        'sag' => 'sg', // Sango
        'san' => 'sa', // Sanskrit
        'sin' => 'si', // Sinhala; Sinhalese
        'slo' => 'sk', // Slovak
        'slv' => 'sl', // Slovenian
        'sme' => 'se', // Northern Sami
        'smo' => 'sm', // Samoan
        'sna' => 'sn', // Shona
        'snd' => 'sd', // Sindhi
        'som' => 'so', // Somali
        'sot' => 'st', // Sotho, Southern
        'spa' => 'es', // Spanish; Castilian
        'srd' => 'sc', // Sardinian
        'srp' => 'sr', // Serbian
        'ssw' => 'ss', // Swati
        'sun' => 'su', // Sundanese
        'swa' => 'sw', // Swahili
        'swe' => 'sv', // Swedish
        'tah' => 'ty', // Tahitian
        'tam' => 'ta', // Tamil
        'tat' => 'tt', // Tatar
        'tel' => 'te', // Telugu
        'tgk' => 'tg', // Tajik
        'tgl' => 'tl', // Tagalog
        'tha' => 'th', // Thai
        'tib' => 'bo', // Tibetan
        'tir' => 'ti', // Tigrinya
        'ton' => 'to', // Tonga (Tonga Islands)
        'tsn' => 'tn', // Tswana
        'tso' => 'ts', // Tsonga
        'tuk' => 'tk', // Turkmen
        'tur' => 'tr', // Turkish
        'twi' => 'tw', // Twi
        'uig' => 'ug', // Uighur; Uyghur
        'ukr' => 'uk', // Ukrainian
        'urd' => 'ur', // Urdu
        'uzb' => 'uz', // Uzbek
        'ven' => 've', // Venda
        'vie' => 'vi', // Vietnamese
        'vol' => 'vo', // Volapük
        'wel' => 'cy', // Welsh
        'wln' => 'wa', // Walloon
        'wol' => 'wo', // Wolof
        'xho' => 'xh', // Xhosa
        'yid' => 'yi', // Yiddish
        'yor' => 'yo', // Yoruba
        'zha' => 'za', // Zhuang; Chuang
        'zul' => 'zu', // Zulu
        ];
	 //include("themes/{$_ARCHON->PublicInterface->Theme}/atom-languages.inc.php");

	 

	 $arrHeadersForAtoM = [
		"legacyId",
		"parentId",
		"qubitParentSlug",
		"accessionNumber",
		"identifier",
		"title",
		"levelOfDescription",
		"extentAndMedium",
		"repository",
		"archivalHistory",
		"acquisition",
		"scopeAndContent",
		"appraisal",
		"accruals",
		"arrangement",
		"accessConditions",
		"reproductionConditions",
		"language",
		"script",
		"languageNote",
		"physicalCharacteristics",
		"findingAids",
		"locationOfOriginals",
		"locationOfCopies",
		"relatedUnitsOfDescription",
		"publicationNote",
		"digitalObjectPath",
		"digitalObjectURI",
		"generalNote",
		"subjectAccessPoints",
		"placeAccessPoints",
		"nameAccessPoints",
		"genreAccessPoints",
		"descriptionIdentifier",
		"institutionIdentifier",
		"rules",
		"descriptionStatus",
		"levelOfDetail",
		"revisionHistory",
		"languageOfDescription",
		"scriptOfDescription",
		"sources",
		"archivistNote",
		"publicationStatus",
		"physicalObjectName",
		"physicalObjectLocation",
		"physicalObjectType",
		"alternativeIdentifiers",
		"alternativeIdentifierLabels",
		"eventDates",
		"eventTypes",
		"eventStartDates",
		"eventEndDates",
		"eventActors",
		"eventActorHistories",
		"culture",
	 ];
	 
	if($_REQUEST['csv']) {
		$out = fopen('php://output', 'w');
		fputcsv($out, $arrHeadersForAtoM);
	} else {
		echo("<table id='list-styled'>");
	
		echo("<tr><th>");
		echo(implode("</th><th>",$arrHeadersForAtoM));
		echo("</th></tr>");
	}
	$selectedRecordGroupID = $_ARCHON->getClassificationIDForNumber($selectedRecordGroup);

	 if($selectedRecordGroupID) {  
		$objRecordGroup = New Classification($selectedRecordGroupID); 
		$objRecordGroup->dbLoad();
		
		//add line for record group
		$legacyIdForAtoM="";
		$parentIdForAtoM="";
		$qubitParentSlugForAtoM="";
		$accessionNumberForAtoM="";
		$identifierForAtoM="";
		$titleForAtoM="";
		$levelOfDescriptionForAtoM="";
		$extentAndMediumForAtoM="";
		$repositoryForAtoM="";
		$archivalHistoryForAtoM="";
		$acquisitionForAtoM="";
		$scopeAndContentForAtoM="";
		$appraisalForAtoM="";
		$accrualsForAtoM="";
		$arrangementForAtoM="";
		$accessConditionsForAtoM="";
		$reproductionConditionsForAtoM="";
		$languageForAtoM="";
		$scriptForAtoM="";
		$languageNoteForAtoM="";
		$physicalCharacteristicsForAtoM="";
		$findingAidsForAtoM="";
		$locationOfOriginalsForAtoM="";
		$locationOfCopiesForAtoM="";
		$relatedUnitsOfDescriptionForAtoM="";
		$publicationNoteForAtoM="";
		$digitalObjectPathForAtoM="";
		$digitalObjectURIForAtoM="";
		$generalNoteForAtoM="";
		$subjectAccessPointsForAtoM="";
		$placeAccessPointsForAtoM="";
		$nameAccessPointsForAtoM="";
		$genreAccessPointsForAtoM="";
		$descriptionIdentifierForAtoM="";
		$institutionIdentifierForAtoM="";
		$rulesForAtoM="";
		$descriptionStatusForAtoM="";
		$levelOfDetailForAtoM="";
		$revisionHistoryForAtoM="";
		$languageOfDescriptionForAtoM="";
		$scriptOfDescriptionForAtoM="";
		$sourcesForAtoM="";
		$archivistNoteForAtoM="";
		$publicationStatusForAtoM="";
		$physicalObjectNameForAtoM="";
		$physicalObjectLocationForAtoM="";
		$physicalObjectTypeForAtoM="";
		$alternativeIdentifiersForAtoM="";
		$alternativeIdentifierLabelsForAtoM="";
		$eventDatesForAtoM="";
		$eventTypesForAtoM="";
		$eventStartDatesForAtoM="";
		$eventEndDatesForAtoM="";
		$eventActorsForAtoM="";
		$eventActorHistoriesForAtoM="";
		$cultureForAtoM="";
		
		/** [legacyId] Archon Fields: ID (plus prefix); Notes: archon id prefixed with ala, archives, ihlc, or rbml*/
		if($objRecordGroup->ID)
		{
			$legacyIdForAtoM = $unitSourcePrefix."-c-".$objRecordGroup->getString('ID');
		}
		/** [identifier] Archon Fields: ClassficationIdentifier; */
		if($objRecordGroup->ClassificationIdentifier){
			$identifierForAtoM = $objRecordGroup->getString('ClassificationIdentifier');
		}

		/**classification as genre term */
		//add classification record subgroup as genre
		if($atomImportClassificationAsGenre){
			$genreAccessPointsForAtoM = $unitSourcePrefix ." ";
			//padded subgroup number
			$genreAccessPointsForAtoM .= str_pad($objRecordGroup->getString('ClassificationIdentifier'),2,'0',STR_PAD_LEFT);
			//title of subgroup
			$genreAccessPointsForAtoM .= " ". $objRecordGroup->Title;
		}

		/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
		$repositoryForAtoM = $defaultRepositoryForAtom;

		/** [title] Archon Fields: Title; Notes: title*/
		if($objRecordGroup->Title)
		{
			$titleForAtoM = $objRecordGroup->getString('Title');
		}

		/** [levelOfDescription] Archon Fields: ; Notes: Record Group or Record Subgroup*/
		$levelOfDescriptionForAtoM="Record Group";

		/** [publicationStatus] Archon Fields: ; Notes: all published*/
		$publicationStatusForAtoM="Published";

		/** [scopeAndContent] Archon Fields: Description; Notes: */
		if($objRecordGroup->Description)
		{
			$scopeAndContentForAtoM = $objRecordGroup->getString('Description');
		}
		/** [alternativeIdentifiers] Archon Fields: ID; Notes: archon id*/
		if($objRecordGroup->ID)
		{
			$alternativeIdentifiersForAtoM = $objRecordGroup->getString('ID');
		}

		/** [alternativeIdentifierLabels] Archon Fields: ; Notes: "ArchonID"*/
		$alternativeIdentifierLabelsForAtoM = "Archon ID";

		/** [eventActors] Archon Fields: ; Notes: creator*/
		if($objRecordGroup->Creator)
		{
			//$objRecordGroup->Creator->dbLoad();
			$eventActorsForAtoM = strip_tags($objRecordGroup->Creator->toString());
			$eventDatesForAtoM = "NULL";
			$eventTypesForAtoM = "Creation";
		}
		/** [culture] Archon Fields: ; Notes: en (for english)*/
		$cultureForAtoM="en";

		//put the data into the html table or csv accordingly
		include 'listall-atom-info-obj-line.inc.php';
		
		//iterate to add lines for subgroups
		if($objRecordGroup->hasChildren()){
			$arrSubGroups = $_ARCHON->getChildClassifications($objRecordGroup->ID);

			foreach($arrSubGroups as $objRecordSubGroup){
				$objRecordSubGroup->dbLoad();

				$legacyIdForAtoM="";
				$parentIdForAtoM="";
				$qubitParentSlugForAtoM="";
				$accessionNumberForAtoM="";
				$identifierForAtoM="";
				$titleForAtoM="";
				$levelOfDescriptionForAtoM="";
				$extentAndMediumForAtoM="";
				$repositoryForAtoM="";
				$archivalHistoryForAtoM="";
				$acquisitionForAtoM="";
				$scopeAndContentForAtoM="";
				$appraisalForAtoM="";
				$accrualsForAtoM="";
				$arrangementForAtoM="";
				$accessConditionsForAtoM="";
				$reproductionConditionsForAtoM="";
				$languageForAtoM="";
				$scriptForAtoM="";
				$languageNoteForAtoM="";
				$physicalCharacteristicsForAtoM="";
				$findingAidsForAtoM="";
				$locationOfOriginalsForAtoM="";
				$locationOfCopiesForAtoM="";
				$relatedUnitsOfDescriptionForAtoM="";
				$publicationNoteForAtoM="";
				$digitalObjectPathForAtoM="";
				$digitalObjectURIForAtoM="";
				$generalNoteForAtoM="";
				$subjectAccessPointsForAtoM="";
				$placeAccessPointsForAtoM="";
				$nameAccessPointsForAtoM="";
				$genreAccessPointsForAtoM="";
				$descriptionIdentifierForAtoM="";
				$institutionIdentifierForAtoM="";
				$rulesForAtoM="";
				$descriptionStatusForAtoM="";
				$levelOfDetailForAtoM="";
				$revisionHistoryForAtoM="";
				$languageOfDescriptionForAtoM="";
				$scriptOfDescriptionForAtoM="";
				$sourcesForAtoM="";
				$archivistNoteForAtoM="";
				$publicationStatusForAtoM="";
				$physicalObjectNameForAtoM="";
				$physicalObjectLocationForAtoM="";
				$physicalObjectTypeForAtoM="";
				$alternativeIdentifiersForAtoM="";
				$alternativeIdentifierLabelsForAtoM="";
				$eventDatesForAtoM="";
				$eventTypesForAtoM="";
				$eventStartDatesForAtoM="";
				$eventEndDatesForAtoM="";
				$eventActorsForAtoM="";
				$eventActorHistoriesForAtoM="";
				$cultureForAtoM="";
				
				/** [legacyId] Archon Fields: ID (plus prefix); Notes: archon id prefixed with ala, archives, ihlc, or rbml*/
				if($objRecordSubGroup->ID)
				{
					$legacyIdForAtoM = $unitSourcePrefix."-c-".$objRecordSubGroup->getString('ID');
				}

				/** [parentId] Archon Fields: ParentID (collection content) or ClassificationID (collection); Notes: archon id of parent prefixed with ala, archives, ihlc, or rbml*/
				if($objRecordSubGroup->ParentID)
				{
					$parentIdForAtoM = $unitSourcePrefix."-c-".$objRecordSubGroup->getString('ParentID');
				}

				/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
				$repositoryForAtoM = $defaultRepositoryForAtom;

				/** [identifier] Archon Fields: CollectionIdentifier; Notes: collection identifier or record series number*/
				if($objRecordSubGroup->ClassificationIdentifier){
					$identifierForAtoM = $objRecordSubGroup->getString('ClassificationIdentifier');
				}

				/**classification as genre term */
				//add classification record subgroup as genre
				if($atomImportClassificationAsGenre){
					$genreAccessPointsForAtoM = $unitSourcePrefix ." ";
					//record group number
					$genreAccessPointsForAtoM .= $objRecordGroup->getString('ClassificationIdentifier') ."-";
					//padded subgroup number
					$genreAccessPointsForAtoM .= str_pad($objRecordSubGroup->getString('ClassificationIdentifier'),2,'0',STR_PAD_LEFT);
					//title of subgroup
					$genreAccessPointsForAtoM .= " ". $objRecordSubGroup->Title;
				}

				/** [title] Archon Fields: Title; Notes: title*/
				if($objRecordSubGroup->Title)
				{
					$titleForAtoM = $objRecordSubGroup->getString('Title');
				}

				/** [levelOfDescription] Archon Fields: ; Notes: Record Group or Record Subgroup*/
				$levelOfDescriptionForAtoM="Record Subgroup";

				/** [publicationStatus] Archon Fields: ; Notes: all published*/
				$publicationStatusForAtoM="Published";

				/** [scopeAndContent] Archon Fields: Description; Notes: */
				if($objRecordSubGroup->Description)
				{
					$scopeAndContentForAtoM = $objRecordSubGroup->getString('Description');
				}
				/** [alternativeIdentifiers] Archon Fields: ID; Notes: archon id*/
				if($objRecordSubGroup->ID)
				{
					$alternativeIdentifiersForAtoM = $objRecordSubGroup->getString('ID');
				}

				/** [alternativeIdentifierLabels] Archon Fields: ; Notes: "ArchonID"*/
				$alternativeIdentifierLabelsForAtoM = "Archon ID";

				/** [eventActors] Archon Fields: ; Notes: creator*/
				if($objRecordSubGroup->Creator)
				{
					$eventActorsForAtoM = strip_tags($objCreator->toString());
					$eventDatesForAtoM = "NULL";
					$eventTypesForAtoM = "Creation";
				}
				/** [culture] Archon Fields: ; Notes: en (for english)*/
				$cultureForAtoM="en";

				//put the data into the html table or csv accordingly
				include 'listall-atom-info-obj-line.inc.php';
			

		//iterate to add lines for record series

			$objRecordSubGroup->dbLoadCollections();

			if(!empty($objRecordSubGroup->Collections))
			{

			foreach($objRecordSubGroup->Collections as $objCollection)
			{
				$objCollection->dbLoadLocationEntries();
			if(!empty($objCollection->LocationEntries)){
				foreach($objCollection->LocationEntries as $objLocationEntry){
					
					$legacyIdForAtoM="";
					$parentIdForAtoM="";
					$qubitParentSlugForAtoM="";
					$accessionNumberForAtoM="";
					$identifierForAtoM="";
					$titleForAtoM="";
					$levelOfDescriptionForAtoM="";
					$extentAndMediumForAtoM="";
					$repositoryForAtoM="";
					$archivalHistoryForAtoM="";
					$acquisitionForAtoM="";
					$scopeAndContentForAtoM="";
					$appraisalForAtoM="";
					$accrualsForAtoM="";
					$arrangementForAtoM="";
					$accessConditionsForAtoM="";
					$reproductionConditionsForAtoM="";
					$languageForAtoM="";
					$scriptForAtoM="";
					$languageNoteForAtoM="";
					$physicalCharacteristicsForAtoM="";
					$findingAidsForAtoM="";
					$locationOfOriginalsForAtoM="";
					$locationOfCopiesForAtoM="";
					$relatedUnitsOfDescriptionForAtoM="";
					$publicationNoteForAtoM="";
					$digitalObjectPathForAtoM="";
					$digitalObjectURIForAtoM="";
					$generalNoteForAtoM="";
					$subjectAccessPointsForAtoM="";
					$placeAccessPointsForAtoM="";
					$nameAccessPointsForAtoM="";
					$genreAccessPointsForAtoM="";
					$descriptionIdentifierForAtoM="";
					$institutionIdentifierForAtoM="";
					$rulesForAtoM="";
					$descriptionStatusForAtoM="";
					$levelOfDetailForAtoM="";
					$revisionHistoryForAtoM="";
					$languageOfDescriptionForAtoM="";
					$scriptOfDescriptionForAtoM="";
					$sourcesForAtoM="";
					$archivistNoteForAtoM="";
					$publicationStatusForAtoM="";
					$physicalObjectNameForAtoM="";
					$physicalObjectLocationForAtoM="";
					$physicalObjectTypeForAtoM="";
					$alternativeIdentifiersForAtoM="";
					$alternativeIdentifierLabelsForAtoM="";
					$eventDatesForAtoM="";
					$eventTypesForAtoM="";
					$eventStartDatesForAtoM="";
					$eventEndDatesForAtoM="";
					$eventActorsForAtoM="";
					$eventActorHistoriesForAtoM="";
					$cultureForAtoM="";
					
					/** [legacyId] Archon Fields: ID (plus prefix); Notes: archon id prefixed with ala, archives, ihlc, or rbml*/
					if($objCollection->getString('ID'))
					{
						$legacyIdForAtoM = $unitSourcePrefix."-a-".$objCollection->getString('ID');
					}

					/** [parentId] Archon Fields: ParentID (collection content) or ClassificationID (collection); Notes: archon id of parent prefixed with ala, archives, ihlc, or rbml*/
					if($atomImportNested && $objCollection->ClassificationID)
					{
						$parentIdForAtoM = $unitSourcePrefix."-a-".$objCollection->ClassificationID;
					}
					
					/** [qubitParentSlug] Archon Fields: ; Notes: only used after imported into atom (use legacy or this atom url slug)*/

					$qubitParentSlugForAtoM="";
					
					/** [accessionNumber] Archon Fields: ; Notes: */
					$accessionNumberForAtoM="";

					/** [identifier] Archon Fields: CollectionIdentifier; Notes: collection identifier or record series number*/
					if(!$atomImportNested && $objCollection->ClassificationID)
					{
						$objCollection->Classification = New Classification($objCollection->ClassificationID);
						$identifierForAtoM = $objCollection->Classification->toString(LINK_NONE, true, false, true, false) . '/';
						$identifierForAtoM = str_replace("/","-",$identifierForAtoM);
					}
					if($objCollection->CollectionIdentifier)
					{
						$identifierForAtoM .= $objCollection->getString('CollectionIdentifier');
					}

					/** [title] Archon Fields: Title; Notes: title*/
					if($objCollection->Title)
					{
						$titleForAtoM = $objCollection->getString('Title');
					}

					/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
					if(!empty($objCollection->RepositoryID))
					{
						$repositoryForAtoM = $arrRepositoriesForAtoM[$objCollection->RepositoryID];
					}

					/** [publicationStatus] Archon Fields: Enabled (converting as follows: 1=Published, 0=Draft); Notes: Published or Draft*/
					if($objCollection->Enabled)
					{
						$accessible = $objCollection->getString('Enabled');
						if($accessible == 1)
						{
							$publicationStatusForAtoM = "Published";
							$publiccount++;
						} else {
							$publicationStatusForAtoM = "Draft";
						}
					} else {
						$publicationStatusForAtoM = "Draft";
					}

					/** [physicalObjectName] Archon Fields: (note: these are the terms from the json) Locations:Content, Locations:Extent, Locations:ExtentUnitID (convert to text), Locations:Shelf (if barcode); Notes: LocationContent, extent and extent unit in parenthesis?, and Barcode (if in shelf field)*/
					if($objLocationEntry->Content){
						$physicalObjectNameForAtoM = $objLocationEntry->getString('Content');
						
						//for IHLC, convert single dashes for unnumbered single containers
						if($unitSourcePrefix == "IHLC" && $physicalObjectNameForAtoM == "-"){
							$physicalObjectNameForAtoM = "All";
						}
					}

					if($objLocationEntry->Extent && $objLocationEntry->ExtentUnit){
						$physicalObjectNameForAtoM .= " (". $objLocationEntry->getString('Extent') ." ". $objLocationEntry->getString('ExtentUnit').")";
					}

					if($objLocationEntry->Shelf){
						$boxHasBarcode=false;
						$testBoxBarcode = trim((string) $objLocationEntry->Shelf);
				
						if(is_numeric($testBoxBarcode) AND strlen($testBoxBarcode)==14){
							$boxHasBarcode=true;
						}

						if($boxHasBarcode){
							$physicalObjectNameForAtoM .= "; Barcode: ".$objLocationEntry->getString('Shelf');
						}
					}

					/** [physicalObjectLocation] Archon Fields: (note: these are the terms from the json) Locations:Location, Locations:Range, Locations:Section, Locations:Shelf (if not barcode); Notes: Location, Range, Section, Shelf (if not holding barcode)*/
					/*
					if($objLocationEntry->Location){
						$physicalObjectLocationForAtoM = $objLocationEntry->getString('Location');
					}
					*/
					if($objLocationEntry->RangeValue){
						$physicalObjectLocationForAtoM .= "[Range] " . $objLocationEntry->getString('RangeValue');
					}
					if($objLocationEntry->Section){
						$physicalObjectLocationForAtoM .= " [Section/Shelf] " . $objLocationEntry->getString('Section');
					}
					if($objLocationEntry->Shelf && !$boxHasBarcode){
						$physicalObjectLocationForAtoM .= " [Shelf] ".$objLocationEntry->getString('Shelf');
					}

					/** [physicalObjectType] Archon Fields: ; Notes: options defined in AtoM taxonomy -- indicates type of box; would we want to use extent and extent unit here as a type of box? OR hack to get controlled vocab for location info*/
					if($objLocationEntry->Location){
						$physicalObjectTypeForAtoM = $objLocationEntry->getString('Location');
					}
					/*
					if($objLocationEntry->ExtentUnit){
						$physicalObjectTypeForAtoM = $objLocationEntry->getString('ExtentUnit');
					}
					//use the extent unit for the box type unless it has a conversion specified
					if($arrExtentConversionForAtomBoxType[$physicalObjectTypeForAtoM]){
						$physicalObjectTypeForAtoM = $arrExtentConversionForAtomBoxType[$physicalObjectTypeForAtoM];
					}
					$physicalObjectTypeForAtoM = ucfirst($physicalObjectTypeForAtoM);
					*/

					/** [culture] Archon Fields: ; Notes: en (for english)*/
					$cultureForAtoM="en";
					
					include 'listall-atom-info-obj-line.inc.php';
					
				}
			} 
         }
		}
		}
	}

	 }
	if(!$_REQUEST['csv']) {
		echo("</table>");
	}
}

if(!$_REQUEST['csv']){
	echo("</div>");
}

?>