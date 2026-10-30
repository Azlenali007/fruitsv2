<?php
/**
 * Order Model
 * N.A Fresh Fruits & Coconuts
 */

class Order {
    public static function createOrder(array $orderData, array $cartItems): array {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // 1. Check idempotency if key provided
            if (!empty($orderData['idempotency_key'])) {
                $stmtCheck = $pdo->prepare("SELECT `id`, `order_number`, `tracking_hash` FROM `orders` WHERE `idempotency_key` = ?");
                $stmtCheck->execute([$orderData['idempotency_key']]);
                $existing = $stmtCheck->fetch();
                if ($existing) {
                    $pdo->rollBack();
                    return [
                        'success' => true,
                        'order_id' => $existing['id'],
                        'order_number' => $existing['order_number'],
                        'tracking_hash' => $existing['tracking_hash'],
                        'is_duplicate' => true
                    ];
                }
            }

            // 2. Validate product & stock using pessimistic locking (FOR UPDATE)
            $subtotal = 0.00;
            $verifiedItems = [];

            foreach ($cartItems as $item) {
                $productId = (int)$item['product_id'];
                $quantity = max(1, (int)$item['quantity']);

                $stmtProd = $pdo->prepare("SELECT * FROM `products` WHERE `id` = ? FOR UPDATE");
                $stmtProd->execute([$productId]);
                $product = $stmtProd->fetch();

                if (!$product) {
                    throw new Exception("Product no longer available in store.");
                }

                if (!$product['is_available']) {
                    throw new Exception("Item '{$product['name']}' is currently marked unavailable.");
                }

                if ($product['stock_quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for '{$product['name']}'. Only {$product['stock_quantity']} left.");
                }

                $itemPrice = (float)$product['price'];
                $lineTotal = $itemPrice * $quantity;
                $subtotal += $lineTotal;

                $verifiedItems[] = [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'unit_price' => $itemPrice,
                    'quantity' => $quantity,
                    'subtotal' => $lineTotal
                ];

                // Atomically deduct stock
                $stmtDeduct = $pdo->prepare("UPDATE `products` SET `stock_quantity` = `stock_quantity` - ?, `updated_at` = NOW() WHERE `id` = ?");
                $stmtDeduct->execute([$quantity, $productId]);
            }

            // 3. Calculate delivery charge and total
            $fulfilment = $orderData['fulfilment_method'] ?? 'delivery';
            $deliveryCharge = 0.00;

            if ($fulfilment === 'delivery') {
                $deliveryEnabled = Setting::get('local_delivery_enabled', '1') === '1';
                if (!$deliveryEnabled) {
                    throw new Exception("Local delivery is currently paused by store manager.");
                }
                $minOrder = (float)Setting::get('min_order_amount', '40.00');
                if ($subtotal < $minOrder) {
                    throw new Exception("Minimum order amount for delivery is " . CURRENCY_SYMBOL . number_format($minOrder, 2));
                }
                $freeAbove = (float)Setting::get('free_delivery_above', '500.00');
                if ($subtotal >= $freeAbove) {
                    $deliveryCharge = 0.00;
                } else {
                    $deliveryCharge = (float)Setting::get('delivery_charge', '40.00');
                }
            } else {
                $pickupEnabled = Setting::get('shop_pickup_enabled', '1') === '1';
                if (!$pickupEnabled) {
                    throw new Exception("Shop pickup is currently not available.");
                }
                $deliveryCharge = 0.00;
            }

            $totalAmount = $subtotal + $deliveryCharge;

            // 4. Generate unique readable order number (e.g. NA1024, NA1025)
            $stmtLast = $pdo->query("SELECT `id` FROM `orders` ORDER BY `id` DESC LIMIT 1");
            $lastId = (int)$stmtLast->fetchColumn();
            $orderNumber = 'NA' . (1000 + $lastId + 1);

            $trackingHash = bin2hex(random_bytes(24));
            $estimatedDelivery = $fulfilment === 'delivery' ? 'Today, ' . date('g:i A', strtotime('+45 minutes')) . ' - ' . date('g:i A', strtotime('+120 minutes')) : 'Ready for pickup in 15 minutes';

            // 5. Connect order with authenticated customer record
            $customerId = !empty($orderData['customer_id']) ? (int)$orderData['customer_id'] : null;
            if ($customerId) {
                // Update customer address if provided
                if (!empty($orderData['delivery_address'])) {
                    $stmtUpCust = $pdo->prepare("UPDATE `customers` SET `address` = ?, `updated_at` = NOW() WHERE `id` = ?");
                    $stmtUpCust->execute([$orderData['delivery_address'], $customerId]);
                }
            } else if (!empty($orderData['customer_phone'])) {
                // Fallback for automated tests / legacy calls
                $stmtCust = $pdo->prepare("SELECT `id` FROM `customers` WHERE `phone` = ? LIMIT 1");
                $stmtCust->execute([$orderData['customer_phone']]);
                $cId = $stmtCust->fetchColumn();
                if ($cId) {
                    $customerId = (int)$cId;
                    if (!empty($orderData['delivery_address'])) {
                        $stmtUpCust = $pdo->prepare("UPDATE `customers` SET `address` = ?, `name` = ?, `updated_at` = NOW() WHERE `id` = ?");
                        $stmtUpCust->execute([$orderData['delivery_address'], $orderData['customer_name'], $customerId]);
                    }
                } else {
                    $stmtInsCust = $pdo->prepare("INSERT INTO `customers` (`name`, `phone`, `address`) VALUES (?, ?, ?)");
                    $stmtInsCust->execute([$orderData['customer_name'], $orderData['customer_phone'], $orderData['delivery_address'] ?? '']);
                    $customerId = (int)$pdo->lastInsertId();
                }
            }

            // 6. Insert Order
            $stmtOrder = $pdo->prepare("
                INSERT INTO `orders` (
                    `order_number`, `customer_id`, `customer_name`, `customer_phone`,
                    `fulfilment_method`, `delivery_address`, `subtotal`, `delivery_charge`,
                    `total_amount`, `payment_method`, `payment_status`, `order_status`,
                    `tracking_hash`, `estimated_delivery`, `notes`, `idempotency_key`, `created_at`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?, NOW())
            ");

            $paymentMethod = $orderData['payment_method'] ?? 'cod';
            // PAYMENT SAFETY: Never mark online payment as 'paid' without real gateway verification.
            // Payment status remains 'pending' for all orders until verified.
            $paymentStatus = 'pending';

            $stmtOrder->execute([
                $orderNumber,
                $customerId,
                $orderData['customer_name'],
                $orderData['customer_phone'],
                $fulfilment,
                $orderData['delivery_address'] ?? '',
                $subtotal,
                $deliveryCharge,
                $totalAmount,
                $paymentMethod,
                $paymentStatus,
                $trackingHash,
                $estimatedDelivery,
                $orderData['notes'] ?? '',
                $orderData['idempotency_key'] ?? null
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // 7. Insert Order Items (Immutable price snapshot)
            $stmtItem = $pdo->prepare("
                INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($verifiedItems as $vi) {
                $stmtItem->execute([
                    $orderId,
                    $vi['product_id'],
                    $vi['product_name'],
                    $vi['unit_price'],
                    $vi['quantity'],
                    $vi['subtotal']
                ]);
            }

            // 8. Record initial status history
            $stmtHist = $pdo->prepare("
                INSERT INTO `order_status_history` (`order_id`, `old_status`, `new_status`, `comment`, `changed_by`)
                VALUES (?, NULL, 'confirmed', 'Order placed successfully by customer', 'Customer')
            ");
            $stmtHist->execute([$orderId]);

            $pdo->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'tracking_hash' => $trackingHash,
                'total_amount' => $totalAmount
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Order creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public static function getById(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = self::getOrderItems($order['id']);
            $order['history'] = self::getOrderHistory($order['id']);
        }
        return $order ?: null;
    }

    public static function getByTrackingHash(string $hash): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `tracking_hash` = ? LIMIT 1");
        $stmt->execute([$hash]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = self::getOrderItems($order['id']);
            $order['history'] = self::getOrderHistory($order['id']);
        }
        return $order ?: null;
    }

    public static function getByOrderNumber(string $orderNumber): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `order_number` = ? LIMIT 1");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = self::getOrderItems($order['id']);
            $order['history'] = self::getOrderHistory($order['id']);
        }
        return $order ?: null;
    }

    public static function getByPhone(string $phone): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `customer_phone` = ? OR `customer_phone` = ? ORDER BY `id` DESC LIMIT 20");
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $withPlus = '+91 ' . substr($cleanPhone, -10);
        $stmt->execute([$phone, $withPlus]);
        $orders = $stmt->fetchAll();
        foreach ($orders as &$o) {
            $o['items'] = self::getOrderItems($o['id']);
        }
        return $orders;
    }

    /**
     * Get all orders strictly belonging to a specific customer ID
     */
    public static function getByCustomerId(int $customerId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `customer_id` = ? ORDER BY `id` DESC");
        $stmt->execute([$customerId]);
        $orders = $stmt->fetchAll();
        foreach ($orders as &$o) {
            $o['items'] = self::getOrderItems($o['id']);
            $o['history'] = self::getOrderHistory($o['id']);
        }
        return $orders;
    }

    /**
     * Retrieve single order strictly checking ownership by authenticated customer
     */
    public static function getCustomerOrderById(int $customerId, int $orderId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ? AND `customer_id` = ? LIMIT 1");
        $stmt->execute([$orderId, $customerId]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = self::getOrderItems($order['id']);
            $order['history'] = self::getOrderHistory($order['id']);
            return $order;
        }
        return null;
    }

    public static function getOrderItems(int $orderId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `order_items` WHERE `order_id` = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public static function getOrderHistory(int $orderId): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM `order_status_history` WHERE `order_id` = ? ORDER BY `id` ASC");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $orderId, string $newStatus, string $comment = '', string $changedBy = 'Admin'): bool {
        $validStatuses = ['pending', 'confirmed', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $validStatuses)) {
            return false;
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Lock order row
            $stmtOrder = $pdo->prepare("SELECT `id`, `order_status`, `payment_method`, `payment_status` FROM `orders` WHERE `id` = ? FOR UPDATE");
            $stmtOrder->execute([$orderId]);
            $order = $stmtOrder->fetch();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $oldStatus = $order['order_status'];
            if ($oldStatus === $newStatus) {
                $pdo->commit();
                return true;
            }

            // Stock restoration rule: if order is cancelled and was not cancelled before, restore stock
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $items = self::getOrderItems($orderId);
                $stmtRestore = $pdo->prepare("UPDATE `products` SET `stock_quantity` = `stock_quantity` + ?, `updated_at` = NOW() WHERE `id` = ?");
                foreach ($items as $item) {
                    $stmtRestore->execute([$item['quantity'], $item['product_id']]);
                }
            }

            // If an order was cancelled and now changed back to confirmed/delivered, re-check and re-deduct
            if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
                $items = self::getOrderItems($orderId);
                $stmtDeduct = $pdo->prepare("UPDATE `products` SET `stock_quantity` = `stock_quantity` - ?, `updated_at` = NOW() WHERE `id` = ?");
                foreach ($items as $item) {
                    $stmtDeduct->execute([$item['quantity'], $item['product_id']]);
                }
            }

            // Update order status
            $paymentStatus = $order['payment_status'];
            if ($newStatus === 'delivered' && $order['payment_method'] === 'cod') {
                $paymentStatus = 'paid';
            }

            $stmtUpdate = $pdo->prepare("UPDATE `orders` SET `order_status` = ?, `payment_status` = ?, `updated_at` = NOW() WHERE `id` = ?");
            $stmtUpdate->execute([$newStatus, $paymentStatus, $orderId]);

            // Add history
            $stmtHist = $pdo->prepare("
                INSERT INTO `order_status_history` (`order_id`, `old_status`, `new_status`, `comment`, `changed_by`)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtHist->execute([$orderId, $oldStatus, $newStatus, $comment, $changedBy]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to update order status: " . $e->getMessage());
            return false;
        }
    }

    public static function getAll(array $filters = [], int $limit = 25, int $offset = 0): array {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "`order_status` = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(`order_number` LIKE ? OR `customer_name` LIKE ? OR `customer_phone` LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        if (!empty($filters['date_from'])) {
            $where[] = "`created_at` >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "`created_at` <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = "SELECT * FROM `orders`";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY `id` DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$o) {
            $o['items'] = self::getOrderItems($o['id']);
        }

        return $orders;
    }

