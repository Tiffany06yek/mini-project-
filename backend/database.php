<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require __DIR__ . '/config.example.php';

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

try {
    $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    mysqli_set_charset($conn, 'utf8mb4');

    $sql = "SELECT id, name, school_email FROM users";
    $result = $conn->query($sql);

} catch (mysqli_sql_exception $e) {
    // Let caller see a meaningful error (you can customize this)
    throw $e;
}

