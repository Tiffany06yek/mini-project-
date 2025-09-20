<?php
// database.php — shared MySQL connection (mysqli)

// Throw mysqli warnings as exceptions (easier to debug)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1) Load default config committed in repo
require __DIR__ . '/config.example.php';

// 2) If a local override exists, load it (do NOT commit this file)
$local = __DIR__ . '/config.local.php';
if (file_exists($local)) {
    require $local;
}

/**
 * Expected variables after config load:
 * $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME
 */

// 3) Connect once; expose $conn for all scripts that `require` this file
try {
    $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Let caller see a meaningful error (you can customize this)
    throw $e;
}

