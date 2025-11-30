<?php
require_once 'core/db.php';
require_once 'core/helpers.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = "Notifications";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Mini Threads</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">
    <div class="max-w-2xl mx-auto bg-white min-h-screen shadow-sm border-x border-gray-200">
        <!-- Header -->
        <div
            class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-200 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="p-2 rounded-full hover:bg-gray-100 transition duration-200">
                    <svg class="w-6 h-6 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-xl font-bold text-gray-900">Notifications</h1>
            </div>
            <button id="markReadBtn" class="text-sm text-blue-500 font-medium hover:text-blue-600">Mark all
                read</button>
        </div>

        <!-- Notifications List -->
        <div id="notificationsContainer" class="divide-y divide-gray-100">
            <!-- Loaded via JS -->
            <div class="p-8 text-center text-gray-500">Loading...</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadNotifications();

            document.getElementById('markReadBtn').addEventListener('click', function () {
                fetch('actions/mark_read.php')
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            loadNotifications(); // Reload to update UI state
                        }
                    });
            });
        });

        function loadNotifications() {
            fetch('actions/get_notifications.php')
                .then(r => r.json())
                .then(res => {
                    const container = document.getElementById('notificationsContainer');
                    container.innerHTML = '';

                    if (res.success && res.notifications.length > 0) {
                        res.notifications.forEach(n => {
                            const div = document.createElement('div');
                            div.className = `p-4 hover:bg-gray-50 transition duration-200 flex items-start space-x-3 ${n.is_read == 0 ? 'bg-blue-50' : ''}`;

                            let icon = '';
                            let text = '';
                            let link = '#';

                            if (n.type === 'reply') {
                                icon = '<div class="bg-blue-500 rounded-full p-1"><svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 012 0v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z"></path><path d="M15 7h1a2 2 0 012 2v5.5a1.5 1.5 0 01-3 0V7z"></path></svg></div>';
                                text = `replied to your thread`;
                                link = `thread.php?id=${n.reference_id}`;
                            } else if (n.type === 'mention') {
                                icon = '<div class="bg-green-500 rounded-full p-1"><svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.243 5.757a6 6 0 10-.986 9.284 1 1 0 111.087 1.678A8 8 0 1118 10a3 3 0 01-4.8 2.401A4 4 0 1114 10a1 1 0 102 0c0-1.537-.586-3.07-1.757-4.243zM12 10a2 2 0 10-4 0 2 2 0 004 0z" clip-rule="evenodd"></path></svg></div>';
                                text = `mentioned you in a post`;
                                link = `thread.php?id=${n.reference_id}`;
                            } else if (n.type === 'like') {
                                icon = '<div class="bg-red-500 rounded-full p-1"><svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path></svg></div>';
                                text = `liked your thread`;
                                link = `thread.php?id=${n.reference_id}`;
                            } else if (n.type === 'follow') {
                                icon = '<div class="bg-purple-500 rounded-full p-1"><svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"></path></svg></div>';
                                text = `started following you`;
                                link = `profile.php?u=${n.actor_username}`;
                            }

                            div.innerHTML = `
                                <div class="relative shrink-0">
                                    <img src="${n.actor_image}" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                                    <div class="absolute -bottom-1 -right-1 border-2 border-white rounded-full">
                                        ${icon}
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <a href="${link}" class="block focus:outline-none">
                                        <p class="text-sm text-gray-900">
                                            <span class="font-bold">${n.actor_fullname || n.actor_username}</span> 
                                            <span class="text-gray-600">${text}</span>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">${n.time_ago}</p>
                                    </a>
                                </div>
                                ${n.is_read == 0 ? '<div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>' : ''}
                            `;

                            container.appendChild(div);
                        });
                    } else {
                        container.innerHTML = '<div class="p-8 text-center text-gray-500">No notifications yet</div>';
                    }
                });
        }
    </script>
</body>

</html>