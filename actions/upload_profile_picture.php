<?php
// actions/upload_profile_picture.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Belum login']);
    exit;
}

require_once '../core/db.php';
require_once '../core/helpers.php';
check_csrf();

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$filename = $_FILES['profile_picture']['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Format gambar tidak didukung (jpg, png, gif, webp)']);
    exit;
}

if ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) { // 2MB
    echo json_encode(['success' => false, 'message' => 'Ukuran gambar terlalu besar (maks 2MB)']);
    exit;
}

try {
    // Create profile uploads directory if not exists
    $uploadDir = '../uploads/profile/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $newFilename = 'user_' . $_SESSION['user_id'] . '_' . uniqid() . '.' . $ext;
    $dest = $uploadDir . $newFilename;
    $relativePath = 'uploads/profile/' . $newFilename;

    // Get old image to delete later
    $stmt = $pdo->prepare("SELECT image_path FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $oldImage = $stmt->fetchColumn();

    // Compress and resize image (max 400x400)
    $info = getimagesize($_FILES['profile_picture']['tmp_name']);
    $image = null;

    if ($info['mime'] == 'image/jpeg')
        $image = imagecreatefromjpeg($_FILES['profile_picture']['tmp_name']);
    elseif ($info['mime'] == 'image/gif')
        $image = imagecreatefromgif($_FILES['profile_picture']['tmp_name']);
    elseif ($info['mime'] == 'image/png')
        $image = imagecreatefrompng($_FILES['profile_picture']['tmp_name']);
    elseif ($info['mime'] == 'image/webp')
        $image = imagecreatefromwebp($_FILES['profile_picture']['tmp_name']);

    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Failed to process image']);
        exit;
    }

    // Resize to 400x400 (square crop from center)
    $width = imagesx($image);
    $height = imagesy($image);
    $size = min($width, $height);
    $x = ($width - $size) / 2;
    $y = ($height - $size) / 2;

    $thumb = imagecreatetruecolor(400, 400);

    // Preserve transparency for PNG/GIF/WEBP
    if ($info['mime'] == 'image/png' || $info['mime'] == 'image/webp' || $info['mime'] == 'image/gif') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, 400, 400, $transparent);
    }

    imagecopyresampled($thumb, $image, 0, 0, $x, $y, 400, 400, $size, $size);

    // Save
    $saved = false;
    if ($info['mime'] == 'image/jpeg')
        $saved = imagejpeg($thumb, $dest, 85);
    elseif ($info['mime'] == 'image/gif')
        $saved = imagegif($thumb, $dest);
    elseif ($info['mime'] == 'image/png')
        $saved = imagepng($thumb, $dest, 8);
    elseif ($info['mime'] == 'image/webp')
        $saved = imagewebp($thumb, $dest, 85);

    imagedestroy($image);
    imagedestroy($thumb);

    if (!$saved) {
        echo json_encode(['success' => false, 'message' => 'Failed to save image']);
        exit;
    }

    // Update database
    $stmt = $pdo->prepare("UPDATE users SET image_path = ? WHERE id = ?");
    $stmt->execute([$relativePath, $_SESSION['user_id']]);

    // Delete old image if exists and not default
    if ($oldImage && $oldImage !== 'assets/default-avatar.png' && file_exists('../' . $oldImage)) {
        unlink('../' . $oldImage);
    }

    echo json_encode([
        'success' => true,
        'image_path' => $relativePath,
        'message' => 'Profile picture updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
