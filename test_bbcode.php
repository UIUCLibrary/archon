<?php

class Archon
{
    public function bbcode_to_html($bbtext)
    {
        $patterns = [

            //simple inline tags
            "/\[i\](.*?)\[\/i\]/i" => "<emph render='italic'>$1</emph>",
            "/\[b\](.*?)\[\/b\]/i" => "<emph render='bold'>$1</emph>",
            "/\[u\](.*?)\[\/u\]/i" => "<emph render='underline'>$1</emph>",
            "/\[sup\](.*?)\[\/sup\]/i" => "<emph render='super'>$1</emph>",
            "/\[sub\](.*?)\[\/sub\]/i" => "<emph render='sub'>$1</emph>",

            // [url=http://example.com]Text[/url]
            '/\[url=(https?:\/\/[^\]]+)\](.*?)\[\/url\]/i'
            => "<extref href='$1'>$2</extref>",

            // [url=mailto:someone@example.com]Text[/url]
            '/\[url=(mailto:[^\]]+)\](.*?)\[\/url\]/i'
            => "<a href='$1'>$2</a>",

            // [email=someone@example.com]Label[/email]
            '/\[e?mail=(.*?)\](.*?)\[\/e?mail\]/i'
            => "<a href='mailto:$1'>$2</a>",
        ];

        foreach ($patterns as $pattern => $replacement) {
            $bbtext = preg_replace($pattern, $replacement, $bbtext);
        }

        return $bbtext;
    }
}

$_ARCHON = new Archon();


//True BBCode

// italic
$italicIn  = "[i]italic text[/i]";
$italicOut = "<emph render='italic'>italic text</emph>";
$italicTest = assert($_ARCHON->bbcode_to_html($italicIn) === $italicOut);
if(!$italicTest) {
    echo "Error: [i] did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($italicIn) . "\n";
} else {
    echo "Success: [i] converted correctly.\n";
}


// bold
$boldIn  = "[b]bold text[/b]";
$boldOut = "<emph render='bold'>bold text</emph>";
$boldTest = assert($_ARCHON->bbcode_to_html($boldIn) === $boldOut);
if(!$boldTest) {
    echo "Error: [b] did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($boldIn) . "\n";
} else {
    echo "Success: [b] converted correctly.\n";
}


// underline
$underlineIn  = "[u]underlined[/u]";
$underlineOut = "<emph render='underline'>underlined</emph>";
$underlineTest = assert($_ARCHON->bbcode_to_html($underlineIn) === $underlineOut);
if(!$underlineTest) {
    echo "Error: [u] did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($underlineIn) . "\n";
} else {
    echo "Success: [u] converted correctly.\n";
}


// superscript
$supIn  = "21[sup]st[/sup]";
$supOut = "21<emph render='super'>st</emph>";
$supTest = assert($_ARCHON->bbcode_to_html($supIn) === $supOut);
if(!$supTest) {
    echo "Error: [sup] did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($supIn) . "\n";
} else {
    echo "Success: [sup] converted correctly.\n";
}


// subscript
$subIn  = "CO[sub]2[/sub]";
$subOut = "CO<emph render='sub'>2</emph>";
$subTest = assert($_ARCHON->bbcode_to_html($subIn) === $subOut);
if(!$subTest) {
    echo "Error: [sub] did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($subIn) . "\n";
} else {
    echo "Success: [sub] converted correctly.\n";
}

// url (http)
$urlIn  = "[url=http://example.com]Example[/url]";
$urlOut = "<extref href='http://example.com'>Example</extref>";
$urlTest = assert($_ARCHON->bbcode_to_html($urlIn) === $urlOut);
if(!$urlTest) {
    echo "Error: http [url=...] http did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($urlIn) . "\n";
} else {
    echo "Success: [url] http converted correctly.\n";
}

// url (https)
$urlIn  = "[url=https://example.com]Example[/url]";
$urlOut = "<extref href='https://example.com'>Example</extref>";
$urlTest = assert($_ARCHON->bbcode_to_html($urlIn) === $urlOut);
if(!$urlTest) {
    echo "Error: [url=...] https did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($urlIn) . "\n";
} else {
    echo "Success: [url] https converted correctly.\n";
}

// url mailto
$mailtoIn  = "[url=mailto:test@example.com]Email Me[/url]";
$mailtoOut = "<a href='mailto:test@example.com'>Email Me</a>";
$mailtoTest = assert($_ARCHON->bbcode_to_html($mailtoIn) === $mailtoOut);
if(!$mailtoTest) {
    echo "Error: mailto URL did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($mailtoIn) . "\n";
} else {
    echo "Success: mailto URL converted correctly.\n";
}

// email
$emailIn  = "[email=test@example.com]Contact[/email]";
$emailOut = "<a href='mailto:test@example.com'>Contact</a>";
$emailTest = assert($_ARCHON->bbcode_to_html($emailIn) === $emailOut);
if(!$emailTest) {
    echo "Error: [email] did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($emailIn) . "\n";
} else {
    echo "Success: [email] converted correctly.\n";
}

// mail
$mailIn  = "[mail=test@example.com]Write[/mail]";
$mailOut = "<a href='mailto:test@example.com'>Write</a>";
$mailTest = assert($_ARCHON->bbcode_to_html($mailIn) === $mailOut);
if(!$mailTest) {
    echo "Error: [mail] did not convert correctly.\n";
    echo $_ARCHON->bbcode_to_html($mailIn) . "\n";
} else {
    echo "Success: [mail] converted correctly.\n";
}


//Mistaken BBCode
$uppercaseB = "Unprocessed box [B]";
$uppercaseBTest = assert($_ARCHON->bbcode_to_html($uppercaseB) === $uppercaseB);
if(!$uppercaseBTest) {
        echo "Error: Uppercase [B] was processed when it should not have been.\n";
    } else {
        echo "Success: Uppercase [B] was not processed, as expected.\n";
    }

$uppercaseU = 'Folder 18: "The Flow of [U]" by Kenneth Gaburo, 1974';
$uppercaseUTest = assert($_ARCHON->bbcode_to_html($uppercaseU) === $uppercaseU);
if(!$uppercaseUTest) {
        echo "Error: Uppercase [U] was processed when it should not have been.\n";
    } else {
        echo "Success: Uppercase [U] was not processed, as expected.\n";
    };
$singleI = '"Prelude to Talk" and "Introduction to Examples of Intonation", black stencil copy of lecture text, endorsed by Partch, Tanglewood, [i] plus 13 pages';
$singleITest = assert($_ARCHON->bbcode_to_html($singleI) === $singleI);
if(!$singleITest) {
        echo "Error: Single [i] was processed when it should not have been.\n";
        echo $_ARCHON->bbcode_to_html($singleI);

} else {
        echo "Success: Single [i] was not processed, as expected.\n";
    }