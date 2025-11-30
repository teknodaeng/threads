<?php
// actions/report_thread.php - Handle thread reporting
session_start();
require_once '../core/db.php';
require_once '../core/helpers.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to report']);
    exit;
}

// Validate CSRF
// Validate CSRF
check_csrf();

// Get POST data
$thread_id = $_POST['thread_id'] ?? null;
$reason = $_POST['reason'] ?? null;
$description = $_POST['description'] ?? '';

// Validate inputs
if (!$thread_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate reason
$valid_reasons = ['spam', 'harassment', 'inappropriate', 'misinformation', 'other'];
if (!in_array($reason, $valid_reasons)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reason']);
    exit;
}

try {
    // Check if thread exists
    $stmt = $pdo->prepare("SELECT id, user_id FROM threads WHERE id = ?");
    $stmt->execute([$thread_id]);
    $thread = $stmt->fetch();

    if (!$thread) {
        echo json_encode(['success' => false, 'message' => 'Thread not found']);
        exit;
    }

    // Prevent users from reporting their own threads
    if ($thread['user_id'] == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot report your own thread']);
        exit;
    }

    // Check if user already reported this thread
    $stmt = $pdo->prepare("SELECT id FROM reports WHERE thread_id = ? AND reporter_id = ?");
    $stmt->execute([$thread_id, $_SESSION['user_id']]);

    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already reported this thread']);
        exit;
    }

    // Insert report
    $stmt = $pdo->prepare("
        INSERT INTO reports (thread_id, reporter_id, reason, description) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$thread_id, $_SESSION['user_id'], $reason, $description]);

    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully. Our team will review it.'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
