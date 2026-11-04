


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
    http_response_code(404);
    exit('Invalid artwork ID. Please return to the gallery.');
}

$stmt = $pdo->prepare(
    'SELECT
        a.artwork_id,
        a.title,
        a.description,
        a.category,
        a.image_path,
        a.created_at,
        a.user_id AS artwork_owner_id,
        u.user_id AS artist_id,
        u.username,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COUNT(DISTINCT r.rating_id) AS rating_count
     FROM artworks a
     JOIN users u ON a.user_id = u.user_id
     LEFT JOIN ratings r ON a.artwork_id = r.artwork_id
     WHERE a.artwork_id = ?
     GROUP BY a.artwork_id'
);

$stmt->execute([$artworkId]);
$artwork = $stmt->fetch();

if (!$artwork) {
    http_response_code(404);
    exit('Artwork not found. It may have been removed.');
}

$isOwner = (int) $artwork['artwork_owner_id'] === $currentUserId;

$stmt = $pdo->prepare(
    'SELECT
        c.comment_text,
        c.created_at,
        u.user_id AS commenter_id,
        u.username
     FROM comments c
     JOIN users u ON c.user_id = u.user_id
     WHERE c.artwork_id = ?
     ORDER BY c.created_at DESC'
);

$stmt->execute([$artworkId]);
$comments = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT rating
     FROM ratings
     WHERE artwork_id = ? AND user_id = ?'
);

