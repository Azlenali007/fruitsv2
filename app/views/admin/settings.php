<?php
/**
 * Admin Delivery & Business Settings View
 * N.A Fresh Fruits & Coconuts
 */
$adminTitle = 'Delivery Settings - N.A Fresh Fruits & Coconuts';
$activeAdminTab = 'settings';
require_once __DIR__ . '/layout-header.php';

$settings = Setting::getAll();
?>

<div class="max-w-3xl mx-auto space-y-6">
    
    <div>
        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Delivery & Store Settings</h1>
        <p class="text-xs text-gray-500">Configure local delivery parameters, pickup options, delivery fees, and service areas</p>
    </div>

    <form action="/admin/settings/update" method="POST" class="space-y-6">
        <input type="hidden" name="<?= CSRF_TOKEN_KEY ?>" value="<?= csrf_token() ?>">

        <!-- Fulfilment Methods Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Fulfilment Methods</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <label class="flex items-start gap-3 p-4 bg-gray-50 border border-gray-200 rounded-2xl cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="local_delivery_enabled" 
                        value="1" 
                        <?= ($settings['local_delivery_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                        class="w-4 h-4 text-brand-600 rounded-sm focus:ring-brand-500 mt-0.5"
                    >
                    <div>
                        <span class="font-bold text-xs text-gray-900 block">Enable Local Delivery</span>
                        <span class="text-[11px] text-gray-500">Allow customers to order for home/office doorstep delivery.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 bg-gray-50 border border-gray-200 rounded-2xl cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="shop_pickup_enabled" 
                        value="1" 
                        <?= ($settings['shop_pickup_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                        class="w-4 h-4 text-brand-600 rounded-sm focus:ring-brand-500 mt-0.5"
                    >
                    <div>
                        <span class="font-bold text-xs text-gray-900 block">Enable Shop Pickup</span>
                        <span class="text-[11px] text-gray-500">Allow customers to collect fresh tender coconuts at the shop.</span>
                    </div>
                </label>

            </div>
        </div>

        <!-- Charges & Minimum Order Thresholds -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Delivery Charges & Minimum Order</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Standard Delivery Fee (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="1" 
                            name="delivery_charge" 
                            value="<?= (int)($settings['delivery_charge'] ?? 40) ?>" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Default charge applied at checkout</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Minimum Order Amount (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="1" 
                            name="min_order_amount" 
                            value="<?= (int)($settings['min_order_amount'] ?? 40) ?>" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Minimum subtotal required to place delivery</span>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Free Delivery Above (₹)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 font-bold">₹</span>
                        <input 
                            type="number" 
                            step="1" 
                            name="free_delivery_above" 
                            value="<?= (int)($settings['free_delivery_above'] ?? 500) ?>" 
                            required 
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-3 py-2.5 font-bold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                        >
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1 block">Zero delivery fee when subtotal reaches this</span>
                </div>

            </div>

            <!-- Service Areas -->
            <div class="text-xs">
                <label class="block font-bold text-gray-700 uppercase mb-1">Supported Service Areas</label>
                <input 
                    type="text" 
                    name="service_areas" 
                    value="<?= e($settings['service_areas'] ?? 'Bhopal, MP Nagar, Arera Colony, Kolar Road, Green Park') ?>" 
                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-medium focus:ring-2 focus:ring-brand-600 focus:outline-none"
                >
                <span class="text-[10px] text-gray-400 mt-1 block">Comma-separated list of localities served</span>
            </div>

        </div>

        <!-- Store Information -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Business & Contact Info</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Store Name</label>
                    <input 
                        type="text" 
                        name="store_name" 
                        value="<?= e($settings['store_name'] ?? 'N.A Fresh Fruits & Coconuts') ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Contact Phone</label>
                    <input 
                        type="text" 
                        name="store_phone" 
                        value="<?= e($settings['store_phone'] ?? '+91 98765 43210') ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-semibold focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label class="block font-bold text-gray-700 uppercase mb-1">Store Address</label>
                    <input 
                        type="text" 
                        name="store_address" 
                        value="<?= e($settings['store_address'] ?? '123, Green Park, Near Main Road, Bhopal, Madhya Pradesh - 462016') ?>" 
                        required 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-medium focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Daily Opening Time</label>
                    <input 
                        type="text" 
                        name="store_open_time" 
                        value="<?= e($settings['store_open_time'] ?? '07:00 AM') ?>" 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>

                <div>
                    <label class="block font-bold text-gray-700 uppercase mb-1">Daily Closing Time</label>
                    <input 
                        type="text" 
                        name="store_close_time" 
                        value="<?= e($settings['store_close_time'] ?? '09:00 PM') ?>" 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs focus:ring-2 focus:ring-brand-600 focus:outline-none"
                    >
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-3 px-6 rounded-xl shadow-xs transition">
                Save All Settings
            </button>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/layout-footer.php'; ?>
