<?php
/**
 * Your Cart View - Exact Match to Reference Image Bottom Left Screen
 * N.A Fresh Fruits & Coconuts
 */
$pageTitle = 'Your Cart - N.A Fresh Fruits & Coconuts';
$activePage = 'cart';
require_once __DIR__ . '/layouts/header.php';

$cartData = CartService::getCart();
$items = $cartData['items'];
$subtotal = $cartData['subtotal'];
$deliveryCharge = $subtotal > 0 ? (float)Setting::get('delivery_charge', '40.00') : 0.00;
$total = $subtotal + $deliveryCharge;
?>

<div class="max-w-md mx-auto px-4 py-4 sm:py-6">
    
    <!-- Header with Back Arrow and Clear All -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <a href="/shop" class="p-1 text-gray-700 hover:text-brand-600 transition" aria-label="Back to shop">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h1 class="text-lg font-bold text-gray-900 tracking-tight">Your Cart</h1>
        </div>

        <?php if (!empty($items)): ?>
        <form action="/cart/clear" method="POST" onsubmit="return confirm('Clear all items from your cart?');">
            <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
            <button type="submit" class="text-xs font-semibold text-gray-500 hover:text-red-600 transition">
                Clear All
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (empty($items)): ?>
    <!-- Empty Cart State -->
    <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm text-center space-y-4 my-6">
        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-50 text-brand-600 flex items-center justify-center">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9z"></path>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900">Your Cart is Empty</h2>
        <p class="text-sm text-gray-500 max-w-xs mx-auto">
            You don't have any fresh coconut water in your cart yet. Stay healthy and hydrated!
        </p>
        <div class="pt-2">
            <a href="/shop" class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm px-6 py-3 rounded-xl shadow-xs transition">
                <span>Shop Fresh Coconuts</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
    </div>
    <?php else: ?>

    <!-- Cart Item List -->
    <div class="space-y-4 mb-6">
        <?php foreach ($items as $item): ?>
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-xs relative">
            <div class="flex items-center gap-4">
                
                <!-- Product Thumbnail -->
                <div class="w-20 h-20 rounded-xl bg-emerald-50/70 p-1 shrink-0 overflow-hidden border border-gray-100">
                    <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-cover rounded-lg">
                </div>

                <!-- Product Details & Controls -->
                <div class="flex-grow min-w-0 pr-8">
                    <h3 class="font-bold text-gray-900 text-sm sm:text-base truncate">
                        <?= e($item['name']) ?>
                    </h3>
                    <p class="text-xs font-semibold text-gray-500 mt-0.5">
                        ₹<?= number_format($item['price']) ?> <span class="text-gray-400 font-normal">/pc</span>
                    </p>

                    <!-- Stepper in Cart -->
                    <div class="flex items-center gap-3 mt-3">
                        <form action="/cart/update" method="POST" class="inline">
                            <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                            <input type="hidden" name="quantity" value="<?= $item['quantity'] - 1 ?>">
                            <button type="submit" class="w-7 h-7 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold flex items-center justify-center text-sm transition">
                                -
                            </button>
                        </form>

                        <span class="font-extrabold text-sm text-gray-900 w-4 text-center">
                            <?= $item['quantity'] ?>
                        </span>

                        <form action="/cart/update" method="POST" class="inline">
                            <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                            <input type="hidden" name="quantity" value="<?= $item['quantity'] + 1 ?>">
                            <button 
                                type="submit" 
                                <?= $item['quantity'] >= $item['max_stock'] ? 'disabled class="w-7 h-7 rounded-md bg-gray-50 text-gray-300 cursor-not-allowed flex items-center justify-center text-sm"' : 'class="w-7 h-7 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold flex items-center justify-center text-sm transition"' ?>
                            >
                                +
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Delete Trash Icon (Top Right of Card) -->
                <form action="/cart/remove" method="POST" class="absolute top-4 right-4">
                    <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                    <button type="submit" class="text-gray-400 hover:text-red-500 p-1 transition" title="Remove item">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </form>

                <!-- Line Total (Bottom Right) -->
                <div class="absolute bottom-4 right-4 text-right">
                    <span class="font-extrabold text-base text-gray-900">₹<?= number_format($item['subtotal']) ?></span>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pricing Summary Card -->
    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-xs space-y-3 mb-6">
        <div class="flex justify-between items-center text-sm text-gray-500">
            <span>Subtotal</span>
            <span class="font-bold text-gray-900">₹<?= number_format($subtotal) ?></span>
        </div>
        <div class="flex justify-between items-center text-sm text-gray-500">
            <span>Delivery Charge</span>
            <span class="font-bold text-gray-900">₹<?= number_format($deliveryCharge) ?></span>
        </div>
        <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
            <span class="font-extrabold text-base text-gray-900">Total</span>
            <span class="font-black text-2xl text-gray-950">₹<?= number_format($total) ?></span>
        </div>
    </div>

    <!-- Proceed to Checkout Button -->
    <div class="mb-6">
        <a href="/checkout" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-base py-3.5 px-6 rounded-xl flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition active:scale-[0.99]">
            <span>Proceed to Checkout</span>
        </a>
    </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
