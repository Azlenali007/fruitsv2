<?php
/**
 * Automated Verification & Testing Suite for Coupons & WhatsApp Popup Modules
 * N.A Fresh Fruits & Coconuts
 */
require_once __DIR__ . '/../app/bootstrap.php';

$pdo = Database::getConnection();
$cPass = 0;
$cFail = 0;

function it_c(string $testName, bool $condition, string $detail = '') {
    global $cPass, $cFail;
    if ($condition) {
        $cPass++;
        echo "  [PASS] {$testName}\n";
    } else {
        $cFail++;
        echo "  [FAIL] {$testName} - {$detail}\n";
    }
}

echo "========================================================\n";
echo "RUNNING COUPONS & WHATSAPP POPUP TESTS\n";
echo "========================================================\n\n";

// ---------------------------------------------------------
// 1. COUPON CODE NORMALIZATION & LOOKUP
// ---------------------------------------------------------
echo "1. COUPON NORMALIZATION & RETRIEVAL:\n";
it_c("Normalize lowercase to uppercase", Coupon::normalizeCode('save10') === 'SAVE10');
it_c("Normalize whitespace and special characters", Coupon::normalizeCode('  hydra_50!  ') === 'HYDRA_50');

$freeDelivery = Coupon::findByCode('freedelivery');
it_c("Case-insensitive lookup for FREEDELIVERY", $freeDelivery !== null && $freeDelivery['code'] === 'FREEDELIVERY');
it_c("FREEDELIVERY is of type free_delivery", $freeDelivery['type'] === 'free_delivery');

// ---------------------------------------------------------
// 2. COUPON CREATION & VALIDATION CONSTRAINTS
// ---------------------------------------------------------
echo "\n2. CREATION & VALIDATION CONSTRAINTS:\n";
$testCode = 'TESTPROMO_' . time();

$createRes = Coupon::create([
    'code' => $testCode,
    'name' => 'Automated Test Promo',
    'type' => 'percentage',
    'discount_value' => 20.00,
    'min_order_amount' => 100.00,
    'max_discount_amount' => 30.00,
    'max_uses' => 50,
    'max_uses_per_customer' => 2,
    'is_active' => 1
]);
it_c("Valid coupon created successfully", $createRes['success'] === true);

// Duplicate code rejection
$dupRes = Coupon::create([
    'code' => $testCode,
    'name' => 'Duplicate Attempt',
    'type' => 'percentage',
    'discount_value' => 10.00
]);
it_c("Duplicate coupon code rejected", $dupRes['success'] === false);

// Invalid percentage checks
$badPct = Coupon::create([
    'code' => 'BADPCT_' . time(),
    'name' => 'Bad Percent',
    'type' => 'percentage',
    'discount_value' => 150.00 // > 100%
]);
it_c("Percentage > 100% rejected", $badPct['success'] === false);

// Short code rejection
$shortCode = Coupon::create([
    'code' => 'AB',
    'name' => 'Too Short',
    'type' => 'fixed',
    'discount_value' => 10.00
]);
it_c("Coupon code shorter than 3 chars rejected", $shortCode['success'] === false);

// ---------------------------------------------------------
// 3. DISCOUNT CALCULATION ACCURACY
// ---------------------------------------------------------
echo "\n3. DISCOUNT CALCULATION TESTS:\n";

// A. Free delivery coupon
$freeDeliveryCalc = Coupon::calculateDiscount($freeDelivery, 120.00, 40.00);
it_c("Free delivery waives ₹40 delivery fee", (float)$freeDeliveryCalc['delivery_discount'] === 40.00);
it_c("Free delivery does not modify items subtotal", (float)$freeDeliveryCalc['discount_amount'] === 0.00);
it_c("Free delivery final total equals subtotal ₹120", (float)$freeDeliveryCalc['final_total'] === 120.00);

// B. Percentage coupon with cap
$pctCoupon = [
    'id' => 999,
    'code' => 'TESTPCT',
    'type' => 'percentage',
    'discount_value' => 25.00, // 25% of ₹200 = ₹50, capped at ₹30
    'max_discount_amount' => 30.00
];
$pctCalc = Coupon::calculateDiscount($pctCoupon, 200.00, 40.00);
it_c("25% on ₹200 is capped at ₹30.00", (float)$pctCalc['discount_amount'] === 30.00);
it_c("Percentage coupon does not waive delivery fee", (float)$pctCalc['effective_delivery_charge'] === 40.00);
it_c("Percentage final total is (200 - 30) + 40 = ₹210", (float)$pctCalc['final_total'] === 210.00);

