<?php
/**
 * Archon production probe -- READ-ONLY environment, schema and data-shape survey.
 *
 * Written to run unchanged on the legacy production stack (PHP 5.3/5.4, mssql
 * extension + FreeTDS, PEAR MDB2) as well as on PHP 8.x with the sqlsrv
 * extension (used to produce a baseline from the test environment).
 *
 * It never modifies files, configuration or the database:
 *   - every SQL statement passes probe_assert_readonly() (SELECT / WITH /
 *     DBCC USEROPTIONS / a handful of session-only SET options);
 *   - the session runs READ UNCOMMITTED, DEADLOCK_PRIORITY LOW, LOCK_TIMEOUT 10s;
 *   - the database section only emits metadata and aggregate counts -- no row
 *     content except small lookup/config tables (secrets and emails redacted);
 *   - nothing is written to disk; the JSON report goes to stdout.
 *
 * Usage (CLI, recommended):
 *   php archon-probe.php --archon-dir=/path/to/archon > probe-<instance>.json
 *   php archon-probe.php --help
 *
 * See tests/production-probe/README.md for details.
 */

define('ARCHON_PROBE_VERSION', '1.0.0');

error_reporting(E_ALL);
@ini_set('display_errors', PHP_SAPI == 'cli' ? 'stderr' : '0');
@ini_set('log_errors', '0');
@ini_set('html_errors', '0');
// Avoid "It is not safe to rely on the system's timezone settings" warnings on PHP 5
@date_default_timezone_set(@date_default_timezone_get());

$GLOBALS['probe_warnings'] = array();
$GLOBALS['probe_errors'] = array();

function probe_error_handler($errno, $errstr, $errfile = '', $errline = 0)
{
    // Respect @-suppression (PHP 5: error_reporting() == 0, PHP 8: masked value)
    if(!(error_reporting() & $errno))
    {
        return true;
    }
    if(count($GLOBALS['probe_warnings']) < 500)
    {
        $GLOBALS['probe_warnings'][] = array('errno' => $errno, 'message' => probe_utf8((string)$errstr), 'file' => basename((string)$errfile), 'line' => $errline);
    }
    return true;
}
set_error_handler('probe_error_handler');

function probe_option($name, $default = null)
{
    return array_key_exists($name, $GLOBALS['probe_options']) ? $GLOBALS['probe_options'][$name] : $default;
}

function probe_parse_cli_options($argv)
{
    $opts = array();
    foreach(array_slice($argv, 1) as $arg)
    {
        if(substr($arg, 0, 2) != '--')
        {
            continue;
        }
        $arg = substr($arg, 2);
        $pos = strpos($arg, '=');
        if($pos === false)
        {
            $opts[strtolower($arg)] = true;
        }
        else
        {
            $opts[strtolower(substr($arg, 0, $pos))] = substr($arg, $pos + 1);
        }
    }
    return $opts;
}