$stmt->execute([$artworkId, $currentUserId]);
$userRating = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($artwork['title']) ?> | Art Space</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        /* Custom Delete Confirmation Modal */

        .delete-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.78);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 9999;
        }

        .delete-modal-overlay.active {
            display: flex;
        }

        .delete-modal {
            width: 100%;
            max-width: 430px;
            background: #17131f;
            border: 1px solid #393047;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalAppear 0.2s ease;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .delete-modal-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #451a25;
            color: #fca5a5;
            font-size: 24px;
            margin-bottom: 18px;
        }

        .delete-modal h2 {
            color: #f5f0ff;
            font-size: 22px;
            margin: 0 0 10px;
        }

        .delete-modal p {
            color: #aaa2b8;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 25px;
        }

        .delete-modal-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }

        .modal-cancel-button,
        .modal-delete-button {
            border: none;
            border-radius: 8px;
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .modal-cancel-button {
            background: #30283b;
            color: #ddd6fe;
        }

        .modal-cancel-button:hover {
            background: #40354f;
        }

        .modal-delete-button {
            background: #dc2626;
            color: white;
        }

        .modal-delete-button:hover {
            background: #b91c1c;
        }

        @media (max-width: 480px) {
            .delete-modal {
                padding: 22px;
            }

            .delete-modal-actions {
                flex-direction: column-reverse;
            }

            .modal-cancel-button,
            .modal-delete-button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header class="navbar">

    <a href="home.php" class="logo">
        Art<span>Space.</span>
    </a>

    <a href="profile.php?id=<?= $currentUserId ?>" class="login-link">
        My Profile
    </a>

</header>


<main class="artwork-page">

    <a href="home.php" class="back-link">
        &larr; Back to gallery
    </a>

    <div class="artwork-layout">

        <div class="artwork-visual">

            <img
                src="<?= e($artwork['image_path']) ?>"
                alt="<?= e($artwork['title']) ?>"
                class="artwork-image"
            >

        </div>

        <section class="artwork-info">

            <p class="eyebrow">
                <?= e($artwork['category']) ?>
            </p>

            <h1>
                <?= e($artwork['title']) ?>
            </h1>

            <p class="creator">

                Created by

                <a
                    href="profile.php?id=<?= (int) $artwork['artist_id'] ?>"
                    class="artist-profile-link"
                >
                    <?= e($artwork['username']) ?>
                </a>

            </p>

            <p>
                <?= nl2br(e(
                    $artwork['description'] ?: 'No description provided.'
                )) ?>
            </p>

            <div class="artwork-stats">

                <span class="stat">
                    ★ <?= number_format(
                        (float) $artwork['average_rating'],
                        1
                    ) ?>/5
                </span>

                <span class="stat">
                    <?= (int) $artwork['rating_count'] ?> ratings
                </span>

                <span class="stat">
                    <?= count($comments) ?> comments
                </span>

            </div>

            <p>
                Shared on
                <?= e(date(
                    'F j, Y',
                    strtotime($artwork['created_at'])
                )) ?>
            </p>

            <?php if ($userRating !== false): ?>

                <p style="margin-top: 12px;">
                    Your rating: <?= (int) $userRating ?>/5
                </p>

            <?php endif; ?>

            <div class="artwork-actions">

                <?php if ($isOwner): ?>

                    <a
                        href="edit_artwork.php?id=<?= (int) $artwork['artwork_id'] ?>"
                        class="action-link edit-action"
                    >
                        Edit Artwork
                    </a>

                    <form
                        action="delete_artwork.php"
                        method="POST"
                        class="delete-form"
                        id="deleteArtworkForm"
                    >

                        <input
                            type="hidden"
                            name="artwork_id"
                            value="<?= (int) $artwork['artwork_id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrfToken) ?>"
                        >

                        <button
                            type="submit"
                            class="action-link delete-action"
                        >
                            Delete Artwork
                        </button>

                    </form>

                <?php endif; ?>

                <a
                    href="rate.php?id=<?= (int) $artwork['artwork_id'] ?>"
                    class="action-link"
                >
                    <?= $userRating !== false
                        ? 'Update Rating'
                        : 'Rate Artwork' ?>
                </a>

                <a
                    href="comment.php?id=<?= (int) $artwork['artwork_id'] ?>"
                    class="action-link"
                >
                    Write a Comment
                </a>

            </div>

        </section>

    </div>

    <section class="comments-panel" id="comments">

        <h2>Community Comments</h2>

        <?php if (empty($comments)): ?>

            <p class="no-comments">
                No comments yet. Be the first to share your thoughts!
            </p>

        <?php else: ?>

            <?php foreach ($comments as $comment): ?>

                <article class="comment">

                    <p class="comment-author">

                        <a
                            href="profile.php?id=<?= (int) $comment['commenter_id'] ?>"
                            class="artist-profile-link"
                        >
                            <?= e($comment['username']) ?>
                        </a>

                    </p>

                    <p class="comment-date">

                        <?= e(date(
                            'M j, Y \a\t g:i A',
                            strtotime($comment['created_at'])
                        )) ?>

                    </p>

                    <p class="comment-text">
                        <?= e($comment['comment_text']) ?>
                    </p>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>




<div
    class="delete-modal-overlay"
    id="deleteModal"
    aria-hidden="true"
>

    <div
        class="delete-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteModalTitle"
        aria-describedby="deleteModalMessage"
    >

        <div class="delete-modal-icon">
            !
        </div>

        <h2 id="deleteModalTitle">
            Delete Artwork?
        </h2>

        <p id="deleteModalMessage">
            Are you sure you want to delete this artwork?
            This action cannot be undone.
        </p>

        <div class="delete-modal-actions">

            <button
                type="button"
                class="modal-cancel-button"
                id="cancelDelete"
            >
                Cancel
            </button>

            <button
                type="button"
                class="modal-delete-button"
                id="confirmDelete"
            >
                Delete Artwork
            </button>

        </div>

    </div>

</div>


<footer>

    <a href="home.php" class="logo">
        Art<span>Space.</span>
    </a>

    <p>
        Made for artists. Built for creativity.
    </p>

</footer>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const deleteForm = document.getElementById('deleteArtworkForm');
    const deleteModal = document.getElementById('deleteModal');
    const cancelButton = document.getElementById('cancelDelete');
    const confirmButton = document.getElementById('confirmDelete');

    if (!deleteForm || !deleteModal) {
        return;
    }

    function openModal() {
        deleteModal.classList.add('active');
        deleteModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        cancelButton.focus();
    }

    function closeModal() {
        deleteModal.classList.remove('active');
        deleteModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    deleteForm.addEventListener('submit', function (event) {
        event.preventDefault();
        openModal();
    });

    cancelButton.addEventListener('click', function () {
        closeModal();
    });

    confirmButton.addEventListener('click', function () {
        confirmButton.disabled = true;
        confirmButton.textContent = 'Deleting...';

        deleteForm.submit();
    });

    deleteModal.addEventListener('click', function (event) {
        if (event.target === deleteModal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            deleteModal.classList.contains('active')
        ) {
            closeModal();
        }
    });

});
</script>

</body>
</html>