// C. Fixed amount coupon
$fixedCoupon = [
    'id' => 998,
    'code' => 'TESTFIXED',
    'type' => 'fixed',
    'discount_value' => 50.00
];
$fixedCalc = Coupon::calculateDiscount($fixedCoupon, 120.00, 40.00);
it_c("Flat ₹50 discount deducted from ₹120 subtotal = ₹50", (float)$fixedCalc['discount_amount'] === 50.00);
it_c("Fixed final total is (120 - 50) + 40 = ₹110", (float)$fixedCalc['final_total'] === 110.00);

// D. Fixed amount larger than subtotal (Never negative)
$overflowCalc = Coupon::calculateDiscount($fixedCoupon, 30.00, 40.00);
it_c("Fixed discount capped at subtotal so payable subtotal is not negative", (float)$overflowCalc['discount_amount'] === 30.00);

// ---------------------------------------------------------
// 4. BUSINESS RULE VALIDATION
// ---------------------------------------------------------
echo "\n4. BUSINESS RULE ENFORCEMENT:\n";

// Minimum order amount
$minOrderCheck = Coupon::validateCoupon('FLAT50', 80.00); // FLAT50 requires ₹200 min
it_c("Coupon below min_order_amount is rejected", $minOrderCheck['valid'] === false);

// Inactive coupon
$inactiveCode = 'INACTIVE_' . time();
Coupon::create([
    'code' => $inactiveCode,
    'name' => 'Inactive Coupon',
    'type' => 'fixed',
    'discount_value' => 20.00,
    'is_active' => 0
]);
$inactiveVal = Coupon::validateCoupon($inactiveCode, 150.00);
it_c("Disabled coupon is rejected", $inactiveVal['valid'] === false);

// Expired coupon
$expiredCode = 'EXPIRED_' . time();
Coupon::create([
    'code' => $expiredCode,
    'name' => 'Expired Coupon',
    'type' => 'fixed',
    'discount_value' => 20.00,
    'expiry_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
    'is_active' => 1
]);
$expiredVal = Coupon::validateCoupon($expiredCode, 150.00);
it_c("Expired coupon is rejected", $expiredVal['valid'] === false);

// ---------------------------------------------------------
// 5. CART SERVICE & SESSION COUPON WORKFLOW
// ---------------------------------------------------------
echo "\n5. CART SERVICE COUPON INTEGRATION:\n";
CartService::clear();
$prod = Product::getPrimary();
CartService::addItem($prod['id'], 3); // 3 * ₹40 = ₹120 subtotal

// Apply SAVE10
$applyRes = CartService::applyCoupon('SAVE10');
it_c("CartService::applyCoupon('SAVE10') succeeds", $applyRes['success'] === true);

$cartTotals = CartService::calculateCheckoutTotals('delivery');
it_c("Cart totals include applied coupon SAVE10", !empty($cartTotals['coupon']) && $cartTotals['coupon']['code'] === 'SAVE10');
it_c("10% discount on ₹120 equals ₹12.00", (float)$cartTotals['discount_amount'] === 12.00);
it_c("Cart total reflects discount: (120 - 12) + 40 = ₹148.00", (float)$cartTotals['total'] === 148.00);

// Remove coupon
CartService::removeCoupon();
$clearedTotals = CartService::calculateCheckoutTotals('delivery');
it_c("CartService::removeCoupon() clears discount", (float)$clearedTotals['discount_amount'] === 0.00);
it_c("Cleared cart total restored to ₹160.00", (float)$clearedTotals['total'] === 160.00);

// ---------------------------------------------------------
// 6. ORDER TRANSACTION & USAGE AUDIT RECORDING
// ---------------------------------------------------------
echo "\n6. ORDER CREATION WITH COUPON & ATOMIC AUDIT:\n";
$uniqueRunPhone = '+91 91' . substr(str_pad((string)time() . rand(10, 99), 8, '0', STR_PAD_LEFT), -8);
$couponOrderData = [
    'customer_name' => 'Coupon Test Customer',
    'customer_phone' => $uniqueRunPhone,
    'fulfilment_method' => 'delivery',
    'delivery_address' => '45 Mango Grove, Bhopal',
    'payment_method' => 'cod',
    'coupon_code' => 'FREEDELIVERY',
    'idempotency_key' => 'coupon_order_' . uniqid()
];

$items = [
    ['product_id' => $prod['id'], 'quantity' => 3] // ₹120 subtotal (>= ₹80 min for FREEDELIVERY)
];

$initialUses = (int)Coupon::findByCode('FREEDELIVERY')['current_uses'];

$orderRes = Order::createOrder($couponOrderData, $items);
it_c("Order with FREEDELIVERY created successfully", $orderRes['success'] === true, $orderRes['error'] ?? '');

