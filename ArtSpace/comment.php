
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

$currentUserId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$artworkId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$artworkId || $artworkId < 1) {
    header('Location: home.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT artwork_id, title
     FROM artworks
     WHERE artwork_id = ?'
);

$stmt->execute([$artworkId]);
$artwork = $stmt->fetch();

if (!$artwork) {
    header('Location: home.php');
    exit;
}

$error = '';
$commentText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid form token. Please return to the artwork and try again.');
    }

    $commentText = trim($_POST['comment_text'] ?? '');

    if ($commentText === '') {
        $error = 'Please write a comment before submitting.';
    } elseif (strlen($commentText) > 5000) {
        $error = 'Your comment must be no longer than 5,000 bytes.';
    } else {
        // Save the comment using the logged-in user's ID.
        $stmt = $pdo->prepare(
            'INSERT INTO comments (user_id, artwork_id, comment_text)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            $currentUserId,
            $artworkId,
            $commentText
        ]);

        header('Location: artwork.php?id=' . $artworkId . '#comments');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Write a Comment | Art Space</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .comment-page {
            max-width: 650px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .comment-card {
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 14px;
            padding: 32px;
        }

        .comment-card h1 {
            margin: 10px 0;
            overflow-wrap: anywhere;
        }

        .comment-card p {
            color: #aaa2b8;
            line-height: 1.6;
        }

        .comment-card label {
            display: block;
            margin: 22px 0 9px;
            color: #f5f1ff;
            font-weight: 600;
        }

        .comment-card textarea {
            width: 100%;
            min-height: 180px;
            padding: 14px;
            border: 1px solid #40344f;
            border-radius: 9px;
            background: #100e15;
            color: #f5f1ff;
            font: inherit;
            resize: vertical;
        }

        .comment-card textarea:focus {
            outline: 2px solid #8b5cf6;
            border-color: transparent;
        }

        .comment-error {
            margin-top: 15px;
            color: #fca5a5 !important;
        }

        .comment-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            margin-top: 22px;
        }

        .cancel-link {
            color: #c4b5fd;
            text-decoration: none;
        }

        .cancel-link:hover {
            color: #a78bfa;
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="home.php" class="logo">Art<span>Space.</span></a>

    <nav>
        <a href="home.php">Explore</a>
    </nav>

    <a href="logout.php" class="login-link">Log out</a>
</header>

<main class="comment-page">

    <section class="comment-card">

        <p class="eyebrow">JOIN THE CONVERSATION</p>

        <h1>Write a comment</h1>

        <p>
            Share your thoughts about
            <strong><?= e($artwork['title']) ?></strong>.
        </p>

        <?php if ($error !== ''): ?>
            <p class="comment-error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <label for="comment_text">Your comment</label>

            <textarea
                id="comment_text"
                name="comment_text"
                maxlength="5000"
                placeholder="What do you think about this artwork?"
                required
            ><?= e($commentText) ?></textarea>

            <div class="comment-actions">
                <button type="submit" class="btn btn-primary">
                    Post Comment
                </button>

                <a
                    href="artwork.php?id=<?= (int) $artworkId ?>"
                    class="cancel-link"
                >
                    Cancel
                </a>
            </div>
        </form>

    </section>

</main>

<footer>
    <a href="home.php" class="logo">Art<span>Space.</span></a>
    <p>Made for artists. Built for creativity.</p>
</footer>

</body>
</html>
```
