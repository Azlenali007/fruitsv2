<?php
/**
 * Checkout View - Exact Match to Reference Image Bottom Middle Screen
 * N.A Fresh Fruits & Coconuts
 */
$pageTitle = 'Checkout - N.A Fresh Fruits & Coconuts';
$activePage = 'cart';
require_once __DIR__ . '/layouts/header.php';

$cartData = CartService::getCart();
if (empty($cartData['items'])) {
    header('Location: /cart');
    exit;
}

// 1. Mandatory Customer Authentication Check
CustomerAuthService::requireLogin('/checkout');
$customer = CustomerAuthService::user();

$subtotal = (float)$cartData['subtotal'];
$deliveryEnabled = Setting::get('local_delivery_enabled', '1') === '1';
$pickupEnabled = Setting::get('shop_pickup_enabled', '1') === '1';
$defaultDeliveryCharge = (float)Setting::get('delivery_charge', '40.00');

$totals = CartService::calculateCheckoutTotals($deliveryEnabled ? 'delivery' : 'pickup', (int)$customer['id'], $customer['phone'] ?? '');
$appliedCoupon = $totals['coupon'];
$couponCalc = $totals['coupon_calculation'];
$discountAmount = (float)$totals['discount_amount'];
$deliveryDiscount = (float)$totals['delivery_discount'];
$effectiveDelivery = (float)$totals['delivery_charge'];

// Initial customer details from verified authenticated session
$savedName = $customer['name'] ?? ($_SESSION['last_customer_name'] ?? '');
$savedPhone = $customer['phone'] ?? ($_SESSION['last_customer_phone'] ?? '');
$savedAddress = !empty($customer['address']) ? $customer['address'] : ($_SESSION['last_customer_address'] ?? '123, Green Park, Near Main Road, Bhopal, Madhya Pradesh - 462016');
$idempotencyKey = bin2hex(random_bytes(16));
?>

