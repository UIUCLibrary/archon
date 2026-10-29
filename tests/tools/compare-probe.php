<?php
/**
 * Reads JSON reports produced by tests/production-probe/archon-probe.php.
 *
 *   php tests/tools/compare-probe.php summary probe.json
 *       Key findings: runtime, FreeTDS, database options, encoding indicators,
 *       NULL-vs-empty usage, most used admin functions, schema differences
 *       against the stock install scripts.
 *   php tests/tools/compare-probe.php schema baseline.json probe.json
 *       Tables / columns / column definitions that differ between two probes
 *       (e.g. the test environment baseline vs. production).
 *   php tests/tools/compare-probe.php manifest probe.json [--rev=35bbafc]
 *       Compares the production file manifest with a git revision and lists
 *       files that were modified, added or removed on the server.
 */

function cp_load($file)
{
    $d = json_decode((string)file_get_contents($file), true);
    if(!is_array($d))
    {
        fwrite(STDERR, "Cannot parse $file\n");
        exit(2);
    }
    return $d;
}

function cp_get($a, $path, $default = null)
{
    foreach(explode('.', $path) as $k)
    {
        if(!is_array($a) || !array_key_exists($k, $a))
        {
            return $default;
        }
        $a = $a[$k];
    }
    return $a;
}

function cp_table_name($key)
{
    $pos = strrpos($key, '.');
    return $pos === false ? $key : substr($key, $pos + 1);
}

/**
 * Columns by table. The probe keys tables outside dbo as "schema.table"; Archon finds them
 * through its database user's default schema, so they are compared by table name unless
 * two schemas hold a table of the same name.
 */
function cp_columns($d)
{
    $columns = (array)cp_get($d, 'database.columns', array());
    $names = array_count_values(array_map('cp_table_name', array_map('strval', array_keys($columns))));
    $out = array();
    foreach($columns as $key => $cols)
    {
        $name = cp_table_name((string)$key);
        $out[$names[$name] > 1 ? $key : $name] = $cols;
    }
    return $out;
}

function cp_table_schemas($d)
{
    $schemas = array();
    foreach((array)cp_get($d, 'database.tables', array()) as $row)
    {
        if(is_array($row) && isset($row['s']))
        {
            $schemas[$row['s']] = (isset($schemas[$row['s']]) ? $schemas[$row['s']] : 0) + 1;
        }
    }
    return $schemas;
}

