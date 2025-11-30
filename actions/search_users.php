<?php
require_once '../core/db.php';
require_once '../core/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$q = $_GET['q'] ?? '';
$q = trim($q);

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

try {
    // Search by username or fullname
    $stmt = $pdo->prepare("
        SELECT username, fullname, image_path 
        FROM users 
        WHERE username LIKE ? OR fullname LIKE ? 
        LIMIT 5
    ");
    $searchTerm = '%' . $q . '%';
    $stmt->execute([$searchTerm, $searchTerm]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);
} catch (PDOException $e) {
    echo json_encode([]);
}
