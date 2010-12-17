<?php


require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/includes/markdown/markdown.php');


// ----------------------------------------------------------------------------
// HTML Template
// ----------------------------------------------------------------------------

$css = file_get_contents(dirname(__FILE__) . '/templates/api_to_html.css');

$header = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta content="text/html; charset=UTF-8" http-equiv="content-type" />
<title>___REPLACE_TITLE___</title>
<style type="text/css">' . "\n$css\n" . '</style>
</head>
<body>
';

$footer = '</body></html>';

// ----------------------------------------------------------------------------
// Scan for files
// ----------------------------------------------------------------------------

$files = array();
foreach(new DirectoryIterator($CONFIG['docdir']) as $file) {

    if (!$file->isFile()) continue;
    if (!preg_match('/(lua|txt)$/', $file->getFilename())) continue;
    $files[] = $file->getPathname();
}

// ----------------------------------------------------------------------------
// Mangle stuff
// ----------------------------------------------------------------------------

// Helper
function strip_linebreaks($string) {
    return str_replace("\n", null, $string);
}

// Debug
// $files = array('/Users/dac514/Desktop/test.lua');

$index = array();
foreach ($files as $file) {

    // Get contents
    $tmp = file_get_contents($file);

    // Find code chunks, encapsulate in Extendend Markdown code tags
    $long_comment = false;
    $long_code = false;
    $long_code_offset = 0;
    $tmp2 = explode("\n", $tmp);
    $count = count($tmp2);
    if (preg_match('/lua$/', $file)) foreach ($tmp2 as $lnum => $line) {

        if ($long_comment) {
            if (preg_match('/\]\]/', $line)) $long_comment = false;
            continue;
        }

        if ($long_code) {
            if ($count-1 <= $lnum) {
                $tmp2[$lnum] = "~~~\n" . $line;
                $long_code = false;
            }
            elseif (preg_match('/^--/', $line)) {
                $tmp2[$lnum - $long_code_offset] = "~~~\n" . $tmp2[$lnum - $long_code_offset];
                $long_code = false;
            }
            elseif (preg_match('/^\s*$/', $line)) {
                $long_code_offset = $long_code_offset + 1;
            }
            else {
                // Not consecutive empty lines
                $long_code_offset = 0;
            }
        }

        if (!$long_comment && preg_match('/^\s*--\[\[/', $line)) {
            $long_comment = true;
            continue;
        }

        if (preg_match('/^\s*--/', $line) || preg_match('/^\s*$/', $line)) {
            continue;
        }

        if (!$long_code) {
            $tmp2[$lnum] = "~~~\n" . $line;
            $long_code = true;
            $long_code_offset = 0;
        }

    }
    $markdown = implode("\n", $tmp2);

    /*
    Regular expression mayhem:
    For modifier explinations, see:
    http://php.net/manual/en/reference.pcre.pattern.modifiers.php
    */

    // Get rid of Lua warning
    $markdown = str_ireplace('Do not try to execute this file. It uses a .lua extension for markup only.', null, $markdown);

    // Find `--[[ % ]]--` or `--[[ % ]]` and replace with %
    $markdown = preg_replace('/--\[\[(.*?)(\]\]--|\]\])/s', '$1', $markdown);

    // Find `--. % \n` and replace with empty string
    $markdown = preg_replace('/^--\.(.*?)$/sm', null, $markdown);

    // Find `--\n` and replace with empty string
    $markdown = preg_replace('/^--$/sm', '', $markdown);

    // Find `-- %` and replace with %
    $markdown = preg_replace('/^--\s{0,1}(.*?)$/sm', '$1', $markdown);

    // Transform ==== header ==== to Markdown equivilant
    $markdown = preg_replace('/={4,}\n(.*?)={4,}\n/se', "strip_linebreaks('# $1')", $markdown);

    // Transform ---- header ---- to Markdown equivilant
    $markdown = preg_replace('/-{4,}\n(.*?)-{4,}\n/se', "strip_linebreaks('## $1')", $markdown);

    // Find `---- Foo` and replace with Markdown equivilant
    $markdown = preg_replace('/-{4,}\s{1}(.*?)\n/s', "### $1\n", $markdown);

    // Convert to markdown
    $markdown = Markdown($markdown);

    // Replace remaining URLs, inspired from: http://php-faq.de/q-regexp-uri-klickbar.html
    // $pattern = '#(^|[^\"=]{1})(http://|ftp://|mailto:|news:)([^\s<>]+)([\s\n<>]|$)#sm';
    // $markdown = preg_replace($pattern, "\\1<a href=\"\\2\\3\"><u>\\2\\3</u></a>\\4", $markdown);

    // ------------------------------------------------------------------------
    // HTMLize stuff
    // ------------------------------------------------------------------------

    $fname = basename($file);
    $tmp = trim(
        str_replace('___REPLACE_TITLE___', htmlspecialchars($fname), $header) .
        $markdown .
        $footer
        );

    // ------------------------------------------------------------------------
    // Output Api File as HTML
    // ------------------------------------------------------------------------

    $fname = $fname . '.html';
    $index[] = $fname;
    file_put_contents($CONFIG['outdir'] . '/' . $fname, $tmp);

}

// ----------------------------------------------------------------------------
// Output index.html
// ----------------------------------------------------------------------------

$index_title = 'Renoise Lua API';
$tmp = "<h1>$index_title</h1>\n";
$tmp .= "<ul>\n";
asort($index);
foreach ($index as $file) {
    $tmp .= "<li><a href='$file'>$file</a></li>\n";
}
$tmp .= "</ul>\n";
$tmp = trim(
    str_replace('___REPLACE_TITLE___', $index_title, $header) .
    $tmp .
    $footer
    );
file_put_contents($CONFIG['outdir'] . '/index.html', $tmp);

?>