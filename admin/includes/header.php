<?php
// admin/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure page title is set
if (!isset($page_title)) {
    $page_title = 'Admin Panel - Mini Threads';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/output.css">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3b82f6">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('../service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans text-gray-900 flex flex-col min-h-screen">
    <!-- Admin Header -->
    <header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo & Brand -->
                <div class="flex">
                    <div class="shrink-0 flex items-center">
                        <a href="index.php" class="text-2xl font-bold text-purple-600 flex items-center">
                            <svg class="w-8 h-8 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                </path>
                            </svg>
                            Admin Panel
                        </a>
                    </div>

                    <!-- Desktop Navigation -->
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="index.php"
                            class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'border-purple-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="reports.php"
                            class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'border-purple-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Reports
                        </a>
                    </div>
                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center">
                    <a href="../index.php"
                        class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Site
                    </a>
                    <div class="ml-3 relative flex items-center">
                        <span
                            class="text-sm font-medium text-gray-700 mr-2"><?= e($_SESSION['username'] ?? 'Admin') ?></span>
                        <a href="../logout.php" class="text-red-500 hover:text-red-700 text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <main class="grow">