function cp_summary($d)
{
    $out = array();
    $out[] = '# Probe summary (' . cp_get($d, 'probe.started') . ', probe ' . cp_get($d, 'probe.version') . ')';
    $out[] = 'PHP ' . cp_get($d, 'php.version') . ' (' . cp_get($d, 'php.sapi') . ') on ' . cp_get($d, 'php.uname');
    $ext = cp_get($d, 'php.extensions', array());
    $out[] = 'DB extensions: ' . implode(', ', array_intersect(array_keys($ext), array('mssql', 'sqlsrv', 'pdo_sqlsrv', 'odbc', 'pdo_dblib', 'mysql', 'mysqli')));
    foreach(array('default_charset', 'mbstring.func_overload', 'mbstring.internal_encoding', 'date.timezone', 'short_open_tag', 'magic_quotes_gpc', 'memory_limit', 'post_max_size', 'upload_max_filesize', 'max_input_vars', 'mssql.charset', 'mssql.textsize', 'mssql.textlimit', 'mssql.datetimeconvert') as $k)
    {
        $out[] = sprintf('  %-28s %s', $k, json_encode(cp_get($d, 'php.ini', array())[$k] ?? null));
    }
    $mdb2 = cp_get($d, 'php.pear_registry', array());
    foreach($mdb2 as $dir => $pkgs)
    {
        foreach($pkgs as $p => $v)
        {
            if(stripos($p, 'mdb2') !== false || $p == 'pear')
            {
                $out[] = "  PEAR $p $v ($dir)";
            }
        }
    }
    $out[] = 'crypt md5 known answer: ' . cp_get($d, 'php.crypt.md5crypt_known_answer') . ' (PHP 8.2 test env: $1$probe123$g.GA71JPiPiUMYq4qeBYe0)';
    $out[] = 'TDSVER env: ' . json_encode(cp_get($d, 'freetds.env.TDSVER')) . '  db VersionTDS: ' . json_encode(cp_get($d, 'archon.db_config.VersionTDS'));
    foreach((array)cp_get($d, 'freetds.conf_files', array()) as $f => $sections)
    {
        foreach((array)$sections as $name => $kv)
        {
            if(is_array($kv) && $kv)
            {
                $out[] = "  $f [$name] " . json_encode($kv);
            }
        }
    }
    $out[] = 'Archon packages: ' . json_encode(cp_get($d, 'archon.packages'));
    $out[] = 'Archon db config: ' . json_encode(cp_get($d, 'archon.db_config'));
    $out[] = 'cryptor.inc.php present: ' . json_encode(cp_get($d, 'archon.root_files', array())['cryptor.inc.php'] ?? null);
    $db = cp_get($d, 'database', array());
    if($db)
    {
        $out[] = 'SQL Server: ' . json_encode(cp_get($db, 'server.0.ProductVersion')) . ' ' . json_encode(cp_get($db, 'server.0.Edition')) . ' collation ' . json_encode(cp_get($db, 'server.0.ServerCollation'));
        $out[] = 'Database: ' . json_encode(cp_get($db, 'database.0'));
        $out[] = 'Application session (DBCC USEROPTIONS): ' . json_encode(cp_get($db, 'session_as_application_sees_it.useroptions'));
        $out[] = 'Connection: ' . json_encode(cp_get($db, 'connection_dmv'));
        $out[] = 'Principal: ' . json_encode(cp_get($db, 'principal'));
        $out[] = 'Tables by schema: ' . json_encode(cp_table_schemas($d)) . ' (Archon finds tables outside dbo only through its database user\'s default schema)';
        $out[] = 'Non-table objects: ' . count((array)cp_get($db, 'other_objects', array())) . ', foreign keys: ' . count((array)cp_get($db, 'foreign_keys', array()));
        $app = cp_get($db, 'application', array());
        foreach(array('users', 'password_hash_formats', 'sessions', 'content_depth', 'content_orphans', 'digital_files', 'accessions_has_repositoryid', 'collections_per_repository') as $k)
        {
            $out[] = sprintf('  %-28s %s', $k, json_encode(isset($app[$k]) ? $app[$k] : null));
        }
        $out[] = 'ResearchAppointmentFields table present: ' . json_encode(isset(cp_columns($d)['tblCollections_ResearchAppointmentFields']));
        $usage = (array)cp_get($app, 'modification_log_usage', array());
        $out[] = 'Most used admin functions (ModificationLog, all time / last 365 days):';
        foreach(array_slice($usage, 0, 40) as $u)
        {
            if(is_array($u) && isset($u['n']))
            {
                $out[] = sprintf('  %6d %6d  %-40s %-24s %s', $u['n'], $u['last_365d'], $u['tbl'], $u['fn'], isset($u['module']) ? $u['module'] : '');
            }
        }
        $rows = cp_get($db, 'row_counts', array());
        arsort($rows);
        $out[] = 'Largest tables: ' . json_encode(array_slice($rows, 0, 15, true));
    }
    $profile = cp_get($d, 'profile', array());
    if($profile)
    {
        $totals = array();
        $nullEmpty = array();
        foreach($profile as $table => $t)
        {
            foreach((array)cp_get($t, 'columns', array()) as $col => $m)
            {
                if(!is_array($m) || !isset($m['max_bytes']))
                {
                    continue;
                }
                foreach(array('non_ascii', 'utf8_bytes_or_mojibake', 'letter_qmark_letter', 'html_entity', 'html_tag', 'bbcode', 'has_cr', 'control_chars', 'nul_char', 'nbsp', 'trailing_ws', 'non_latin1', 'cp1252_mojibake') as $k)
                {
                    if(!empty($m[$k]))
                    {
                        $totals[$k][] = "$table.$col=" . $m[$k];
                    }
                }
                if(!empty($m['null']) && !empty($m['empty']))
                {
                    $nullEmpty[] = "$table.$col (null={$m['null']}, empty={$m['empty']})";
                }
            }
        }
        $out[] = 'Encoding / content indicators (rows per column):';
        foreach($totals as $k => $cols)
        {
            $out[] = sprintf('  %-24s %d columns: %s', $k, count($cols), implode(', ', array_slice($cols, 0, 25)) . (count($cols) > 25 ? ', ...' : ''));
        }
        $out[] = 'Columns holding both NULL and empty strings: ' . count($nullEmpty);
        foreach(array_slice($nullEmpty, 0, 60) as $c)
        {
            $out[] = '  ' . $c;
        }
    }
    $out[] = 'Probe errors: ' . json_encode(cp_get($d, 'probe.errors')) . '  warnings: ' . count((array)cp_get($d, 'probe.warnings', array()));
    return implode("\n", $out) . "\n";
}

