</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-600">
            <div class="mb-4 md:mb-0">
                <p>&copy; <?= date('Y') ?> Mini Threads. All rights reserved.</p>
            </div>
            <div class="flex space-x-6">
                <a href="#" class="hover:text-blue-500">About</a>
                <a href="#" class="hover:text-blue-500">Privacy</a>
                <a href="#" class="hover:text-blue-500">Terms</a>
                <a href="#" class="hover:text-blue-500">Contact</a>
            </div>
        </div>
    </div>
</footer>

<!-- Mobile Bottom Navigation -->
<!-- Mobile Bottom Navigation -->
<div class="md:hidden fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-md border-t border-gray-200 flex justify-around items-center py-2 z-50 pb-safe shadow-[0_-1px_3px_rgba(0,0,0,0.05)]">
    <a href="index.php" class="flex flex-col items-center p-2 <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-black' : 'text-gray-400 hover:text-gray-600' ?>">
        <svg class="w-7 h-7" fill="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
        </svg>
    </a>
    
    <a href="search.php" class="flex flex-col items-center p-2 <?= basename($_SERVER['PHP_SELF']) == 'search.php' ? 'text-black' : 'text-gray-400 hover:text-gray-600' ?>">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="<?= basename($_SERVER['PHP_SELF']) == 'search.php' ? '3' : '2' ?>" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </a>

    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Post Button (Center) -->
        <a href="index.php#threadContent" onclick="document.getElementById('threadContent').focus()" class="bg-black text-white p-3 rounded-xl shadow-lg hover:bg-gray-800 transition transform hover:scale-105 mx-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
        </a>

        <a href="notifications.php" class="relative flex flex-col items-center p-2 <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'text-black' : 'text-gray-400 hover:text-gray-600' ?>">
            <svg class="w-7 h-7" fill="<?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <span id="mobileNotifBadge" class="hidden absolute top-1 right-1 bg-red-500 text-white text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center border-2 border-white">0</span>
        </a>

        <a href="profile.php?u=<?= $_SESSION['username'] ?>" class="flex flex-col items-center p-2 <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'text-black' : 'text-gray-400 hover:text-gray-600' ?>">
            <svg class="w-7 h-7" fill="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
        </a>
    <?php else: ?>
        <a href="login.php" class="flex flex-col items-center p-2 text-gray-400 hover:text-gray-600">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
            </svg>
        </a>
    <?php endif; ?>
</div>

<!-- Add padding to bottom of body to prevent content from being hidden behind bottom nav -->
<style>
    @media (max-width: 768px) {
        body {
            padding-bottom: 80px;
        }
    }

    .pb-safe {
        padding-bottom: env(safe-area-inset-bottom);
    }
</style>
</body>

</html>