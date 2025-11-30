<?php
// actions/search.php
session_start();
header('Content-Type: application/json');

require_once '../core/db.php';
require_once '../core/helpers.php';

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo json_encode(['success' => true, 'users' => [], 'threads' => []]);
    exit;
}

try {
    // Search Users
    $stmtUsers = $pdo->prepare("SELECT id, username, fullname, COALESCE(image_path, 'assets/default-avatar.png') as user_image FROM users WHERE username LIKE ? OR fullname LIKE ? LIMIT 10");
    $stmtUsers->execute(['%' . $q . '%', '%' . $q . '%']);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Search Threads
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $stmtThreads = $pdo->prepare("
        SELECT t.id, t.content, t.image_path, u.username, u.fullname, t.created_at,
            COALESCE(u.image_path, 'assets/default-avatar.png') as user_image,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id AND l.user_id = ?) AS is_liked,
            (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count,
            (SELECT GROUP_CONCAT(image_path SEPARATOR ',') FROM thread_images ti WHERE ti.thread_id = t.id) AS images
        FROM threads t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.content LIKE ? 
        ORDER BY t.created_at DESC 
        LIMIT 20
    ");
    $stmtThreads->execute([$currentUserId, '%' . $q . '%']);
    $threads = $stmtThreads->fetchAll(PDO::FETCH_ASSOC);

    // Format threads for display (e.g., escape content)
    foreach ($threads as &$thread) {
        $thread['content'] = parseContent($thread['content']);
        $thread['username'] = e($thread['username']);
        $thread['fullname'] = e($thread['fullname']);
        $thread['created_at'] = time_elapsed_string($thread['created_at']);
    }

    foreach ($users as &$user) {
        $user['username'] = e($user['username']);
        $user['fullname'] = e($user['fullname']);
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'threads' => $threads
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server']);
}
