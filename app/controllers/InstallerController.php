<?php
/**
 * In-Browser Web Installer Controller
 * N.A Fresh Fruits & Coconuts
 */

class InstallerController {
    
    public static function handle(): void {
        // If application is already installed, lock the installer completely
        if (is_installed()) {
            self::renderLockedView();
            return;
        }

        $step = max(1, min(6, (int)($_GET['step'] ?? 1)));
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($step) {
            case 1:
                self::step1Requirements();
                break;
            case 2:
                self::step2Database($method);
                break;
            case 3:
                self::step3Initialize($method);
                break;
            case 4:
                self::step4Admin($method);
                break;
            case 5:
                self::step5Business($method);
                break;
            case 6:
                self::step6Complete();
                break;
            default:
                header('Location: /install?step=1');
                exit;
        }
    }

    private static function renderLockedView(): void {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Installer Locked - N.A Fresh Fruits & Coconuts</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-[#f8faf8] text-gray-800 min-h-screen flex items-center justify-center p-4">
            <div class="max-w-md w-full bg-white p-8 rounded-3xl border border-gray-100 shadow-sm text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Installer is Locked</h1>
                <p class="text-sm text-gray-500 leading-relaxed">
                    N.A Fresh Fruits & Coconuts has already been installed and initialized. For security reasons, the installation wizard cannot be re-run.
                </p>
                <div class="pt-3 flex flex-col gap-2">
                    <a href="/" class="bg-[#235d3a] hover:bg-[#1b4a2e] text-white font-bold text-sm py-3 px-6 rounded-xl transition">
                        Go to Storefront
                    </a>
                    <a href="/admin" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-xs py-2.5 px-4 rounded-xl transition">
                        Store Admin Login
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    private static function step1Requirements(): void {
        $checks = [
            'php_version' => [
                'name' => 'PHP Version (8.2+ required)',
                'current' => PHP_VERSION,
                'pass' => version_compare(PHP_VERSION, '8.2.0', '>=')
            ],
            'ext_pdo' => [
                'name' => 'PDO Extension',
                'current' => extension_loaded('pdo') ? 'Installed' : 'Missing',
                'pass' => extension_loaded('pdo')
            ],
            'ext_pdo_mysql' => [
                'name' => 'PDO MySQL Driver',
                'current' => extension_loaded('pdo_mysql') ? 'Installed' : 'Missing',
                'pass' => extension_loaded('pdo_mysql')
            ],
            'ext_mbstring' => [
                'name' => 'Mbstring Extension',
                'current' => extension_loaded('mbstring') ? 'Installed' : 'Missing',
                'pass' => extension_loaded('mbstring')
            ],
            'ext_curl' => [
                'name' => 'cURL Extension',
                'current' => extension_loaded('curl') ? 'Installed' : 'Missing',
                'pass' => extension_loaded('curl')
            ],
            'ext_gd' => [
                'name' => 'GD Image Extension',
                'current' => extension_loaded('gd') ? 'Installed' : 'Missing',
                'pass' => extension_loaded('gd')
            ],
            'dir_storage' => [
                'name' => 'Storage Directory Writable',
                'current' => is_writable(__DIR__ . '/../../storage') ? 'Writable' : 'Read-only',
                'pass' => is_writable(__DIR__ . '/../../storage')
            ],
            'dir_config' => [
                'name' => 'Config Directory Writable',
                'current' => is_writable(__DIR__ . '/../../app/config') ? 'Writable' : 'Read-only',
                'pass' => is_writable(__DIR__ . '/../../app/config')
            ],
            'dir_uploads' => [
                'name' => 'Uploads Directory Writable',
                'current' => is_writable(__DIR__ . '/../../public/assets/uploads') ? 'Writable' : 'Read-only',
                'pass' => is_writable(__DIR__ . '/../../public/assets/uploads')
            ],
        ];

        $allPassed = true;
        foreach ($checks as $c) {
            if (!$c['pass']) {
                $allPassed = false;
                break;
            }
        }

        require __DIR__ . '/../views/installer/step1.php';
    }

    private static function step2Database(string $method): void {
        $error = null;
        $success = null;

        // Default or existing config
        $host = $_POST['host'] ?? DB_HOST;
        $port = $_POST['port'] ?? DB_PORT;
        $database = $_POST['database'] ?? DB_NAME;
        $username = $_POST['username'] ?? DB_USER;
        $password = $_POST['password'] ?? DB_PASS;
        $createDb = isset($_POST['create_db']) || !empty($_POST['create_db_flag']);

        if ($method === 'POST') {
            if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                $error = "Session security token expired. Please try again.";
            } else {
                $test = Database::testConnection($host, $port, $database, $username, $password, $createDb);
                if ($test['success']) {
                    // Save to db_config.php
                    $configContent = "<?php\n/**\n * Database Configuration - Saved by Web Installer\n */\nreturn [\n"
                        . "    'host' => " . var_export($host, true) . ",\n"
                        . "    'port' => " . var_export($port, true) . ",\n"
                        . "    'database' => " . var_export($database, true) . ",\n"
                        . "    'username' => " . var_export($username, true) . ",\n"
                        . "    'password' => " . var_export($password, true) . ",\n"
                        . "    'prefix' => '',\n"
                        . "];\n";

                    $configFile = __DIR__ . '/../config/db_config.php';
                    if (@file_put_contents($configFile, $configContent)) {
                        Database::resetConnection();
                        header('Location: /install?step=3');
                        exit;
                    } else {
                        $error = "Connected to MySQL successfully, but could not write to app/config/db_config.php. Please check file permissions.";
                    }
                } else {
                    $error = $test['error'];
                }
            }
        }

        require __DIR__ . '/../views/installer/step2.php';
    }

    private static function step3Initialize(string $method): void {
        $error = null;
        $existingTables = [];
        $isInitialized = false;
        $connectionError = null;

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $existingTables = $tables;
            $requiredTables = ['products', 'orders', 'admins', 'settings', 'order_items', 'customers'];
            $matching = array_intersect($requiredTables, $tables);
            $isInitialized = (count($matching) === count($requiredTables));
        } catch (Exception $e) {
            $connectionError = Database::sanitizeErrorMessage($e->getMessage());
        }

        if ($method === 'POST') {
            if ($connectionError) {
                $error = "Cannot connect to configured database: " . $connectionError . ". Please return to Step 2 and verify credentials.";
            } elseif (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                $error = "Session token expired. Please try again.";
            } else {
                try {
                    $schemaFile = __DIR__ . '/../../database/schema.sql';
                    if (!file_exists($schemaFile)) {
                        throw new Exception("Schema file database/schema.sql not found.");
                    }
                    $sql = file_get_contents($schemaFile);
                    
                    // Split into discrete SQL statements
                    $statements = self::splitSqlStatements($sql);

                    foreach ($statements as $stmtSql) {
                        $stmtSql = trim($stmtSql);
                        if (empty($stmtSql)) continue;

                        // Security & hosting compatibility: Never execute CREATE DATABASE or USE statements
                        if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $stmtSql)) {
                            continue;
                        }

                        $pdo->exec($stmtSql);
                    }

                    // Seed default product if not present (non-destructive)
                    $stmtCheckProd = $pdo->query("SELECT COUNT(*) FROM `products` WHERE `slug` = 'fresh-coconut-water'");
                    if ($stmtCheckProd->fetchColumn() == 0) {
                        $gallery = json_encode([
                            '/assets/images/product-main.png',
                            '/assets/images/thumb-1.png',
                            '/assets/images/thumb-2.png',
                            '/assets/images/thumb-3.png',
                            '/assets/images/thumb-4.png'
                        ]);
                        $stmtInsProd = $pdo->prepare("
                            INSERT INTO `products` (`name`, `slug`, `tagline`, `description`, `price`, `stock_quantity`, `is_available`, `image_url`, `gallery_json`)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmtInsProd->execute([
                            'Fresh Coconut Water',
                            'fresh-coconut-water',
                            'Pure • Natural • Healthy',
                            'Fresh tender coconut water, straight from nature. No added sugar, no preservatives, no chemicals. Just pure and natural hydration.',
                            40.00,
                            150,
                            1,
                            '/assets/images/product-main.png',
                            $gallery
                        ]);
                    }

                    // Verify required tables exist
                    $stmtVerify = $pdo->query("SHOW TABLES");
                    $postTables = $stmtVerify->fetchAll(PDO::FETCH_COLUMN);
                    $missingTables = array_diff(['products', 'orders', 'admins', 'settings'], $postTables);
                    if (!empty($missingTables)) {
                        throw new Exception("Required tables could not be verified: " . implode(', ', $missingTables));
                    }

                    header('Location: /install?step=4');
                    exit;
                } catch (PDOException $e) {
                    $errCode = (int)$e->getCode();
                    $errMsg = $e->getMessage();
                    if ($errCode === 1044 || $errCode === 1142 || str_contains($errMsg, '1044') || str_contains($errMsg, '1142') || str_contains($errMsg, 'Access denied')) {
                        $error = "Hosting Permission Error (1044/1142): MySQL user '" . e(DB_USER) . "' does not have permission to CREATE or ALTER tables in database '" . e(DB_NAME) . "'.\n\n"
                               . "Action Required in cPanel:\n"
                               . "1. In cPanel, navigate to 'MySQL Databases'.\n"
                               . "2. Scroll down to the 'Add User to Database' section.\n"
                               . "3. Select User: '" . e(DB_USER) . "' and Database: '" . e(DB_NAME) . "'.\n"
                               . "4. Click 'Add', tick the 'ALL PRIVILEGES' checkbox, and click 'Make Changes'.\n"
                               . "5. Return here and click 'Create Tables & Continue'.";
                    } else {
                        $error = "Database initialization error: " . Database::sanitizeErrorMessage($errMsg);
                    }
                } catch (Exception $e) {
                    $error = "Database initialization error: " . Database::sanitizeErrorMessage($e->getMessage());
                }
            }
        }

