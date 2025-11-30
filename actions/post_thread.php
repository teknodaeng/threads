<?php
// post_thread.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Belum login']);
    exit;
}

require_once '../core/db.php';
require_once '../core/helpers.php';
check_csrf();

$content = trim($_POST['content'] ?? '');
$parent_id = $_POST['parent_id'] ?? null;

if ($content === '') {
    echo json_encode(['success' => false, 'message' => 'Konten kosong']);
    exit;
}

// Validasi panjang konten
if (mb_strlen($content) > 280) {
    echo json_encode(['success' => false, 'message' => 'Konten terlalu panjang (maks 280 karakter)']);
    exit;
}

// pakai prepared statement biar aman dari SQL Injection
if ($parent_id === null || $parent_id === '') {
    // thread utama
    $stmt = $pdo->prepare("INSERT INTO threads (user_id, content, parent_id) VALUES (?, ?, NULL)");
    $ok = $stmt->execute([$_SESSION['user_id'], $content]);
    $thread_id = $pdo->lastInsertId();

    // Handle Multiple Image Uploads
    if (isset($_FILES['images'])) {
        $files = $_FILES['images'];
        $count = count($files['name']);

        if ($count > 10) {
            echo json_encode(['success' => false, 'message' => 'Maksimal 10 gambar']);
            exit;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $stmtImg = $pdo->prepare("INSERT INTO thread_images (thread_id, image_path) VALUES (?, ?)");

        // Re-structure files array
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $filename = $files['name'][$i];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    continue; // Skip invalid formats or handle error
                }

                if ($files['size'][$i] > 2 * 1024 * 1024) { // 2MB per file
                    continue; // Skip too large files
                }

                $newFilename = uniqid() . '_' . $i . '.' . $ext;
                $dest = $uploadDir . $newFilename;

                // Compress and save
                if (!compressImage($files['tmp_name'][$i], $dest, 75)) {
                    // Fallback if compression fails (e.g. GD not installed or format issue)
                    if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
                        continue; // Failed to save
                    }
                }

                $image_path = 'uploads/' . $newFilename;
                $stmtImg->execute([$thread_id, $image_path]);
            }
        }
    }

    // Handle Mentions in Main Thread
    preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
    if (!empty($matches[1])) {
        $mentionedUsernames = array_unique($matches[1]);
        foreach ($mentionedUsernames as $username) {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtUser->execute([$username]);
            $user = $stmtUser->fetch();
            if ($user) {
                createNotification($user['id'], $_SESSION['user_id'], 'mention', $thread_id);
            }
        }
    }
} else {
    // Validasi parent_id
    $stmtCheck = $pdo->prepare("SELECT id, parent_id FROM threads WHERE id = ?");
    $stmtCheck->execute([$parent_id]);
    $parentThread = $stmtCheck->fetch();

    if (!$parentThread) {
        echo json_encode(['success' => false, 'message' => 'Thread yang dibalas tidak ditemukan']);
        exit;
    }

    // Fix: Jika membalas balasan (nested reply), arahkan ke thread utama (grandparent)
    $final_parent_id = $parent_id;
    if ($parentThread['parent_id'] !== null) {
        $final_parent_id = $parentThread['parent_id'];
    }

    // Handle Image Upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Format gambar tidak didukung (jpg, png, gif, webp)']);
            exit;
        }

        if ($_FILES['image']['size'] > 2 * 1024 * 1024) { // 2MB
            echo json_encode(['success' => false, 'message' => 'Ukuran gambar terlalu besar (maks 2MB)']);
            exit;
        }

        // Create uploads directory if not exists
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newFilename = uniqid() . '.' . $ext;
        $dest = $uploadDir . $newFilename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $image_path = 'uploads/' . $newFilename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupload gambar']);
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO threads (user_id, content, parent_id, image_path) VALUES (?, ?, ?, ?)");
    $ok = $stmt->execute([$_SESSION['user_id'], $content, (int) $final_parent_id, $image_path]);

    // Bump parent thread: update updated_at
    if ($ok) {
        $stmt = $pdo->prepare("UPDATE threads SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$final_parent_id]);

        // Notify Parent Thread Owner (Reply)
        // We need to know who owns the parent thread (or the direct parent if nested)
        // For simplicity, let's notify the owner of the thread being replied to ($parent_id)
        // Re-fetch to be sure
        $stmtOwner = $pdo->prepare("SELECT user_id FROM threads WHERE id = ?");
        $stmtOwner->execute([$parent_id]);
        $owner = $stmtOwner->fetch();
        if ($owner) {
            createNotification($owner['user_id'], $_SESSION['user_id'], 'reply', $final_parent_id);
        }

        // Handle Mentions in Reply
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        if (!empty($matches[1])) {
            $mentionedUsernames = array_unique($matches[1]);
            foreach ($mentionedUsernames as $username) {
                $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtUser->execute([$username]);
                $user = $stmtUser->fetch();
                if ($user) {
                    createNotification($user['id'], $_SESSION['user_id'], 'mention', $final_parent_id);
                }
            }
        }
    }
}

echo json_encode(['success' => $ok]);
