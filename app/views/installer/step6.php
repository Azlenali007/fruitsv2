<?php
$currentStep = 6;
$installerTitle = 'Installation Completed - N.A Installer';
ob_start();
?>

<div class="space-y-6 text-center">
    
    <!-- Big Checkmark -->
    <div class="w-20 h-20 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>

    <div class="space-y-1">
        <h2 class="text-2xl font-black text-gray-950 tracking-tight">Installation Completed Successfully!</h2>
        <p class="text-xs text-gray-500 max-w-md mx-auto leading-relaxed">
            N.A Fresh Fruits & Coconuts has been installed, initialized with MySQL, and locked for security.
        </p>
    </div>

    <!-- Verification Checklist -->
    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 text-left text-xs space-y-2.5">
        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">System Verification Status</span>
        <?php foreach ($validation as $v): ?>
        <div class="flex items-center justify-between">
            <span class="font-bold text-gray-800"><?= e($v['name']) ?></span>
            <span class="text-emerald-700 font-bold flex items-center gap-1 text-[11px]">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                Verified
            </span>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-800 text-left">
        <span class="font-bold block">Security Lock Created</span>
        <span class="text-[11px] text-emerald-700">The installer lock file <code class="font-mono font-bold">storage/installed.lock</code> has been created. All installer routes are now permanently locked.</span>
    </div>

    <!-- Launch Buttons -->
    <div class="pt-2 flex flex-col sm:flex-row gap-3 justify-center">
        <a href="/" class="bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm py-3 px-6 rounded-xl shadow-xs transition flex items-center justify-center gap-2">
            <span>Visit Live Storefront</span>
            &rarr;
        </a>

        <a href="/admin" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-sm py-3 px-6 rounded-xl transition flex items-center justify-center gap-2">
            <span>Log In to Store Admin</span>
        </a>
    </div>

</div>

<?php
$stepContent = ob_get_clean();
require __DIR__ . '/layout.php';
