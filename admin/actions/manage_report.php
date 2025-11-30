<?php
// admin/actions/manage_report.php
session_start();
require_once '../../core/db.php';
require_once '../../core/helpers.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../reports.php');
    exit;
}

check_csrf();

$report_id = $_POST['report_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$report_id || !$action) {
    header('Location: ../reports.php?error=missing_params');
    exit;
}

try {
    // Get report details
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch();

    if (!$report) {
        header('Location: ../reports.php?error=not_found');
        exit;
    }

    if ($action === 'dismiss') {
        // Mark report as dismissed
        $stmt = $pdo->prepare("UPDATE reports SET status = 'dismissed', reviewed_by = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $report_id]);

        header('Location: ../reports.php?status=pending&msg=dismissed');
    } elseif ($action === 'delete_thread') {
        // 1. Mark ALL pending reports for this thread as actioned (including the current one)
        // We must do this BEFORE deleting the thread, otherwise thread_id becomes NULL
        // and we won't be able to find other reports for this thread.
        $stmt = $pdo->prepare("UPDATE reports SET status = 'actioned', reviewed_by = ? WHERE thread_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id'], $report['thread_id']]);

        // 2. Delete the thread
        $stmt = $pdo->prepare("DELETE FROM threads WHERE id = ?");
        $stmt->execute([$report['thread_id']]);

        header('Location: ../reports.php?status=pending&msg=thread_deleted');
    } else {
        header('Location: ../reports.php?error=invalid_action');
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
