<?php
require_once 'packages/core/lib/archonobject.inc.php';

$test_instance = new class() extends ArchonObject {
    public function __construct() {
    }
};

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
        "Mailto url" => ["[url=mailto:test@example.com]Email Me[/url]", "<extref href='mailto:test@example.com'>Email Me</extref>"],
        "Email labeled link" =>["[email=test@example.com]Contact[/email]","<extref href='mailto:test@example.com'>Contact</extref>"],
        "Email plain link" =>["[email]test@example.com[/email]","<extref href='mailto:test@example.com'>test@example.com</extref>"],
        "Mail labeled link" =>["[mail=test@example.com]Write[/mail]", "<extref href='mailto:test@example.com'>Write</extref>"],
        "Mail plain link" =>["[mail]test@example.com[/mail]", "<extref href='mailto:test@example.com'>test@example.com</extref>"],
        "String that looks like BBCode but isn't" => ["[i]This is not italic[/b]", "[i]This is not italic[/b]"],
        "Nested tags" => ["[b]Bold and [i]italic[/i] text[/b]", "<emph render='bold'>Bold and <emph render='italic'>italic</emph> text</emph>"],
        "Multiple tags" => ["[b]Bold[/b] and [i]italic[/i] and [u]underlined[/u]", "<emph render='bold'>Bold</emph> and <emph render='italic'>italic</emph> and <emph render='underline'>underlined</emph>"],
        "[i] with no closing tag" => ["[i]this is a footnote", "[i]this is a footnote"],
        "[B] that means box" => ["[B] this is a box", "[B] this is a box"],
        "[U] that is in a title" => ["Folder 18: \"The Flow of [U]\" by Kenneth Gaburo, 1974", "Folder 18: \"The Flow of [U]\" by Kenneth Gaburo, 1974"],
        "Already Escaped &" => ["[url=http://example.com?param=value&amp;other=othervalue]Example[/url]", "<extref href='http://example.com?param=value&amp;other=othervalue'>Example</extref>"],
    ];

foreach($expectedTransforms as $name => $test) {
    $in = $test[0];
    $out = $test_instance->bbcode_to_html($in);
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