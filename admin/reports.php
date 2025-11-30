<?php
// admin/reports.php - Manage Reports
session_start();
require_once '../core/db.php';
require_once '../core/helpers.php';

requireAdmin();

$status_filter = $_GET['status'] ?? 'pending';
$page_title = 'Manage Reports - Admin Panel';

// Build query
$sql = "SELECT r.*, t.content as thread_content, t.image_path as thread_image, 
               u.username as reporter_username, u.fullname as reporter_fullname,
               tu.username as thread_author_username
        FROM reports r
        LEFT JOIN threads t ON r.thread_id = t.id
        JOIN users u ON r.reporter_id = u.id
        LEFT JOIN users tu ON t.user_id = tu.id
        WHERE r.status = ?
        ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$status_filter]);
$reports = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="max-w-7xl mx-auto p-4">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manage Reports</h1>
            <p class="text-gray-600 text-sm">Review and take action on user reports</p>
        </div>
        <div class="flex space-x-2">
            <a href="reports.php?status=pending"
                class="px-3 py-2 rounded-md text-sm font-medium <?= $status_filter === 'pending' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' ?>">Pending</a>
            <a href="reports.php?status=dismissed"
                class="px-3 py-2 rounded-md text-sm font-medium <?= $status_filter === 'dismissed' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' ?>">Dismissed</a>
            <a href="reports.php?status=actioned"
                class="px-3 py-2 rounded-md text-sm font-medium <?= $status_filter === 'actioned' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:bg-gray-100' ?>">Actioned</a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <?php if (empty($reports)): ?>
            <div class="p-8 text-center text-gray-500">
                <p>No <?= e($status_filter) ?> reports found.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Reported Content</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Reporter</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 font-medium mb-1">
                                        <?php if ($report['thread_author_username']): ?>
                                            Thread by @<?= e($report['thread_author_username']) ?>
                                        <?php else: ?>
                                            <span class="text-gray-500 italic">Thread Author Deleted/Unknown</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-600 line-clamp-2">
                                        <?php if ($report['thread_content']): ?>
                                            <?= e($report['thread_content']) ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">[Thread Deleted]</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($report['thread_id']): ?>
                                        <a href="../thread.php?id=<?= $report['thread_id'] ?>" target="_blank"
                                            class="text-blue-500 hover:text-blue-700 text-xs mt-1 inline-block">View Thread
                                            &rarr;</a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <?= e(ucfirst($report['reason'])) ?>
                                    </span>
                                    <?php if ($report['description']): ?>
                                        <div class="text-xs text-gray-500 mt-1 max-w-xs truncate"
                                            title="<?= e($report['description']) ?>">
                                            <?= e($report['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= e($report['reporter_fullname']) ?></div>
                                    <div class="text-sm text-gray-500">@<?= e($report['reporter_username']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= time_elapsed_string($report['created_at']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <button onclick="handleReport(<?= $report['id'] ?>, 'dismiss')"
                                            class="text-gray-600 hover:text-gray-900 mr-3">Dismiss</button>
                                        <button onclick="handleReport(<?= $report['id'] ?>, 'delete_thread')"
                                            class="text-red-600 hover:text-red-900">Delete Thread</button>
                                    <?php else: ?>
                                        <span class="text-gray-400">No actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<form id="actionForm" method="POST" action="actions/manage_report.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="report_id" id="formReportId">
    <input type="hidden" name="action" id="formAction">
</form>

<script>
    function handleReport(reportId, action) {
        if (action === 'delete_thread') {
            if (!confirm('Are you sure you want to DELETE this thread? This action cannot be undone.')) {
                return;
            }
        } else if (action === 'dismiss') {
            if (!confirm('Dismiss this report?')) {
                return;
            }
        }

        document.getElementById('formReportId').value = reportId;
        document.getElementById('formAction').value = action;
        document.getElementById('actionForm').submit();
    }
</script>

<?php require_once 'includes/footer.php'; ?>