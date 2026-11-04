

<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$errors = [];
$title = '';
$description = '';
$category = '';

$allowedCategories = [
    'Digital Drawing',
    'Traditional Art',
    'Digital Painting',
    'Photography',
    '3D Art',
    'Pixel Art',
    'Graphic Design',
    'Illustration',
    'Sculpture',
    'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';

    if ($title === '' || strlen($title) > 150) {
        $errors[] = 'Title is required and must be 150 characters or fewer.';
    }

    if (!in_array($category, $allowedCategories, true)) {
        $errors[] = 'Please select a valid artwork category.';
    }

    if (strlen($description) > 10000) {
        $errors[] = 'Description is too long.';
    }

    if (
        !isset($_FILES['artwork']) ||
        $_FILES['artwork']['error'] !== UPLOAD_ERR_OK
    ) {
        $errors[] = 'Please select an image to upload.';
    }

    $imageExtension = '';
    $temporaryPath = '';

    if (empty($errors)) {
        $file = $_FILES['artwork'];
        $temporaryPath = $file['tmp_name'];

        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image size must not exceed 5 MB.';
        }

        $imageInfo = @getimagesize($temporaryPath);

        if ($imageInfo === false) {
            $errors[] = 'The selected file must be a valid image.';
        } else {
            $mime = $imageInfo['mime'];

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            if (!isset($allowedTypes[$mime])) {
                $errors[] = 'Use a JPG, PNG, GIF, or WebP image.';
            } else {
                $imageExtension = $allowedTypes[$mime];
            }
        }
    }

    if (empty($errors)) {
        $uploadDirectory = __DIR__ . '/uploads/';

        if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
            $errors[] = 'The uploads folder is missing or not writable.';
        } else {
            $fileName = bin2hex(random_bytes(16)) . '.' . $imageExtension;
            $destination = $uploadDirectory . $fileName;
            $relativePath = 'uploads/' . $fileName;

            if (!move_uploaded_file($temporaryPath, $destination)) {
                $errors[] = 'Could not save the image. Please try again.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO artworks
                         (user_id, title, description, category, image_path)
                         VALUES (?, ?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $_SESSION['user_id'],
                        $title,
                        $description,
                        $category,
                        $relativePath
                    ]);

                    header('Location: home.php');
                    exit;
                } catch (PDOException $e) {
                    if (is_file($destination)) {
                        unlink($destination);
                    }

                    error_log($e->getMessage());
                    $errors[] = 'Could not save your artwork details. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Share Artwork | Art Space</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .upload-container {
            max-width: 650px;
            margin: 50px auto;
            padding: 32px;
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 16px;
        }

        .upload-container h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            margin: 8px 0;
        }

        .upload-intro {
            color: #aaa2b8;
            margin-bottom: 28px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background: #0d0b12;
            color: #f5f1ff;
            border: 1px solid #40354c;
            border-radius: 8px;
            font: inherit;
        }

        .form-group textarea {
            min-height: 130px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: 2px solid #a78bfa;
            border-color: transparent;
        }

        .form-hint {
            display: block;
            color: #aaa2b8;
            font-size: 12px;
            margin-top: 7px;
        }

        .upload-container button {
            width: 100%;
            border: none;
            cursor: pointer;
            font: inherit;
            font-weight: 600;
        }

        .error-list {
            margin-bottom: 20px;
            padding: 12px 12px 12px 32px;
            border-radius: 8px;
            background: #40202b;
            color: #ffc5d0;
            font-size: 13px;
        }

        @media (max-width: 700px) {
            .upload-container {
                margin: 30px 16px;
                padding: 24px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="home.php" class="logo">Art<span>Space.</span></a>
</header>

<main class="upload-container">

    <p class="eyebrow">SHOW THE WORLD YOUR CREATIVITY</p>

    <h1>Share your artwork</h1>

    <p class="upload-intro">
        Upload your creation and let the Art Space community discover it.
    </p>

    <?php if (!empty($errors)): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="upload.php" enctype="multipart/form-data">

        <div class="form-group">
            <label for="title">Artwork title</label>

            <input
                type="text"
                id="title"
                name="title"
                maxlength="150"
                value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="category">Category</label>

            <select id="category" name="category" required>
                <option value="">Choose a category</option>

                <?php foreach ($allowedCategories as $option): ?>
                    <option
                        value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $category === $option ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                maxlength="10000"
                placeholder="Tell us about your artwork..."
            ><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group">
            <label for="artwork">Artwork image</label>

            <input
                type="file"
                id="artwork"
                name="artwork"
                accept="image/jpeg,image/png,image/gif,image/webp"
                required
            >

            <span class="form-hint">
                JPG, PNG, GIF, or WebP. Maximum size: 5 MB.
            </span>
        </div>

        <button type="submit" class="btn btn-primary">
            Publish Artwork
        </button>

    </form>

</main>

</body>
</html>
