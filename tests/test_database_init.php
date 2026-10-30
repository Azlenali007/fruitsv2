<?php
/**
 * Test Custom Database Initialization Without Hardcoded na_coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/InstallerController.php';

echo "========================================================\n";
echo "TESTING CUSTOM DATABASE INITIALIZATION ISOLATION\n";
echo "========================================================\n\n";

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $msg = '') {
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$name}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$name} ({$msg})\n";
    }
}

// 1. Verify schema.sql contains no hardcoded CREATE DATABASE or USE
$schemaFile = __DIR__ . '/../database/schema.sql';
$rawSql = file_get_contents($schemaFile);
check("schema.sql has no 'USE na_coconuts'", !str_contains($rawSql, 'USE `na_coconuts`') && !str_contains($rawSql, 'USE na_coconuts'));
check("schema.sql has no 'CREATE DATABASE na_coconuts'", !str_contains($rawSql, 'CREATE DATABASE') && !str_contains($rawSql, 'CREATE SCHEMA'));

// 2. Test splitSqlStatements
$reflection = new ReflectionClass('InstallerController');
$method = $reflection->getMethod('splitSqlStatements');
$method->setAccessible(true);
$statements = $method->invoke(null, $rawSql);

check("SQL split produced discrete statements", count($statements) >= 8);
$hasForbidden = false;
foreach ($statements as $st) {
    if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $st)) {
        $hasForbidden = true;
    }
}
check("No statement attempts CREATE DATABASE or USE", !$hasForbidden);

// 3. Create a test database mimicking a cPanel database name (e.g. test_cpanel_db)
$pdoRoot = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$customDb = 'test_cpanel_db_' . time();
$pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$customDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Connect specifically to $customDb
$pdoCustom = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname={$customDb};charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Execute statements in $customDb
$execSuccess = true;
try {
    foreach ($statements as $st) {
        $st = trim($st);
        if (empty($st)) continue;
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $st)) continue;
        $pdoCustom->exec($st);
    }
} catch (Exception $e) {
    $execSuccess = false;
    echo "  Execution error: " . $e->getMessage() . "\n";
}

check("Schema executed cleanly in custom database without na_coconuts", $execSuccess);

// Verify tables were created in $customDb
$stmt = $pdoCustom->query("SHOW TABLES");
$createdTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
check("Core tables created in custom database", in_array('products', $createdTables) && in_array('admins', $createdTables) && in_array('orders', $createdTables));

// Verify active database on connection is still $customDb
$currentDb = $pdoCustom->query("SELECT DATABASE()")->fetchColumn();
check("Active database remains custom database", $currentDb === $customDb);

// Clean up temporary test database
$pdoRoot->exec("DROP DATABASE IF EXISTS `{$customDb}`");

echo "\n========================================================\n";
echo "SUMMARY: {$pass} PASSED, {$fail} FAILED\n";
echo "========================================================\n";

if ($fail > 0) exit(1);
