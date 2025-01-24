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
	echo("Full list of records series by record group in Archon, formatted for AtoM CSV import<br />");
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
				$objCollection->dbLoad();
				
				$count++;

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
					$parentIdForAtoM = $unitSourcePrefix."-c-".$objRecordSubGroup->getString('ID');
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

				/**classification as genre term */
				//add classification record subgroup as genre
				if($atomImportClassificationAsGenre && $objCollection->ClassificationID)
				{
					//create classification object if needed
					if($atomImportNested){
						$objCollection->Classification = New Classification($objCollection->ClassificationID);
					}
					$genreAccessPointsForAtoM = $unitSourcePrefix ." ";
					//record group number
					$genreAccessPointsForAtoM .= $objCollection->Classification->toString(LINK_NONE, false, false, true, false,"") ."-";
					//padded subgroup number
					$genreAccessPointsForAtoM .= str_pad($objCollection->Classification->toString(LINK_NONE, true, false, false, false,""),2,'0',STR_PAD_LEFT);
					//title of subgroup
					$genreAccessPointsForAtoM .= " ". strip_tags($objCollection->Classification-> toString(LINK_NONE, false, true, false, false,""));
				}

				/** [title] Archon Fields: Title; Notes: title*/
				if($objCollection->Title)
				{
					$titleForAtoM = $objCollection->getString('Title');
				}


				/** [levelOfDescription] Archon Fields: ; Notes: collection for ihlc and rbml, record series for archives and ala*/
				$levelOfDescriptionForAtoM = $atomLevelDescription;

				/** [extentAndMedium] Archon Fields: Extent, ExtentUnitID (convert to text first), AltExtentStatement; Notes: concatenate extent and extent unit plus alternate extent*/

				if($objCollection->ExtentUnitID)
				{
					$objCollection->ExtentUnit = New ExtentUnit($objCollection->ExtentUnitID);
					$objCollection->ExtentUnit->dbLoad();
				}
				else
				{
					$objCollection->ExtentUnit = New ExtentUnit($objCollection->ExtentUnitID);
				}
				if($objCollection->Extent)
				{
					$extentAndMediumForAtoM = preg_replace('/\.(\d)0/', ".$1", $objCollection->getString('Extent')) . " " . $objCollection->getString('ExtentUnit');
				}
				if($objCollection->ExtentUnitID && $objCollection->AltExtentStatement){
					$extentAndMediumForAtoM .= "; ";
				}
				if($objCollection->AltExtentStatement)
				{
					$extentAndMediumForAtoM .= $objCollection->AltExtentStatement;
				}

				/** [repository] Archon Fields: RepositoryID (convert to text); Notes: repository*/
				if(!empty($objCollection->RepositoryID))
				{
					$repositoryForAtoM = $arrRepositoriesForAtoM[$objCollection->RepositoryID];
				}

				/** [archivalHistory] Archon Fields: CustodialHistory; Notes: custodial history*/
				if($objCollection->CustodialHistory)
				{
					$archivalHistoryForAtoM = $objCollection->getString('CustodialHistory');
				}

				/** [acquisition] Archon Fields: AcquisitionSource, AcquisitionMethod, AcquisitionDate; Notes: acquisition - source, method, date*/
				if($objCollection->AcquisitionDate)
				{
					$acquisitionForAtoM = "Acquisition Date(s): ";
					if($objCollection->AcquisitionDateMonth <> "00")
					{
						$acquisitionForAtoM .= $objCollection->AcquisitionDateMonth . '/';
					}
					if($objCollection->AcquisitionDateDay <> "00")
					{
						$acquisitionForAtoM .= $objCollection->AcquisitionDateDay . '/';
					}
					$acquisitionForAtoM .= $objCollection->AcquisitionDateYear . ".  ";
				}
				if($objCollection->AcquisitionDate && $objCollection->AcquisitionSource){
					$acquisitionForAtoM .= "<br/><br/>";
				}
				if($objCollection->AcquisitionSource)
				{
					$acquisitionForAtoM .= "Acquisition Source: " .  $objCollection->getString('AcquisitionSource');
				}
				if($objCollection->AcquisitionMethod && $objCollection->AcquisitionSource){
					$acquisitionForAtoM .= "<br/><br/>";
				}
				if($objCollection->AcquisitionMethod)
				{
					$acquisitionForAtoM .= "Acquisition Method: " . $objCollection->getString('AcquisitionMethod');
				}

				/** [scopeAndContent] Archon Fields: Scope; Notes: scope*/
				if($objCollection->Scope)
				{
					//$scopeAndContentForAtoM = "tdb";
					$scopeAndContentForAtoM = $objCollection->getString('Scope');
				}

				/** [appraisal] Archon Fields: AppraisalInfo; Notes: appraisal info*/
				if($objCollection->AppraisalInfo)
				{
					$appraisalForAtoM = $objCollection->getString('AppraisalInfo');
				}

				/** [accruals] Archon Fields: AccrualInfo; Notes: accruals/additions*/
				if($objCollection->AccrualInfo)
				{
					$accrualsForAtoM = $objCollection->getString('AccrualInfo');
				}

				/** [arrangement] Archon Fields: Arrangement; Notes: arrangement*/
				if($objCollection->Arrangement){
					$arrangementForAtoM = $objCollection->getString('Arrangement');
				}

				/** [accessConditions] Archon Fields: AccessRestrictions; Notes: access/general*/
				if(($objCollection->AccessRestrictions))
				{
					$accessConditionsForAtoM = $objCollection->getString('AccessRestrictions');
				}

				/** [reproductionConditions] Archon Fields: UseRestrictions; Notes: use/rights*/
				if(($objCollection->UseRestrictions))
				{
					$reproductionConditionsForAtoM = $objCollection->getString('UseRestrictions');
				}

				/** [language] Archon Fields: Languages (stored as array; will need to iterate to get the codes AtoM wants); Notes: language codes with | between; note that this is in the collection object in archon but isn't in the json Chris provided for some reason*/
				$objCollection->dbLoadLanguages();
				if(!empty($objCollection->Languages) && $atomLanguageCodesArray)
				{
					$collLanguageArr = array();
					foreach ($objCollection->Languages as $objLanguage) {
						$collLanguageArr[]=$atomLanguageCodesArray[$objLanguage->getString('LanguageShort', 0, false)];
					}
					$languageForAtoM = implode($collLanguageArr,"|");
				}

				/** [script] Archon Fields: ; Notes: latin for everything?*/

				$scriptForAtoM = "";

				/** [languageNote] Archon Fields: ; Notes: */
				
				$languageNoteForAtoM = "";

				/** [physicalCharacteristics] Archon Fields: PhysicalAccessNote, TechnicalAccessNote; Notes: physical and technical access*/
				if($objCollection->PhysicalAccessNote)
				{
					$physicalCharacteristicsForAtoM = "Physical access notes: " . $objCollection->getString('PhysicalAccessNote');
				}
				if($objCollection->PhysicalAccessNote && $objCollection->TechnicalAccessNote)
				{
					$physicalCharacteristicsForAtoM .= "<br /><br />";
				}
				if($objCollection->TechnicalAccessNote)
				{
					$physicalCharacteristicsForAtoM .= "Technical access notes: " . $objCollection->getString('TechnicalAccessNote');
				}

				/** [findingAids] Archon Fields: OtherURL, OtherNote (assuming these are always/ nearly always finding aids for us here); Notes: could potentially put in the finding aid url here but this isn't a good long term solution*/
				if($objCollection->OtherURL)
				{
					$findingAidsForAtoM = $objCollection->getString('OtherURL');
				}
				if($objCollection->OtherURL && $objCollection->OtherNote)
				{
					$findingAidsForAtoM .= "<br /><br />";
				}
				if($objCollection->OtherNote)
				{
					$findingAidsForAtoM .= $objCollection->getString('OtherNote');
				}


				/** [locationOfOriginals] Archon Fields: OrigCopiesNote, OrigCopiesURL; Notes: orig/copies field plus the url*/
				if(!empty($objCollection->OrigCopiesNote) || !empty($objCollection->OrigCopiesURL))
				{
					if($objCollection->OrigCopiesNote)
					{
						$locationOfOriginalsForAtoM = $objCollection->getString('OrigCopiesNote');
					}
					if($objCollection->OrigCopiesURL)
					{
						$locationOfOriginalsForAtoM .= 
						"<br/>For more information please see ". $objCollection->getString('OrigCopiesURL') . ".";
					}
				}

				/** [locationOfCopies] Archon Fields: ; Notes: not sure what to do about this being combined as above, but likely we aren't noting the location of copies all that much?*/
				
				$locationOfCopiesForAtoM="";

				/** [relatedUnitsOfDescription] Archon Fields: RelatedMaterials, RelatedMaterialsURL; Notes: related materials and url*/
				if($objCollection->RelatedMaterials || $objCollection->RelatedMaterialsURL)
				{
					if($objCollection->RelatedMaterials)
					{
						$relatedUnitsOfDescriptionForAtoM = $objCollection->getString('RelatedMaterials');
					}
					if($objCollection->RelatedMaterialsURL)
					{
						$relatedUnitsOfDescriptionForAtoM .= "<br/>For more information please see " . $objCollection->getString('RelatedMaterialsURL') . ".";
					}

				}

				/** [publicationNote] Archon Fields: RelatedPublications; Notes: related publications*/
				if($objCollection->RelatedPublications)
				{
					$publicationNoteForAtoM = $objCollection->getString('RelatedPublications');
				}

				/** [digitalObjectPath] Archon Fields: ; Notes: for digital content that is an image stored in Archon*/

				$digitalObjectPathForAtoM = "";

				/** [digitalObjectURI] Archon Fields: ; Notes: for a URL to the digital colletions platform (or similar)*/
				
				$digitalObjectURIForAtoM = "";

				/** [generalNote] Archon Fields: SeparatedMaterials, PreferredCitation; Notes: for fields not captured: SeparatedMaterials, preferred citation*/
				if($objCollection->PreferredCitation)
				{
					$generalNoteForAtoM = "Preferred Citation: " . $objCollection->getString('PreferredCitation');
				}
				if($objCollection->PreferredCitation && $objCollection->SeparatedMaterials) {
					$generalNoteForAtoM .= "|";
				}
				if($objCollection->SeparatedMaterials)
				{
					$generalNoteForAtoM .= "Separated Materials: " . $objCollection->getString('SeparatedMaterials');
				}

				/*set up for sorting subject terms into categories */
				$objCollection->dbLoadSubjects();
				$arrGenres=array();
				$arrGeogSubj=array();
				$arrCorpSubj=array();
				$arrNameSubj=array();
				$arrOccupationSubj=array();
				$arrTopicalSubj=array();
				
				if(!empty($objCollection->Subjects)){	
					foreach($objCollection->Subjects as $objSubject){
					if($objSubject->SubjectTypeID == $GenreSubjectTypeID)
					{
						$arrGenres[$objSubject->ID] = $objSubject;
					} 
					elseif($objSubject->SubjectTypeID == $GeogSubjectTypeID) 
					{
						$arrGeogSubj[$objSubject->ID] = $objSubject;
					} 
					elseif($objSubject->SubjectTypeID == $CorpSubjectTypeID)
					{
						$arrCorpSubj[$objSubject->ID] = $objSubject;
					} 
					elseif($objSubject->SubjectTypeID == $NameSubjectTypeID)
					{
						$arrNameSubj[$objSubject->ID] = $objSubject;
					}
					elseif($objSubject->SubjectTypeID == $OccupationSubjectTypeID)
					{
						$arrOccupationSubj[$objSubject->ID] = $objSubject;
					}
					elseif($objSubject->SubjectTypeID == $TopicalSubjectTypeID)
					{
						$arrTopicalSubj[$objSubject->ID] = $objSubject;
					}
					}
				}	

				/** [subjectAccessPoints] Archon Fields: Subjects (stored as array -- need to separate out by subject type and not include the types covered below); Notes: topical subject terms with | between*/
				if(!empty($arrTopicalSubj)){
					$subjectAccessPointsForAtoM = strip_tags($_ARCHON->createStringFromSubjectArray($arrTopicalSubj, "|"));
				}
				if(!empty($arrTopicalSubj) && !empty($arrOccupationSubj)){
					$subjectAccessPointsForAtoM .= "|";
				}
				if(!empty($arrOccupationSubj)){
					$subjectAccessPointsForAtoM .= strip_tags($_ARCHON->createStringFromSubjectArray($arrOccupationSubj, "|"));
				}

				/** [placeAccessPoints] Archon Fields: Subjects (stored as array -- need to determine which are geographic terms); Notes: geographic subject terms with | between*/
				if(!empty($arrGeogSubj)){
					$placeAccessPointsForAtoM = strip_tags($_ARCHON->createStringFromSubjectArray($arrGeogSubj, "|"));
				}

				/** [nameAccessPoints] Archon Fields: Subjects (stored as array -- need to determine which are name terms); Notes: name subject terms with | between*/
				if(!empty($arrCorpSubj)){
					$nameAccessPointsForAtoM = strip_tags($_ARCHON->createStringFromSubjectArray($arrCorpSubj, "|"));
				}
				if(!empty($arrCorpSubj) && !empty($arrNameSubj)){
					$nameAccessPointsForAtoM .= "|";
				}
				if(!empty($arrNameSubj)){
					$nameAccessPointsForAtoM .= strip_tags($_ARCHON->createStringFromSubjectArray($arrNameSubj, "|"));
				}

				/** [genreAccessPoints] Archon Fields: MaterialType, Subjects (stored as array -- need to determine which are genre terms); Notes: material type plus genre subject terms with | between*/
				if($genreAccessPointsForAtoM && ($objCollection->MaterialType || !empty($arrGenres))){
					$genreAccessPointsForAtoM .= "|";
				}
				if($objCollection->MaterialType){
					$genreAccessPointsForAtoM .= $objCollection->MaterialType->toString();
				}
				if($objCollection->MaterialType && !empty($arrGenres)){
					$genreAccessPointsForAtoM .= "|";
				}
				if(!empty($arrGenres)){
					$genreAccessPointsForAtoM .= (strip_tags($_ARCHON->createStringFromSubjectArray($arrGenres, "|")));
				}

				/** [descriptionIdentifier] Archon Fields: ; Notes: ?*/
				
				$descriptionIdentifierForAtoM = "";

				/** [institutionIdentifier] Archon Fields: ; Notes: presumably would set for all imports to be the same*/
				
				$institutionIdentifierForAtoM = "";

				/** [rules] Archon Fields: DescriptiveRulesID (convert to text); Notes: finding aid info - rules used*/
				if($objCollection->DescriptiveRulesID)
				{
					$rulesForAtoM = $arrDescriptiveRules[$objCollection->DescriptiveRulesID]->toString();
				}

				/** [descriptionStatus] Archon Fields: ; Notes: */
				
				$descriptionStatusForAtoM = "";

				/** [levelOfDetail] Archon Fields: ; Notes: */
				
				$levelOfDetailForAtoM = "";

				/** [revisionHistory] Archon Fields: RevisionHistory, PublicationDate, PublicationNote, ProcessingInfo; Notes: finding aid info - revision history plus publication date and publication note? And processing info?*/
				if($objCollection->RevisionHistory)
				{
					$revisionHistoryForAtoM = "Revision History: " . $objCollection->getString('RevisionHistory');
				}
				if($objCollection->RevisionHistory && $objCollection->PublicationDate) {
					$revisionHistoryForAtoM .= "<br/><br />";
				}
				if($objCollection->PublicationDate)
				{
					$revisionHistoryForAtoM .= "Publication Date: ";
					if($objCollection->PublicationDateMonth <> "00")
					{
						$revisionHistoryForAtoM .= $objCollection->PublicationDateMonth . '/';
					}
					if($objCollection->PublicationDateDay <> "00")
					{
						$revisionHistoryForAtoM .= $objCollection->PublicationDateDay . '/';
					}
					$revisionHistoryForAtoM .= $objCollection->PublicationDateYear;
				}
				if($objCollection->PublicationNote && $objCollection->PublicationDate) {
					$revisionHistoryForAtoM .= "<br/><br />";
				}
				if($objCollection->PublicationNote)
				{
					$revisionHistoryForAtoM .= "Publication Note: " . $objCollection->getString('PublicationNote');
				}
				if($objCollection->PublicationNote && $objCollection->ProcessingInfo) {
					$revisionHistoryForAtoM .= "<br/><br />";
				}
				if($objCollection->ProcessingInfo)
				{
					$revisionHistoryForAtoM .= "Processing Information: ". $objCollection->getString('ProcessingInfo');
				}

				/** [languageOfDescription] Archon Fields: FindingLanguage; Notes: finding aid info - written in*/
				if($objCollection->FindingLanguageID)
				{
					$languageOfDescriptionForAtoM = $atomLanguageCodesArray[$arrLanguages[$objCollection->FindingLanguageID]->getString('LanguageShort', 0, false)];
				}

				/** [scriptOfDescription] Archon Fields: ; Notes: latin for all?*/

				$scriptOfDescriptionForAtoM="";

				/** [sources] Archon Fields: ; Notes: */

				$sourcesForAtoM="";

				/** [archivistNote] Archon Fields: FindingAidAuthor; Notes: Finding aid author*/
				if($objCollection->FindingAidAuthor)
				{
					$archivistNoteForAtoM = $objCollection->getString('FindingAidAuthor');
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
				$physicalObjectNameForAtoM = "";

				/** [physicalObjectLocation] Archon Fields: (note: these are the terms from the json) Locations:Location, Locations:Range, Locations:Section, Locations:Shelf (if not barcode); Notes: Location, Range, Section, Shelf (if not holding barcode)*/
				$physicalObjectLocationForAtoM = "";

				/** [physicalObjectType] Archon Fields: ; Notes: options defined in AtoM taxonomy -- indicates type of box; would we want to use extent and extent unit here as a type of box?*/
				$physicalObjectTypeForAtoM = "";

				/** [alternativeIdentifiers] Archon Fields: ID; Notes: archon id*/
				if($objCollection->ID)
				{
					$alternativeIdentifiersForAtoM = $objCollection->getString('ID');
				}

				/** [alternativeIdentifierLabels] Archon Fields: ; Notes: "ArchonID"*/
				$alternativeIdentifierLabelsForAtoM = "Archon ID";

				/** [eventActors] Archon Fields: Creators (array - convert to text); Notes: creator name*/
				$objCollection->dbLoadCreators();
				if(!empty($objCollection->Creators)){
					foreach($objCollection->Creators as $objCreator){
						$eventActorsForAtoM .= strip_tags($objCreator->toString()) . "|";
						
						//associated the biographical history only with the primary creator record
						if($objCollection->PrimaryCreator){
							if($objCollection->PrimaryCreator->ID == $objCreator->ID){
								if($objCollection->BiogHist)
								{
									$eventActorHistoriesForAtoM .= $objCollection->getString('BiogHist');

									if($objCollection->BiogHistAuthor)
									{
									$eventActorHistoriesForAtoM .= " (by " . $objCollection->getString('BiogHistAuthor') . ")";
									}

									$eventActorHistoriesForAtoM .= "|";
								} else {
									$eventActorHistoriesForAtoM .= "NULL|";
								}
								
							}else{
								$eventActorHistoriesForAtoM .= "NULL|";
							}
						}

						//add type creation without date information to make this a creator
						$eventDatesForAtoM .= "NULL|";
						$eventTypesForAtoM .= "Creation|";
						$eventStartDatesForAtoM .= "NULL|";
						$eventEndDatesForAtoM .= "NULL|";
					}
					//remove the last '|' from the creator and creator history, as applicable
					if(substr($eventActorsForAtoM,-1)=="|"){
						$eventActorsForAtoM = substr($eventActorsForAtoM,0,-1);
					}
					if(substr($eventActorHistoriesForAtoM,-1)=="|"){
						$eventActorHistoriesForAtoM = substr($eventActorHistoriesForAtoM,0,-1);
					}

					//$eventActorsForAtoM = strip_tags($_ARCHON->createStringFromCreatorArray($objCollection->Creators, '|', LINK_NONE));
				}

				/** [eventActorHistories] Archon Fields: BiogHist, BiogHistAuthor; Notes: creator biograhy/history*/
				
				//handled above in eventActors

				/** [eventDates] Archon Fields: InclusiveDates, PredominantDates; Notes: date range of collection; can separate multiple types (inclusive, predominant?) with a |*/
				if($objCollection->InclusiveDates)
				{
					$eventDatesForAtoM .= $objCollection->getString('InclusiveDates');
				}
				if($objCollection->InclusiveDates && $objCollection->PredominantDates){
					$eventDatesForAtoM .= "|";
				}
				if($objCollection->PredominantDates)
				{
					$eventDatesForAtoM .= $objCollection->getString('PredominantDates');
				}

				/** [eventTypes] Archon Fields: ; Notes: in same order as above with | as needed (e.g. inclusive, predominant?)*/
				if($objCollection->InclusiveDates)
				{
					$eventTypesForAtoM .= "Inclusive Dates";
				}
				if($objCollection->InclusiveDates && $objCollection->PredominantDates){
					$eventTypesForAtoM .= "|";
				}
				if($objCollection->PredominantDates)
				{
					$eventTypesForAtoM .= "Predominant Dates";
				}
				
				/** [eventStartDates] Archon Fields: NormalDateBegin; Notes: normal date start*/
				if($objCollection->NormalDateBegin)
				{
					$eventStartDatesForAtoM .= $objCollection->getString('NormalDateBegin');
				}

				/** [eventEndDates] Archon Fields: NormalDateEnd; Notes: normal date end*/
				if($objCollection->NormalDateEnd)
				{
					$eventEndDatesForAtoM .= $objCollection->getString('NormalDateEnd');
				}


				/** [culture] Archon Fields: ; Notes: en (for english)*/
				$cultureForAtoM="en";
				
				$arrDataLineForAtoM = [
					$legacyIdForAtoM,
					$parentIdForAtoM,
					$qubitParentSlugForAtoM,
					$accessionNumberForAtoM,
					$identifierForAtoM,
					$titleForAtoM,
					$levelOfDescriptionForAtoM,
					$extentAndMediumForAtoM,
					$repositoryForAtoM,
					$archivalHistoryForAtoM,
					$acquisitionForAtoM,
					$scopeAndContentForAtoM,
					$appraisalForAtoM,
					$accrualsForAtoM,
					$arrangementForAtoM,
					$accessConditionsForAtoM,
					$reproductionConditionsForAtoM,
					$languageForAtoM,
					$scriptForAtoM,
					$languageNoteForAtoM,
					$physicalCharacteristicsForAtoM,
					$findingAidsForAtoM,
					$locationOfOriginalsForAtoM,
					$locationOfCopiesForAtoM,
					$relatedUnitsOfDescriptionForAtoM,
					$publicationNoteForAtoM,
					$digitalObjectPathForAtoM,
					$digitalObjectURIForAtoM,
					$generalNoteForAtoM,
					$subjectAccessPointsForAtoM,
					$placeAccessPointsForAtoM,
					$nameAccessPointsForAtoM,
					$genreAccessPointsForAtoM,
					$descriptionIdentifierForAtoM,
					$institutionIdentifierForAtoM,
					$rulesForAtoM,
					$descriptionStatusForAtoM,
					$levelOfDetailForAtoM,
					$revisionHistoryForAtoM,
					$languageOfDescriptionForAtoM,
					$scriptOfDescriptionForAtoM,
					$sourcesForAtoM,
					$archivistNoteForAtoM,
					$publicationStatusForAtoM,
					$physicalObjectNameForAtoM,
					$physicalObjectLocationForAtoM,
					$physicalObjectTypeForAtoM,
					$alternativeIdentifiersForAtoM,
					$alternativeIdentifierLabelsForAtoM,
					$eventDatesForAtoM,
					$eventTypesForAtoM,
					$eventStartDatesForAtoM,
					$eventEndDatesForAtoM,
					$eventActorsForAtoM,
					$eventActorHistoriesForAtoM,
					$cultureForAtoM,
				];

				if(!$_REQUEST['striptags']){
					foreach($arrDataLineForAtoM as $keyAtoM => $dataPointForAtoM){
						//only remove the odd tabs that Archon adds to successive paragraphs
						$editedValueForAtoM = str_replace("</p><p>\t","</p><p>",$dataPointForAtoM);

						$markdown = new HTML_To_Markdown($editedValueForAtoM);
						$editedValueForAtoM = $markdown->output();
						$arrDataLineForAtoM[$keyAtoM] = $editedValueForAtoM;
					}
				} else {
					foreach($arrDataLineForAtoM as $keyAtoM => $dataPointForAtoM){
						//AtoM doesn't like importing html paragraph or line breaks; try new lines instead
						$editedValueForAtoM = str_replace("</p><p>\t","\n\n",$dataPointForAtoM);
						$editedValueForAtoM = str_replace("</p><p>","\n\n",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("<br/>","\n",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("<br />","\n",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("</p>","",$editedValueForAtoM);
						$editedValueForAtoM = str_replace("<p>","",$editedValueForAtoM);
						$editedValueForAtoM = strip_tags($editedValueForAtoM);
						$arrDataLineForAtoM[$keyAtoM] = $editedValueForAtoM;
					}
				}
				

				if($_REQUEST['csv']) {
					fputcsv($out, $arrDataLineForAtoM);
				} else {
					echo("<tr><td>");
					echo(implode("</td><td>",$arrDataLineForAtoM));
					echo("</td></tr>");
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