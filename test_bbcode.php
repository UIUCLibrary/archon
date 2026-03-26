<?php

class Archon
{
    public function bbcode_to_html($bbtext)
    {
        $patterns = [
            "/\[i\](.*?)\[\/i\]/i"              => "<emph render='italic'>%1</emph>",
            "/\[b\](.*?)\[\/b\]/i"              => "<emph render='bold'>%1</emph>",
            "/\[u\](.*?)\[\/u\]/i"              => "<emph render='underline'>%1</emph>",
            "/\[sup\](.*?)\[\/sup\]/i"          => "<emph render='super'>%1</emph>",
            "/\[sub\](.*?)\[\/sub\]/i"          => "<emph render='sub'>%1</emph>",

            // [url=http://example.com]Text[/url]          => "<extref href='http://example.com'>Text</extref>",
            '/\[url=(https?:\/\/[^\]]+)\](.*?)\[\/url\]/i' => "<extref href='%1'>%2</extref>",

            // [url=mailto:someone@example.com]Text[/url]  => "<extref href='mailto:someone@example.com'>Text</extref>",
            '/\[url=mailto:([^\]]+)\](.*?)\[\/url\]/i'     => "<extref href='mailto:%1'>%2</extref>",

            // [email=someone@example.com]Label[/email]    => "<extref href='mailto:someone@example.com'>Label</extref>",
            '/\[e?mail=(.*?)\](.*?)\[\/e?mail\]/i'         => "<extref href='mailto:%1'>%2</extref>",

            //[email]someone@example.com[/email]           => "<extref href='mailto:someone@example.com">someone@example.com</extref>",
            '/\[e?mail\](.*?)\[\/e?mail\]/i'               => "<extref href='mailto:%1'>%1</extref>",
        ];

        foreach ($patterns as $pattern => $template) {
            $bbtext = preg_replace_callback($pattern, function($match) use ($template) {
                $replaced = $template;
                foreach ($match as $index => $value) {
                    if ($index === 0) continue; // Skip the full match
                    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    $replaced = str_replace("%$index", $escaped, $replaced);
                }
                return $replaced;
            }, $bbtext);
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
        "Url with &" => ["[url=http://example.com?param=value&other=othervalue]Example[/url]", "<extref href='http://example.com?param=value&amp;other=othervalue'>Example</extref>"],
        "Url with ' and \"" => ["[url=http://example.com?param='value\"withquotes]Example[/url]", "<extref href='http://example.com?param=&#039;value&quot;withquotes'>Example</extref>"],
        "Url with < and >" => ["[url=http://example.com?param=<value>]Example[/url]", "<extref href='http://example.com?param=&lt;value&gt;'>Example</extref>"],
        "Mailto url" => ["[url=mailto:test@example.com]Email Me[/url]", "<extref href='mailto:test@example.com'>Email Me</extref>"],
        "Email labeled link" =>["[email=test@example.com]Contact[/email]","<extref href='mailto:test@example.com'>Contact</extref>"],
        "Email plain link" =>["[email]test@example.com[/email]","<extref href='mailto:test@example.com'>test@example.com</extref>"],
        "Mail labeled link" =>["[mail=test@example.com]Write[/mail]", "<extref href='mailto:test@example.com'>Write</extref>"],
        "Mail plain link" =>["[mail]test@example.com[/mail]", "<extref href='mailto:test@example.com'>test@example.com</extref>"]
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