function probe_constant_time_equals($a, $b)
{
    $a = (string)$a;
    $b = (string)$b;
    if(strlen($a) != strlen($b))
    {
        return false;
    }
    $diff = 0;
    for($i = 0; $i < strlen($a); $i++)
    {
        $diff |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $diff === 0;
}

function probe_usage()
{
    return <<<'USAGE'
Archon production probe (read-only)

  php archon-probe.php [options] > probe.json

Options:
  --archon-dir=PATH        Archon installation directory (contains config.inc.php).
                           Default: auto-detected from the current directory or the
                           probe's own location.
  --sections=LIST          Comma separated subset of:
                           php,freetds,archon,files,apache,database,profile
                           (default: all of them)
  --show-hosts             Do not redact host names / IP addresses / logins.
  --db-driver=NAME         auto (default), mssql or sqlsrv.
  --db-login=LOGIN         Connect with this (read-only) login instead of the
                           credentials in config.inc.php. The password is read
                           from the ARCHON_PROBE_DB_PASSWORD environment variable.
  --max-profile-rows=N     Skip profiling tables with more rows (default 2000000).
  --query-timeout=SECONDS  Per-query timeout (default 300).
  --no-lookup-dumps        Do not include the small lookup/config table contents.
  --max-files=N            Maximum number of files in the file manifest (default 50000).
  --help                   Show this text.

Web mode (optional): set the environment variable ARCHON_PROBE_WEB_TOKEN (>= 24
characters, e.g. via Apache SetEnv) and request archon-probe.php?token=<value>.
Without a matching token every web request receives 403.
USAGE;
}

// ---------------------------------------------------------------------------
// Encoding / redaction helpers
// ---------------------------------------------------------------------------

function probe_latin1_to_utf8($s)
{
    if(function_exists('iconv'))
    {
        $r = @iconv('ISO-8859-1', 'UTF-8', $s);
        if($r !== false)
        {
            return $r;
        }
    }
    $out = '';
    $len = strlen($s);
    for($i = 0; $i < $len; $i++)
    {
        $c = ord($s[$i]);
        if($c < 0x80)
        {
            $out .= $s[$i];
        }
        else
        {
            $out .= chr(0xC0 | ($c >> 6)) . chr(0x80 | ($c & 0x3F));
        }
    }
    return $out;
}

function probe_utf8($s)
{
    if(!is_string($s))
    {
        return $s;
    }
    if($s === '' || preg_match('//u', $s))
    {
        return $s;
    }
    return '[latin1]' . probe_latin1_to_utf8($s);
}

function probe_clean($v, $depth = 0)
{
    if($depth > 12)
    {
        return '(depth limit)';
    }
    if(is_array($v))
    {
        $r = array();
        foreach($v as $k => $x)
        {
            $r[probe_utf8((string)$k)] = probe_clean($x, $depth + 1);
        }
        return $r;
    }
    if(is_object($v))
    {
        return probe_clean(get_object_vars($v), $depth + 1);
    }
    if(is_resource($v))
    {
        return '(resource)';
    }
    if(is_float($v) && (is_nan($v) || is_infinite($v)))
    {
        return (string)$v;
    }
    return probe_utf8($v);
}

function probe_hash8($s)
{
    return substr(md5((string)$s), 0, 8);
}

/**
 * Redacts a host specification such as "db.example.edu", "10.1.2.3:1433",
 * "server\INSTANCE" or "host,1433" unless --show-hosts was given.
 */
function probe_redact_host($h)
{
    if($h === null || $h === '' || probe_option('show-hosts'))
    {
        return $h;
    }
    $h = (string)$h;
    $suffix = '';
    if(preg_match('/^(.*?)([:,]\d+|\\\\[^\\\\]+)$/', $h, $m))
    {
        $h = $m[1];
        $suffix = $m[2];
    }
    $lower = strtolower($h);
    if($lower == 'localhost' || $lower == '127.0.0.1' || $lower == '::1' || $lower == '(local)' || $lower == '.')
    {
        return $h . $suffix;
    }
    if(preg_match('/^[0-9.]+$/', $h) || strpos($h, ':') !== false)
    {
        $type = 'ip';
    }
    elseif(strpos($h, '.') !== false)
    {
        $type = 'fqdn';
    }
    else
    {
        $type = 'name';
    }
    return '<' . $type . ':' . probe_hash8($lower) . '>' . $suffix;
}

function probe_is_secret_name($name)
{
    return (bool)preg_match('/pass|pwd|secret|token|salt|cred|private|apikey|api_key|(^|_)key$|authkey/i', (string)$name);
}

function probe_set_or_empty($v)
{
    if($v === null)
    {
        return '<null>';
    }
    if(is_array($v) || is_object($v))
    {
        return '<set:' . gettype($v) . '>';
    }
    return ((string)$v === '') ? '<empty>' : '<set>';
}

function probe_mask_text($s)
{
    if(!is_string($s))
    {
        return $s;
    }
    // e-mail addresses: keep the domain only
    $s = preg_replace('/[A-Za-z0-9._%+\-]+@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/', '<email>@$1', $s);
    if(!probe_option('show-hosts'))
    {
        $s = preg_replace_callback('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', 'probe_mask_ip_cb', $s);
    }
    return $s;
}

function probe_mask_ip_cb($m)
{
    if($m[1] == '127.0.0.1' || $m[1] == '0.0.0.0')
    {
        return $m[1];
    }
    return '<ip:' . probe_hash8($m[1]) . '>';
}

/** Recursively redacts values whose names look secret and masks e-mails / IPs. */
function probe_redact_value($name, $v, $depth = 0)
{
    if(probe_is_secret_name($name))
    {
        return probe_set_or_empty($v);
    }
    if($depth > 8)
    {
        return '(depth limit)';
    }
    if(is_object($v))
    {
        $v = get_object_vars($v);
    }
    if(is_array($v))
    {
        $r = array();
        foreach($v as $k => $x)
        {
            $r[$k] = probe_redact_value($k, $x, $depth + 1);
        }
        return $r;
    }
    if(is_string($v))
    {
        return probe_mask_text($v);
    }
    return $v;
}

function probe_qi($name)
{
    return '[' . str_replace(']', ']]', $name) . ']';
}

function probe_qs($s)
{
    return "'" . str_replace("'", "''", $s) . "'";
}

function probe_read_file_head($file, $bytes = 65536)
{
    $h = @fopen($file, 'rb');
    if(!$h)
    {
        return false;
    }
    $data = fread($h, $bytes);
    fclose($h);
    return $data;
}

function probe_shell($cmd)
{
    if(!function_exists('shell_exec'))
    {
        return null;
    }
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if(in_array('shell_exec', $disabled))
    {
        return null;
    }
    $out = @shell_exec($cmd . ' 2>&1');
    if($out === null || $out === false)
    {
        return null;
    }
    return probe_utf8(rtrim($out));
}

// ---------------------------------------------------------------------------
// Section: php
// ---------------------------------------------------------------------------

function probe_section_php()
{
    $r = array();
    $r['version'] = PHP_VERSION;
    $r['version_id'] = PHP_VERSION_ID;
    $r['sapi'] = PHP_SAPI;
    $r['os'] = PHP_OS;
    $r['uname'] = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m');
    $r['int_size'] = PHP_INT_SIZE;
    $r['zend_version'] = zend_version();
    $r['loaded_ini_file'] = php_ini_loaded_file();
    $scanned = php_ini_scanned_files();
    $r['scanned_ini_files'] = $scanned ? array_values(array_filter(array_map('trim', explode(',', $scanned)))) : array();
    foreach(array('/etc/redhat-release', '/etc/centos-release', '/etc/os-release', '/etc/debian_version') as $f)
    {
        if(@is_readable($f))
        {
            $r['os_release'][$f] = probe_utf8(trim((string)probe_read_file_head($f, 4096)));
        }
    }

    $ext = array();
    foreach(get_loaded_extensions() as $e)
    {
        $v = phpversion($e);
        $ext[$e] = $v ? $v : null;
    }
    ksort($ext);
    $r['extensions'] = $ext;
    $r['zend_extensions'] = get_loaded_extensions(true);

    $keys = array('short_open_tag', 'asp_tags', 'magic_quotes_gpc', 'magic_quotes_runtime', 'magic_quotes_sybase',
        'register_globals', 'register_long_arrays', 'safe_mode', 'allow_call_time_pass_reference',
        'error_reporting', 'display_errors', 'display_startup_errors', 'log_errors', 'error_log', 'html_errors', 'track_errors',
        'memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars', 'max_input_nesting_level',
        'post_max_size', 'upload_max_filesize', 'max_file_uploads', 'file_uploads', 'upload_tmp_dir', 'sys_temp_dir',
        'default_charset', 'default_mimetype', 'internal_encoding', 'input_encoding', 'output_encoding',
        'mbstring.func_overload', 'mbstring.internal_encoding', 'mbstring.http_output', 'mbstring.language', 'mbstring.substitute_character',
        'date.timezone', 'include_path', 'open_basedir', 'disable_functions', 'disable_classes',
        'output_buffering', 'output_handler', 'zlib.output_compression', 'implicit_flush', 'arg_separator.output', 'arg_separator.input',
        'variables_order', 'request_order', 'auto_detect_line_endings', 'allow_url_fopen', 'allow_url_include', 'default_socket_timeout',
        'precision', 'serialize_precision', 'expose_php', 'user_agent', 'sendmail_path', 'SMTP', 'smtp_port', 'sendmail_from', 'mail.add_x_header',
        'auto_prepend_file', 'auto_append_file', 'ignore_user_abort', 'enable_dl', 'realpath_cache_size', 'zend.enable_gc',
        'session.save_handler', 'session.save_path', 'session.name', 'session.gc_maxlifetime', 'session.gc_probability', 'session.gc_divisor',
        'session.cookie_lifetime', 'session.cookie_secure', 'session.cookie_httponly', 'session.use_only_cookies', 'session.use_trans_sid',
        'session.hash_function', 'session.auto_start', 'session.serialize_handler',
        'pcre.backtrack_limit', 'pcre.recursion_limit', 'pcre.jit',
        'iconv.input_encoding', 'iconv.internal_encoding', 'iconv.output_encoding',
        'mssql.textsize', 'mssql.textlimit', 'mssql.charset', 'mssql.datetimeconvert', 'mssql.timeout', 'mssql.connect_timeout',
        'mssql.compatability_mode', 'mssql.compatibility_mode', 'mssql.min_error_severity', 'mssql.min_message_severity', 'mssql.secure_connection', 'mssql.max_procs', 'mssql.batchsize',
        'sqlsrv.ClientBufferMaxKBSize', 'sqlsrv.LogSeverity', 'sqlsrv.LogSubsystems', 'sqlsrv.WarningsReturnAsErrors',
        'opcache.enable', 'apc.enabled', 'xcache.size', 'eaccelerator.enable', 'suhosin.simulation');
    $ini = array();
    foreach($keys as $k)
    {
        $v = ini_get($k);
        $ini[$k] = ($v === false) ? '<not-defined>' : probe_utf8($v);
    }
    $r['ini'] = $ini;
    $groups = array();
    foreach(array('mssql', 'sqlsrv', 'pdo_sqlsrv', 'mbstring', 'session', 'date', 'pcre', 'iconv', 'gd', 'mysql') as $g)
    {
        if(extension_loaded($g))
        {
            $all = @ini_get_all($g, false);
            if(is_array($all))
            {
                $groups[$g] = probe_clean($all);
            }
        }
    }
    $r['ini_groups'] = $groups;
    if(isset($r['ini_groups']['session']['session.save_path']) && !probe_option('show-hosts'))
    {
        $r['ini_groups']['session']['session.save_path'] = probe_mask_text($r['ini_groups']['session']['session.save_path']);
    }
    $r['locale'] = @setlocale(LC_ALL, 0);
    $r['timezone'] = @date_default_timezone_get();
    $r['now_local'] = @date('c');

    $funcs = array('mssql_connect', 'mssql_pconnect', 'sqlsrv_connect', 'odbc_connect', 'mysql_connect', 'mysqli_connect',
        'mb_strlen', 'mb_convert_encoding', 'iconv', 'json_encode', 'crypt', 'hash', 'mcrypt_encrypt', 'openssl_encrypt',
        'ereg', 'eregi', 'split', 'each', 'create_function', 'get_magic_quotes_gpc', 'set_magic_quotes_runtime', 'utf8_encode',
        'gd_info', 'imagecreatefromjpeg', 'imagecreatefrompng', 'imagecreatefromgif', 'imagettftext', 'exif_read_data',
        'simplexml_load_string', 'xml_parser_create', 'xslt_create', 'curl_init', 'finfo_open', 'mime_content_type',
        'gzcompress', 'gzencode', 'bzopen', 'zip_open', 'apache_get_modules', 'apache_get_version', 'shell_exec', 'exec', 'proc_open',
        'ldap_connect', 'imap_open', 'mail', 'session_start', 'iconv_strlen', 'idn_to_ascii', 'normalizer_normalize', 'money_format');
    $fa = array();
    foreach($funcs as $f)
    {
        $fa[$f] = function_exists($f);
    }
    $r['functions'] = $fa;
    $classes = array();
    foreach(array('DOMDocument', 'XSLTProcessor', 'ZipArchive', 'SimpleXMLElement', 'PDO', 'finfo', 'Collator', 'Normalizer', 'Imagick') as $c)
    {
        $classes[$c] = class_exists($c, false);
    }
    $r['classes'] = $classes;
    if(class_exists('PDO', false))
    {
        $r['pdo_drivers'] = PDO::getAvailableDrivers();
    }
    if(function_exists('gd_info'))
    {
        $r['gd_info'] = probe_clean(@gd_info());
    }

    $crypt = array();
    foreach(array('CRYPT_SALT_LENGTH', 'CRYPT_STD_DES', 'CRYPT_EXT_DES', 'CRYPT_MD5', 'CRYPT_BLOWFISH', 'CRYPT_SHA256', 'CRYPT_SHA512') as $c)
    {
        $crypt[$c] = defined($c) ? constant($c) : null;
    }
    // Known-answer test so that PHP 8 crypt() can be compared with production output
    $crypt['md5crypt_known_answer'] = @crypt('archon-probe', '$1$probe123$');
    $crypt['md5crypt_short_salt'] = @crypt('archon-probe', '$1$ab$');
    $r['crypt'] = $crypt;

    // PEAR / MDB2 as resolved through the include_path
    $pear = array();
    $files = array('PEAR.php', 'MDB2.php', 'MDB2/Driver/mssql.php', 'MDB2/Driver/Datatype/mssql.php', 'MDB2/Driver/Manager/mssql.php',
        'MDB2/Driver/Reverse/mssql.php', 'MDB2/Driver/Function/mssql.php', 'MDB2/Driver/Native/mssql.php',
        'MDB2/Driver/Datatype/Common.php', 'MDB2/Driver/Manager/Common.php', 'MDB2/Extended.php', 'MDB2/Date.php', 'MDB2/LOB.php');
    foreach($files as $f)
    {
        $path = function_exists('stream_resolve_include_path') ? stream_resolve_include_path($f) : false;
        if(!$path)
        {
            $pear[$f] = null;
            continue;
        }
        $head = (string)probe_read_file_head($path, 131072);
        $info = array('path' => $path, 'md5' => @md5_file($path), 'size' => @filesize($path), 'mtime' => @filemtime($path));
        if(preg_match_all('/@version\s+([^\r\n]+)/', $head, $m))
        {
            $info['version_tags'] = array_slice(array_map('trim', $m[1]), 0, 3);
        }
        if(preg_match('/\$Id: [^$]*\$/', $head, $m))
        {
            $info['cvs_id'] = $m[0];
        }
        if(preg_match('/function\s+apiVersion\s*\(\s*\)\s*\{\s*return\s+[\'"]([^\'"]+)[\'"]/', $head, $m))
        {
            $info['api_version'] = $m[1];
        }
        $pear[$f] = $info;
    }
    $r['pear_files'] = $pear;

    // PEAR registry (*.reg files are serialized arrays; read with a regex, never unserialize)
    $regdirs = array();
    foreach(explode(PATH_SEPARATOR, (string)get_include_path()) as $d)
    {
        if($d !== '' && $d !== '.')
        {
            $regdirs[] = rtrim($d, '/') . '/.registry';
        }
    }
    foreach(array('/usr/share/pear/.registry', '/usr/share/php/.registry', '/usr/lib/php/.registry', '/usr/local/lib/php/.registry') as $d)
    {
        $regdirs[] = $d;
    }
    $registry = array();
    foreach(array_unique($regdirs) as $d)
    {
        if(!@is_dir($d))
        {
            continue;
        }
        $regs = @glob($d . '/*.reg');
        if(!$regs)
        {
            continue;
        }
        foreach($regs as $reg)
        {
            $data = (string)probe_read_file_head($reg, 262144);
            $ver = null;
            if(preg_match('/"version";a:\d+:\{s:\d+:"release";s:\d+:"([^"]*)"/', $data, $m))
            {
                $ver = $m[1];
            }
            $registry[$d][basename($reg, '.reg')] = $ver;
        }
    }
    $r['pear_registry'] = $registry;

    $r['system_packages'] = probe_system_packages();
    return $r;
}

function probe_system_packages()
{
    $out = probe_shell("rpm -qa --qf '%{NAME} %{VERSION}-%{RELEASE}\\n'");
    $re = '/php|freetds|pear|httpd|apache|unixodbc|msodbc|mssql|openssl|libiconv|glibc|tzdata|icu|pcre|gd|libxml/i';
    $res = array();
    if($out && strpos($out, 'not found') === false && strpos($out, 'No such file') === false)
    {
        foreach(explode("\n", $out) as $line)
        {
            if(preg_match($re, $line))
            {
                $res[] = trim($line);
            }
        }
        sort($res);
        return array('source' => 'rpm', 'packages' => $res);
    }
    $out = probe_shell("dpkg-query -W -f='\${Package} \${Version}\\n'");
    if($out && strpos($out, 'not found') === false)
    {
        foreach(explode("\n", $out) as $line)
        {
            if(preg_match($re, $line))
            {
                $res[] = trim($line);
            }
        }
        sort($res);
        return array('source' => 'dpkg', 'packages' => $res);
    }
    return null;
}

// ---------------------------------------------------------------------------
// Section: freetds
// ---------------------------------------------------------------------------

function probe_section_freetds()
{
    $r = array();
    $env = array();
    foreach(array('TDSVER', 'FREETDSCONF', 'FREETDS', 'SYBASE', 'TDSDUMP', 'TDSDUMPCONFIG', 'TDSHOST', 'TDSPORT', 'TDSQUERY', 'DSQUERY',
        'LANG', 'LC_ALL', 'LC_CTYPE', 'ODBCSYSINI', 'ODBCINI', 'TZ', 'HOME') as $k)
    {
        $v = getenv($k);
        $env[$k] = ($v === false) ? null : probe_mask_text($v);
    }
    if($env['TDSHOST'] !== null)
    {
        $env['TDSHOST'] = probe_redact_host(getenv('TDSHOST'));
    }
    $r['env'] = $env;

    $candidates = array();
    if(getenv('FREETDSCONF'))
    {
        $candidates[] = getenv('FREETDSCONF');
    }
    if(getenv('FREETDS'))
    {
        $candidates[] = rtrim(getenv('FREETDS'), '/') . '/freetds.conf';
        $candidates[] = rtrim(getenv('FREETDS'), '/') . '/etc/freetds.conf';
        $candidates[] = getenv('FREETDS');
    }
    if(getenv('HOME'))
    {
        $candidates[] = rtrim(getenv('HOME'), '/') . '/.freetds.conf';
    }
    foreach(array('/etc/freetds.conf', '/etc/freetds/freetds.conf', '/usr/local/etc/freetds.conf', '/usr/etc/freetds.conf',
        '/opt/freetds/etc/freetds.conf', '/usr/share/httpd/.freetds.conf', '/var/www/.freetds.conf') as $c)
    {
        $candidates[] = $c;
    }
    $confs = array();
    foreach(array_unique($candidates) as $c)
    {
        if(!@is_file($c))
        {
            continue;
        }
        if(!@is_readable($c))
        {
            $confs[$c] = '<not readable>';
            continue;
        }
        $confs[$c] = probe_parse_freetds_conf((string)@file_get_contents($c));
    }
    $r['conf_files'] = $confs;

    $tsql = probe_shell('tsql -C');
    if($tsql !== null && preg_match('/freetds|version/i', $tsql))
    {
        $r['tsql_C'] = $tsql;
    }
    else
    {
        $r['tsql_C'] = null;
    }
    return $r;
}

function probe_parse_freetds_conf($text)
{
    $sections = array();
    $current = '(top)';
    foreach(preg_split('/\r\n|\r|\n/', $text) as $line)
    {
        $t = trim($line);
        if($t === '' || $t[0] == ';' || $t[0] == '#')
        {
            continue;
        }
        if(preg_match('/^\[(.+)\]$/', $t, $m))
        {
            $current = trim($m[1]);
            if(!probe_option('show-hosts') && !in_array(strtolower($current), array('global', 'egserver50', 'egserver70', 'egserver73')))
            {
                $current = $current . ' (' . probe_redact_host($current) . ')';
            }
            $sections[$current] = array();
            continue;
        }
        if(preg_match('/^([^=]+?)\s*=\s*(.*)$/', $t, $m))
        {
            $k = strtolower(trim($m[1]));
            $v = trim($m[2]);
            if(probe_is_secret_name($k))
            {
                $v = probe_set_or_empty($v);
            }
            elseif($k == 'host')
            {
                $v = probe_redact_host($v);
            }
            else
            {
                $v = probe_mask_text($v);
            }
            $sections[$current][$k] = probe_utf8($v);
        }
    }
    return $sections;
}

// ---------------------------------------------------------------------------
// Section: archon
// ---------------------------------------------------------------------------

function probe_find_archon_dir()
{
    $explicit = probe_option('archon-dir');
    if($explicit)
    {
        return rtrim($explicit, '/');
    }
    $starts = array(getcwd(), dirname(__FILE__));
    foreach($starts as $d)
    {
        for($i = 0; $i < 5 && $d && $d != '/'; $i++)
        {
            if(@is_file($d . '/packages/core/index.php') && (@is_file($d . '/config.inc.php') || @is_file($d . '/start.inc.php')))
            {
                return $d;
            }
            $d = dirname($d);
        }
    }
    return null;
}

/** Includes config.inc.php inside a function scope so that it cannot touch globals. */
function probe_load_config($dir)
{
    $_ARCHON = new stdClass();
    $_ARCHON->db = new stdClass();
    $_ARCHON->db->TablePrefixes = '';
    $_ARCHON->config = new stdClass();
    $previousGlobal = isset($GLOBALS['_ARCHON']) ? $GLOBALS['_ARCHON'] : null;
    $GLOBALS['_ARCHON'] = $_ARCHON;

    $iniBefore = @ini_get_all(null, false);
    $constBefore = get_defined_constants(true);
    $constBefore = isset($constBefore['user']) ? $constBefore['user'] : array();
    $envKeys = array('TDSVER', 'FREETDSCONF', 'FREETDS', 'SYBASE', 'TDSDUMP', 'LANG', 'LC_ALL', 'TZ');
    $envBefore = array();
    foreach($envKeys as $k)
    {
        $envBefore[$k] = getenv($k);
    }

    $cwd = getcwd();
    @chdir($dir);
    ob_start();
    $error = null;
    try
    {
        include $dir . '/config.inc.php';
    }
    catch(Exception $e)
    {
        $error = get_class($e) . ': ' . $e->getMessage();
    }
    $output = ob_get_clean();
    @chdir($cwd);
    $vars = array_keys(get_defined_vars());

    $iniAfter = @ini_get_all(null, false);
    $iniChanges = array();
    if(is_array($iniBefore) && is_array($iniAfter))
    {
        foreach($iniAfter as $k => $v)
        {
            if(!array_key_exists($k, $iniBefore) || (string)$iniBefore[$k] !== (string)$v)
            {
                $iniChanges[$k] = probe_redact_value($k, $v);
            }
        }
    }
    $constAfter = get_defined_constants(true);
    $constAfter = isset($constAfter['user']) ? $constAfter['user'] : array();
    $constChanges = array();
    foreach($constAfter as $k => $v)
    {
        if(!array_key_exists($k, $constBefore) && strpos($k, 'ARCHON_PROBE') !== 0)
        {
            $constChanges[$k] = probe_redact_value($k, $v);
        }
    }
    $envChanges = array();
    foreach($envKeys as $k)
    {
        if(getenv($k) !== $envBefore[$k])
        {
            $envChanges[$k] = probe_mask_text((string)getenv($k));
        }
    }
    $GLOBALS['_ARCHON'] = $previousGlobal;

    return array(
        'archon' => $_ARCHON,
        'output_bytes' => strlen($output),
        'output_starts_with_bom' => substr($output, 0, 3) == "\xEF\xBB\xBF",
        'output_is_whitespace' => trim($output) === '',
        'include_error' => $error,
        'ini_changes' => $iniChanges,
        'constants_defined' => $constChanges,
        'env_changes' => $envChanges,
        'local_variables' => array_values(array_diff($vars, array('dir', '_ARCHON', 'previousGlobal', 'iniBefore', 'constBefore', 'envKeys', 'envBefore', 'k', 'cwd', 'error', 'output', 'e'))),
    );
}

function probe_section_archon($dir)
{
    $r = array('dir' => $dir);
    if(!$dir)
    {
        $r['error'] = 'Archon directory not found; pass --archon-dir';
        return $r;
    }
    $packages = array();
    foreach((array)@glob($dir . '/packages/*/index.php') as $f)
    {
        $src = (string)probe_read_file_head($f, 8192);
        $p = array();
        if(preg_match('/\$APRCode\s*=\s*[\'"]([^\'"]+)/', $src, $m))
        {
            $p['APRCode'] = $m[1];
        }
        if(preg_match('/\$Version\s*=\s*[\'"]([^\'"]+)/', $src, $m))
        {
            $p['Version'] = $m[1];
        }
        $packages[basename(dirname($f))] = $p;
    }
    $r['packages'] = $packages;

    $cfgFile = $dir . '/config.inc.php';
    $r['config_file'] = array('exists' => @is_file($cfgFile), 'readable' => @is_readable($cfgFile), 'size' => @filesize($cfgFile), 'mtime' => @filemtime($cfgFile));
    if(@is_readable($cfgFile))
    {
        $src = (string)@file_get_contents($cfgFile);
        $r['config_file']['md5'] = md5($src);
        $r['config_file']['line_endings'] = (strpos($src, "\r\n") !== false) ? 'CRLF' : 'LF';
        $r['config_file']['short_open_tags'] = (bool)preg_match('/<\?(?!php|=|xml)/i', $src);
        $calls = array();
        if(preg_match_all('/\b(ini_set|ini_alter|putenv|define|error_reporting|date_default_timezone_set|setlocale|set_include_path|session_[a-z_]+|header|set_error_handler|require|require_once|include|include_once)\b\s*\(?([^;\r\n]*)/i', $src, $mm, PREG_SET_ORDER))
        {
            foreach($mm as $m)
            {
                $fn = strtolower($m[1]);
                $args = trim($m[2]);
                if(probe_is_secret_name($args) || $fn == 'define')
                {
                    $args = preg_replace('/([\'"])(?:(?!\1).)*\1\s*\)\s*$/', '<redacted>)', $args);
                }
                $calls[] = $fn . ' ' . substr(probe_mask_text($args), 0, 200);
            }
        }
        $r['config_file']['calls'] = $calls;

        $loaded = probe_load_config($dir);
        $archon = $loaded['archon'];
        unset($loaded['archon']);
        $r['config_load'] = $loaded;

        $db = array();
        foreach(get_object_vars($archon->db) as $k => $v)
        {
            if(in_array($k, array('Login', 'Password')) || probe_is_secret_name($k))
            {
                $db[$k] = probe_set_or_empty($v);
            }
            elseif($k == 'ServerAddress')
            {
                $db[$k] = probe_redact_host($v);
            }
            else
            {
                $db[$k] = probe_redact_value($k, $v);
            }
        }
        $r['db_config'] = probe_clean($db);
        $cfg = array();
        foreach(get_object_vars($archon->config) as $k => $v)
        {
            $cfg[$k] = probe_redact_value($k, $v);
            if(preg_match('/ip/i', $k) && !probe_option('show-hosts') && is_array($v))
            {
                $cfg[$k] = '<array:' . count($v) . ' entries>';
            }
        }
        $r['config'] = probe_clean($cfg);
        $GLOBALS['probe_db_config'] = $archon->db;
    }

    foreach(array('cryptor.inc.php', 'cryptorblank.inc.php', 'install.php', 'install.php_', 'upgrade.php', '.htaccess', 'robots.txt', 'favicon.ico') as $f)
    {
        $r['root_files'][$f] = @file_exists($dir . '/' . $f);
    }
    $r['cryptor_md5'] = @is_file($dir . '/cryptor.inc.php') ? @md5_file($dir . '/cryptor.inc.php') : null;
    if(@is_readable($dir . '/.htaccess'))
    {
        $r['htaccess'] = probe_mask_text(probe_utf8((string)probe_read_file_head($dir . '/.htaccess', 16384)));
    }

    $entries = array();
    foreach((array)@scandir($dir) as $e)
    {
        if($e === '.' || $e === '..' || $e === false)
        {
            continue;
        }
        $p = $dir . '/' . $e;
        $entries[$e] = (@is_link($p) ? 'link' : (@is_dir($p) ? 'dir' : 'file')) . (@is_writable($p) ? ',writable' : '');
    }
    $r['top_level'] = $entries;

    $themes = array();
    foreach((array)@glob($dir . '/themes/*', GLOB_ONLYDIR) as $t)
    {
        $files = (array)@glob($t . '/*');
        $newest = 0;
        foreach($files as $f)
        {
            $newest = max($newest, (int)@filemtime($f));
        }
        $themes[basename($t)] = array('files' => count($files), 'newest_mtime' => $newest ? date('c', $newest) : null);
    }
    $r['themes'] = $themes;
    $templates = array();
    foreach((array)@glob($dir . '/packages/*/templates/*', GLOB_ONLYDIR) as $t)
    {
        $templates[basename($t)][] = basename(dirname(dirname($t)));
    }
    $r['template_sets'] = $templates;

    if(@is_file($dir . '/.git/HEAD'))
    {
        $head = trim((string)@file_get_contents($dir . '/.git/HEAD'));
        $git = array('HEAD' => $head);
        if(preg_match('/^ref: (.+)$/', $head, $m))
        {
            $ref = $m[1];
            if(@is_file($dir . '/.git/' . $ref))
            {
                $git['commit'] = trim((string)@file_get_contents($dir . '/.git/' . $ref));
            }
            elseif(@is_file($dir . '/.git/packed-refs'))
            {
                foreach(file($dir . '/.git/packed-refs') as $line)
                {
                    if(preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/', trim($line), $m2))
                    {
                        $git['commit'] = $m2[1];
                    }
                }
            }
        }
        $r['git'] = $git;
    }

    $writable = array();
    foreach(array('', 'packages', 'themes', 'incoming', 'cache', 'packages/core/lib', 'packages/digitallibrary', 'packages/collections') as $d)
    {
        $p = rtrim($dir . '/' . $d, '/');
        if(@file_exists($p))
        {
            $writable[$d === '' ? '.' : $d] = @is_writable($p);
        }
    }
    $r['writable'] = $writable;
    $r['tmp_dir'] = array('sys_get_temp_dir' => sys_get_temp_dir(), 'writable' => @is_writable(sys_get_temp_dir()));
    return $r;
}

// ---------------------------------------------------------------------------
// Section: files (manifest that can be compared against git)
// ---------------------------------------------------------------------------

function probe_section_files($dir)
{
    $r = array('dir' => $dir, 'excluded' => array('config.inc.php', 'cryptor.inc.php', '.git/', 'incoming/', 'cache/'));
    if(!$dir)
    {
        $r['error'] = 'Archon directory not found';
        return $r;
    }
    $max = (int)probe_option('max-files', 50000);
    $manifest = array();
    $truncated = false;
    $skippedLarge = array();
    $base = strlen($dir) + 1;
    try
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach($it as $path => $info)
        {
            $rel = str_replace('\\', '/', substr($path, $base));
            if($rel === 'config.inc.php' || $rel === 'cryptor.inc.php' || preg_match('#^(\.git|incoming|cache)/#', $rel) || strpos($rel, '/.git/') !== false)
            {
                continue;
            }
            if(!$info->isFile())
            {
                continue;
            }
            if(count($manifest) >= $max)
            {
                $truncated = true;
                break;
            }
            $size = $info->getSize();
            if($size > 50 * 1024 * 1024)
            {
                $skippedLarge[] = $rel;
                $manifest[probe_utf8($rel)] = 'skipped ' . $size . ' ' . $info->getMTime();
                continue;
            }
            $manifest[probe_utf8($rel)] = @md5_file($path) . ' ' . $size . ' ' . $info->getMTime();
        }
    }
    catch(Exception $e)
    {
        $r['error'] = get_class($e) . ': ' . $e->getMessage();
    }
    ksort($manifest);
    $r['count'] = count($manifest);
    $r['truncated'] = $truncated;
    $r['manifest'] = $manifest;
    return $r;
}

// ---------------------------------------------------------------------------
// Section: apache
// ---------------------------------------------------------------------------

function probe_section_apache($archonDir)
{
    $r = array();
    if(function_exists('apache_get_version'))
    {
        $r['apache_get_version'] = @apache_get_version();
    }
    if(function_exists('apache_get_modules'))
    {
        $r['apache_get_modules'] = @apache_get_modules();
    }
    if(PHP_SAPI != 'cli')
    {
        $srv = array();
        foreach(array('SERVER_SOFTWARE', 'SERVER_PROTOCOL', 'DOCUMENT_ROOT', 'SCRIPT_FILENAME', 'HTTPS', 'REQUEST_SCHEME', 'GATEWAY_INTERFACE') as $k)
        {
            $srv[$k] = isset($_SERVER[$k]) ? probe_utf8($_SERVER[$k]) : null;
        }
        $r['server'] = $srv;
    }
    $bin = null;
    foreach(array('/usr/sbin/httpd', '/usr/sbin/apache2', '/usr/local/apache2/bin/httpd') as $b)
    {
        if(@is_file($b))
        {
            $bin = $b;
            break;
        }
    }
    if($bin)
    {
        $r['binary'] = $bin;
        $r['version'] = probe_shell(escapeshellarg($bin) . ' -v');
    }
    $files = array();
    foreach(array('/etc/httpd/conf/*.conf', '/etc/httpd/conf.d/*.conf', '/etc/httpd/conf.modules.d/*.conf',
        '/etc/apache2/apache2.conf', '/etc/apache2/sites-enabled/*', '/etc/apache2/conf-enabled/*', '/etc/apache2/mods-enabled/*.conf',
        '/etc/apache2/mods-enabled/*.load', '/usr/local/apache2/conf/httpd.conf', '/usr/local/apache2/conf/extra/*.conf') as $g)
    {
        foreach((array)@glob($g) as $f)
        {
            if($f)
            {
                $files[] = $f;
            }
        }
    }
    $re = '/^\s*(<\/?(Directory|DirectoryMatch|Location|LocationMatch|Files|FilesMatch|VirtualHost|IfModule)\b|php_value|php_admin_value|php_flag|php_admin_flag|PHPIniDir|Alias|AliasMatch|ScriptAlias|DocumentRoot|SetEnv|SetEnvIf|PassEnv|UnsetEnv|Rewrite\w*|AddHandler|SetHandler|AddType|DirectoryIndex|AddDefaultCharset|LoadModule|Options|AllowOverride|Require|Order|Allow|Deny|Satisfy|AuthType|ServerName|ServerAlias|Listen|SSLProtocol|SSLCipherSuite|Header|LimitRequestBody|Timeout|KeepAlive\w*|MaxRequestWorkers|MaxClients|ServerLimit|StartServers|MinSpareServers|MaxSpareServers|MaxRequestsPerChild|MaxConnectionsPerChild|Include\w*|User|Group)\b/i';
    $conf = array();
    foreach(array_unique($files) as $f)
    {
        if(!@is_readable($f))
        {
            $conf[$f] = '<not readable>';
            continue;
        }
        $lines = array();
        foreach((array)@file($f) as $n => $line)
        {
            if(preg_match($re, $line) || stripos($line, 'archon') !== false)
            {
                $t = trim($line);
                if(preg_match('/^(SetEnv|PassEnv)\s+(\S+)/i', $t, $m) && probe_is_secret_name($m[2]))
                {
                    $t = $m[1] . ' ' . $m[2] . ' <redacted>';
                }
                $lines[] = ($n + 1) . ': ' . probe_mask_text(probe_utf8($t));
            }
        }
        $conf[$f] = $lines;
    }
    $r['config_lines'] = $conf;
    return $r;
}

// ---------------------------------------------------------------------------
// Database access (read-only)
// ---------------------------------------------------------------------------

/**
 * Rejects anything that is not a single read-only statement. All SQL used by
 * the probe is generated here, so this is defence in depth.
 */
function probe_assert_readonly($sql)
{
    $s = preg_replace("/N?'(?:[^']|'')*'/s", "''", $sql);
    $s = preg_replace('/\[(?:[^\]]|\]\])*\]/', '[x]', $s);
    $s = preg_replace('#/\*.*?\*/#s', ' ', $s);
    $s = preg_replace('/--[^\r\n]*/', ' ', $s);
    $u = strtoupper(trim($s));
    $u = rtrim($u, "; \t\r\n");
    if(strpos($u, ';') !== false)
    {
        throw new Exception('read-only guard: multiple statements are not allowed');
    }
    $allowedStart = '/^(SELECT\b|WITH\b|DBCC\s+USEROPTIONS$|SET\s+(DEADLOCK_PRIORITY\s+LOW|LOCK_TIMEOUT\s+\d+|TRANSACTION\s+ISOLATION\s+LEVEL\s+READ\s+UNCOMMITTED|DATEFORMAT\s+YMD|TEXTSIZE\s+\d+|NOCOUNT\s+ON)$)/';
    if(!preg_match($allowedStart, $u))
    {
        throw new Exception('read-only guard: statement type not allowed');
    }
    if(preg_match('/\b(INSERT|UPDATE|DELETE|MERGE|INTO|CREATE|ALTER|DROP|TRUNCATE|EXEC|EXECUTE|GRANT|REVOKE|DENY|BACKUP|RESTORE|SHUTDOWN|KILL|RECONFIGURE|OPENROWSET|OPENQUERY|OPENDATASOURCE|OPENXML|BULK|WRITETEXT|UPDATETEXT|READTEXT|USE|DECLARE|WAITFOR|GOTO|SP_\w+|XP_\w+)\b/', $u, $m))
    {
        throw new Exception('read-only guard: forbidden keyword ' . $m[1]);
    }
    if(preg_match('/\bDBCC\b/', $u) && !preg_match('/^DBCC\s+USEROPTIONS$/', $u))
    {
        throw new Exception('read-only guard: only DBCC USEROPTIONS is allowed');
    }
    return true;
}

class ProbeDb
{
    public $driver = null;
    public $link = null;
    public $connectInfo = array();
    public $queryLog = array();

    public function connect($cfg)
    {
        $driver = probe_option('db-driver', 'auto');
        if($driver == 'auto')
        {
            $driver = function_exists('mssql_connect') ? 'mssql' : (function_exists('sqlsrv_connect') ? 'sqlsrv' : null);
        }
        if(!$driver)
        {
            throw new Exception('Neither the mssql nor the sqlsrv extension is available');
        }
        $this->driver = $driver;
        $server = isset($cfg->ServerAddress) ? (string)$cfg->ServerAddress : '';
        $database = isset($cfg->DatabaseName) ? (string)$cfg->DatabaseName : '';
        $login = isset($cfg->Login) ? (string)$cfg->Login : '';
        $password = isset($cfg->Password) ? (string)$cfg->Password : '';
        $this->connectInfo = array('driver' => $driver, 'server' => probe_redact_host($server), 'database' => $database, 'credentials' => 'config.inc.php');
        if(probe_option('db-login'))
        {
            $login = (string)probe_option('db-login');
            $password = (string)getenv('ARCHON_PROBE_DB_PASSWORD');
            $this->connectInfo['credentials'] = '--db-login';
        }
        $timeout = (int)probe_option('query-timeout', 300);

        if($driver == 'mssql')
        {
            // Mirror start.inc.php + MDB2's mssql driver
            $tds = (isset($cfg->VersionTDS) && $cfg->VersionTDS) ? $cfg->VersionTDS : '70';
            if(getenv('TDSVER') === false || getenv('TDSVER') === '')
            {
                putenv('TDSVER=' . $tds);
            }
            $this->connectInfo['TDSVER'] = getenv('TDSVER');
            @ini_set('mssql.textsize', '2147483647');
            @ini_set('mssql.textlimit', '2147483647');
            @ini_set('mssql.datetimeconvert', '0');
            @ini_set('mssql.timeout', (string)$timeout);
            $this->link = mssql_connect($server, $login, $password, true);
            if(!$this->link)
            {
                $msg = function_exists('mssql_get_last_message') ? mssql_get_last_message() : '';
                throw new Exception('mssql_connect failed: ' . $msg);
            }
            if(!mssql_select_db($database, $this->link))
            {
                throw new Exception('mssql_select_db failed: ' . mssql_get_last_message());
            }
            $this->exec('SET DATEFORMAT ymd');
        }
        else
        {
            $host = $server;
            if(preg_match('/^([^:\\\\]+):(\d+)$/', $server, $m))
            {
                $host = $m[1] . ',' . $m[2];
            }
            $options = array('Database' => $database, 'UID' => $login, 'PWD' => $password, 'CharacterSet' => 'UTF-8', 'ReturnDatesAsStrings' => true);
            if(isset($cfg->SqlsrvOptions) && is_array($cfg->SqlsrvOptions))
            {
                $options = array_merge($options, $cfg->SqlsrvOptions);
            }
            $this->link = sqlsrv_connect($host, $options);
            if(!$this->link)
            {
                throw new Exception('sqlsrv_connect failed: ' . probe_sqlsrv_errors());
            }
            $this->exec('SET DATEFORMAT ymd');
        }
        return true;
    }

    /** Runs a read-only statement and returns all rows of the first result set. */
    public function query($sql, $maxRows = 100000)
    {
        probe_assert_readonly($sql);
        $start = microtime(true);
        $rows = array();
        if($this->driver == 'mssql')
        {
            $res = @mssql_query($sql, $this->link);
            if($res === false)
            {
                throw new Exception('query failed: ' . probe_utf8(mssql_get_last_message()));
            }
            if(is_resource($res))
            {
                while(($row = mssql_fetch_assoc($res)) !== false)
                {
                    if(count($rows) < $maxRows)
                    {
                        $rows[] = $row;
                    }
                }
                mssql_free_result($res);
            }
        }
        else
        {
            $stmt = sqlsrv_query($this->link, $sql, array(), array('QueryTimeout' => (int)probe_option('query-timeout', 300)));
            if($stmt === false)
            {
                throw new Exception('query failed: ' . probe_sqlsrv_errors());
            }
            while(($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null)
            {
                if($row === false)
                {
                    throw new Exception('fetch failed: ' . probe_sqlsrv_errors());
                }
                if(count($rows) < $maxRows)
                {
                    $rows[] = $row;
                }
            }
            sqlsrv_free_stmt($stmt);
        }
        $this->queryLog[] = round(microtime(true) - $start, 3);
        return probe_clean($rows);
    }

    public function exec($sql)
    {
        probe_assert_readonly($sql);
        if($this->driver == 'mssql')
        {
            $res = @mssql_query($sql, $this->link);
            if($res === false)
            {
                throw new Exception('statement failed: ' . mssql_get_last_message());
            }
            if(is_resource($res))
            {
                mssql_free_result($res);
            }
        }
        else
        {
            $stmt = sqlsrv_query($this->link, $sql);
            if($stmt === false)
            {
                throw new Exception('statement failed: ' . probe_sqlsrv_errors());
            }
            sqlsrv_free_stmt($stmt);
        }
    }

    public function close()
    {
        if($this->link)
        {
            if($this->driver == 'mssql')
            {
                @mssql_close($this->link);
            }
            else
            {
                @sqlsrv_close($this->link);
            }
        }
        $this->link = null;
    }
}

function probe_sqlsrv_errors()
{
    $out = array();
    foreach((array)sqlsrv_errors() as $e)
    {
        $out[] = (isset($e['SQLSTATE']) ? $e['SQLSTATE'] : '') . ' ' . (isset($e['message']) ? $e['message'] : '');
    }
    return probe_utf8(implode(' | ', $out));
}

/** Runs a query and stores either its rows or its error under $key. */
function probe_try_query($db, &$target, $key, $sql, $maxRows = 100000)
{
    try
    {
        $target[$key] = $db->query($sql, $maxRows);
        return $target[$key];
    }
    catch(Exception $e)
    {
        $target[$key] = array('error' => probe_utf8($e->getMessage()));
        return null;
    }
}

function probe_rows_to_map($rows, $keyCol, $valCol)
{
    $map = array();
    foreach((array)$rows as $row)
    {
        if(is_array($row) && array_key_exists($keyCol, $row))
        {
            $map[$row[$keyCol]] = $row[$valCol];
        }
    }
    return $map;
}

function probe_db_connect()
{
    if(!isset($GLOBALS['probe_db_config']))
    {
        throw new Exception('Database configuration not loaded (archon section failed?)');
    }
    if(isset($GLOBALS['probe_db']) && $GLOBALS['probe_db']->link)
    {
        return $GLOBALS['probe_db'];
    }
    $db = new ProbeDb();
    $db->connect($GLOBALS['probe_db_config']);
    $GLOBALS['probe_db'] = $db;

    // What the application session sees (before the probe's own safety settings)
    $info = array();
    probe_try_query($db, $info, 'useroptions', 'DBCC USEROPTIONS');
    $GLOBALS['probe_session_before'] = $info;
    $db->exec('SET DEADLOCK_PRIORITY LOW');
    $db->exec('SET LOCK_TIMEOUT 10000');
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
    return $db;
}

// ---------------------------------------------------------------------------
// Section: database
// ---------------------------------------------------------------------------

function probe_section_database()
{
    $r = array();
    $db = probe_db_connect();
    $r['connection'] = $db->connectInfo;
    $r['session_as_application_sees_it'] = $GLOBALS['probe_session_before'];

    probe_try_query($db, $r, 'server', "SELECT CAST(SERVERPROPERTY('ProductVersion') AS varchar(128)) AS ProductVersion,
        CAST(SERVERPROPERTY('ProductLevel') AS varchar(128)) AS ProductLevel,
        CAST(SERVERPROPERTY('ProductUpdateLevel') AS varchar(128)) AS ProductUpdateLevel,
        CAST(SERVERPROPERTY('Edition') AS varchar(128)) AS Edition,
        CAST(SERVERPROPERTY('EngineEdition') AS int) AS EngineEdition,
        CAST(SERVERPROPERTY('Collation') AS varchar(128)) AS ServerCollation,
        CAST(SERVERPROPERTY('InstanceName') AS varchar(128)) AS InstanceName,
        CAST(SERVERPROPERTY('IsClustered') AS int) AS IsClustered,
        CAST(SERVERPROPERTY('IsFullTextInstalled') AS int) AS IsFullTextInstalled,
        CAST(SERVERPROPERTY('IsIntegratedSecurityOnly') AS int) AS IsIntegratedSecurityOnly,
        CAST(@@VERSION AS varchar(512)) AS Version,
        CAST(@@LANGUAGE AS varchar(128)) AS SessionLanguage,
        CAST(@@TEXTSIZE AS int) AS SessionTextSize,
        CAST(@@SPID AS int) AS Spid");

    probe_try_query($db, $r, 'database', "SELECT CAST(name AS varchar(128)) AS name, CAST(collation_name AS varchar(128)) AS collation_name,
        CAST(compatibility_level AS int) AS compatibility_level, CAST(is_ansi_nulls_on AS int) AS ansi_nulls,
        CAST(is_ansi_padding_on AS int) AS ansi_padding, CAST(is_ansi_warnings_on AS int) AS ansi_warnings,
        CAST(is_arithabort_on AS int) AS arithabort, CAST(is_concat_null_yields_null_on AS int) AS concat_null_yields_null,
        CAST(is_quoted_identifier_on AS int) AS quoted_identifier, CAST(is_numeric_roundabort_on AS int) AS numeric_roundabort,
        CAST(is_ansi_null_default_on AS int) AS ansi_null_default, CAST(is_recursive_triggers_on AS int) AS recursive_triggers,
        CAST(is_read_committed_snapshot_on AS int) AS read_committed_snapshot, CAST(snapshot_isolation_state AS int) AS snapshot_isolation_state,
        CAST(recovery_model_desc AS varchar(60)) AS recovery_model, CAST(state_desc AS varchar(60)) AS state,
        CAST(user_access_desc AS varchar(60)) AS user_access, CAST(is_read_only AS int) AS is_read_only,
        CAST(page_verify_option_desc AS varchar(60)) AS page_verify, CAST(is_auto_close_on AS int) AS auto_close,
        CAST(is_auto_shrink_on AS int) AS auto_shrink, CAST(is_trustworthy_on AS int) AS trustworthy,
        CAST(is_fulltext_enabled AS int) AS fulltext_enabled, CONVERT(varchar(30), create_date, 126) AS create_date
        FROM sys.databases WHERE database_id = DB_ID()");

    probe_try_query($db, $r, 'database_files', "SELECT CAST(name AS varchar(128)) AS name, CAST(type_desc AS varchar(60)) AS type,
        CAST(CAST(size AS bigint) * 8 / 1024 AS int) AS size_mb, CAST(max_size AS int) AS max_size_pages,
        CAST(growth AS int) AS growth, CAST(is_percent_growth AS int) AS is_percent_growth FROM sys.database_files");

    probe_try_query($db, $r, 'session', "SELECT CAST(client_interface_name AS varchar(64)) AS client_interface_name,
        CAST(client_version AS int) AS client_version, CAST(program_name AS varchar(128)) AS program_name,
        CAST(language AS varchar(64)) AS language, CAST(date_format AS varchar(16)) AS date_format, CAST(date_first AS int) AS date_first,
        CAST(quoted_identifier AS int) AS quoted_identifier, CAST(arithabort AS int) AS arithabort,
        CAST(ansi_null_dflt_on AS int) AS ansi_null_dflt_on, CAST(ansi_defaults AS int) AS ansi_defaults,
        CAST(ansi_warnings AS int) AS ansi_warnings, CAST(ansi_padding AS int) AS ansi_padding, CAST(ansi_nulls AS int) AS ansi_nulls,
        CAST(concat_null_yields_null AS int) AS concat_null_yields_null, CAST(transaction_isolation_level AS int) AS transaction_isolation_level,
        CAST(lock_timeout AS int) AS lock_timeout, CAST(text_size AS int) AS text_size
        FROM sys.dm_exec_sessions WHERE session_id = @@SPID");

    $conn = array();
    probe_try_query($db, $conn, 'rows', "SELECT CAST(protocol_type AS varchar(40)) AS protocol_type, CAST(protocol_version AS int) AS protocol_version,
        CAST(net_transport AS varchar(40)) AS net_transport, CAST(encrypt_option AS varchar(40)) AS encrypt_option,
        CAST(auth_scheme AS varchar(40)) AS auth_scheme, CAST(net_packet_size AS int) AS net_packet_size
        FROM sys.dm_exec_connections WHERE session_id = @@SPID");
    if(isset($conn['rows'][0]['protocol_version']))
    {
        $conn['rows'][0]['protocol_version_hex'] = sprintf('0x%08X', (int)$conn['rows'][0]['protocol_version']);
    }
    $r['connection_dmv'] = $conn['rows'];

    probe_try_query($db, $r, 'principal', "SELECT CASE WHEN USER_NAME() = 'dbo' THEN 'dbo' ELSE 'other' END AS user_kind,
        CAST(IS_MEMBER('db_owner') AS int) AS db_owner, CAST(IS_MEMBER('db_datareader') AS int) AS db_datareader,
        CAST(IS_MEMBER('db_datawriter') AS int) AS db_datawriter, CAST(IS_MEMBER('db_ddladmin') AS int) AS db_ddladmin,
        CAST(IS_SRVROLEMEMBER('sysadmin') AS int) AS sysadmin");
    $perm = array();
    probe_try_query($db, $perm, 'database', "SELECT CAST(permission_name AS varchar(128)) AS p FROM fn_my_permissions(NULL, 'DATABASE')");
    probe_try_query($db, $perm, 'server', "SELECT CAST(permission_name AS varchar(128)) AS p FROM fn_my_permissions(NULL, 'SERVER')");
    foreach($perm as $k => $rows)
    {
        if(is_array($rows) && !isset($rows['error']))
        {
            $list = array();
            foreach($rows as $row)
            {
                $list[] = $row['p'];
            }
            $perm[$k] = $list;
        }
    }
    $r['my_permissions'] = $perm;

    // ---- schema -----------------------------------------------------------------
    probe_try_query($db, $r, 'tables', "SELECT CAST(SCHEMA_NAME(schema_id) AS varchar(128)) AS s, CAST(name AS varchar(128)) AS name,
        CONVERT(varchar(30), create_date, 126) AS create_date, CONVERT(varchar(30), modify_date, 126) AS modify_date
        FROM sys.tables WHERE is_ms_shipped = 0 ORDER BY 1, 2");
    $columns = $db->query("SELECT CAST(c.TABLE_SCHEMA AS varchar(128)) AS s, CAST(c.TABLE_NAME AS varchar(128)) AS t,
        CAST(c.COLUMN_NAME AS varchar(128)) AS c, CAST(c.ORDINAL_POSITION AS int) AS o, CAST(c.DATA_TYPE AS varchar(64)) AS dt,
        CAST(c.CHARACTER_MAXIMUM_LENGTH AS int) AS len, CAST(c.NUMERIC_PRECISION AS int) AS np, CAST(c.NUMERIC_SCALE AS int) AS ns,
        CAST(c.IS_NULLABLE AS varchar(3)) AS nul, CAST(c.COLUMN_DEFAULT AS varchar(512)) AS def, CAST(c.COLLATION_NAME AS varchar(128)) AS coll,
        CAST(COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.TABLE_SCHEMA) + '.' + QUOTENAME(c.TABLE_NAME)), c.COLUMN_NAME, 'IsIdentity') AS int) AS idn
        FROM INFORMATION_SCHEMA.COLUMNS c JOIN INFORMATION_SCHEMA.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
        WHERE t.TABLE_TYPE = 'BASE TABLE' ORDER BY 1, 2, 4");
    $schema = array();
    foreach($columns as $col)
    {
        $tkey = ($col['s'] == 'dbo' ? '' : $col['s'] . '.') . $col['t'];
        $type = $col['dt'];
        if($col['len'] !== null && $col['len'] !== '')
        {
            $type .= '(' . ($col['len'] == -1 ? 'max' : $col['len']) . ')';
        }
        elseif(in_array($col['dt'], array('decimal', 'numeric')))
        {
            $type .= '(' . $col['np'] . ',' . $col['ns'] . ')';
        }
        $desc = $type . ($col['nul'] == 'YES' ? ' NULL' : ' NOT NULL');
        if($col['idn'])
        {
            $desc .= ' IDENTITY';
        }
        if($col['def'] !== null && $col['def'] !== '')
        {
            $desc .= ' DEFAULT ' . $col['def'];
        }
        if($col['coll'] !== null && $col['coll'] !== '')
        {
            $desc .= ' COLLATE ' . $col['coll'];
        }
        $schema[$tkey][$col['c']] = $desc;
    }
    $r['columns'] = $schema;
    $GLOBALS['probe_columns'] = $columns;

    probe_try_query($db, $r, 'indexes', "SELECT CAST(SCHEMA_NAME(o.schema_id) AS varchar(128)) AS s, CAST(o.name AS varchar(128)) AS t,
        CAST(i.name AS varchar(128)) AS i, CAST(i.type_desc AS varchar(60)) AS type, CAST(i.is_unique AS int) AS u,
        CAST(i.is_primary_key AS int) AS pk, CAST(c.name AS varchar(128)) AS col, CAST(ic.key_ordinal AS int) AS ko,
        CAST(ic.is_included_column AS int) AS inc
        FROM sys.indexes i JOIN sys.objects o ON o.object_id = i.object_id
        JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
        JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
        WHERE o.is_ms_shipped = 0 AND i.type > 0 ORDER BY 1, 2, 3, 8");
    probe_try_query($db, $r, 'foreign_keys', "SELECT CAST(name AS varchar(128)) AS n, CAST(OBJECT_NAME(parent_object_id) AS varchar(128)) AS t,
        CAST(OBJECT_NAME(referenced_object_id) AS varchar(128)) AS rt, CAST(delete_referential_action_desc AS varchar(60)) AS on_delete
        FROM sys.foreign_keys ORDER BY 2, 1");
    probe_try_query($db, $r, 'check_constraints', "SELECT CAST(OBJECT_NAME(parent_object_id) AS varchar(128)) AS t, CAST(name AS varchar(128)) AS n,
        CAST(definition AS nvarchar(4000)) AS d FROM sys.check_constraints ORDER BY 1, 2");
    probe_try_query($db, $r, 'other_objects', "SELECT CAST(SCHEMA_NAME(schema_id) AS varchar(128)) AS s, CAST(name AS varchar(128)) AS n,
        CAST(type_desc AS varchar(60)) AS type, CAST(OBJECT_NAME(parent_object_id) AS varchar(128)) AS parent,
        CONVERT(varchar(30), create_date, 126) AS create_date, CONVERT(varchar(30), modify_date, 126) AS modify_date
        FROM sys.objects WHERE is_ms_shipped = 0 AND type NOT IN ('U', 'PK', 'UQ', 'D', 'C', 'F', 'IT', 'S', 'SQ', 'TT') ORDER BY 3, 1, 2");
    probe_try_query($db, $r, 'module_definitions', "SELECT CAST(OBJECT_NAME(m.object_id) AS varchar(128)) AS n, CAST(m.definition AS nvarchar(4000)) AS d
        FROM sys.sql_modules m JOIN sys.objects o ON o.object_id = m.object_id WHERE o.is_ms_shipped = 0 ORDER BY 1");
    probe_try_query($db, $r, 'fulltext_indexes', "SELECT CAST(OBJECT_NAME(object_id) AS varchar(128)) AS t, CAST(is_enabled AS int) AS enabled FROM sys.fulltext_indexes");

    $counts = $db->query("SELECT CAST(SCHEMA_NAME(t.schema_id) AS varchar(128)) AS s, CAST(t.name AS varchar(128)) AS n, CAST(SUM(p.rows) AS int) AS r
        FROM sys.tables t JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0, 1)
        WHERE t.is_ms_shipped = 0 GROUP BY t.schema_id, t.name ORDER BY 1, 2");
    $rc = array();
    foreach($counts as $row)
    {
        $rc[($row['s'] == 'dbo' ? '' : $row['s'] . '.') . $row['n']] = (int)$row['r'];
    }
    $r['row_counts'] = $rc;
    $GLOBALS['probe_row_counts'] = $rc;

    probe_try_query($db, $r, 'identity', "SELECT CAST(TABLE_NAME AS varchar(128)) AS t,
        CAST(IDENT_SEED(QUOTENAME(TABLE_SCHEMA) + '.' + QUOTENAME(TABLE_NAME)) AS int) AS seed,
        CAST(IDENT_CURRENT(QUOTENAME(TABLE_SCHEMA) + '.' + QUOTENAME(TABLE_NAME)) AS int) AS cur
        FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'
        AND OBJECTPROPERTY(OBJECT_ID(QUOTENAME(TABLE_SCHEMA) + '.' + QUOTENAME(TABLE_NAME)), 'TableHasIdentity') = 1 ORDER BY 1");

    $r['application'] = probe_application_queries($db, $schema);
    return $r;
}

function probe_has_table($schema, $t)
{
    return isset($schema[$t]);
}

function probe_has_column($schema, $t, $c)
{
    return isset($schema[$t][$c]);
}

/** Dumps a small lookup table, casting every column to text (binary columns are summarised). */
function probe_dump_table($db, $schema, $table, $maxRows, $redactCallback = null)
{
    if(!probe_has_table($schema, $table))
    {
        return null;
    }
    $select = array();
    $aliases = array();
    $i = 0;
    foreach($schema[$table] as $col => $desc)
    {
        $alias = 'c' . $i++;
        $aliases[$alias] = $col;
        $type = strtolower(preg_replace('/[\s(].*$/', '', $desc));
        $q = probe_qi($col);
        if(in_array($type, array('binary', 'varbinary', 'image', 'timestamp', 'rowversion')))
        {
            $select[] = "CAST(DATALENGTH($q) AS int) AS $alias";
        }
        elseif(in_array($type, array('datetime', 'smalldatetime', 'date', 'datetime2', 'datetimeoffset', 'time')))
        {
            $select[] = "CONVERT(varchar(40), $q, 126) AS $alias";
        }
        elseif(in_array($type, array('int', 'smallint', 'tinyint', 'bit')))
        {
            $select[] = "CAST($q AS int) AS $alias";
        }
        elseif(in_array($type, array('nvarchar', 'nchar', 'ntext', 'xml')))
        {
            $select[] = "CAST(LEFT(CAST($q AS nvarchar(max)), 400) AS nvarchar(400)) AS $alias";
        }
        else
        {
            $select[] = "CAST(LEFT(CAST($q AS varchar(max)), 400) AS varchar(400)) AS $alias";
        }
    }
    $order = isset($schema[$table]['ID']) ? ' ORDER BY [ID]' : '';
    try
    {
        $rows = $db->query('SELECT TOP ' . (int)$maxRows . ' ' . implode(', ', $select) . ' FROM ' . probe_qi($table) . $order, $maxRows);
    }
    catch(Exception $e)
    {
        return array('error' => $e->getMessage());
    }
    $out = array();
    foreach($rows as $row)
    {
        $named = array();
        foreach($row as $alias => $v)
        {
            $col = $aliases[$alias];
            $named[$col] = probe_is_secret_name($col) ? probe_set_or_empty($v) : probe_mask_text($v);
        }
        if($redactCallback)
        {
            $named = call_user_func($redactCallback, $named);
        }
        $out[] = $named;
    }
    return $out;
}

function probe_redact_configuration_row($row)
{
    $directive = isset($row['Directive']) ? $row['Directive'] : '';
    if((isset($row['Encrypted']) && (int)$row['Encrypted'] == 1) || probe_is_secret_name($directive) || preg_match('/smtp.*(user|login)/i', $directive))
    {
        $row['Value'] = probe_set_or_empty(isset($row['Value']) ? $row['Value'] : null);
    }
    return $row;
}

function probe_application_queries($db, $schema)
{
    $a = array();
    if(!probe_option('no-lookup-dumps'))
    {
        $lookups = array(
            'tblCore_Packages' => 100, 'tblCore_Modules' => 500, 'tblCore_Repositories' => 100, 'tblCore_Usergroups' => 100,
            'tblCore_Patterns' => 200, 'tblCore_UserProfileFields' => 200, 'tblCore_UserProfileFieldCategories' => 100,
            'tblCollections_ExtentUnits' => 200, 'tblCollections_MaterialTypes' => 200, 'tblCollections_LevelContainers' => 200,
            'tblCollections_ResearchAppointmentPurposes' => 100, 'tblCollections_ResearcherTypes' => 100, 'tblCollections_ResearchAppointmentFields' => 100,
            'tblCollections_Locations' => 300, 'tblCollections_Classifications' => 300,
            'tblAccessions_ProcessingPriorities' => 100, 'tblCreators_CreatorSources' => 100, 'tblCreators_CreatorTypes' => 100,
            'tblSubjects_SubjectSources' => 100, 'tblSubjects_SubjectTypes' => 100, 'tblDigitalLibrary_FileTypes' => 200,
            'tblDigitalLibrary_MediaTypes' => 100, 'tblCore_Languages' => 0, 'tblAVSAP_Institutions' => 50, 'tblAVSAP_StorageFacilities' => 100);
        $dumps = array();
        foreach($lookups as $t => $max)
        {
            if($max > 0)
            {
                $d = probe_dump_table($db, $schema, $t, $max);
                if($d !== null)
                {
                    $dumps[$t] = $d;
                }
            }
        }
        $cfg = probe_dump_table($db, $schema, 'tblCore_Configuration', 1000, 'probe_redact_configuration_row');
        if($cfg !== null)
        {
            $dumps['tblCore_Configuration'] = $cfg;
        }
        $a['lookup_tables'] = $dumps;
    }

    if(probe_has_table($schema, 'tblCore_Users'))
    {
        $cols = array('CAST(COUNT(*) AS int) AS total');
        foreach(array('IsAdminUser', 'Pending', 'Locked', 'RepositoryLimit') as $c)
        {
            if(probe_has_column($schema, 'tblCore_Users', $c))
            {
                $cols[] = 'CAST(SUM(CASE WHEN ' . probe_qi($c) . ' = 1 THEN 1 ELSE 0 END) AS int) AS ' . $c;
            }
        }
        if(probe_has_column($schema, 'tblCore_Users', 'Email'))
        {
            $cols[] = "CAST(SUM(CASE WHEN [Email] IS NULL OR [Email] = '' THEN 1 ELSE 0 END) AS int) AS NoEmail";
        }
        if(probe_has_column($schema, 'tblCore_Users', 'RegisterTime'))
        {
            $cols[] = 'CAST(MIN([RegisterTime]) AS int) AS MinRegisterTime';
            $cols[] = 'CAST(MAX([RegisterTime]) AS int) AS MaxRegisterTime';
        }
        probe_try_query($db, $a, 'users', 'SELECT ' . implode(', ', $cols) . ' FROM [tblCore_Users]');
        if(probe_has_column($schema, 'tblCore_Users', 'PasswordHash'))
        {
            probe_try_query($db, $a, 'password_hash_formats', "SELECT k, CAST(COUNT(*) AS int) AS n FROM (
                SELECT CASE WHEN [PasswordHash] IS NULL THEN 'null' WHEN DATALENGTH([PasswordHash]) = 0 THEN 'empty'
                WHEN LEFT([PasswordHash], 1) = '$' THEN CAST(LEFT([PasswordHash], 3) AS varchar(3)) + ':len' + CAST(LEN([PasswordHash]) AS varchar(10))
                ELSE 'nodollar:len' + CAST(LEN([PasswordHash]) AS varchar(10)) END AS k FROM [tblCore_Users]) x GROUP BY k ORDER BY k");
        }
    }
    if(probe_has_table($schema, 'tblCore_Sessions'))
    {
        probe_try_query($db, $a, 'sessions', "SELECT CAST(COUNT(*) AS int) AS total,
            CAST(SUM(CASE WHEN [Expires] > DATEDIFF(second, '19700101', GETUTCDATE()) THEN 1 ELSE 0 END) AS int) AS unexpired,
            CAST(MAX(DATALENGTH([Hash])) AS int) AS max_hash_len FROM [tblCore_Sessions]");
    }
    if(probe_has_table($schema, 'tblCore_ModificationLog'))
    {
        $since = "DATEDIFF(second, '19700101', GETUTCDATE()) - 31536000";
        $byModule = probe_has_table($schema, 'tblCore_Modules') && probe_has_column($schema, 'tblCore_ModificationLog', 'ModuleID');
        $sql = "SELECT CAST(l.[TableName] AS varchar(64)) AS tbl, CAST(l.[ArchonFunction] AS varchar(64)) AS fn, "
            . ($byModule ? "CAST(m.[Script] AS varchar(100)) AS module, " : "")
            . "CAST(COUNT(*) AS int) AS n, CAST(SUM(CASE WHEN l.[Timestamp] >= $since THEN 1 ELSE 0 END) AS int) AS last_365d,
            CAST(MIN(l.[Timestamp]) AS int) AS first_ts, CAST(MAX(l.[Timestamp]) AS int) AS last_ts
            FROM [tblCore_ModificationLog] l "
            . ($byModule ? "LEFT JOIN [tblCore_Modules] m ON m.[ID] = l.[ModuleID] " : "")
            . "GROUP BY l.[TableName], l.[ArchonFunction]" . ($byModule ? ", m.[Script]" : "") . " ORDER BY 4 DESC";
        probe_try_query($db, $a, 'modification_log_usage', $sql, 2000);
        probe_try_query($db, $a, 'modification_log_users', "SELECT CAST(COUNT(DISTINCT [UserID]) AS int) AS distinct_users,
            CAST(COUNT(DISTINCT CASE WHEN [Timestamp] >= $since THEN [UserID] END) AS int) AS distinct_users_365d FROM [tblCore_ModificationLog]");
    }
    if(probe_has_table($schema, 'tblCollections_Content') && probe_has_column($schema, 'tblCollections_Content', 'ParentID'))
    {
        probe_try_query($db, $a, 'content_depth', "WITH t AS (SELECT [ID], 1 AS d FROM [tblCollections_Content] WHERE [ParentID] = 0
            UNION ALL SELECT c.[ID], t.d + 1 FROM [tblCollections_Content] c JOIN t ON c.[ParentID] = t.[ID])
            SELECT CAST(MAX(d) AS int) AS max_depth, CAST(COUNT(*) AS int) AS reachable FROM t OPTION (MAXRECURSION 100)");
        probe_try_query($db, $a, 'content_orphans', "SELECT CAST(COUNT(*) AS int) AS orphans FROM [tblCollections_Content] c
            WHERE c.[ParentID] <> 0 AND NOT EXISTS (SELECT 1 FROM [tblCollections_Content] p WHERE p.[ID] = c.[ParentID])");
        probe_try_query($db, $a, 'content_per_collection', "SELECT TOP 10 CAST([CollectionID] AS int) AS collection_id, CAST(COUNT(*) AS int) AS items
            FROM [tblCollections_Content] GROUP BY [CollectionID] ORDER BY 2 DESC");
        probe_try_query($db, $a, 'content_children_max', "SELECT TOP 5 CAST([ParentID] AS int) AS parent_id, CAST(COUNT(*) AS int) AS children
            FROM [tblCollections_Content] GROUP BY [ParentID] ORDER BY 2 DESC");
    }
    if(probe_has_table($schema, 'tblCollections_Collections') && probe_has_column($schema, 'tblCollections_Collections', 'RepositoryID'))
    {
        probe_try_query($db, $a, 'collections_per_repository', "SELECT CAST([RepositoryID] AS int) AS repository_id, CAST(COUNT(*) AS int) AS n
            FROM [tblCollections_Collections] GROUP BY [RepositoryID] ORDER BY 1");
    }
    if(probe_has_table($schema, 'tblAccessions_Accessions'))
    {
        $a['accessions_has_repositoryid'] = probe_has_column($schema, 'tblAccessions_Accessions', 'RepositoryID');
    }
    if(probe_has_table($schema, 'tblDigitalLibrary_Files'))
    {
        $cols = array('CAST(COUNT(*) AS int) AS files');
        foreach(array('FileContents', 'FilePreviewLong', 'FilePreviewShort') as $c)
        {
            if(probe_has_column($schema, 'tblDigitalLibrary_Files', $c))
            {
                $q = probe_qi($c);
                $cols[] = "CAST(MAX(DATALENGTH($q)) / 1024 AS int) AS {$c}_max_kb";
                $cols[] = "CAST(SUM(CAST(DATALENGTH($q) AS bigint)) / 1048576 AS int) AS {$c}_total_mb";
                $cols[] = "CAST(SUM(CASE WHEN DATALENGTH($q) > 8388608 THEN 1 ELSE 0 END) AS int) AS {$c}_over_8mb";
            }
        }
        probe_try_query($db, $a, 'digital_files', 'SELECT ' . implode(', ', $cols) . ' FROM [tblDigitalLibrary_Files]');
        if(probe_has_column($schema, 'tblDigitalLibrary_Files', 'FileTypeID'))
        {
            $size = probe_has_column($schema, 'tblDigitalLibrary_Files', 'FileContents') ? ', CAST(MAX(DATALENGTH([FileContents])) / 1024 AS int) AS max_kb' : '';
            probe_try_query($db, $a, 'digital_files_by_type', "SELECT CAST([FileTypeID] AS int) AS file_type_id, CAST(COUNT(*) AS int) AS n$size
                FROM [tblDigitalLibrary_Files] GROUP BY [FileTypeID] ORDER BY 2 DESC");
        }
    }
    return $a;
}

// ---------------------------------------------------------------------------
// Section: profile (aggregate data-shape metrics per column)
// ---------------------------------------------------------------------------

function probe_profile_expressions($col, $type)
{
    $q = probe_qi($col);
    $e = array();
    $char = in_array($type, array('char', 'varchar', 'text'));
    $nchar = in_array($type, array('nchar', 'nvarchar', 'ntext'));
    if($char || $nchar)
    {
        $x = ($type == 'text') ? "CAST($q AS varchar(max))" : (($type == 'ntext') ? "CAST($q AS nvarchar(max))" : $q);
        $b = "$x COLLATE Latin1_General_BIN2";
        $c = $nchar ? 'NCHAR' : 'CHAR';
        $s = $nchar ? "N'" : "'";
        $e['null'] = "SUM(CASE WHEN $q IS NULL THEN 1 ELSE 0 END)";
        $e['empty'] = "SUM(CASE WHEN DATALENGTH($q) = 0 THEN 1 ELSE 0 END)";
        $e['spaces_only'] = "SUM(CASE WHEN DATALENGTH($q) > 0 AND LEN($x) = 0 THEN 1 ELSE 0 END)";
        if(!in_array($type, array('char', 'nchar')))
        {
            $e['trailing_ws'] = "SUM(CASE WHEN DATALENGTH($x) <> DATALENGTH(RTRIM($x)) THEN 1 ELSE 0 END)";
        }
        $e['leading_ws'] = "SUM(CASE WHEN DATALENGTH($x) > 0 AND LEN($x) > 0 AND DATALENGTH($x) <> DATALENGTH(LTRIM($x)) THEN 1 ELSE 0 END)";
        $e['max_bytes'] = "MAX(DATALENGTH($q))";
        $hi = $nchar ? 65535 : 255;
        $e['non_ascii'] = "SUM(CASE WHEN $b LIKE {$s}%[' + $c(128) + {$s}-' + $c($hi) + {$s}]%' THEN 1 ELSE 0 END)";
        $e['utf8_bytes_or_mojibake'] = "SUM(CASE WHEN $b LIKE {$s}%[' + $c(194) + {$s}-' + $c(244) + {$s}][' + $c(128) + {$s}-' + $c(191) + {$s}]%' THEN 1 ELSE 0 END)";
        $e['letter_qmark_letter'] = "SUM(CASE WHEN $b LIKE {$s}%[A-Za-z][?][A-Za-z]%' THEN 1 ELSE 0 END)";
        $e['html_entity'] = "SUM(CASE WHEN $x LIKE {$s}%&[#a-zA-Z]%;%' THEN 1 ELSE 0 END)";
        $e['html_tag'] = "SUM(CASE WHEN $x LIKE {$s}%<[a-zA-Z/!]%>%' THEN 1 ELSE 0 END)";
        $e['bbcode'] = "SUM(CASE WHEN $x LIKE {$s}%[[][/a-zA-Z]%]%' THEN 1 ELSE 0 END)";
        $e['has_cr'] = "SUM(CASE WHEN CHARINDEX($c(13), $b) > 0 THEN 1 ELSE 0 END)";
        $e['has_lf'] = "SUM(CASE WHEN CHARINDEX($c(10), $b) > 0 THEN 1 ELSE 0 END)";
        $e['has_tab'] = "SUM(CASE WHEN CHARINDEX($c(9), $b) > 0 THEN 1 ELSE 0 END)";
        $e['control_chars'] = "SUM(CASE WHEN $b LIKE {$s}%[' + $c(1) + {$s}-' + $c(8) + $c(11) + $c(12) + $c(14) + {$s}-' + $c(31) + {$s}]%' THEN 1 ELSE 0 END)";
        $e['nul_char'] = "SUM(CASE WHEN CHARINDEX($c(0), $b) > 0 THEN 1 ELSE 0 END)";
        $e['nbsp'] = "SUM(CASE WHEN CHARINDEX($c(160), $b) > 0 THEN 1 ELSE 0 END)";
        if($nchar)
        {
            $e['non_latin1'] = "SUM(CASE WHEN $b LIKE N'%[' + NCHAR(256) + N'-' + NCHAR(65535) + N']%' THEN 1 ELSE 0 END)";
            $e['combining_marks'] = "SUM(CASE WHEN $b LIKE N'%[' + NCHAR(768) + N'-' + NCHAR(879) + N']%' THEN 1 ELSE 0 END)";
            $e['zero_width_or_bom'] = "SUM(CASE WHEN $b LIKE N'%[' + NCHAR(8203) + N'-' + NCHAR(8207) + NCHAR(65279) + N']%' THEN 1 ELSE 0 END)";
            $e['cp1252_mojibake'] = "SUM(CASE WHEN CHARINDEX(NCHAR(226) + NCHAR(8364), $b) > 0 THEN 1 ELSE 0 END)";
        }
        foreach($e as $k => $v)
        {
            $e[$k] = "CAST($v AS int)";
        }
        return $e;
    }
    if(in_array($type, array('int', 'smallint', 'tinyint', 'bigint', 'bit', 'decimal', 'numeric', 'float', 'real', 'money', 'smallmoney')))
    {
        $v = ($type == 'bit') ? "CAST($q AS int)" : $q;
        return array(
            'null' => "CAST(SUM(CASE WHEN $q IS NULL THEN 1 ELSE 0 END) AS int)",
            'zero' => "CAST(SUM(CASE WHEN $q = 0 THEN 1 ELSE 0 END) AS int)",
            'negative' => "CAST(SUM(CASE WHEN $q < 0 THEN 1 ELSE 0 END) AS int)",
            'min' => "CAST(MIN($v) AS varchar(40))",
            'max' => "CAST(MAX($v) AS varchar(40))",
            'distinct' => "CAST(COUNT(DISTINCT $v) AS int)");
    }
    if(in_array($type, array('datetime', 'smalldatetime', 'date', 'datetime2', 'datetimeoffset', 'time')))
    {
        return array(
            'null' => "CAST(SUM(CASE WHEN $q IS NULL THEN 1 ELSE 0 END) AS int)",
            'min' => "CONVERT(varchar(40), MIN($q), 126)",
            'max' => "CONVERT(varchar(40), MAX($q), 126)");
    }
    if(in_array($type, array('binary', 'varbinary', 'image')))
    {
        return array(
            'null' => "CAST(SUM(CASE WHEN $q IS NULL THEN 1 ELSE 0 END) AS int)",
            'empty' => "CAST(SUM(CASE WHEN DATALENGTH($q) = 0 THEN 1 ELSE 0 END) AS int)",
            'max_kb' => "CAST(MAX(DATALENGTH($q)) / 1024 AS int)",
            'total_kb' => "CAST(SUM(CAST(DATALENGTH($q) AS bigint)) / 1024 AS int)");
    }
    return array('null' => "CAST(SUM(CASE WHEN $q IS NULL THEN 1 ELSE 0 END) AS int)");
}

function probe_section_profile()
{
    $db = probe_db_connect();
    if(!isset($GLOBALS['probe_columns']))
    {
        throw new Exception('profile requires the database section');
    }
    $maxRows = (int)probe_option('max-profile-rows', 2000000);
    $tables = array();
    foreach($GLOBALS['probe_columns'] as $col)
    {
        $tables[$col['s']][$col['t']][] = $col;
    }
    $r = array();
    foreach($tables as $schemaName => $ts)
    {
        foreach($ts as $table => $cols)
        {
            $key = ($schemaName == 'dbo' ? '' : $schemaName . '.') . $table;
            $rows = isset($GLOBALS['probe_row_counts'][$key]) ? $GLOBALS['probe_row_counts'][$key] : null;
            $entry = array('rows' => $rows);
            if($rows !== null && $rows > $maxRows)
            {
                $entry['skipped'] = 'more than --max-profile-rows';
                $r[$key] = $entry;
                continue;
            }
            $from = probe_qi($schemaName) . '.' . probe_qi($table);
            $start = microtime(true);
            foreach(array_chunk($cols, 10) as $chunk)
            {
                $select = array('CAST(COUNT(*) AS int) AS n');
                $map = array();
                $i = 0;
                foreach($chunk as $col)
                {
                    foreach(probe_profile_expressions($col['c'], strtolower($col['dt'])) as $metric => $expr)
                    {
                        $alias = 'a' . $i++;
                        $map[$alias] = array($col['c'], $metric);
                        $select[] = $expr . ' AS ' . $alias;
                    }
                }
                try
                {
                    $res = $db->query('SELECT ' . implode(', ', $select) . ' FROM ' . $from, 1);
                    $row = $res ? $res[0] : array();
                    foreach($map as $alias => $cm)
                    {
                        $v = isset($row[$alias]) ? $row[$alias] : null;
                        $entry['columns'][$cm[0]][$cm[1]] = (is_numeric($v) && strpos((string)$v, '.') === false && strpos((string)$v, 'E') === false && strpos((string)$v, 'e') === false) ? (int)$v : $v;
                    }
                }
                catch(Exception $e)
                {
                    foreach($chunk as $col)
                    {
                        $entry['columns'][$col['c']] = array('error' => probe_utf8($e->getMessage()));
                    }
                }
            }
            $entry['seconds'] = round(microtime(true) - $start, 2);
            $r[$key] = $entry;
        }
    }
    return $r;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

function probe_json($data)
{
    $flags = 0;
    foreach(array('JSON_PRETTY_PRINT', 'JSON_UNESCAPED_SLASHES', 'JSON_UNESCAPED_UNICODE') as $f)
    {
        if(defined($f))
        {
            $flags |= constant($f);
        }
    }
    $json = json_encode($data, $flags);
    if($json === false)
    {
        $json = json_encode(array('probe_error' => 'json_encode failed: ' . (function_exists('json_last_error_msg') ? json_last_error_msg() : json_last_error())));
    }
    return $json;
}

function probe_main()
{
    $isCli = (PHP_SAPI == 'cli');
    if($isCli)
    {
        $GLOBALS['probe_options'] = probe_parse_cli_options($GLOBALS['argv']);
    }
    else
    {
        $token = getenv('ARCHON_PROBE_WEB_TOKEN');
        if(!$token && isset($_SERVER['ARCHON_PROBE_WEB_TOKEN']))
        {
            $token = $_SERVER['ARCHON_PROBE_WEB_TOKEN'];
        }
        if(!$token || strlen($token) < 24 || !isset($_GET['token']) || !probe_constant_time_equals($token, $_GET['token']))
        {
            header('HTTP/1.0 403 Forbidden');
            header('Content-Type: text/plain');
            echo "Forbidden\n";
            return;
        }
        $opts = array();
        foreach(array('sections', 'show-hosts', 'max-profile-rows', 'query-timeout', 'no-lookup-dumps', 'max-files', 'db-driver') as $k)
        {
            if(isset($_GET[$k]))
            {
                $opts[$k] = $_GET[$k];
            }
        }
        $GLOBALS['probe_options'] = $opts;
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    if(probe_option('help'))
    {
        echo probe_usage(), "\n";
        return;
    }
    @set_time_limit(0);

    $all = array('php', 'freetds', 'archon', 'files', 'apache', 'database', 'profile');
    $sections = probe_option('sections') ? array_map('trim', explode(',', strtolower(probe_option('sections')))) : $all;
    $unknown = array_diff($sections, $all);
    if($unknown)
    {
        fwrite(STDERR, 'Unknown section(s): ' . implode(', ', $unknown) . "\n");
    }
    $report = array('probe' => array(
        'version' => ARCHON_PROBE_VERSION,
        'started' => date('c'),
        'php_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'sections' => array_values(array_intersect($all, $sections)),
        'options' => probe_redact_value('', array_merge($GLOBALS['probe_options'], isset($GLOBALS['probe_options']['db-login']) ? array('db-login' => '<set>') : array())),
    ));
    $dir = probe_find_archon_dir();
    // The archon section loads config.inc.php, which the database sections need
    if(array_intersect(array('database', 'profile'), $sections) && !in_array('archon', $sections))
    {
        $sections[] = 'archon';
    }
    $timings = array();
    foreach($all as $section)
    {
        if(!in_array($section, $sections))
        {
            continue;
        }
        $start = microtime(true);
        if($isCli)
        {
            fwrite(STDERR, "[archon-probe] section $section ...\n");
        }
        try
        {
            switch($section)
            {
                case 'php':
                    $report['php'] = probe_section_php();
                    break;
                case 'freetds':
                    $report['freetds'] = probe_section_freetds();
                    break;
                case 'archon':
                    $report['archon'] = probe_section_archon($dir);
                    break;
                case 'files':
                    $report['files'] = probe_section_files($dir);
                    break;
                case 'apache':
                    $report['apache'] = probe_section_apache($dir);
                    break;
                case 'database':
                    $report['database'] = probe_section_database();
                    break;
                case 'profile':
                    $report['profile'] = probe_section_profile();
                    break;
            }
        }
        catch(Exception $e)
        {
            $report[$section] = array('error' => probe_utf8(get_class($e) . ': ' . $e->getMessage()));
            $GLOBALS['probe_errors'][] = $section . ': ' . probe_utf8($e->getMessage());
        }
        catch(Throwable $e)
        {
            // PHP 7+ only (TypeError, Error, ...); harmless on PHP 5
            $report[$section] = array('error' => probe_utf8(get_class($e) . ': ' . $e->getMessage()));
            $GLOBALS['probe_errors'][] = $section . ': ' . probe_utf8($e->getMessage());
        }
        $timings[$section] = round(microtime(true) - $start, 2);
    }
    if(isset($GLOBALS['probe_db']))
    {
        $report['probe']['queries'] = count($GLOBALS['probe_db']->queryLog);
        $report['probe']['query_seconds'] = round(array_sum($GLOBALS['probe_db']->queryLog), 2);
        $GLOBALS['probe_db']->close();
    }
    $report['probe']['finished'] = date('c');
    $report['probe']['section_seconds'] = $timings;
    $report['probe']['errors'] = $GLOBALS['probe_errors'];
    $report['probe']['warnings'] = $GLOBALS['probe_warnings'];
    echo probe_json(probe_clean($report)), "\n";
}

// Allows the helper functions to be unit tested without running the probe
if(!defined('ARCHON_PROBE_LIBRARY_ONLY'))
{
    probe_main();
}
