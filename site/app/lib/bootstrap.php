<?php
declare(strict_types=1);

// Buffer everything: a route that fails half-way is replaced by a clean 500 (never a cached 200 fragment).
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
mb_internal_encoding('UTF-8');
date_default_timezone_set('America/New_York');

$CONFIG = require __DIR__ . '/../config.php';
if (is_file(__DIR__ . '/../config.local.php')) {
    $CONFIG = array_replace_recursive($CONFIG, require __DIR__ . '/../config.local.php');
}
$CATEGORIES = require __DIR__ . '/../categories.php';
$SPONSORS = require __DIR__ . '/../sponsors.php';

require __DIR__ . '/states.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/render.php';

set_exception_handler(function (Throwable $e): void {
    error_log('TDUSA: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>Temporary error</title></head>'
        . '<body style="font-family:sans-serif;background:#0f0f1a;color:#ddd;padding:40px"><h1>Temporary error</h1><p>Please try again in a moment.</p></body></html>';
    exit;
});
