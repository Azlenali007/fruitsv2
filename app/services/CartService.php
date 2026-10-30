<?php
/**
 * Cart Service
 * N.A Fresh Fruits & Coconuts
 * Manages cart state in session while strictly validating real-time prices & stock from MySQL.
 */

class CartService {
    private const SESSION_KEY = 'na_cart';

    public static function getCart(): array {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $rawItems = $_SESSION[self::SESSION_KEY];
        $verifiedItems = [];
        $subtotal = 0.00;
        $totalItemsCount = 0;

        foreach ($rawItems as $productId => $qty) {
            $product = Product::getById((int)$productId);
            if (!$product || !$product['is_available'] || $product['stock_quantity'] <= 0) {
                // Remove item if no longer available
                unset($_SESSION[self::SESSION_KEY][$productId]);
                continue;
            }

            // Cap quantity to current available stock
            $safeQty = min((int)$qty, (int)$product['stock_quantity']);
            if ($safeQty <= 0) {
                unset($_SESSION[self::SESSION_KEY][$productId]);
                continue;
            }

            $unitPrice = (float)$product['price'];
            $lineTotal = $unitPrice * $safeQty;
            $subtotal += $lineTotal;
            $totalItemsCount += $safeQty;

            $verifiedItems[] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'tagline' => $product['tagline'],
                'price' => $unitPrice,
                'image_url' => $product['image_url'],
                'quantity' => $safeQty,
                'max_stock' => (int)$product['stock_quantity'],
                'subtotal' => $lineTotal
            ];
        }

        return [
            'items' => $verifiedItems,
            'count' => $totalItemsCount,
            'subtotal' => $subtotal
        ];
    }

    public static function addItem(int $productId, int $quantity = 1): array {
        $product = Product::getById($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }
        if (!$product['is_available'] || $product['stock_quantity'] <= 0) {
            return ['success' => false, 'error' => 'This product is currently out of stock.'];
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $currentQty = $_SESSION[self::SESSION_KEY][$productId] ?? 0;
        $newQty = $currentQty + $quantity;

        if ($newQty > $product['stock_quantity']) {
            $newQty = $product['stock_quantity'];
        }

        $_SESSION[self::SESSION_KEY][$productId] = max(1, $newQty);

        return ['success' => true, 'cart' => self::getCart()];
    }

    public static function updateQuantity(int $productId, int $quantity): array {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        if ($quantity <= 0) {
            return self::removeItem($productId);
        }

        $product = Product::getById($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }

        $safeQty = min($quantity, (int)$product['stock_quantity']);
        $_SESSION[self::SESSION_KEY][$productId] = $safeQty;

        return ['success' => true, 'cart' => self::getCart()];
    }

    public static function removeItem(int $productId): array {
        if (isset($_SESSION[self::SESSION_KEY][$productId])) {
            unset($_SESSION[self::SESSION_KEY][$productId]);
        }
        return ['success' => true, 'cart' => self::getCart()];
    }

    public static function clear(): void {
        $_SESSION[self::SESSION_KEY] = [];
    }

    public static function calculateCheckoutTotals(string $fulfilment = 'delivery'): array {
        $cart = self::getCart();
        $subtotal = $cart['subtotal'];
        $deliveryCharge = 0.00;

        if ($fulfilment === 'delivery' && $subtotal > 0) {
            $freeAbove = (float)Setting::get('free_delivery_above', '500.00');
            if ($subtotal >= $freeAbove) {
                $deliveryCharge = 0.00;
            } else {
                $deliveryCharge = (float)Setting::get('delivery_charge', '40.00');
            }
        }

        $total = $subtotal + $deliveryCharge;

        return [
            'subtotal' => $subtotal,
            'delivery_charge' => $deliveryCharge,
            'total' => $total,
            'items' => $cart['items'],
            'count' => $cart['count']
        ];
    }
}
