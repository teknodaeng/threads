<?php
// like_thread.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Belum login']);
    exit;
}

require_once '../core/db.php';
require_once '../core/helpers.php';
check_csrf();

$thread_id = isset($_POST['thread_id']) ? (int) $_POST['thread_id'] : 0;
if ($thread_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thread tidak valid']);
    exit;
}

try {
    // cek apakah user sudah like
    $stmt = $pdo->prepare("SELECT id FROM thread_likes WHERE user_id = ? AND thread_id = ?");
    $stmt->execute([$_SESSION['user_id'], $thread_id]);
    $liked = $stmt->fetch();

    if ($liked) {
        // sudah like -> berarti unlike (hapus)
        $stmt = $pdo->prepare("DELETE FROM thread_likes WHERE id = ?");
        $stmt->execute([$liked['id']]);
        $status = 'unliked';
    } else {
        // belum like -> tambahkan
        $stmt = $pdo->prepare("INSERT INTO thread_likes (user_id, thread_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $thread_id]);
        $status = 'liked';

        // Bump thread: update updated_at
        $stmt = $pdo->prepare("UPDATE threads SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$thread_id]);

        // Notify thread owner about the like
        $stmt = $pdo->prepare("SELECT user_id FROM threads WHERE id = ?");
        $stmt->execute([$thread_id]);
        $thread = $stmt->fetch();
        if ($thread) {
            createNotification($thread['user_id'], $_SESSION['user_id'], 'like', $thread_id);
        }
    }

    // hitung ulang total like
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM thread_likes WHERE thread_id = ?");
    $stmt->execute([$thread_id]);
    $count = $stmt->fetch()['c'];

    echo json_encode([
        'success' => true,
        'status' => $status,
        'count' => $count
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server']);
    // Log error if needed: error_log($e->getMessage());
}
