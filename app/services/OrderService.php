<?php
/**
 * Order Service
 * N.A Fresh Fruits & Coconuts
 * Enforces mandatory customer authentication, server-side validation, and transactional order creation.
 */

class OrderService {
    public static function processCheckout(array $postData): array {
        // 1. Mandatory Customer Authentication Check
        if (!CustomerAuthService::check()) {
            return [
                'success' => false,
                'auth_required' => true,
                'error' => 'Please log in or create an account to place your order.'
            ];
        }

        $customer = CustomerAuthService::user();
        if (!$customer || $customer['status'] !== 'active') {
            CustomerAuthService::logout();
            return [
                'success' => false,
                'auth_required' => true,
                'error' => 'Your customer account is currently inactive. Please log in again.'
            ];
        }

        // 2. CSRF Security Check
        if (!verify_csrf_token($postData[CSRF_TOKEN_KEY] ?? '')) {
            return [
                'success' => false,
                'error' => 'Security token expired. Please refresh and try again.'
            ];
        }

        // 3. Customer Info: prefill/confirm with authenticated account
        $name = trim($postData['customer_name'] ?? '');
        if (empty($name)) {
            $name = $customer['name'];
        }

        $phone = trim($postData['customer_phone'] ?? '');
        if (empty($phone)) {
            $phone = $customer['phone'];
        }

        $fulfilment = trim($postData['fulfilment_method'] ?? 'delivery');
        $address = trim($postData['delivery_address'] ?? '');
        $paymentMethod = trim($postData['payment_method'] ?? 'cod');

        if (empty($name) || mb_strlen($name) < 2) {
            return ['success' => false, 'error' => 'Please enter your full name.'];
        }

        // Clean Indian phone number
        $cleanPhone = Customer::normalizePhone($phone);
        if (strlen($cleanPhone) < 10) {
            return ['success' => false, 'error' => 'Please enter a valid 10-digit mobile number.'];
        }

        if ($fulfilment === 'delivery' && (empty($address) || mb_strlen($address) < 8)) {
            return ['success' => false, 'error' => 'Please provide a complete delivery address including street and locality.'];
        }

        if (!in_array($fulfilment, ['delivery', 'pickup'])) {
            $fulfilment = 'delivery';
        }

        if (!in_array($paymentMethod, ['cod', 'online'])) {
            $paymentMethod = 'cod';
        }

        // 4. Cart Validation
        $cart = CartService::getCart();
        if (empty($cart['items'])) {
            return ['success' => false, 'error' => 'Your cart is empty. Please add fresh coconut water before checking out.'];
        }

        // 5. Build order data strictly tying to authenticated customer ID
        $orderData = [
            'customer_id' => (int)$customer['id'], // Derived from secure server-side session
            'customer_name' => $name,
            'customer_phone' => '+91 ' . $cleanPhone,
            'fulfilment_method' => $fulfilment,
            'delivery_address' => $address,
            'payment_method' => $paymentMethod,
            'idempotency_key' => $postData['idempotency_key'] ?? null,
            'notes' => trim($postData['notes'] ?? '')
        ];

        $result = Order::createOrder($orderData, $cart['items']);

        if ($result['success']) {
            // Clear cart upon successful order
            CartService::clear();
        }

        return $result;
    }
}