$placedOrder = Order::getById($orderRes['order_id']);
it_c("Order records coupon_code as 'FREEDELIVERY'", $placedOrder['coupon_code'] === 'FREEDELIVERY');
it_c("Order records original_delivery_charge as 40.00", (float)$placedOrder['original_delivery_charge'] === 40.00);
it_c("Order records delivery_discount as 40.00", (float)$placedOrder['delivery_discount'] === 40.00);
it_c("Order net delivery_charge is 0.00", (float)$placedOrder['delivery_charge'] === 0.00);
it_c("Order total amount is ₹120.00", (float)$placedOrder['total_amount'] === 120.00);

// Verify coupon_usage table
$stmtUsage = $pdo->prepare("SELECT * FROM `coupon_usage` WHERE `order_id` = ?");
$stmtUsage->execute([$placedOrder['id']]);
$usageRow = $stmtUsage->fetch();
it_c("Record inserted in coupon_usage audit table", $usageRow !== false);
it_c("Usage record tracks delivery_discount of 40.00", (float)$usageRow['delivery_discount'] === 40.00);

// Verify atomic increment of current_uses
$newUses = (int)Coupon::findByCode('FREEDELIVERY')['current_uses'];
it_c("Coupon current_uses atomically incremented from {$initialUses} to {$newUses}", $newUses === ($initialUses + 1));

// Per-customer limit test with max 1 use
$perCustTestCode = 'PERCUST_' . time();
Coupon::create([
    'code' => $perCustTestCode,
    'name' => 'Single Use Per Customer',
    'type' => 'fixed',
    'discount_value' => 10.00,
    'max_uses_per_customer' => 1,
    'is_active' => 1
]);

$custPhone = '+91 97' . substr(str_pad((string)time() . rand(10, 99), 8, '0', STR_PAD_LEFT), -8);
// 1st order should pass
$ord1 = Order::createOrder([
    'customer_name' => 'Lim Customer',
    'customer_phone' => $custPhone,
    'fulfilment_method' => 'delivery',
    'delivery_address' => 'Road 1, Bhopal',
    'coupon_code' => $perCustTestCode,
    'idempotency_key' => 'lim_ord_1_' . uniqid()
], [['product_id' => $prod['id'], 'quantity' => 1]]);
it_c("First order under per-customer limit passes", $ord1['success'] === true, $ord1['error'] ?? '');

// 2nd order should be rejected due to per-customer limit
$ord2 = Order::createOrder([
    'customer_name' => 'Lim Customer',
    'customer_phone' => $custPhone,
    'fulfilment_method' => 'delivery',
    'delivery_address' => 'Road 1, Bhopal',
    'coupon_code' => $perCustTestCode,
    'idempotency_key' => 'lim_ord_2_' . uniqid()
], [['product_id' => $prod['id'], 'quantity' => 1]]);
it_c("Second order by same customer exceeds limit and is rejected", $ord2['success'] === false);

// ---------------------------------------------------------
// 7. WHATSAPP CHANNEL POPUP CONFIGURATION & SETTINGS
// ---------------------------------------------------------
echo "\n7. WHATSAPP CHANNEL POPUP TESTS:\n";
it_c("WhatsApp popup setting exists and enabled", Setting::get('whatsapp_popup_enabled') === '1');
$waUrl = Setting::get('whatsapp_channel_url');
it_c("WhatsApp Channel URL is a valid HTTPS link", str_starts_with($waUrl, 'https://'));
it_c("WhatsApp Popup heading is configured", !empty(Setting::get('whatsapp_popup_heading')));
it_c("WhatsApp Popup frequency is configured", in_array(Setting::get('whatsapp_popup_frequency'), ['session', 'daily', 'weekly', 'always']));

// Update settings test
Setting::setMany([
    'whatsapp_popup_frequency' => 'daily',
    'whatsapp_popup_button_text' => 'Join VIP Channel'
]);
it_c("WhatsApp popup frequency updated to 'daily'", Setting::get('whatsapp_popup_frequency') === 'daily');
it_c("WhatsApp popup button text updated", Setting::get('whatsapp_popup_button_text') === 'Join VIP Channel');

// Revert to default session frequency
Setting::set('whatsapp_popup_frequency', 'session');
Setting::set('whatsapp_popup_button_text', 'Join WhatsApp Channel');

echo "\n========================================================\n";
echo "COUPONS & WHATSAPP SUITE SUMMARY: {$cPass} PASSED, {$cFail} FAILED\n";
echo "========================================================\n";

if ($cFail > 0) {
    exit(1);
}
