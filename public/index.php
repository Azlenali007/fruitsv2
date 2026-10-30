<?php
/**
 * Public Front Controller & Router
 * N.A Fresh Fruits & Coconuts (PHP 8.2 & MySQL)
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/InstallerController.php';

// Parse incoming request path
$rawUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($rawUri);
$method = $_SERVER['REQUEST_METHOD'];

// Normalize slashes (e.g. /shop/ -> /shop)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

// Client IP helper
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (str_contains($clientIp, ',')) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

// ----------------------------------------------------
// 1. WEB INSTALLER ROUTE HANDLING
// ----------------------------------------------------
if ($uri === '/install' || str_starts_with($uri, '/install/')) {
    InstallerController::handle();
    exit;
}

// ----------------------------------------------------
// 2. INSTALLATION STATE CHECKING (STATES A, B, C, D)
// ----------------------------------------------------
if (!is_installed()) {
    // If DB is already configured and tables exist, verify and lock safely
    if (Database::isReady()) {
        @file_put_contents(INSTALL_LOCK_FILE, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'verified' => true
        ], JSON_PRETTY_PRINT));
    } else {
        // Direct uninstalled users cleanly to Web Installer
        header('Location: /install?step=1');
        exit;
    }
}

// ----------------------------------------------------
// 3. MAIN APPLICATION ROUTING PROTECTED BY STATE D
// ----------------------------------------------------
try {
    switch ($uri) {
        // ------------------------------------------------
        // Customer Routes
        // ------------------------------------------------
        case '':
        case '/':
        case '/index.html':
        case '/index.php':
            $product = Product::getPrimary();
            if (!$product) {
                // If product is missing, direct safely to installer or create
                header('Location: /install?step=3');
                exit;
            }
            require __DIR__ . '/../app/views/home.php';
            break;

        case '/shop':
            $product = Product::getPrimary();
            require __DIR__ . '/../app/views/shop.php';
            break;

        case '/cart':
            require __DIR__ . '/../app/views/cart.php';
            break;

        case '/cart/add':
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Session expired. Please try again.');
                    header('Location: /shop');
                    exit;
                }
                $productId = (int)($_POST['product_id'] ?? 1);
                $qty = max(1, (int)($_POST['quantity'] ?? 1));
                $res = CartService::addItem($productId, $qty);
                if (!$res['success']) {
                    set_flash('error', $res['error']);
                } else {
                    set_flash('success', 'Added fresh coconut water to your cart.');
                }
                header('Location: /cart');
                exit;
            }
            header('Location: /cart');
            break;

        case '/cart/update':
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    header('Location: /cart');
                    exit;
                }
                $productId = (int)($_POST['product_id'] ?? 1);
                $qty = (int)($_POST['quantity'] ?? 1);
                CartService::updateQuantity($productId, $qty);
                header('Location: /cart');
                exit;
            }
            header('Location: /cart');
            break;

        case '/cart/remove':
            if ($method === 'POST') {
                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    $productId = (int)($_POST['product_id'] ?? 1);
                    CartService::removeItem($productId);
                    set_flash('info', 'Item removed from cart.');
                }
            }
            header('Location: /cart');
            break;

        case '/cart/clear':
            if ($method === 'POST') {
                if (verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    CartService::clear();
                    set_flash('info', 'Cart cleared.');
                }
            }
            header('Location: /cart');
            break;

        case '/checkout':
            CustomerAuthService::requireLogin('/checkout');
            require __DIR__ . '/../app/views/checkout.php';
            break;

        case '/checkout/place':
            if ($method === 'POST') {
                if (!CustomerAuthService::check()) {
                    set_flash('error', 'Please log in or create an account to place your order.');
                    $_SESSION['customer_intended_url'] = '/checkout';
                    header('Location: /login');
                    exit;
                }

                $result = OrderService::processCheckout($_POST);
                if ($result['success']) {
                    $_SESSION['last_customer_name'] = $_POST['customer_name'] ?? '';
                    $_SESSION['last_customer_phone'] = $_POST['customer_phone'] ?? '';
                    $_SESSION['last_customer_address'] = $_POST['delivery_address'] ?? '';
                    header('Location: /confirmation?num=' . urlencode($result['order_number']) . '&h=' . urlencode($result['tracking_hash']));
                    exit;
                } else {
                    set_flash('error', $result['error']);
                    if (!empty($result['auth_required'])) {
                        $_SESSION['customer_intended_url'] = '/checkout';
                        header('Location: /login');
                    } else {
                        header('Location: /checkout');
                    }
                    exit;
                }
            }
            header('Location: /checkout');
            break;

        // ------------------------------------------------
        // Customer Authentication & Account Routes
        // ------------------------------------------------
        case '/register':
            if (CustomerAuthService::check()) {
                header('Location: /account');
                exit;
            }
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Session token mismatch. Please try again.');
                } else {
                    $res = CustomerAuthService::register($_POST, $clientIp);
                    if ($res['success']) {
                        set_flash('success', 'Account registered successfully! Welcome to N.A Fresh Fruits & Coconuts.');
                        $target = $_SESSION['customer_intended_url'] ?? '/account';
                        unset($_SESSION['customer_intended_url']);
                        header('Location: ' . $target);
                        exit;
                    } else {
                        set_flash('error', $res['error']);
                    }
                }
            }
            require __DIR__ . '/../app/views/customer/register.php';
            break;

        case '/login':
            if (CustomerAuthService::check()) {
                header('Location: /account');
                exit;
            }
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Session token expired. Please try again.');
                } else {
                    $identifier = trim($_POST['identifier'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $res = CustomerAuthService::login($identifier, $password, $clientIp);
                    if ($res['success']) {
                        $cUser = $res['customer'];
                        set_flash('success', 'Welcome back, ' . e($cUser['name']) . '!');
                        $target = $_SESSION['customer_intended_url'] ?? '/account';
                        unset($_SESSION['customer_intended_url']);
                        header('Location: ' . $target);
                        exit;
                    } else {
                        set_flash('error', $res['error']);
                    }
                }
            }
            require __DIR__ . '/../app/views/customer/login.php';
            break;

        case '/logout':
            if ($method === 'POST') {
                verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '');
            }
            CustomerAuthService::logout();
            set_flash('info', 'You have been safely logged out.');
            header('Location: /');
            exit;
            break;

        case '/account':
            CustomerAuthService::requireLogin('/account');
            require __DIR__ . '/../app/views/customer/account.php';
            break;

        case '/account/profile':
            CustomerAuthService::requireLogin('/account');
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token expired. Please try again.');
                } else {
                    $cid = CustomerAuthService::id();
                    $update = [
                        'name' => trim($_POST['name'] ?? ''),
                        'email' => trim($_POST['email'] ?? ''),
                        'address' => trim($_POST['address'] ?? ''),
                        'city' => trim($_POST['city'] ?? ''),
                        'pincode' => trim($_POST['pincode'] ?? '')
                    ];
                    if (!empty($update['email']) && Customer::emailExists($update['email'], $cid)) {
                        set_flash('error', 'That email address is already in use by another account.');
                    } else {
                        Customer::updateProfile($cid, $update);
                        $_SESSION[CustomerAuthService::SESSION_CUSTOMER_NAME] = $update['name'];
                        set_flash('success', 'Your account details have been updated.');
                    }
                }
            }
            header('Location: /account');
            exit;
            break;

        case '/my-orders':
            CustomerAuthService::requireLogin('/my-orders');
            require __DIR__ . '/../app/views/customer/my-orders.php';
            break;

        case '/confirmation':
            $orderNumber = $_GET['num'] ?? '';
            $trackingHash = $_GET['h'] ?? '';
            $order = null;
            if (!empty($trackingHash)) {
                $order = Order::getByTrackingHash($trackingHash);
            } else if (!empty($orderNumber)) {
                $order = Order::getByOrderNumber($orderNumber);
            }
            if (!$order) {
                header('Location: /');
                exit;
            }
            require __DIR__ . '/../app/views/confirmation.php';
            break;

        case '/track':
            $foundOrder = null;
            if (!empty($_GET['h'])) {
                $foundOrder = Order::getByTrackingHash($_GET['h']);
            } else if (!empty($_GET['num'])) {
                $foundOrder = Order::getByOrderNumber(trim($_GET['num']));
            } else if (!empty($_GET['phone'])) {
                $phoneOrders = Order::getByPhone(trim($_GET['phone']));
                $foundOrder = !empty($phoneOrders) ? $phoneOrders[0] : null;
            }
            require __DIR__ . '/../app/views/track.php';
            break;

        // ------------------------------------------------
        // Admin Routes
        // ------------------------------------------------
        case '/admin/login':
            if (AuthService::check()) {
                header('Location: /admin');
                exit;
            }
            $loginError = '';
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    $loginError = 'Session token mismatch. Please try again.';
                } else {
                    $username = trim($_POST['username'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $res = AuthService::login($username, $password, $clientIp);
                    if ($res['success']) {
                        $redirect = $_SESSION['admin_intended_url'] ?? '/admin';
                        unset($_SESSION['admin_intended_url']);
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        $loginError = $res['error'];
                    }
                }
            }
            require __DIR__ . '/../app/views/admin/login.php';
            break;

        case '/admin/logout':
            if ($method === 'POST') {
                verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '');
                AuthService::logout();
            }
            header('Location: /admin/login');
            break;

        case '/admin':
        case '/admin/dashboard':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/dashboard.php';
            break;

        case '/admin/orders':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/orders.php';
            break;

        case '/admin/orders/view':
            AuthService::requireAdmin();
            $orderId = (int)($_GET['id'] ?? 0);
            $order = Order::getById($orderId);
            if (!$order) {
                set_flash('error', 'Order not found.');
                header('Location: /admin/orders');
                exit;
            }
            require __DIR__ . '/../app/views/admin/order-detail.php';
            break;

        case '/admin/orders/update-status':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'CSRF verification failed.');
                    header('Location: /admin/orders');
                    exit;
                }
                $orderId = (int)($_POST['order_id'] ?? 0);
                $newStatus = trim($_POST['new_status'] ?? '');
                $comment = trim($_POST['comment'] ?? 'Status updated by admin');
                $admin = AuthService::user();
                $changedBy = $admin['name'] ?? 'Admin';

                $success = Order::updateStatus($orderId, $newStatus, $comment, $changedBy);
                if ($success) {
                    set_flash('success', "Order #$orderId updated to " . ucfirst($newStatus) . ".");
                } else {
                    set_flash('error', 'Failed to update order status.');
                }
                header('Location: /admin/orders/view?id=' . $orderId);
                exit;
            }
            header('Location: /admin/orders');
            break;

        case '/admin/products':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/products.php';
            break;

        case '/admin/products/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token mismatch.');
                    header('Location: /admin/products');
                    exit;
                }

                $productId = (int)($_POST['product_id'] ?? 1);
                $name = trim($_POST['name'] ?? '');
                $tagline = trim($_POST['tagline'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = max(1.0, (float)($_POST['price'] ?? 40.0));
                $stock = max(0, (int)($_POST['stock_quantity'] ?? 0));
                $isAvailable = isset($_POST['is_available']) ? 1 : 0;
                $imageUrl = trim($_POST['image_url'] ?? '/assets/images/product-main.png');

                // Handle image upload if provided
                if (!empty($_FILES['product_image']['name']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['product_image']['tmp_name'];
                    $fileName = $_FILES['product_image']['name'];
                    $fileSize = $_FILES['product_image']['size'];

                    if ($fileSize <= MAX_UPLOAD_SIZE) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $fileTmp);
                        finfo_close($finfo);

                        $mimeToExt = [
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp',
                        ];
                        if (isset($mimeToExt[$mime])) {
                            $ext = $mimeToExt[$mime];
                            $safeName = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $destination = UPLOAD_DIR . $safeName;
                            if (move_uploaded_file($fileTmp, $destination)) {
                                $imageUrl = '/assets/uploads/' . $safeName;
                            }
                        }
                    }
                }

                $updateData = [
                    'name' => $name,
                    'tagline' => $tagline,
                    'description' => $description,
                    'price' => $price,
                    'stock_quantity' => $stock,
                    'is_available' => $isAvailable,
                    'image_url' => $imageUrl
                ];

                Product::update($productId, $updateData);
                set_flash('success', 'Product details and stock successfully saved in MySQL.');
                header('Location: /admin/products');
                exit;
            }
            header('Location: /admin/products');
            break;

        case '/admin/customers':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/customers.php';
            break;

        case '/admin/customers/view':
            AuthService::requireAdmin();
            $customerId = (int)($_GET['id'] ?? 0);
            $customer = Customer::findById($customerId);
            if (!$customer) {
                set_flash('error', 'Customer not found.');
                header('Location: /admin/customers');
                exit;
            }
            require __DIR__ . '/../app/views/admin/customer-detail.php';
            break;

        case '/admin/settings':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/settings.php';
            break;

        case '/admin/settings/update':
            AuthService::requireAdmin();
            if ($method === 'POST') {
                if (!verify_csrf_token($_POST[CSRF_TOKEN_KEY] ?? '')) {
                    set_flash('error', 'Security token expired.');
                    header('Location: /admin/settings');
                    exit;
                }

                $settingsData = [
                    'local_delivery_enabled' => isset($_POST['local_delivery_enabled']) ? '1' : '0',
                    'shop_pickup_enabled' => isset($_POST['shop_pickup_enabled']) ? '1' : '0',
                    'delivery_charge' => number_format((float)($_POST['delivery_charge'] ?? 40), 2, '.', ''),
                    'min_order_amount' => number_format((float)($_POST['min_order_amount'] ?? 40), 2, '.', ''),
                    'free_delivery_above' => number_format((float)($_POST['free_delivery_above'] ?? 500), 2, '.', ''),
                    'service_areas' => trim($_POST['service_areas'] ?? ''),
                    'store_name' => trim($_POST['store_name'] ?? 'N.A Fresh Fruits & Coconuts'),
                    'store_phone' => trim($_POST['store_phone'] ?? '+91 98765 43210'),
                    'store_address' => trim($_POST['store_address'] ?? ''),
                    'store_open_time' => trim($_POST['store_open_time'] ?? '07:00 AM'),
                    'store_close_time' => trim($_POST['store_close_time'] ?? '09:00 PM'),
                ];

                Setting::setMany($settingsData);
                set_flash('success', 'Store delivery and business settings updated.');
                header('Location: /admin/settings');
                exit;
            }
            header('Location: /admin/settings');
            break;

        case '/admin/reports':
            AuthService::requireAdmin();
            require __DIR__ . '/../app/views/admin/reports.php';
            break;

        // Proper 404 handler for missing routes
        default:
            http_response_code(404);
            echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>404 Page Not Found - N.A Fresh Fruits & Coconuts</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-[#f8faf8] flex items-center justify-center min-h-screen text-center p-4'><div class='bg-white p-8 rounded-3xl shadow-xs border border-gray-100 max-w-md w-full space-y-4'><div class='w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-[#235d3a] flex items-center justify-center font-black text-2xl'>NA</div><h1 class='text-4xl font-extrabold text-gray-900'>404</h1><p class='text-sm text-gray-500'>The page you requested could not be found.</p><div class='pt-2'><a href='/' class='bg-[#235d3a] hover:bg-[#1b4a2e] text-white text-xs font-bold py-3 px-6 rounded-xl transition inline-block'>Back to Storefront</a></div></div></body></html>";
            break;
    }
} catch (Exception $e) {
    // State D: Safe Configuration / Database Error Handler
    error_log("Application Execution Error: " . $e->getMessage());
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error - N.A Fresh Fruits & Coconuts</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-[#f8faf8] flex items-center justify-center min-h-screen p-4 text-center">
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 max-w-md w-full space-y-4">
            <div class="w-14 h-14 mx-auto rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900">Database Connection Notice</h1>
            <p class="text-xs text-gray-500 leading-relaxed">
                The store could not establish a connection to MySQL database. Please verify your MySQL server status or re-configure credentials.
            </p>
            <div class="pt-2 flex flex-col gap-2">
                <a href="/install?step=2" class="bg-[#235d3a] text-white text-xs font-bold py-2.5 px-4 rounded-xl hover:bg-[#1b4a2e] transition">
                    Run Web Installer / Configure Database
                </a>
                <button onclick="window.location.reload()" class="bg-gray-100 text-gray-700 text-xs font-semibold py-2.5 px-4 rounded-xl hover:bg-gray-200 transition">
                    Retry Connection
                </button>
            </div>
        </div>
    </body>
    </html>
    <?php
}
