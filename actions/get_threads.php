<?php
// get_threads.php
session_start();
header('Content-Type: application/json');

require_once '../core/db.php';
require_once '../core/helpers.php';

$is_logged_in = isset($_SESSION['user_id']);
$offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
$tab = $_POST['tab'] ?? 'foryou';
$perPage = 5;

// ambil thread utama batch berikutnya
if ($tab === 'following' && $is_logged_in) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.fullname, COALESCE(u.image_path, 'assets/default-avatar.png') as user_image,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id AND l.user_id = ?) AS is_liked,
            (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count
        FROM threads t
        JOIN users u ON u.id = t.user_id
        JOIN follows f ON f.followed_id = t.user_id
        WHERE t.parent_id IS NULL AND f.follower_id = ?
        ORDER BY t.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
} else {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.fullname, COALESCE(u.image_path, 'assets/default-avatar.png') as user_image,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id) AS like_count,
            (SELECT COUNT(*) FROM thread_likes l WHERE l.thread_id = t.id AND l.user_id = ?) AS is_liked,
            (SELECT COUNT(*) FROM threads r WHERE r.parent_id = t.id) AS reply_count
        FROM threads t
        JOIN users u ON u.id = t.user_id
        WHERE t.parent_id IS NULL
        ORDER BY t.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $is_logged_in ? $_SESSION['user_id'] : 0, PDO::PARAM_INT);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
}
$stmt->execute();
$threads = $stmt->fetchAll();

// Optimization: Eager load replies
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

    // 2. Ambil status like user login
    if ($is_logged_in) {
        $sqlLikes = "SELECT thread_id FROM thread_likes WHERE user_id = ? AND thread_id IN ($placeholders)";
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

// generate HTML yang siap disisipkan
ob_start();

foreach ($threads as $t) {
    $is_liked = $t['is_liked'] > 0;
    $like_text = $is_liked ? 'Unlike' : 'Like';
    $like_class = $is_liked ? 'text-red-500' : 'text-gray-500 hover:text-red-500';
    $t_images = $images_by_thread[$t['id']] ?? [];

    // Fallback
    if (empty($t_images) && !empty($t['image_path'])) {
        $t_images[] = ['image_path' => $t['image_path']];
    }

    echo '<div class="thread bg-white p-4 rounded-lg shadow-sm border border-gray-200" data-id="' . $t['id'] . '" data-username="' . e($t['username']) . '">';
    echo '<a href="thread.php?id=' . $t['id'] . '" class="hover:underline">';
    echo '<div class="flex items-center mb-2 space-x-2">';
    echo '<img src="' . e($t['user_image']) . '" alt="' . e($t['username']) . '" class="w-10 h-10 rounded-full object-cover border border-gray-200">';
    echo '<div>';
    echo '<strong class="text-gray-900 mr-1">' . e($t['fullname']) . '</strong>';
    echo '<span class="text-gray-500 text-sm">@' . e($t['username']) . '</span>';
    echo '</div>';
    echo '</div>';
    echo '<div class="thread-content text-gray-800 mb-2 text-lg leading-relaxed wrap-break-word line-clamp-5 overflow-hidden relative transition-all duration-300">' . parseContent($t['content']) . '</div>';
    echo '<button class="btnReadMore text-blue-500 hover:text-blue-700 text-sm font-medium mb-2 hidden" onclick="event.preventDefault(); this.previousElementSibling.classList.remove(\'line-clamp-5\'); this.remove();">Read more</button>';

    if (!empty($t_images)) {
        echo '<div class="relative mb-3 group">';
        echo '<div id="carousel-' . $t['id'] . '" class="flex overflow-x-auto snap-x snap-mandatory gap-2 pb-2 scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">';
        foreach ($t_images as $img) {
            $widthClass = count($t_images) > 1 ? 'w-5/6' : 'w-full';
            echo '<div class="snap-center shrink-0 ' . $widthClass . '">';
            echo '<img src="' . e($img['image_path']) . '" alt="Thread Image" class="rounded-lg w-full max-h-96 object-cover border border-gray-200">';
            echo '</div>';
        }
        echo '</div>';
        if (count($t_images) > 1) {
            echo '<button onclick="scrollCarousel(\'carousel-' . $t['id'] . '\', -1)" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">';
            echo '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>';
            echo '</button>';
            echo '<button onclick="scrollCarousel(\'carousel-' . $t['id'] . '\', 1)" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">';
            echo '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
            echo '</button>';
        }
        echo '</div>';
    }

    echo '<div class="text-xs text-gray-400 mb-3">';
    echo time_elapsed_string($t['created_at']);
    echo '</div>';
    echo '</a>'; // End anchor tag

    echo '<div class="flex items-center space-x-4">';
    if ($is_logged_in) {
        echo '<button class="btnLike flex items-center space-x-1 ' . $like_class . ' transition duration-200 group">';
        echo '<svg class="w-5 h-5 icon-like ' . ($is_liked ? 'fill-current' : '') . '" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>';
        echo '</svg>';
        echo '<span class="text-xs bg-gray-100 px-2 py-1 rounded-full likeCount">' . $t['like_count'] . '</span>';
        echo '</button>';
        echo '<button class="btnTagReply text-gray-500 hover:text-blue-500 font-medium transition duration-200 flex items-center space-x-1">';
        echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>';
        echo '</svg>';
        if ($t['reply_count'] > 0) {
            echo '<span class="text-xs bg-gray-100 px-2 py-1 rounded-full">' . $t['reply_count'] . '</span>';
        }
        echo '</button>';
    } else {
        echo '<div class="flex items-center space-x-1 text-gray-500">';
        echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>';
        echo '</svg>';
        echo '<span class="text-xs bg-gray-100 px-2 py-1 rounded-full">' . $t['like_count'] . '</span>';
        echo '</div>';
        echo '<div class="flex items-center space-x-1 text-gray-500 ml-4">';
        echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>';
        echo '</svg>';
        if ($t['reply_count'] > 0) {
            echo '<span class="text-xs bg-gray-100 px-2 py-1 rounded-full">' . $t['reply_count'] . '</span>';
        }
        echo '</div>';
    }
    echo '</div>';

    if ($is_logged_in) {
        echo '<div class="mt-4 hidden">';
        echo '<textarea class="replyContent w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm" rows="2" placeholder="Balas thread ini..."></textarea>';
        echo '<div class="mt-2 text-right">';
        echo '<button class="btnReply bg-blue-500 text-white px-3 py-1 rounded-md text-sm font-bold hover:bg-blue-600 transition duration-200">Reply</button>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>'; // End .thread
}

$html = ob_get_clean();

// cek apakah masih ada thread berikutnya
if ($tab === 'following' && $is_logged_in) {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as c FROM threads t JOIN follows f ON f.followed_id = t.user_id WHERE t.parent_id IS NULL AND f.follower_id = ?");
    $stmtCount->execute([$_SESSION['user_id']]);
} else {
    $stmtCount = $pdo->query("SELECT COUNT(*) as c FROM threads WHERE parent_id IS NULL");
}
$totalThreads = $stmtCount->fetch()['c'];
$hasMore = ($offset + $perPage) < $totalThreads;

echo json_encode([
    'success' => true,
    'html' => $html,
    'hasMore' => $hasMore,
    'nextOffset' => $offset + $perPage
]);