        require __DIR__ . '/../views/installer/step3.php';
    }

    private static function splitSqlStatements(string $sql): array {
        // Strip block comments
        $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
        // Strip line comments
        $sql = preg_replace('/^--.*?$/m', '', $sql);

        $tokens = explode(';', $sql);
        $clean = [];
        foreach ($tokens as $t) {
            $t = trim($t);
            if (!empty($t)) {
                $clean[] = $t;
            }
        }
        return $clean;
    }

    private static function step4Admin(string $method): void {
        $error = null;
        $pdo = Database::getConnection();

        $stmtAdmin = $pdo->query("SELECT COUNT(*) FROM `admins`");
        $adminCount = (int)$stmtAdmin->fetchColumn();

        if ($method === 'POST') {
            if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                $error = "Security token mismatch.";
            } else {
                $name = trim($_POST['admin_name'] ?? '');
                $username = trim($_POST['admin_username'] ?? '');
                $email = trim($_POST['admin_email'] ?? '');
                $password = $_POST['admin_password'] ?? '';
                $confirmPassword = $_POST['admin_password_confirm'] ?? '';

                if (empty($name) || strlen($name) < 2) {
                    $error = "Please enter administrator's full name.";
                } elseif (empty($username) || strlen($username) < 3) {
                    $error = "Username must be at least 3 characters.";
                } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please enter a valid administrator email.";
                } elseif (strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long.";
                } elseif ($password !== $confirmPassword) {
                    $error = "Passwords do not match.";
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    // Insert or update admin
                    if ($adminCount === 0) {
                        $stmt = $pdo->prepare("INSERT INTO `admins` (`username`, `email`, `password_hash`, `name`, `role`) VALUES (?, ?, ?, ?, 'superadmin')");
                        $stmt->execute([$username, $email, $hash, $name]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE `admins` SET `username` = ?, `email` = ?, `password_hash` = ?, `name` = ?, `updated_at` = NOW() ORDER BY `id` ASC LIMIT 1");
                        $stmt->execute([$username, $email, $hash, $name]);
                    }

                    header('Location: /install?step=5');
                    exit;
                }
            }
        }

        require __DIR__ . '/../views/installer/step4.php';
    }

    private static function step5Business(string $method): void {
        $error = null;
        $pdo = Database::getConnection();

        if ($method === 'POST') {
            if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                $error = "Security token mismatch.";
            } else {
                $storeName = trim($_POST['store_name'] ?? 'N.A Fresh Fruits & Coconuts');
                $currency = trim($_POST['currency_symbol'] ?? '₹');
                $price = max(1.0, (float)($_POST['product_price'] ?? 40.0));
                $stock = max(0, (int)($_POST['product_stock'] ?? 150));
                $deliveryFee = max(0.0, (float)($_POST['delivery_charge'] ?? 40.0));
                $minOrder = max(0.0, (float)($_POST['min_order_amount'] ?? 40.0));
                $freeAbove = max(0.0, (float)($_POST['free_delivery_above'] ?? 500.0));
                $localDelivery = isset($_POST['local_delivery_enabled']) ? '1' : '0';
                $shopPickup = isset($_POST['shop_pickup_enabled']) ? '1' : '0';
                $timezone = trim($_POST['timezone'] ?? 'Asia/Kolkata');
                $phone = trim($_POST['store_phone'] ?? '+91 98765 43210');
                $address = trim($_POST['store_address'] ?? '123, Green Park, Near Main Road, Bhopal, Madhya Pradesh - 462016');

                $settings = [
                    'store_name' => $storeName,
                    'currency_symbol' => $currency,
                    'delivery_charge' => number_format($deliveryFee, 2, '.', ''),
                    'min_order_amount' => number_format($minOrder, 2, '.', ''),
                    'free_delivery_above' => number_format($freeAbove, 2, '.', ''),
                    'local_delivery_enabled' => $localDelivery,
                    'shop_pickup_enabled' => $shopPickup,
                    'store_timezone' => $timezone,
                    'store_phone' => $phone,
                    'store_address' => $address,
                    'store_open_time' => '07:00 AM',
                    'store_close_time' => '09:00 PM',
                ];

                Setting::setMany($settings);

                // Update product price & stock
                $stmtProd = $pdo->prepare("UPDATE `products` SET `price` = ?, `stock_quantity` = ?, `updated_at` = NOW() WHERE `slug` = 'fresh-coconut-water'");
                $stmtProd->execute([$price, $stock]);

                header('Location: /install?step=6');
                exit;
            }
        }

        require __DIR__ . '/../views/installer/step5.php';
    }

    private static function step6Complete(): void {
        $validation = [];
        $allPassed = true;

        // 1. Check DB Connection
        try {
            $pdo = Database::getConnection();
            $validation['database'] = ['name' => 'MySQL Database Connection', 'pass' => true];
        } catch (Exception $e) {
            $validation['database'] = ['name' => 'MySQL Database Connection', 'pass' => false, 'error' => $e->getMessage()];
            $allPassed = false;
        }

        // 2. Check Required Tables
        if ($allPassed) {
            $requiredTables = ['admins', 'products', 'orders', 'order_items', 'settings', 'customers'];
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_diff($requiredTables, $tables);
            $validation['tables'] = ['name' => 'Schema Tables Verified', 'pass' => empty($missing)];
            if (!empty($missing)) $allPassed = false;
        }

        // 3. Check Admin User Exists
        if ($allPassed) {
            $stmtAdmin = $pdo->query("SELECT `username`, `email` FROM `admins` LIMIT 1");
            $admin = $stmtAdmin->fetch();
            $validation['admin'] = ['name' => 'Administrator Account Ready', 'pass' => (bool)$admin, 'user' => $admin['username'] ?? 'admin'];
            if (!$admin) $allPassed = false;
        }

        // 4. Check Product Ready
        if ($allPassed) {
            $product = Product::getPrimary();
            $validation['product'] = ['name' => 'Fresh Coconut Water Initialized', 'pass' => (bool)$product];
            if (!$product) $allPassed = false;
        }

        // If everything passed, create the lock file
        if ($allPassed) {
            $lockData = json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'version' => '1.0.0',
                'app_name' => Setting::get('store_name', 'N.A Fresh Fruits & Coconuts')
            ], JSON_PRETTY_PRINT);
            @file_put_contents(INSTALL_LOCK_FILE, $lockData);
        }

        require __DIR__ . '/../views/installer/step6.php';
    }
}
