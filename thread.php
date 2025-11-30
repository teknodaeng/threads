<?php
// thread.php
session_start();
require_once 'core/db.php';
require_once 'core/helpers.php';

$thread_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($thread_id <= 0) {
    die('Thread tidak valid. <a href="index.php">Kembali</a>');
}

$currentUserId = $_SESSION['user_id'] ?? 0;

// Fetch Thread
$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.fullname, COALESCE(u.image_path, 'assets/default-avatar.png') as user_image,
        (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
        (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id AND l.user_id = ?) AS is_liked,
        (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count
    FROM threads t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$currentUserId, $thread_id]);
$thread = $stmt->fetch();

if (!$thread) {
    die('Thread tidak ditemukan. <a href="index.php">Kembali</a>');
}

// Fetch Replies
$stmtReplies = $pdo->prepare("
    SELECT t.*, u.username, u.fullname, COALESCE(u.image_path, 'assets/default-avatar.png') as user_image,
        (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
        (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id AND l.user_id = ?) AS is_liked,
        (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count
    FROM threads t
    JOIN users u ON t.user_id = u.id
    WHERE t.parent_id = ?
    ORDER BY t.created_at ASC
");
$stmtReplies->execute([$currentUserId, $thread_id]);
$replies = $stmtReplies->fetchAll();

$is_logged_in = isset($_SESSION['user_id']);
// Ambil images
$stmtImages = $pdo->prepare("SELECT * FROM thread_images WHERE thread_id = ?");
$stmtImages->execute([$thread_id]);
$t_images = $stmtImages->fetchAll();

// Fallback
if (empty($t_images) && !empty($thread['image_path'])) {
    $t_images[] = ['image_path' => $thread['image_path']];
}
$is_logged_in = isset($_SESSION['user_id']);
$page_title = 'Thread Detail - Mini Threads';
require_once 'includes/header.php';
?>

<div class="max-w-2xl mx-auto p-4">

    <div class="mb-4">
        <button onclick="history.back()"
            class="text-gray-500 hover:text-blue-500 flex items-center transition duration-200">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            Kembali
        </button>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($thread['parent_id']): ?>
        <div class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200 text-sm text-gray-600">
            Membalas thread lain. <a href="thread.php?id=<?= $thread['parent_id'] ?>"
                class="text-blue-500 hover:underline">Lihat
                Thread Utama</a>
        </div>
    <?php endif; ?>

    <div class="thread bg-white p-6 rounded-lg shadow-md mb-8 border border-gray-200" data-id="<?= $thread['id'] ?>"
        data-username="<?= e($thread['username']) ?>">
        <div class="flex items-center mb-4 space-x-3">
            <img src="<?= e($thread['user_image']) ?>" alt="<?= e($thread['username']) ?>"
                class="w-12 h-12 rounded-full object-cover border-2 border-gray-200">
            <div>
                <h2 class="text-lg font-bold text-gray-900"><?= e($thread['fullname']) ?></h2>
                <a href="profile.php?u=<?= $thread['username'] ?>"
                    class="text-gray-500 text-sm hover:text-blue-500">@<?= e($thread['username']) ?></a>
            </div>
            <span class="text-gray-400 mx-2">•</span>
            <span class="text-gray-500 text-sm"><?= time_elapsed_string($thread['created_at']) ?></span>
        </div>
        <div
            class="thread-content text-gray-800 mb-2 text-lg leading-relaxed wrap-break-word line-clamp-5 overflow-hidden relative transition-all duration-300">
            <?= parseContent($thread['content']) ?>
        </div>

        <?php if (!empty($t_images)): ?>
            <div class="relative mb-6 group">
                <div id="carousel-detail-<?= $thread['id'] ?>"
                    class="flex overflow-x-auto snap-x snap-mandatory gap-2 pb-2 scrollbar-hide"
                    style="scrollbar-width: none; -ms-overflow-style: none;">
                    <?php foreach ($t_images as $img): ?>
                        <div class="snap-center shrink-0 <?= count($t_images) > 1 ? 'w-5/6' : 'w-full' ?>">
                            <img src="<?= e($img['image_path']) ?>" alt="Thread Image"
                                class="rounded-lg w-full max-h-[500px] object-cover border border-gray-200">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($t_images) > 1): ?>
                    <button onclick="scrollCarousel('carousel-detail-<?= $thread['id'] ?>', -1)"
                        class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                    </button>
                    <button onclick="scrollCarousel('carousel-detail-<?= $thread['id'] ?>', 1)"
                        class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="flex items-center space-x-4 border-t border-gray-100 pt-4">
            <?php
            $is_liked = $thread['is_liked'] > 0;
            $like_text = $is_liked ? 'Unlike' : 'Like';
            $like_class = $is_liked ? 'text-red-500' : 'text-gray-500 hover:text-red-500';
            ?>
            <?php if ($is_logged_in): ?>
                <button class="btnLike flex items-center space-x-1 <?= $like_class ?> transition duration-200 group">
                    <svg class="w-5 h-5 icon-like <?= $is_liked ? 'fill-current' : '' ?>" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                        </path>
                    </svg>
                    <span class="text-xs bg-gray-100 px-2 py-1 rounded-full likeCount"><?= $thread['like_count'] ?></span>
                </button>
                <button
                    class="btnTagReply text-gray-500 hover:text-blue-500 font-medium transition duration-200 flex items-center space-x-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                        </path>
                    </svg>
                    <?php if ($thread['reply_count'] > 0): ?>
                        <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $thread['reply_count'] ?></span>
                    <?php endif; ?>
                </button>

                <!-- Report Button (only for logged-in users who don't own the thread) -->
                <?php if ($is_logged_in && $_SESSION['user_id'] != $thread['user_id']): ?>
                    <button onclick="openReportModal(<?= $thread['id'] ?>)"
                        class="text-gray-500 hover:text-red-500 text-sm inline-flex items-center space-x-1 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                        </svg>
                        <span>Report</span>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <div class="flex items-center space-x-1 text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                        </path>
                    </svg>
                    <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $thread['like_count'] ?></span>
                </div>
                <div class="flex items-center space-x-1 text-gray-500 ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                        </path>
                    </svg>
                    <?php if ($thread['reply_count'] > 0): ?>
                        <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $thread['reply_count'] ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_logged_in && !$thread['parent_id']): ?>
            <div class="mt-6">
                <textarea
                    class="replyContent w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                    rows="3" placeholder="Balas thread ini..."></textarea>
                <div class="mt-2 text-right">
                    <button
                        class="btnReply bg-blue-500 text-white px-4 py-2 rounded-md font-bold hover:bg-blue-600 transition duration-200">Reply</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <h3 class="text-xl font-bold text-gray-800 mb-4">Balasan</h3>

    <div class="space-y-4">
        <?php if (count($replies) === 0): ?>
            <p class="text-gray-600">Belum ada balasan.</p>
        <?php else: ?>
            <?php foreach ($replies as $r): ?>
                <?php
                $is_liked = $r['is_liked'] > 0;
                $like_text = $is_liked ? 'Unlike' : 'Like';
                $like_class = $is_liked ? 'text-red-500' : 'text-gray-500 hover:text-red-500';
                ?>
                <div class="thread bg-white p-4 rounded-lg shadow-sm border border-gray-200 ml-8" data-id="<?= $r['id'] ?>"
                    data-username="<?= e($r['username']) ?>">
                    <div class="flex items-center mb-2 space-x-2">
                        <img src="<?= e($r['user_image']) ?>" alt="<?= e($r['username']) ?>"
                            class="w-10 h-10 rounded-full object-cover border border-gray-200">
                        <div>
                            <strong class="text-gray-900 mr-1"><?= e($r['fullname'] ?? $r['username']) ?></strong>
                            <a href="profile.php?u=<?= $r['username'] ?>"
                                class="text-gray-500 text-sm hover:text-blue-500">@<?= e($r['username']) ?></a>
                        </div>
                        <span class="text-gray-400 mx-2">•</span>
                        <span class="text-gray-500 text-xs"><?= time_elapsed_string($r['created_at']) ?></span>
                    </div>
                    <div class="text-gray-800 mb-2 leading-relaxed"><?= parseContent($r['content']) ?></div>

                    <div class="flex items-center space-x-4">
                        <?php if ($is_logged_in): ?>
                            <button class="btnLike flex items-center space-x-1 <?= $like_class ?> transition duration-200 group">
                                <svg class="w-5 h-5 icon-like <?= $is_liked ? 'fill-current' : '' ?>" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded-full likeCount"><?= $r['like_count'] ?></span>
                            </button>
                            <button
                                class="btnTagReply text-gray-500 hover:text-blue-500 font-medium transition duration-200 flex items-center space-x-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                    </path>
                                </svg>
                                <?php if ($r['reply_count'] > 0): ?>
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $r['reply_count'] ?></span>
                                <?php endif; ?>
                            </button>
                        <?php else: ?>
                            <div class="flex items-center space-x-1 text-gray-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                    </path>
                                </svg>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $r['like_count'] ?></span>
                            </div>
                            <div class="flex items-center space-x-1 text-gray-500 ml-4">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                    </path>
                                </svg>
                                <?php if ($r['reply_count'] > 0): ?>
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $r['reply_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Nested Reply Form -->
                    <?php if ($is_logged_in): ?>
                        <div class="mt-4 hidden">
                            <textarea
                                class="replyContent w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm"
                                rows="2" placeholder="Balas..."></textarea>
                            <div class="mt-2 text-right">
                                <button
                                    class="btnReply bg-blue-500 text-white px-3 py-1 rounded-md text-sm font-bold hover:bg-blue-600 transition duration-200">Reply</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">
<script>
    const IS_LOGGED_IN = <?= $is_logged_in ? 'true' : 'false' ?>;
</script>
<script src="assets/app.js"></script>
<script src="assets/image-modal.js"></script>
<?php if ($is_logged_in): ?>
    <script>
        function checkNotifications() {
            fetch('actions/get_notifications.php')
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const unreadCount = res.notifications.filter(n => n.is_read == 0).length;
                        const badge = document.getElementById('notifBadge');
                        if (unreadCount > 0) {
                            badge.innerText = unreadCount;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                });
        }
        // Check every 10 seconds
        setInterval(checkNotifications, 10000);
        checkNotifications(); // Initial check
    </script>
<?php endif; ?>

<!-- Report Modal -->
<div id="reportModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 p-4"
    style="display: none; align-items: center; justify-content: center;">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900">Report Thread</h3>
            <button onclick="closeReportModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form id="reportForm" onsubmit="submitReport(event)">
            <input type="hidden" id="reportThreadId" name="thread_id">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason *</label>
                <select name="reason" required
                    class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Select Reason --</option>
                    <option value="spam">Spam</option>
                    <option value="harassment">Harassment</option>
                    <option value="inappropriate">Inappropriate Content</option>
                    <option value="misinformation">Misinformation</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Details (optional)</label>
                <textarea name="description" rows="3"
                    class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Provide more context..."></textarea>
            </div>

            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeReportModal()"
                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">Submit
                    Report</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReportModal(threadId) {
        const modal = document.getElementById('reportModal');
        document.getElementById('reportThreadId').value = threadId;
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeReportModal() {
        const modal = document.getElementById('reportModal');
        modal.style.display = 'none';
        modal.classList.add('hidden');
        document.getElementById('reportForm').reset();
        document.body.style.overflow = '';
    }

    function submitReport(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('csrf_token', document.getElementById('csrfToken').value);

        fetch('actions/report_thread.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(r => r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Server response:', text);
                    throw new Error('Invalid server response');
                }
            }))
            .then(res => {
                if (res.success) {
                    alert(res.message);
                    closeReportModal();
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to submit report. Please try again.');
            });
    }
</script>
<?php require_once 'includes/footer.php'; ?>