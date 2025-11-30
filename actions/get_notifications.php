<?php
require_once '../core/db.php';
require_once '../core/helpers.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT n.*, 
               u.username as actor_username, 
               u.fullname as actor_fullname,
               COALESCE(u.image_path, 'assets/default-avatar.png') as actor_image
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for frontend
    foreach ($notifications as &$n) {
        $n['time_ago'] = time_elapsed_string($n['created_at']);
    }

    echo json_encode(['success' => true, 'notifications' => $notifications]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