<div class="max-w-md mx-auto px-4 py-4 sm:py-6" x-data="{
    fulfilment: '<?= $deliveryEnabled ? 'delivery' : 'pickup' ?>',
    paymentMethod: 'cod',
    editAddress: false,
    customerName: '<?= e($savedName) ?>',
    customerPhone: '<?= e($savedPhone) ?>',
    customerAddress: '<?= e($savedAddress) ?>',
    subtotal: <?= (float)$subtotal ?>,
    defaultDeliveryFee: <?= (float)$defaultDeliveryCharge ?>,
    discountAmount: <?= (float)$discountAmount ?>,
    deliveryDiscount: <?= (float)$deliveryDiscount ?>,
    couponType: '<?= e($appliedCoupon['type'] ?? '') ?>',
    couponCode: '<?= e($appliedCoupon['code'] ?? '') ?>',
    get effectiveDeliveryFee() {
        if (this.fulfilment === 'pickup') return 0;
        if (this.subtotal >= 500) return 0;
        return Math.max(0, this.defaultDeliveryFee - this.deliveryDiscount);
    },
    get total() {
        const netSubtotal = Math.max(0, this.subtotal - this.discountAmount);
        return (netSubtotal + this.effectiveDeliveryFee).toFixed(2);
    }
}">

    <!-- Flash Notification Banner -->
    <?php $flash = get_flash(); if ($flash): ?>
    <div class="mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= $flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : ($flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-blue-50 text-blue-800 border border-blue-200') ?>">
        <?php if ($flash['type'] === 'error'): ?>
        <svg class="w-4 h-4 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?php elseif ($flash['type'] === 'success'): ?>
        <svg class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <?php else: ?>
        <svg class="w-4 h-4 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?php endif; ?>
        <span><?= e($flash['message']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Header with Back Arrow -->
    <div class="flex items-center gap-3 mb-5">
        <a href="/cart" class="p-1 text-gray-700 hover:text-brand-600 transition" aria-label="Back to cart">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <h1 class="text-lg font-bold text-gray-900 tracking-tight">Checkout</h1>
    </div>

    <form action="/checkout/place" method="POST" class="space-y-6">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
        <input type="hidden" name="idempotency_key" value="<?= $idempotencyKey ?>">

        <!-- Fulfilment Method Selector (Delivery vs Shop Pickup) -->
        <div class="bg-gray-100 p-1 rounded-xl grid grid-cols-2 gap-1 text-sm font-semibold">
            <button 
                type="button" 
                @click="fulfilment = 'delivery'"
                :class="fulfilment === 'delivery' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800'"
                class="py-2.5 rounded-lg transition text-center"
            >
                Local Delivery
            </button>
            <button 
                type="button" 
                @click="fulfilment = 'pickup'"
                :class="fulfilment === 'pickup' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800'"
                class="py-2.5 rounded-lg transition text-center"
            >
                Shop Pickup
            </button>
        </div>
        <input type="hidden" name="fulfilment_method" :value="fulfilment">

        <!-- SECTION 1: Delivery Address (Matches reference image card with green dot & edit link) -->
        <div class="space-y-3" x-show="fulfilment === 'delivery'">
            <div class="flex items-center gap-2 text-sm font-bold text-gray-800">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>Delivery Address</span>
            </div>

            <!-- Address Card -->
            <div class="bg-white rounded-2xl p-4 border border-gray-200/90 shadow-xs relative">
                
                <!-- Display Mode -->
                <div x-show="!editAddress" class="flex items-start gap-3">
                    <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></div>
                    <div class="flex-grow space-y-1 pr-12">
                        <p class="font-bold text-sm text-gray-900" x-text="customerName"></p>
                        <p class="text-xs text-gray-500 font-medium" x-text="customerPhone"></p>
                        <p class="text-xs text-gray-600 leading-relaxed pt-0.5" x-text="customerAddress"></p>
                    </div>
                    <button 
                        type="button" 
                        @click="editAddress = true"
                        class="absolute top-4 right-4 text-xs font-semibold text-brand-600 hover:text-brand-700 underline"
                    >
                        Edit
                    </button>
                </div>

                <!-- Edit Mode Form Fields -->
                <div x-show="editAddress" x-cloak class="space-y-3 pt-1">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Your Full Name</label>
                        <input type="text" x-model="customerName" name="customer_name" required class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Phone Number (10 Digits)</label>
                        <input type="tel" x-model="customerPhone" name="customer_phone" required class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">Complete Delivery Address</label>
                        <textarea x-model="customerAddress" name="delivery_address" rows="2" required class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="editAddress = false" class="bg-brand-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-brand-700">
                            Done
                        </button>
                    </div>
                </div>

                <!-- Hidden inputs to submit current values if not editing -->
                <template x-if="!editAddress">
                    <div>
                        <input type="hidden" name="customer_name" :value="customerName">
                        <input type="hidden" name="customer_phone" :value="customerPhone">
                        <input type="hidden" name="delivery_address" :value="customerAddress">
                    </div>
                </template>

            </div>
        </div>

        <!-- In case of Pickup, show pickup customer name and contact -->
        <div class="space-y-3" x-show="fulfilment === 'pickup'" x-cloak>
            <div class="flex items-center gap-2 text-sm font-bold text-gray-800">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <span>Store Pickup Location</span>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-gray-200 shadow-xs space-y-2">
                <p class="font-bold text-xs text-gray-900"><?= e(Setting::get('store_name', 'N.A Fresh Fruits & Coconuts')) ?></p>
                <p class="text-xs text-gray-500 leading-relaxed"><?= e(Setting::get('store_address', '123, Green Park, Near Main Road, Bhopal')) ?></p>
                <p class="text-[11px] font-semibold text-brand-600">Open: <?= e(Setting::get('store_open_time', '07:00 AM')) ?> - <?= e(Setting::get('store_close_time', '09:00 PM')) ?></p>
            </div>
        </div>

        <!-- SECTION 2: Promotional Coupon Code -->
        <div class="space-y-3">
            <div class="flex items-center gap-2 text-sm font-bold text-gray-800">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                <span>Coupon & Offers</span>
            </div>

            <div class="bg-white rounded-2xl p-4 border border-gray-200/90 shadow-xs">
                <?php if ($appliedCoupon): ?>
                    <!-- Applied Coupon display -->
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center justify-between gap-3">
                        <div class="space-y-0.5 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-black text-xs text-brand-900 tracking-wider bg-white px-2 py-0.5 rounded border border-emerald-300">
                                    <?= e($appliedCoupon['code']) ?>
                                </span>
                                <span class="text-[11px] font-bold text-emerald-800">
                                    <?= e($couponCalc['benefit_message'] ?? 'Applied') ?>
                                </span>
                            </div>
                            <p class="text-[10px] text-emerald-700 truncate"><?= e($appliedCoupon['name']) ?></p>
                        </div>
                        <button 
                            type="button" 
                            @click="document.getElementById('removeCheckoutCouponForm').submit()" 
                            class="text-xs font-bold text-red-600 hover:text-red-700 hover:underline shrink-0"
                        >
                            Remove
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Coupon Input inside checkout -->
                    <div class="flex gap-2">
                        <input 
                            type="text" 
                            id="checkoutCouponInput"
                            placeholder="Enter code (e.g. FREEDELIVERY)" 
                            oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_\-]/g, '')"
                            class="flex-grow bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono font-bold tracking-wider uppercase focus:ring-2 focus:ring-brand-500 focus:outline-none"
                        >
                        <button 
                            type="button" 
                            onclick="applyCheckoutCoupon()" 
                            class="bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition shadow-xs shrink-0"
                        >
                            Apply
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1.5">Try: <code class="text-brand-700 font-bold">FREEDELIVERY</code>, <code class="text-brand-700 font-bold">SAVE10</code>, or <code class="text-brand-700 font-bold">FLAT50</code></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hidden input for coupon_code in order form -->
        <input type="hidden" name="coupon_code" :value="couponCode">

        <!-- SECTION 3: Payment Method (Matches reference image cards layout) -->
        <div class="space-y-3">
            <div class="flex items-center gap-2 text-sm font-bold text-gray-800">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                    <line x1="2" x2="22" y1="10" y2="10"></line>
                </svg>
                <span>Payment Method</span>
            </div>

            <div class="space-y-2.5">
                
                <!-- Option 1: Cash on Delivery (Checked by default in reference) -->
                <label 
                    class="flex items-center justify-between p-3.5 rounded-2xl border transition cursor-pointer"
                    :class="paymentMethod === 'cod' ? 'bg-white border-brand-600 ring-1 ring-brand-600 shadow-xs' : 'bg-white border-gray-200 hover:border-gray-300'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full flex items-center justify-center border"
                             :class="paymentMethod === 'cod' ? 'bg-brand-600 border-brand-600 text-white' : 'border-gray-300'">
                            <svg x-show="paymentMethod === 'cod'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                💵
                            </div>
                            <div>
                                <p class="font-bold text-xs sm:text-sm text-gray-900 leading-none">Cash on Delivery</p>
                                <p class="text-[11px] text-gray-400 mt-1">Pay when you receive your order</p>
                            </div>
                        </div>
                    </div>
                    <input type="radio" name="payment_method" value="cod" x-model="paymentMethod" class="sr-only">
                </label>

                <!-- Option 2: Online Payment -->
                <label 
                    class="flex items-center justify-between p-3.5 rounded-2xl border transition cursor-pointer"
                    :class="paymentMethod === 'online' ? 'bg-white border-brand-600 ring-1 ring-brand-600 shadow-xs' : 'bg-white border-gray-200 hover:border-gray-300'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full flex items-center justify-center border"
                             :class="paymentMethod === 'online' ? 'bg-brand-600 border-brand-600 text-white' : 'border-gray-300'">
                            <svg x-show="paymentMethod === 'online'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                💳
                            </div>
                            <div>
                                <p class="font-bold text-xs sm:text-sm text-gray-900 leading-none">Online Payment</p>
                                <p class="text-[11px] text-gray-400 mt-1">UPI / Card / Wallet</p>
                            </div>
                        </div>
                    </div>
                    <input type="radio" name="payment_method" value="online" x-model="paymentMethod" class="sr-only">
                </label>

            </div>
        </div>

        <!-- Order Total Preview -->
        <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 text-xs text-gray-600 space-y-2">
            <div class="flex justify-between">
                <span>Items Subtotal:</span>
                <span class="font-bold text-gray-900">₹<?= number_format($subtotal, 2) ?></span>
            </div>

            <!-- Discount Row -->
            <div class="flex justify-between text-emerald-700 font-semibold" x-show="discountAmount > 0" x-cloak>
                <span>Coupon Discount (<span x-text="couponCode"></span>):</span>
                <span>-₹<span x-text="discountAmount.toFixed(2)"></span></span>
            </div>

            <!-- Delivery Row -->
            <div class="flex justify-between items-center" x-show="fulfilment === 'delivery'">
                <span>Delivery Charge:</span>
                <div>
                    <template x-if="deliveryDiscount > 0">
                        <span class="space-x-1">
                            <span class="line-through text-gray-400">₹<span x-text="defaultDeliveryFee.toFixed(2)"></span></span>
                            <span class="font-bold text-emerald-700">FREE</span>
                        </span>
                    </template>
                    <template x-if="deliveryDiscount <= 0">
                        <span class="font-bold text-gray-900">₹<span x-text="effectiveDeliveryFee.toFixed(2)"></span></span>
                    </template>
                </div>
            </div>

            <!-- Final Payable Total -->
            <div class="flex justify-between items-baseline text-sm font-extrabold text-gray-950 pt-2 border-t border-gray-200">
                <div>
                    <span>Final Payable:</span>
                    <?php if ($totals['total_savings'] > 0): ?>
                    <span class="text-[10px] text-emerald-600 font-bold block">Saved ₹<?= number_format($totals['total_savings'], 2) ?> with coupon</span>
                    <?php endif; ?>
                </div>
                <span class="text-brand-700 text-lg font-black" x-text="'₹' + total"></span>
            </div>
        </div>

        <!-- Place Order Button (Matches reference bottom green button) -->
        <div class="pt-2">
            <button 
                type="submit" 
                class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-base py-3.5 px-6 rounded-xl flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition active:scale-[0.99]"
            >
                <span>Place Order</span>
            </button>
        </div>

    </form>

    <!-- Hidden standalone form for removing coupon -->
    <form id="removeCheckoutCouponForm" action="/cart/remove-coupon" method="POST" class="hidden">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
        <input type="hidden" name="redirect_to" value="/checkout">
    </form>

    <!-- Hidden standalone form for applying coupon -->
    <form id="applyCheckoutCouponForm" action="/cart/apply-coupon" method="POST" class="hidden">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">
        <input type="hidden" name="coupon_code" id="hiddenCouponCode">
        <input type="hidden" name="redirect_to" value="/checkout">
    </form>

    <script>
    function applyCheckoutCoupon() {
        const val = document.getElementById('checkoutCouponInput').value.trim();
        if (!val) {
            alert('Please enter a coupon code.');
            return;
        }
        document.getElementById('hiddenCouponCode').value = val;
        document.getElementById('applyCheckoutCouponForm').submit();
    }
    </script>

</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
