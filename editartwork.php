
<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$artworkId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$artworkId || $artworkId < 1) {
    http_response_code(400);
    exit('Invalid artwork ID.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$title = '';
$description = '';
$category = '';

$stmt = $pdo->prepare(
    'SELECT artwork_id, user_id, title, description, category
     FROM artworks
     WHERE artwork_id = :artwork_id AND user_id = :user_id
     LIMIT 1'
);
$stmt->execute([
    'artwork_id' => $artworkId,
    'user_id' => $userId
]);

$artwork = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artwork) {
    http_response_code(404);
    exit('Artwork not found, or you do not have permission to edit it.');
}

$title = $artwork['title'] ?? '';
$description = $artwork['description'] ?? '';
$category = $artwork['category'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please return to the artwork page and try again.');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if ($title === '') {
        $errors[] = 'The artwork title is required.';
    } elseif (mb_strlen($title) > 150) {
        $errors[] = 'The title must not exceed 150 characters.';
    }

    if (mb_strlen($description) > 5000) {
        $errors[] = 'The description must not exceed 5000 characters.';
    }

    if ($category === '') {
        $errors[] = 'The category is required.';
    } elseif (mb_strlen($category) > 100) {
        $errors[] = 'The category must not exceed 100 characters.';
    }

    if (!$errors) {
    
        $update = $pdo->prepare(
            'UPDATE artworks
             SET title = :title,
                 description = :description,
                 category = :category
             WHERE artwork_id = :artwork_id
               AND user_id = :user_id'
        );

        $update->execute([
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'artwork_id' => $artworkId,
            'user_id' => $userId
        ]);

        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: artwork.php?id=' . $artworkId . '&updated=1');
        exit;
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Artwork | Art Space</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: #100d19;
            color: #f5f1ff;
            font-family: Arial, sans-serif;
            padding: 35px 16px;
        }

        .container {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }

        .card {
            background: #1b1628;
            border: 1px solid #352747;
            border-radius: 18px;
            padding: 30px;
        }

        h1 { margin-top: 0; }
        .subtitle { color: #b9aecb; margin-bottom: 25px; }

        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #49375f;
            border-radius: 9px;
            background: #110d1b;
            color: #fff;
            font: inherit;
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        input:focus, textarea:focus {
            outline: 2px solid #9b6dff;
            border-color: #9b6dff;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .button {
            display: inline-block;
            border: 0;
            border-radius: 9px;
            padding: 12px 18px;
            background: #8b5cf6;
            color: white;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .button:hover { background: #7545df; }

        .secondary {
            background: #30263f;
            color: #e8ddff;
        }

        .secondary:hover { background: #423253; }

        .error {
            padding: 12px 15px;
            border-radius: 8px;
            background: #4a202e;
            color: #ffdce5;
            margin: 10px 0;
        }

        .hint {
            color: #b9aecb;
            font-size: 13px;
            margin-top: 6px;
        }

        @media (max-width: 500px) {
            .card { padding: 20px; }
            .button { text-align: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Edit Artwork</h1>
        <p class="subtitle">Update the details of your artwork.</p>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="POST"
              action="edit_artwork.php?id=<?= (int) $artworkId ?>">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <label for="title">Artwork Title</label>
            <input
                type="text"
                id="title"
                name="title"
                maxlength="150"
                value="<?= e($title) ?>"
                required
            >

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                maxlength="5000"
            ><?= e($description) ?></textarea>
            <p class="hint">Maximum 5,000 characters.</p>

            <label for="category">Category</label>
            <input
                type="text"
                id="category"
                name="category"
                maxlength="100"
                value="<?= e($category) ?>"
                required
            >

            <div class="buttons">
                <button type="submit" class="button">Save Changes</button>
                <a class="button secondary"
                   href="artwork.php?id=<?= (int) $artworkId ?>">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
