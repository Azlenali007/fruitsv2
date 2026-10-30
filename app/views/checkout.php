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

$subtotal = $cartData['subtotal'];
$deliveryEnabled = Setting::get('local_delivery_enabled', '1') === '1';
$pickupEnabled = Setting::get('shop_pickup_enabled', '1') === '1';
$defaultDeliveryCharge = (float)Setting::get('delivery_charge', '40.00');

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
    deliveryFee: <?= (float)$defaultDeliveryCharge ?>,
    get total() {
        return this.fulfilment === 'delivery' ? (this.subtotal + this.deliveryFee) : this.subtotal;
    }
}">

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

        <!-- SECTION 2: Payment Method (Matches reference image cards layout) -->
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
        <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 text-xs text-gray-600 space-y-1.5">
            <div class="flex justify-between">
                <span>Items Subtotal:</span>
                <span class="font-bold text-gray-900">₹<?= number_format($subtotal) ?></span>
            </div>
            <div class="flex justify-between" x-show="fulfilment === 'delivery'">
                <span>Delivery Charge:</span>
                <span class="font-bold text-gray-900">₹<?= number_format($defaultDeliveryCharge) ?></span>
            </div>
            <div class="flex justify-between text-sm font-extrabold text-gray-950 pt-1 border-t border-gray-200">
                <span>Final Payable:</span>
                <span class="text-brand-700 text-base" x-text="'₹' + total"></span>
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

</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
