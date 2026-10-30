<?php
/**
 * Test Web Installer & Routing States
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/InstallerController.php';

$passCount = 0;
$failCount = 0;

function it(string $testName, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$testName}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$testName} - {$detail}\n";
    }
}

echo "========================================================\n";
echo "TESTING WEB INSTALLER & ROUTING STATES\n";
echo "========================================================\n\n";

// 1. Test System Requirements
echo "1. SYSTEM REQUIREMENTS CHECKS:\n";
it("PHP 8.2+ verified", version_compare(PHP_VERSION, '8.2.0', '>='));
it("PDO MySQL extension loaded", extension_loaded('pdo_mysql'));
it("Mbstring extension loaded", extension_loaded('mbstring'));
it("cURL extension loaded", extension_loaded('curl'));
it("GD extension loaded", extension_loaded('gd'));
it("Storage dir writable", is_writable(__DIR__ . '/../storage'));
it("Config dir writable", is_writable(__DIR__ . '/../app/config'));

// 2. Test Database Connection Tester
echo "\n2. DATABASE CONNECTION VALIDATOR:\n";
$badTest = Database::testConnection('127.0.0.1', '3306', 'na_coconuts', 'invalid_user_xyz', 'bad_pass');
it("Invalid DB credentials rejected safely", $badTest['success'] === false);
it("Password not leaked in error message", !str_contains($badTest['error'] ?? '', 'bad_pass'));

$goodTest = Database::testConnection(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
it("Valid DB credentials connect successfully", $goodTest['success'] === true);
it("Target database exists", $goodTest['db_exists'] === true);

// 3. Test Installation Lock Mechanism
echo "\n3. INSTALLATION LOCK INTEGRITY:\n";
$lockFile = INSTALL_LOCK_FILE;
@unlink($lockFile);
it("When lock file removed, is_installed() returns false", is_installed() === false);

// Create lock file
$testLockContent = json_encode(['installed_at' => date('Y-m-d H:i:s'), 'version' => '1.0.0']);
file_put_contents($lockFile, $testLockContent);
it("When lock file created, is_installed() returns true", is_installed() === true);

// 4. Test HTTP Endpoints via cURL
echo "\n4. LIVE HTTP ROUTING TESTS:\n";
$ch = curl_init('http://127.0.0.1:3000/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$homeHtml = curl_exec($ch);
$homeStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

it("Homepage / returns HTTP 200", $homeStatus === 200);
it("Homepage does not contain redirect loop string", !str_contains($homeHtml, 'Redirecting to'));
it("Homepage contains brand heading", str_contains($homeHtml, 'Fresh Coconut Water'));

// Test missing route returns 404
$ch404 = curl_init('http://127.0.0.1:3000/non-existent-page-xyz');
curl_setopt($ch404, CURLOPT_RETURNTRANSFER, true);
$html404 = curl_exec($ch404);
$status404 = curl_getinfo($ch404, CURLINFO_HTTP_CODE);
curl_close($ch404);

it("Non-existent route returns HTTP 404", $status404 === 404);
it("404 page contains proper error message", str_contains($html404, '404'));

// Test Installer route when locked
$chInst = curl_init('http://127.0.0.1:3000/install?status=locked');
curl_setopt($chInst, CURLOPT_RETURNTRANSFER, true);
$instHtml = curl_exec($chInst);
$instStatus = curl_getinfo($chInst, CURLINFO_HTTP_CODE);
curl_close($chInst);

it("Installer route renders locked screen when locked", str_contains($instHtml, 'Installer is Locked'));

echo "\n========================================================\n";
echo "INSTALLER TESTS SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) exit(1);
