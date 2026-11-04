
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);

    if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
        $error = 'Please select a rating from 1 to 5 stars.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO ratings (user_id, artwork_id, rating)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                updated_at = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            $_SESSION['user_id'],
            $artworkId,
            $rating
        ]);

        header('Location: artwork.php?id=' . $artworkId);
        exit;
    }
}

$stmt = $pdo->prepare(
    'SELECT rating
     FROM ratings
     WHERE user_id = ? AND artwork_id = ?'
);
$stmt->execute([$_SESSION['user_id'], $artworkId]);
$currentRating = $stmt->fetchColumn();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Artwork | Art Space</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .rating-page {
            max-width: 600px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .rating-card {
            padding: 32px;
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 14px;
            text-align: center;
        }

        .rating-card h1 {
            margin-bottom: 12px;
            overflow-wrap: anywhere;
        }

        .rating-card p {
            color: #aaa2b8;
            line-height: 1.6;
        }

        .stars {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 28px 0;
            flex-wrap: wrap;
        }

        .stars input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .stars label {
            font-size: 42px;
            color: #554b63;
            cursor: pointer;
            transition: color 0.15s, transform 0.15s;
        }

        .stars label:hover {
            transform: scale(1.12);
        }

        .stars label:has(input:checked),
        .stars label:has(input:focus-visible) {
            color: #fbbf24;
        }

        .stars:has(input[value="1"]:checked) label:nth-child(-n+1),
        .stars:has(input[value="2"]:checked) label:nth-child(-n+2),
        .stars:has(input[value="3"]:checked) label:nth-child(-n+3),
        .stars:has(input[value="4"]:checked) label:nth-child(-n+4),
        .stars:has(input[value="5"]:checked) label:nth-child(-n+5) {
            color: #fbbf24;
        }

        .rating-error {
            color: #fca5a5 !important;
            margin-bottom: 15px;
        }

        .rating-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
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

<main class="rating-page">
    <section class="rating-card">

        <p class="eyebrow">SUPPORT THE CREATOR</p>

        <h1>Rate this artwork</h1>

        <p>
            You're rating <strong><?= e($artwork['title']) ?></strong>.
            Choose between 1 and 5 stars.
        </p>

        <?php if ($error !== ''): ?>
            <p class="rating-error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="stars" role="radiogroup" aria-label="Artwork rating">

                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label>
                        <input
                            type="radio"
                            name="rating"
                            value="<?= $i ?>"
                            required
                            <?= (string) $currentRating === (string) $i ? 'checked' : '' ?>
                        >
                        <span aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</span>
                    </label>
                <?php endfor; ?>

            </div>

            <div class="rating-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $currentRating !== false ? 'Update Rating' : 'Submit Rating' ?>
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
