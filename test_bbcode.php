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
            '/\[url=mailto:([^\]]+)\](.*?)\[\/url\]/i'
            => "<extref href='mailto:$1'>$2</extref>",

            // [email=someone@example.com]Label[/email]
            '/\[e?mail=mailto:(.*?)\](.*?)\[\/e?mail\]/i'
            => "<extref href='mailto:$1'>$2</extref>",
        ];

        foreach ($patterns as $pattern => $replacement) {
            $bbtext = preg_replace($pattern, $replacement, $bbtext);
        }

        return $bbtext;
    }
}

$_ARCHON = new Archon();


//True BBCode
$expectedTransforms =
    [
        "Italics" => ["[i]italic text[/i]" , "<emph render='italic'>italic text</emph>"],
        "Bold" => ["[b]bold text[/b]" , "<emph render='bold'>bold text</emph>"],
        "Underline" =>["[u]underlined[/u]" , "<emph render='underline'>underlined</emph>"],
        "Superscript" =>["21[sup]st[/sup]" , "21<emph render='super'>st</emph>"],
        "Subscript" => ["CO[sub]2[/sub]" , "CO<emph render='sub'>2</emph>"],
        "Https url" => ["[url=http://example.com]Example[/url]", "<extref href='http://example.com'>Example</extref>"],
        "Http url" => ["[url=https://example.com]Example[/url]" , "<extref href='https://example.com'>Example</extref>"],
        "Mailto url" => ["[url=mailto:test@example.com]Email Me[/url]", "<extref href='mailto:test@example.com'>Email Me</extref>"],
        "Email" =>["[email=mailto:test@example.com]Contact[/email]","<extref href='mailto:test@example.com'>Contact</extref>"],
        "Mail" =>["[mail=mailto:test@example.com]Write[/mail]", "<extref href='mailto:test@example.com'>Write</extref>"]
    ];

foreach($expectedTransforms as $name => $test) {
    $in = $test[0];
    $out = $_ARCHON->bbcode_to_html($in);
    $expected = $test[1];
    $differences = strcmp($out, $expected);
    if($differences) {
        echo "Error: BBCode in $name did not convert correctly.\n";
        echo "Input: " . $in . "\n";
        echo "Expected: " . $expected . "\n";
        echo "Output: " . $out . "\n\n";
    } else {
        echo "Success: BBCode $name converted correctly.\n";
    }
}