
<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed. Artwork must be deleted through the confirmation form.');
}

$userId = (int) $_SESSION['user_id'];
$artworkId = filter_input(INPUT_POST, 'artwork_id', FILTER_VALIDATE_INT);
$submittedToken = $_POST['csrf_token'] ?? '';

if (!$artworkId || $artworkId < 1) {
    http_response_code(400);
    exit('Invalid artwork ID.');
}

if (
    !is_string($submittedToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT artwork_id, image_path
         FROM artworks
         WHERE artwork_id = :artwork_id
           AND user_id = :user_id
         FOR UPDATE'
    );

    $stmt->execute([
        'artwork_id' => $artworkId,
        'user_id' => $userId
    ]);

    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artwork) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Artwork not found, or you do not have permission to delete it.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM ratings WHERE artwork_id = :artwork_id'
    );
    $stmt->execute(['artwork_id' => $artworkId]);

    $stmt = $pdo->prepare(
        'DELETE FROM comments WHERE artwork_id = :artwork_id'
    );
    $stmt->execute(['artwork_id' => $artworkId]);

    $stmt = $pdo->prepare(
        'DELETE FROM artworks
         WHERE artwork_id = :artwork_id
           AND user_id = :user_id'
    );

    $stmt->execute([
        'artwork_id' => $artworkId,
        'user_id' => $userId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The artwork could not be deleted.');
    }

    $imagePath = $artwork['image_path'] ?? '';

    $pdo->commit();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if (is_string($imagePath) && $imagePath !== '') {
        $uploadsRoot = realpath(__DIR__ . '/uploads');
        $imageFullPath = realpath(__DIR__ . '/' . ltrim($imagePath, '/\\'));

        if (
            $uploadsRoot !== false &&
            $imageFullPath !== false &&
            is_file($imageFullPath) &&
            strpos(
                $imageFullPath,
                $uploadsRoot . DIRECTORY_SEPARATOR
            ) === 0
        ) {
            @unlink($imageFullPath);
        }
    }

    header('Location: profile.php?id=' . $userId . '&deleted=1');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Art Space artwork deletion failed: ' . $e->getMessage());

    http_response_code(500);
    exit('The artwork could not be deleted. Please check your database configuration and try again.');
}