function cp_schema($a, $b)
{
    $ca = cp_columns($a);
    $cb = cp_columns($b);
    $out = array();
    foreach(array_diff(array_keys($ca), array_keys($cb)) as $t)
    {
        $out[] = "- table only in first: $t";
    }
    foreach(array_diff(array_keys($cb), array_keys($ca)) as $t)
    {
        $out[] = "+ table only in second: $t";
    }
    foreach(array_intersect(array_keys($ca), array_keys($cb)) as $t)
    {
        foreach($ca[$t] as $col => $def)
        {
            if(!isset($cb[$t][$col]))
            {
                $out[] = "- $t.$col ($def)";
            }
            elseif($cb[$t][$col] !== $def)
            {
                $out[] = "~ $t.$col: $def  =>  {$cb[$t][$col]}";
            }
        }
        foreach($cb[$t] as $col => $def)
        {
            if(!isset($ca[$t][$col]))
            {
                $out[] = "+ $t.$col ($def)";
            }
        }
    }
    foreach(array('database.0', 'server.0') as $k)
    {
        foreach((array)cp_get($a, "database.$k", array()) as $opt => $v)
        {
            $w = cp_get($b, "database.$k.$opt");
            if($w !== $v && !in_array($opt, array('name', 'create_date', 'Spid', 'Version')))
            {
                $out[] = "~ $k.$opt: " . json_encode($v) . ' => ' . json_encode($w);
            }
        }
    }
    return $out ? implode("\n", $out) . "\n" : "No schema differences.\n";
}

function cp_manifest($d, $rev)
{
    $manifest = cp_get($d, 'files.manifest', array());
    if(!$manifest)
    {
        return "No file manifest in this probe.\n";
    }
    $root = dirname(__DIR__, 2);
    $ls = shell_exec('git -C ' . escapeshellarg($root) . ' ls-tree -r --full-tree ' . escapeshellarg($rev));
    $blobs = array();
    foreach(explode("\n", trim((string)$ls)) as $line)
    {
        if(preg_match('/^\d+ blob ([0-9a-f]+)\t(.+)$/', $line, $m))
        {
            $blobs[$m[2]] = $m[1];
        }
    }
    $modified = $prodOnly = array();
    foreach($manifest as $path => $info)
    {
        list($md5) = explode(' ', $info);
        if(!isset($blobs[$path]))
        {
            $prodOnly[] = $path;
            continue;
        }
        $content = shell_exec('git -C ' . escapeshellarg($root) . ' cat-file blob ' . $blobs[$path]);
        if(md5((string)$content) !== $md5)
        {
            // tolerate line-ending only differences
            if(md5(str_replace("\r\n", "\n", (string)$content)) === $md5 || md5(str_replace("\n", "\r\n", str_replace("\r\n", "\n", (string)$content))) === $md5)
            {
                $modified[] = "$path (line endings only)";
            }
            else
            {
                $modified[] = $path;
            }
        }
    }
    $gitOnly = array_diff(array_keys($blobs), array_keys($manifest), array('config.inc.php'));
    $out = array("Compared " . count($manifest) . " production files with git $rev (" . count($blobs) . " files)");
    $out[] = "\nModified on the server (" . count($modified) . "):";
    foreach($modified as $p)
    {
        $out[] = "  ~ $p";
    }
    $out[] = "\nOnly on the server (" . count($prodOnly) . "):";
    foreach($prodOnly as $p)
    {
        $out[] = "  + $p";
    }
    $out[] = "\nIn git but not on the server (" . count($gitOnly) . "):";
    foreach($gitOnly as $p)
    {
        $out[] = "  - $p";
    }
    return implode("\n", $out) . "\n";
}

$args = array_slice($argv, 1);
$opts = array();
$pos = array();
foreach($args as $a)
{
    if(preg_match('/^--([^=]+)=(.*)$/', $a, $m))
    {
        $opts[$m[1]] = $m[2];
    }
    else
    {
        $pos[] = $a;
    }
}
$mode = isset($pos[0]) ? $pos[0] : '';
if($mode == 'summary' && isset($pos[1]))
{
    echo cp_summary(cp_load($pos[1]));
}
elseif($mode == 'schema' && isset($pos[2]))
{
    echo cp_schema(cp_load($pos[1]), cp_load($pos[2]));
}
elseif($mode == 'manifest' && isset($pos[1]))
{
    echo cp_manifest(cp_load($pos[1]), isset($opts['rev']) ? $opts['rev'] : '35bbafc');
}
else
{
    fwrite(STDERR, "usage: compare-probe.php summary probe.json | schema a.json b.json | manifest probe.json [--rev=35bbafc]\n");
    exit(64);
}