    public static function countAll(array $filters = []): int {
        $pdo = Database::getConnection();
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "`order_status` = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(`order_number` LIKE ? OR `customer_name` LIKE ? OR `customer_phone` LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        if (!empty($filters['date_from'])) {
            $where[] = "`created_at` >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "`created_at` <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = "SELECT COUNT(*) FROM `orders`";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function getDashboardStats(): array {
        $pdo = Database::getConnection();

        // 1. Orders today
        $today = date('Y-m-d');
        $stmtToday = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE DATE(`created_at`) = ?");
        $stmtToday->execute([$today]);
        $ordersToday = (int)$stmtToday->fetchColumn();

        // 2. Sales today (Excluding cancelled orders)
        $stmtSalesToday = $pdo->prepare("SELECT COALESCE(SUM(`total_amount`), 0) FROM `orders` WHERE DATE(`created_at`) = ? AND `order_status` != 'cancelled'");
        $stmtSalesToday->execute([$today]);
        $salesToday = (float)$stmtSalesToday->fetchColumn();

        // 3. Status breakdown
        $stmtCounts = $pdo->query("SELECT `order_status`, COUNT(*) as cnt FROM `orders` GROUP BY `order_status`");
        $counts = ['pending' => 0, 'confirmed' => 0, 'delivered' => 0, 'cancelled' => 0];
        while ($r = $stmtCounts->fetch()) {
            if (isset($counts[$r['order_status']])) {
                $counts[$r['order_status']] = (int)$r['cnt'];
            }
        }

        // 4. Total revenue all time
        $stmtRev = $pdo->query("SELECT COALESCE(SUM(`total_amount`), 0) FROM `orders` WHERE `order_status` != 'cancelled'");
        $totalRevenue = (float)$stmtRev->fetchColumn();

        // 5. Total coconuts sold
        $stmtItemsSold = $pdo->query("SELECT COALESCE(SUM(oi.`quantity`), 0) FROM `order_items` oi JOIN `orders` o ON oi.`order_id` = o.`id` WHERE o.`order_status` != 'cancelled'");
        $totalCoconutsSold = (int)$stmtItemsSold->fetchColumn();

        // 6. 7-Day sales trend for Chart.js
        $trendDates = [];
        $trendSales = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $dLabel = date('D, M j', strtotime("-$i days"));
            $stmtD = $pdo->prepare("SELECT COALESCE(SUM(`total_amount`), 0) FROM `orders` WHERE DATE(`created_at`) = ? AND `order_status` != 'cancelled'");
            $stmtD->execute([$d]);
            $trendDates[] = $dLabel;
            $trendSales[] = (float)$stmtD->fetchColumn();
        }

        return [
            'orders_today' => $ordersToday,
            'sales_today' => $salesToday,
            'pending_orders' => $counts['pending'],
            'confirmed_orders' => $counts['confirmed'],
            'delivered_orders' => $counts['delivered'],
            'cancelled_orders' => $counts['cancelled'],
            'total_revenue' => $totalRevenue,
            'coconuts_sold' => $totalCoconutsSold,
            'trend_dates' => $trendDates,
            'trend_sales' => $trendSales
        ];
    }
}
