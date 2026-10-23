<?php
/**
 * Static files for the Archon test environment (PHP-FPM image variant).
 *
 * The host's Apache httpd forwards every request for a site to PHP-FPM
 * (tests/testenv/httpd/archon-testenv.conf): PHP scripts go to the script itself, and everything else
 * (theme images, style sheets, JavaScript, help pages) comes here, because the code only exists inside
 * the container. /archon-testenv/ shows the generated index of sites.
 */
const DOCUMENT_ROOT = '/var/www/html';

$Types = array(
   'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json', 'map' => 'application/json',
   'gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'ico' => 'image/x-icon',
   'svg' => 'image/svg+xml', 'bmp' => 'image/bmp', 'webp' => 'image/webp',
   'html' => 'text/html; charset=UTF-8', 'htm' => 'text/html; charset=UTF-8', 'txt' => 'text/plain; charset=UTF-8',
   'xml' => 'text/xml', 'xsl' => 'text/xml', 'xsd' => 'text/xml', 'dtd' => 'application/xml-dtd',
   'pdf' => 'application/pdf', 'csv' => 'text/csv', 'xls' => 'application/vnd.ms-excel',
   'swf' => 'application/x-shockwave-flash', 'htc' => 'text/x-component',
   'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
);

function finish($Status, $Message)
{
   http_response_code($Status);
   header('Content-Type: text/plain; charset=UTF-8');
   header('X-Content-Type-Options: nosniff');
   echo $Message . "\n";
   exit;
}

$Path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$Path = rawurldecode(is_string($Path) ? $Path : '/');

if($Path === '/archon-testenv' || $Path === '/archon-testenv/')
{
   $Path = '/index.html';
}
elseif(strpos($Path, "\0") !== false || preg_match('#/\.#', $Path) || !preg_match('#^/[a-z][a-z0-9_]*/#', $Path))
{
   finish(404, 'Not Found');
}

$File = realpath(DOCUMENT_ROOT . $Path);
if($File === false || strpos($File, DOCUMENT_ROOT . '/') !== 0)
{
   finish(404, 'Not Found');
}

if(is_dir($File))
{
   // Apache adds the trailing slash to directory URLs; the directory's index.php then runs through PHP-FPM.
   if(substr($Path, -1) != '/')
   {
      $Query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
      header('Location: ' . str_replace('%2F', '/', rawurlencode($Path)) . '/' . $Query, true, 301);
      exit;
   }
   finish(403, 'Forbidden');
}

$Extension = strtolower(pathinfo($File, PATHINFO_EXTENSION));
if(!is_file($File) || !isset($Types[$Extension]))
{
   finish(404, 'Not Found');
}

$Modified = filemtime($File);
$Size = filesize($File);
$ETag = '"' . dechex($Size) . '-' . dechex($Modified) . '"';
header('Content-Type: ' . $Types[$Extension]);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $Modified) . ' GMT');
header('ETag: ' . $ETag);
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

$IfNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : NULL;
$IfModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
if(($IfNoneMatch !== NULL && trim($IfNoneMatch) === $ETag) || ($IfNoneMatch === NULL && $IfModifiedSince !== false && $IfModifiedSince >= $Modified))
{
   http_response_code(304);
   exit;
}

header('Content-Length: ' . $Size);
if(!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'HEAD')
{
   readfile($File);
}
