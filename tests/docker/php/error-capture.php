<?php
/**
 * Test-only diagnostics recorder (loaded through auto_prepend_file in the test images).
 *
 * Archon lowers error_reporting() to E_ERROR | E_PARSE at runtime, which hides the
 * warnings and deprecations we need to see while validating a PHP upgrade. This
 * handler records every diagnostic (deduplicated per request) as JSON lines and then
 * lets PHP's normal error handling continue unchanged.
 *
 * Must stay PHP 5.6 compatible because the legacy baseline image loads it too.
 */
if (!defined('ARCHON_TEST_ERROR_CAPTURE')) {
    define('ARCHON_TEST_ERROR_CAPTURE', 1);

    $GLOBALS['__archon_test_diagnostics'] = array();

    function __archon_test_error_type($type)
    {
        $names = array(
            E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        );
        if (defined('E_STRICT') && $type === 2048) {
            return 'E_STRICT';
        }
        return isset($names[$type]) ? $names[$type] : (string)$type;
    }

    function __archon_test_relative_path($file)
    {
        $root = getenv('ARCHON_TEST_DOCROOT_PATTERN');
        $root = $root ? $root : '#^/var/www/html/[^/]+/#';
        return preg_replace($root, '', (string)$file);
    }

    function __archon_test_record($type, $message, $file, $line, $trace)
    {
        $key = $type . '|' . $file . '|' . $line . '|' . $message;
        if (isset($GLOBALS['__archon_test_diagnostics'][$key])) {
            $GLOBALS['__archon_test_diagnostics'][$key]['count']++;
            return;
        }
        $frames = array();
        foreach ((array)$trace as $frame) {
            if (isset($frame['file'])) {
                $frames[] = __archon_test_relative_path($frame['file']) . ':' . (isset($frame['line']) ? $frame['line'] : '?')
                    . (isset($frame['function']) ? ' ' . (isset($frame['class']) ? $frame['class'] . '::' : '') . $frame['function'] : '');
            }
        }
        $GLOBALS['__archon_test_diagnostics'][$key] = array(
            'type' => __archon_test_error_type($type),
            'message' => (string)$message,
            'file' => __archon_test_relative_path($file),
            'line' => (int)$line,
            'count' => 1,
            'trace' => array_slice($frames, 0, 8),
        );
    }

    function __archon_test_flush()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            __archon_test_record($error['type'], $error['message'], $error['file'], $error['line'], array());
        }
        if (empty($GLOBALS['__archon_test_diagnostics'])) {
            return;
        }
        $context = PHP_SAPI === 'cli'
            ? 'cli:' . implode(' ', isset($GLOBALS['argv']) ? (array)$GLOBALS['argv'] : array())
            : (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') . ' ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        $log = getenv('ARCHON_TEST_DIAGNOSTICS_LOG');
        $log = $log ? $log : '/var/log/archon/diagnostics.jsonl';
        $lines = '';
        foreach ($GLOBALS['__archon_test_diagnostics'] as $entry) {
            $entry['request'] = $context;
            $entry['php'] = PHP_VERSION;
            $lines .= json_encode($entry) . "\n";
        }
        @file_put_contents($log, $lines, FILE_APPEND | LOCK_EX);
        $GLOBALS['__archon_test_diagnostics'] = array();
    }

    set_error_handler(function ($type, $message, $file = '', $line = 0) {
        __archon_test_record($type, $message, $file, $line, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10));
        return false;
    });
    register_shutdown_function('__archon_test_flush');
}
