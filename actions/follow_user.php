<?php
// actions/follow_user.php
session_start();
header('Content-Type: application/json');

require_once '../core/db.php';
require_once '../core/helpers.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Belum login']);
    exit;
}

check_csrf();

$follower_id = $_SESSION['user_id'];
$followed_id = $_POST['user_id'] ?? null;

if (!$followed_id || $follower_id == $followed_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

// Check if already following
$stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
$stmt->execute([$follower_id, $followed_id]);
$exists = $stmt->fetchColumn();

if ($exists) {
    // Unfollow
    $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt->execute([$follower_id, $followed_id]);
    $status = 'unfollowed';
} else {
    // Follow
    $stmt = $pdo->prepare("INSERT INTO follows (follower_id, followed_id) VALUES (?, ?)");
    $stmt->execute([$follower_id, $followed_id]);
    $status = 'followed';

    // Notify the followed user
    createNotification($followed_id, $follower_id, 'follow', $follower_id);
}

// Get new follower count (optional, but good for UI)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
$stmt->execute([$followed_id]);
$follower_count = $stmt->fetchColumn();

echo json_encode(['success' => true, 'status' => $status, 'follower_count' => $follower_count]);
