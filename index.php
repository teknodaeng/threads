<?php
// index.php
session_start();
require_once 'core/db.php';
require_once 'core/helpers.php';
check_csrf();

$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['username'] ?? '') : null;
$perPage = 5;

// Ambil semua thread utama (parent_id IS NULL)
// Fix: Gunakan prepare state// Ambil 10 thread pertama
$is_logged_in = isset($_SESSION['user_id']);
$tab = $_GET['tab'] ?? 'foryou'; // 'foryou' or 'following'

// Prepare SQL based on tab
if ($tab === 'following' && $is_logged_in) {
    $sql = "
        SELECT t.*, u.username, u.fullname, COALESCE(u.image_path, 'assets/default-avatar.png') as user_image,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id AND l.user_id = ?) AS is_liked,
            (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count
        FROM threads t
        JOIN users u ON u.id = t.user_id
        JOIN follows f ON f.followed_id = t.user_id
        WHERE t.parent_id IS NULL AND f.follower_id = ?
        ORDER BY t.updated_at DESC
        LIMIT 10
    ";
    $params = [$_SESSION['user_id'], $_SESSION['user_id']];
} else {
    // Default / For You
    $sql = "
        SELECT t.*, u.username, u.fullname, COALESCE(u.image_path, 'assets/default-avatar.png') as user_image,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id AND l.user_id = ?) AS is_liked,
            (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count
        FROM threads t
        JOIN users u ON u.id = t.user_id
        WHERE t.parent_id IS NULL
        ORDER BY t.updated_at DESC
        LIMIT 10
    ";
    $params = [$is_logged_in ? $_SESSION['user_id'] : 0];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$threads = $stmt->fetchAll();

// Optimization: Eager load replies to avoid N+1
$thread_ids = array_column($threads, 'id');
$replies_by_thread = [];
$liked_thread_ids = [];

if (!empty($thread_ids)) {
    // 1. Ambil replies
    $placeholders = str_repeat('?,', count($thread_ids) - 1) . '?';
    $sql = "
        SELECT t.*, u.username 
        FROM threads t
        JOIN users u ON u.id = t.user_id
        WHERE t.parent_id IN ($placeholders)
        ORDER BY t.created_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($thread_ids);
    $all_replies = $stmt->fetchAll();

    foreach ($all_replies as $r) {
        $replies_by_thread[$r['parent_id']][] = $r;
    }

    // 2. Ambil status like user login untuk thread-thread ini
    if ($is_logged_in) {
        $sqlLikes = "SELECT thread_id FROM thread_likes WHERE user_id = ? AND thread_id IN ($placeholders)";
        // merge params: [user_id, ...thread_ids]
        $params = array_merge([$_SESSION['user_id']], $thread_ids);
        $stmtLikes = $pdo->prepare($sqlLikes);
        $stmtLikes->execute($params);
        $liked_thread_ids = $stmtLikes->fetchAll(PDO::FETCH_COLUMN);
    }

    // 3. Ambil images untuk thread-thread ini
    $sqlImages = "SELECT * FROM thread_images WHERE thread_id IN ($placeholders)";
    $stmtImages = $pdo->prepare($sqlImages);
    $stmtImages->execute($thread_ids);
    $all_images = $stmtImages->fetchAll();

    $images_by_thread = [];
    foreach ($all_images as $img) {
        $images_by_thread[$img['thread_id']][] = $img;
    }
}

$page_title = 'Mini Threads - Home';
require_once 'includes/header.php';
?>

<div class="max-w-2xl mx-auto p-4">
    <!-- Tabs -->
    <?php if ($is_logged_in): ?>
        <div class="flex mb-4 bg-white rounded-lg shadow-sm p-1">
            <a href="index.php?tab=foryou"
                class="flex-1 text-center py-2 rounded-md font-medium transition <?= $tab !== 'following' ? 'bg-black text-white' : 'text-gray-500 hover:bg-gray-100' ?>">For
                You</a>
            <a href="index.php?tab=following"
                class="flex-1 text-center py-2 rounded-md font-medium transition <?= $tab === 'following' ? 'bg-black text-white' : 'text-gray-500 hover:bg-gray-100' ?>">Following</a>
        </div>
    <?php endif; ?>

    <!-- Form Post Thread -->
    <?php if ($is_logged_in): ?>
        <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
            <textarea id="threadContent"
                class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                rows="3" placeholder="Apa yang sedang terjadi?"></textarea>
            <div class="mt-2">
                <input type="file" id="threadImages" accept="image/*" multiple class="hidden">
                <button onclick="document.getElementById('threadImages').click()"
                    class="text-blue-500 hover:text-blue-600 flex items-center space-x-1 text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                    <span>Add Images</span>
                </button>
            </div>
            <div id="imagePreview" class="mt-4 flex overflow-x-auto gap-4 pb-2 scrollbar-hide"></div>
            <div class="flex justify-between items-center mt-2">
                <span id="charCount" class="text-xs text-gray-500">0/280</span>
                <button id="btnPost"
                    class="bg-blue-500 text-white px-4 py-2 rounded-full font-bold hover:bg-blue-600 transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed">Post</button>
            </div>
        </div>
    <?php endif; ?>

    <div id="threadsContainer" class="space-y-4">
        <?php foreach ($threads as $t): ?>
            <?php
            $is_liked = $t['is_liked'] > 0;
            $like_text = $is_liked ? 'Unlike' : 'Like';
            $like_class = $is_liked ? 'text-red-500' : 'text-gray-500 hover:text-red-500';
            $t_images = $images_by_thread[$t['id']] ?? [];
            // Fallback to old image_path if no new images (for backward compatibility during migration if needed, though we migrated)
            if (empty($t_images) && !empty($t['image_path'])) {
                $t_images[] = ['image_path' => $t['image_path']];
            }
            ?>

            <div class="thread bg-white p-4 rounded-lg shadow-sm border border-gray-200" data-id="<?= $t['id'] ?>"
                data-username="<?= e($t['username']) ?>">
                <a href="thread.php?id=<?= $t['id'] ?>" class="hover:underline-none">
                    <div class="flex items-center mb-2 space-x-2">
                        <img src="<?= e($t['user_image']) ?>" alt="<?= e($t['username']) ?>"
                            class="w-10 h-10 rounded-full object-cover border border-gray-200">
                        <div>
                            <strong class="text-gray-900 mr-1"><?= e($t['fullname']) ?></strong>
                            <span class="text-gray-500 text-sm">@<?= e($t['username']) ?></span>
                        </div>
                    </div>

                    <div
                        class="thread-content text-gray-800 mb-2 text-lg leading-relaxed wrap-break-word line-clamp-5 overflow-hidden relative transition-all duration-300">
                        <?= parseContent($t['content']) ?>
                    </div>
                    <button class="btnReadMore text-blue-500 hover:text-blue-700 text-sm font-medium mb-2 hidden"
                        onclick="event.preventDefault(); this.previousElementSibling.classList.remove('line-clamp-5'); this.remove();">Read
                        more</button>

                    <?php if (!empty($t_images)): ?>
                        <div class="relative mb-3 group">
                            <div id="carousel-<?= $t['id'] ?>"
                                class="flex overflow-x-auto snap-x snap-mandatory gap-2 pb-2 scrollbar-hide"
                                style="scrollbar-width: none; -ms-overflow-style: none;">
                                <?php foreach ($t_images as $img): ?>
                                    <div class="snap-center shrink-0 <?= count($t_images) > 1 ? 'w-5/6' : 'w-full' ?>">
                                        <img src="<?= e($img['image_path']) ?>" alt="Thread Image"
                                            class="rounded-lg w-full max-h-96 object-cover border border-gray-200">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($t_images) > 1): ?>
                                <button onclick="scrollCarousel('carousel-<?= $t['id'] ?>', -1)"
                                    class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                                        </path>
                                    </svg>
                                </button>
                                <button onclick="scrollCarousel('carousel-<?= $t['id'] ?>', 1)"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                                        </path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="text-xs text-gray-400 mb-3">
                        <?= time_elapsed_string($t['created_at']) ?>
                    </div>
                </a>
                <div class="flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                        <button class="btnLike flex items-center space-x-1 <?= $like_class ?> transition duration-200 group">
                            <svg class="w-5 h-5 icon-like <?= $is_liked ? 'fill-current' : '' ?>" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                </path>
                            </svg>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded-full likeCount"><?= $t['like_count'] ?></span>
                        </button>
                        <button
                            class="btnTagReply text-gray-500 hover:text-blue-500 font-medium transition duration-200 flex items-center space-x-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                </path>
                            </svg>
                            <?php if ($t['reply_count'] > 0): ?>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $t['reply_count'] ?></span>
                            <?php endif; ?>
                        </button>
                    <?php else: ?>
                        <div class="flex items-center space-x-1 text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                </path>
                            </svg>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $t['like_count'] ?></span>
                        </div>
                        <div class="flex items-center space-x-1 text-gray-500 ml-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                </path>
                            </svg>
                            <?php if ($t['reply_count'] > 0): ?>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?= $t['reply_count'] ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Form reply -->
                <?php if ($is_logged_in): ?>
                    <div class="mt-4 hidden">
                        <textarea
                            class="replyContent w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm"
                            rows="2" placeholder="Balas thread ini..."></textarea>
                        <div class="mt-2 text-right">
                            <button
                                class="btnReply bg-blue-500 text-white px-3 py-1 rounded-md text-sm font-bold hover:bg-blue-600 transition duration-200">Reply</button>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>
    </div>

    <div class="text-center mt-8">
        <button id="btnLoadMore"
            class="bg-white text-blue-500 border border-blue-500 px-6 py-2 rounded-full font-bold hover:bg-blue-50 transition duration-200"
            data-offset="<?= $perPage ?>">Load More</button>
    </div>
    <div id="loadMoreStatus" class="text-center mt-2 text-gray-500"></div>

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
</div>

<?php require_once 'includes/footer.php'; ?>