<?php
// helpers.php
function e($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function parseContent($text)
{
    // 1. Escape HTML first
    $text = e($text);

    // 2. Link Hashtags (#tag)
    $text = preg_replace_callback('/(^|\s)#([\p{L}0-9_]+)/u', function ($matches) {
        $space = $matches[1];
        $tag = $matches[2];
        $url = 'search.php?q=' . urlencode('#' . $tag);
        return $space . '<a href="' . $url . '" class="text-blue-500 hover:underline">#' . $tag . '</a>';
    }, $text);

    // 3. Link Mentions (@username)
    $text = preg_replace_callback('/@([a-zA-Z0-9_]+)/', function ($matches) {
        $username = $matches[1];
        $url = 'profile.php?u=' . urlencode($username);
        return '<a href="' . $url . '">@' . $username . '</a>';
    }, $text);

    // 4. Convert newlines to <br>
    return nl2br($text);
}

// Deprecated: Alias for backward compatibility if needed, but we should switch to parseContent
function linkHashtags($text)
{
    return parseContent($text); // Note: parseContent already escapes, so don't double escape if using this alias incorrectly
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function check_csrf()
{
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            // Check if client expects JSON
            $isJson = false;
            if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                $isJson = true;
            }
            // Or if it's an AJAX request (common header)
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                $isJson = true;
            }

            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
                exit;
            } else {
                die('CSRF token invalid');
            }
        }
    }
}

function compressImage($source, $destination, $quality)
{
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg')
        $image = imagecreatefromjpeg($source);
    elseif ($info['mime'] == 'image/gif')
        $image = imagecreatefromgif($source);
    elseif ($info['mime'] == 'image/png')
        $image = imagecreatefrompng($source);
    elseif ($info['mime'] == 'image/webp')
        $image = imagecreatefromwebp($source);
    else
        return false;

    // Resize if width > 1200
    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 1200;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
        $tmp = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency
        if ($info['mime'] == 'image/png' || $info['mime'] == 'image/webp' || $info['mime'] == 'image/gif') {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
            imagefilledrectangle($tmp, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($tmp, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $tmp;
    }

    // Save
    $result = false;
    if ($info['mime'] == 'image/jpeg')
        $result = imagejpeg($image, $destination, $quality);
    elseif ($info['mime'] == 'image/gif')
        $result = imagegif($image, $destination);
    elseif ($info['mime'] == 'image/png')
        $result = imagepng($image, $destination, 9); // 0-9
    elseif ($info['mime'] == 'image/webp')
        $result = imagewebp($image, $destination, $quality);

    imagedestroy($image);
    return $result;
}

function createNotification($userId, $actorId, $type, $referenceId)
{
    global $pdo;

    // Don't notify self
    if ($userId == $actorId)
        return;

    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, reference_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $actorId, $type, $referenceId]);
    } catch (PDOException $e) {
        // Ignore errors to prevent blocking main action
    }
}

/**
 * Check if current logged-in user is an admin
 * @return bool
 */
function isAdmin()
{
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        return $user && $user['role'] === 'admin';
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Require admin permission, redirect if not admin
 */
function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit;
    }
}

function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );

    $values = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    ];

    foreach ($string as $k => &$v) {
        if ($values[$k]) {
            $v = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full)
        $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
