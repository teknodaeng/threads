<?php
// register.php
session_start();
require 'core/db.php';
require 'core/helpers.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname'] ?? '');
    $password = $_POST['password'];

    if (empty($fullname))
        $fullname = $username;

    // Cek username
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $error = "Username sudah dipakai.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, fullname, password_hash) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $fullname, $hash])) {
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            header('Location: index.php');
            exit;
        } else {
            $error = "Gagal daftar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Daftar - Mini Threads</title>
    <link rel="stylesheet" href="assets/output.css">
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm">
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Daftar</h2>
        <?php if ($error): ?>
            <p class="text-red-500 text-sm mb-4 text-center"><?= $error ?></p>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                <input type="text" name="username"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Username" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap</label>
                <input type="text" name="fullname"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Nama Lengkap">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <input type="password" name="password"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Password" required>
            </div>
            <button type="submit"
                class="w-full bg-green-500 text-white font-bold py-2 px-4 rounded-md hover:bg-green-600 transition duration-200">Daftar</button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-600">Sudah punya akun? <a href="login.php"
                class="text-blue-500 hover:underline">Login</a></p>
    </div>
</body>

</html>