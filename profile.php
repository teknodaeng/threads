<?php
// profile.php
session_start();
require_once 'core/db.php';
require_once 'core/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_GET['u'] ?? '';
$username = trim($username);

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    die('User tidak ditemukan. <a href="index.php">Kembali</a>');
}

// ambil semua thread utama user ini
$currentUserId = $_SESSION['user_id'] ?? 0;
$is_logged_in = isset($_SESSION['user_id']);

// Cek status follow
$is_following = false;
if ($is_logged_in && $currentUserId != $user['id']) {
    $stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt->execute([$currentUserId, $user['id']]);
    $is_following = $stmt->fetchColumn();
}

// Hitung followers & following
$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
$stmt->execute([$user['id']]);
$follower_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$stmt->execute([$user['id']]);
$following_count = $stmt->fetchColumn();

// 1. Ambil thread utama user ini
$stmt = $pdo->prepare("
    SELECT t.*,
        (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
        (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count
    FROM threads t
    WHERE t.user_id = ? AND t.parent_id IS NULL
    ORDER BY t.updated_at DESC
");
$stmt->execute([$user['id']]);
$threads = $stmt->fetchAll();

$liked_thread_ids = [];
$images_by_thread = [];

if (!empty($threads)) {
    $thread_ids = array_column($threads, 'id');
    $placeholders = implode(',', array_fill(0, count($thread_ids), '?'));

    // 2. Ambil status like user login untuk thread-thread ini
    if ($is_logged_in) {
        $sqlLikes = "SELECT thread_id FROM thread_likes WHERE user_id = ? AND thread_id IN ($placeholders)";
        $params = array_merge([$_SESSION['user_id']], $thread_ids);
        $stmtLikes = $pdo->prepare($sqlLikes);
        $stmtLikes->execute($params);
        $liked_thread_ids = $stmtLikes->fetchAll(PDO::FETCH_COLUMN);
    }

    // 3. Ambil images
    $sqlImages = "SELECT * FROM thread_images WHERE thread_id IN ($placeholders)";
    $stmtImages = $pdo->prepare($sqlImages);
    $stmtImages->execute($thread_ids);
    $all_images = $stmtImages->fetchAll();

    foreach ($all_images as $img) {
        $images_by_thread[$img['thread_id']][] = $img;
    }
}

$is_logged_in = isset($_SESSION['user_id']);
$is_own_profile = $is_logged_in && $_SESSION['username'] == $user['username'];
$page_title = e($user['fullname']) . ' (@' . e($user['username']) . ') - Mini Threads';
require_once 'includes/header.php';
?>

<div class="max-w-2xl mx-auto p-4">
    <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
        <!-- Profile Picture -->
        <div class="flex flex-col items-center mb-4">
            <?php
            $profileImage = $user['image_path'] ?? 'assets/default-avatar.png';
            ?>
            <img id="profilePicture" src="<?= e($profileImage) ?>" alt="Profile Picture"
                class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 mb-4">

            <?php if ($is_logged_in && $currentUserId == $user['id']): ?>
                <input type="file" id="profilePictureInput" accept="image/*" class="hidden">
                <button id="uploadPhotoBtn" class="text-sm text-blue-500 hover:text-blue-600 font-medium mb-2">
                    Change Photo
                </button>
            <?php endif; ?>

            <h2 class="text-xl font-bold text-gray-800"><?= e($user['fullname']) ?></h2>
            <p class="text-gray-500 mb-4">@<?= e($user['username']) ?></p>

            <div class="flex space-x-4 mb-4 text-sm text-gray-600">
                <div><span class="font-bold text-gray-900" id="followerCount"><?= $follower_count ?></span>
                    Followers
                </div>
                <div><span class="font-bold text-gray-900"><?= $following_count ?></span> Following</div>
            </div>

            <?php if ($is_logged_in && $currentUserId != $user['id']): ?>
                <button id="btnFollow" data-id="<?= $user['id'] ?>"
                    class="px-4 py-2 rounded-full font-bold transition duration-200 <?= $is_following ? 'bg-white border border-gray-300 text-gray-900 hover:bg-gray-50' : 'bg-black hover:bg-gray-800' ?>">
                    <?= $is_following ? 'Following' : 'Follow' ?>
                </button>
            <?php endif; ?>

            <?php if ($is_logged_in && $currentUserId == $user['id']): ?>
                <button id="mobileInstallAppBtn"
                    class="hidden w-full mt-2 bg-blue-600 text-white px-4 py-2 rounded-full font-bold hover:bg-blue-700 transition">
                    Install App
                </button>
                <a href="logout.php"
                    class="md:hidden mt-4 text-red-500 hover:text-red-700 font-medium text-sm flex items-center justify-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                        </path>
                    </svg>
                    Logout
                </a>
            <?php endif; ?>
        </div>
    </div>

    <h3 class="text-xl font-bold text-gray-800 mb-4">Postingan</h3>

    <?php if (empty($threads)): ?>
        <p class="text-gray-500 text-center py-8">Belum ada postingan.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($threads as $t): ?>
                <?php
                $is_liked = in_array($t['id'], $liked_thread_ids);
                $like_text = $is_liked ? 'Unlike' : 'Like';
                $like_class = $is_liked ? 'text-red-500' : 'text-gray-500 hover:text-red-500';
                $t_images = $images_by_thread[$t['id']] ?? [];
                if (empty($t_images) && !empty($t['image_path'])) {
                    $t_images[] = ['image_path' => $t['image_path']];
                }
                ?>
                <div class="thread bg-white p-4 rounded-lg shadow-sm border border-gray-200" data-id="<?= $t['id'] ?>"
                    data-username="<?= e($user['username']) ?>">
                    <div class="text-xs text-gray-400 mb-2">
                        <?= time_elapsed_string($t['created_at']) ?>
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
                            <div id="carousel-profile-<?= $t['id'] ?>"
                                class="flex overflow-x-auto snap-x snap-mandatory gap-2 pb-2 scrollbar-hide"
                                style="scrollbar-width: none; -ms-overflow-style: none;">
                                <?php foreach ($t_images as $img): ?>
                                    <div class="snap-center shrink-0 <?= count($t_images) > 1 ? 'w-5/6' : 'w-full' ?>">
                                        <img src="<?= e($img['image_path']) ?>" alt="Thread Image"
                                            class="rounded-lg w-full h-48 object-cover border border-gray-200">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($t_images) > 1): ?>
                                <button onclick="scrollCarousel('carousel-profile-<?= $t['id'] ?>', -1)"
                                    class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                                        </path>
                                    </svg>
                                </button>
                                <button onclick="scrollCarousel('carousel-profile-<?= $t['id'] ?>', 1)"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                                        </path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center space-x-4">
                        <?php if (isset($_SESSION['user_id'])): ?>
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
                    <?php if (isset($_SESSION['user_id'])): ?>
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
    <?php endif; ?>
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
                        const mobileBadge = document.getElementById('mobileNotifBadge');

                        if (unreadCount > 0) {
                            if (badge) {
                                badge.innerText = unreadCount;
                                badge.classList.remove('hidden');
                            }
                            if (mobileBadge) {
                                mobileBadge.innerText = unreadCount;
                                mobileBadge.classList.remove('hidden');
                            }
                        } else {
                            if (badge) badge.classList.add('hidden');
                            if (mobileBadge) mobileBadge.classList.add('hidden');
                        }
                    }
                });
        }
        // Check every 10 seconds
        setInterval(checkNotifications, 10000);
        checkNotifications(); // Initial check

        // Profile picture upload
        <?php if ($currentUserId == $user['id']): ?>
            const uploadBtn = document.getElementById('uploadPhotoBtn');
            const fileInput = document.getElementById('profilePictureInput');
            const profilePic = document.getElementById('profilePicture');

            uploadBtn.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format gambar tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.');
                    return;
                }

                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file terlalu besar. Maksimal 2MB.');
                    return;
                }

                // Show loading state
                uploadBtn.textContent = 'Uploading...';
                uploadBtn.disabled = true;

                // Upload via AJAX
                const formData = new FormData();
                formData.append('profile_picture', file);
                formData.append('csrf_token', document.getElementById('csrfToken').value);

                fetch('actions/upload_profile_picture.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            // Update the profile picture instantly
                            profilePic.src = res.image_path + '?t=' + Date.now(); // Cache bust
                            uploadBtn.textContent = 'Change Photo';
                            uploadBtn.disabled = false;

                            // Show success message briefly
                            const oldText = uploadBtn.textContent;
                            uploadBtn.textContent = '✓ Updated!';
                            setTimeout(() => {
                                uploadBtn.textContent = oldText;
                            }, 2000);
                        } else {
                            alert(res.message || 'Upload failed');
                            uploadBtn.textContent = 'Change Photo';
                            uploadBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        alert('Upload failed: ' + err.message);
                        uploadBtn.textContent = 'Change Photo';
                        uploadBtn.disabled = false;
                    });
            });
        <?php endif; ?>
    </script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>