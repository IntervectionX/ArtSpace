

<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare(
    'SELECT username, bio, profile_picture
     FROM users
     WHERE user_id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Your session could not be verified. Please try again.';
    } else {
        $bio = trim($_POST['bio'] ?? '');

        if (strlen($bio) > 2000) {
            $error = 'Your bio must not exceed 2000 characters.';
        } else {
            $profilePicture = $user['profile_picture'];

            if (
                isset($_FILES['profile_picture']) &&
                $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $file = $_FILES['profile_picture'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = 'The image upload failed. Please try again.';
                } elseif ($file['size'] > 2 * 1024 * 1024) {
                    $error = 'Your profile picture must be 2 MB or smaller.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($file['tmp_name']);

                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($allowedTypes[$mimeType])) {
                        $error = 'Use a JPG, PNG, or WEBP image.';
                    } elseif (@getimagesize($file['tmp_name']) === false) {
                        $error = 'The uploaded file is not a valid image.';
                    } else {
                        $uploadDirectory =
                            __DIR__ . '/uploads/profile_pictures/';

                        if (
                            !is_dir($uploadDirectory) &&
                            !mkdir($uploadDirectory, 0755, true) &&
                            !is_dir($uploadDirectory)
                        ) {
                            $error = 'Could not create the profile picture folder.';
                        } else {
                            $fileName = bin2hex(random_bytes(16))
                                . '.' . $allowedTypes[$mimeType];

                            $destination = $uploadDirectory . $fileName;

                            if (move_uploaded_file(
                                $file['tmp_name'],
                                $destination
                            )) {
                                $profilePicture =
                                    'uploads/profile_pictures/' . $fileName;
                            } else {
                                $error = 'Could not save your profile picture.';
                            }
                        }
                    }
                }
            }

            if ($error === '') {
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET bio = ?, profile_picture = ?
                     WHERE user_id = ?'
                );

                $stmt->execute([
                    $bio !== '' ? $bio : null,
                    $profilePicture,
                    $userId
                ]);

                header('Location: profile.php?id=' . $userId . '&updated=1');
                exit;
            }
        }
    }

    $user['bio'] = $_POST['bio'] ?? $user['bio'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profile | Art Space</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .edit-page {
            max-width: 680px;
            margin: 0 auto;
            padding: 45px 6% 80px;
        }

        .edit-card {
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 16px;
            padding: 30px;
        }

        .edit-card h1 {
            margin: 0 0 8px;
        }

        .edit-subtitle {
            color: #aaa2b8;
            margin-bottom: 28px;
        }

        .profile-preview {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin-bottom: 18px;
            border: 2px solid #8b5cf6;
            background: #25202f;
        }

        .profile-initial {
            width: 110px;
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #8b5cf6;
            color: white;
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            color: #ddd6fe;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .form-group textarea,
        .form-group input[type="file"] {
            width: 100%;
            box-sizing: border-box;
            color: #eee8f7;
            background: #100e15;
            border: 1px solid #393047;
            border-radius: 9px;
            padding: 12px;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-group small {
            display: block;
            color: #9990a8;
            margin-top: 8px;
        }

        .username-display {
            color: #b6aec3;
            background: #25202f;
            padding: 12px;
            border-radius: 9px;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
        }

        .save-button,
        .cancel-button {
            display: inline-block;
            border: none;
            border-radius: 8px;
            padding: 12px 18px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .save-button {
            color: white;
            background: #8b5cf6;
        }

        .save-button:hover {
            background: #7c3aed;
        }

        .cancel-button {
            color: #ddd6fe;
            background: #30283b;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .error-message {
            color: #fecaca;
            background: #451a25;
        }

        @media (max-width: 500px) {
            .edit-card {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">

    <a href="home.php" class="logo">
        Art<span>Space.</span>
    </a>

    <!-- Explore and Share Artwork removed -->

    <a href="logout.php" class="login-link">
        Log out
    </a>

</header>

<main class="edit-page">

    <section class="edit-card">

        <h1>Edit Your Profile</h1>

        <p class="edit-subtitle">
            Tell the community about yourself and your art.
        </p>

        <?php if ($error !== ''): ?>
            <div class="message error-message">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="form-group">

                <label>Profile Picture</label>

                <?php if (!empty($user['profile_picture'])): ?>

                    <img
                        src="<?= e($user['profile_picture']) ?>"
                        alt="Current profile picture"
                        class="profile-preview"
                    >

                <?php else: ?>

                    <div class="profile-initial">
                        <?= e(strtoupper(substr($user['username'], 0, 1))) ?>
                    </div>

                <?php endif; ?>

                <input
                    type="file"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/webp"
                >

                <small>
                    JPG, PNG, or WEBP. Maximum file size: 2 MB.
                </small>

            </div>

            <div class="form-group">

                <label>Username</label>

                <div class="username-display">
                    <?= e($user['username']) ?>
                </div>

                <small>Your username cannot be changed here.</small>

            </div>

            <div class="form-group">

                <label for="bio">About Me</label>

                <textarea
                    id="bio"
                    name="bio"
                    maxlength="2000"
                    placeholder="Tell other artists a little about yourself..."
                ><?= e($user['bio'] ?? '') ?></textarea>

                <small>Maximum 2000 characters.</small>

            </div>

            <div class="form-actions">

                <button type="submit" class="save-button">
                    Save Changes
                </button>

                <a
                    href="profile.php?id=<?= $userId ?>"
                    class="cancel-button"
                >
                    Cancel
                </a>

            </div>

        </form>

    </section>

</main>

<footer>

    <a href="home.php" class="logo">
        Art<span>Space.</span>
    </a>

    <p>Made for artists. Built for creativity.</p>

</footer>

</body>
</html>
