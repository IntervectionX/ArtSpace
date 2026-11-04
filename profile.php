
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

$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$profileId || $profileId < 1) {
    $profileId = (int) $_SESSION['user_id'];
}

$stmt = $pdo->prepare(
    'SELECT
        u.user_id,
        u.username,
        u.bio,
        u.profile_picture,
        u.created_at,
        (SELECT COUNT(*) FROM artworks a
         WHERE a.user_id = u.user_id) AS artwork_count,
        (SELECT COUNT(*) FROM ratings r
         JOIN artworks a ON a.artwork_id = r.artwork_id
         WHERE a.user_id = u.user_id) AS ratings_received,
        (SELECT COUNT(*) FROM comments c
         JOIN artworks a ON a.artwork_id = c.artwork_id
         WHERE a.user_id = u.user_id) AS comments_received
     FROM users u
     WHERE u.user_id = ?'
);

$stmt->execute([$profileId]);
$artist = $stmt->fetch();

if (!$artist) {
    http_response_code(404);
    exit('Artist profile not found.');
}

$isOwnProfile = (int) $artist['user_id'] === (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS total_ratings,
            COALESCE(AVG(r.rating), 0) AS overall_average
     FROM artworks a
     INNER JOIN ratings r ON r.artwork_id = a.artwork_id
     WHERE a.user_id = ?'
);

$stmt->execute([$profileId]);
$overallRating = $stmt->fetch();

$totalRatings = (int) $overallRating['total_ratings'];
$averageRating = (float) $overallRating['overall_average'];

$stmt = $pdo->prepare(
    'SELECT a.artwork_id, a.title, a.category, a.image_path, a.created_at,
            COALESCE(AVG(r.rating), 0) AS average_rating,
            COUNT(DISTINCT r.rating_id) AS rating_count
     FROM artworks a
     LEFT JOIN ratings r ON a.artwork_id = r.artwork_id
     WHERE a.user_id = ?
     GROUP BY a.artwork_id
     ORDER BY a.created_at DESC'
);

$stmt->execute([$profileId]);
$artworks = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($artist['username']) ?> | Art Space</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .delete-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(5px);
        }

        .delete-modal-overlay.active {
            display: flex;
        }

        .delete-modal {
            width: 100%;
            max-width: 430px;
            padding: 30px;
            background: #17131f;
            border: 1px solid #594576;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
            animation: modalAppear .2s ease;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(12px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .delete-modal-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            margin-bottom: 18px;
            background: rgba(248, 113, 113, .12);
            border: 1px solid rgba(248, 113, 113, .3);
            border-radius: 50%;
            color: #f87171;
            font-size: 25px;
        }

        .delete-modal h2 {
            margin-bottom: 10px;
            color: #f5f1ff;
            font-size: 23px;
        }

        .delete-modal p {
            margin-bottom: 25px;
            color: #aaa2b8;
            font-size: 14px;
            line-height: 1.7;
        }

        .delete-modal-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }

        .modal-cancel,
        .modal-delete {
            min-height: 42px;
            padding: 10px 18px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s ease,
                        border-color .2s ease,
                        transform .2s ease;
        }

        .modal-cancel {
            background: #25202f;
            border-color: #393047;
            color: #ddd6e8;
        }

        .modal-cancel:hover {
            background: #30283b;
            border-color: #594576;
        }

        .modal-delete {
            background: #8f303d;
            color: #fff;
        }

        .modal-delete:hover {
            background: #b42335;
            transform: translateY(-1px);
        }

        @media (max-width: 480px) {
            .delete-modal {
                padding: 23px;
            }

            .delete-modal-actions {
                flex-direction: column-reverse;
            }

            .modal-cancel,
            .modal-delete {
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

    <nav>
        <a href="home.php">Explore</a>
    </nav>

    <div class="nav-user">
        <a href="logout.php" class="login-link">Log out</a>
    </div>

</header>


<main class="profile-page">

    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1' && $isOwnProfile): ?>

        <div class="profile-notice">
            Your profile has been updated successfully!
        </div>

    <?php endif; ?>


    <section class="profile-header">

        <?php if (!empty($artist['profile_picture'])): ?>

            <img
                src="<?= e($artist['profile_picture']) ?>"
                alt="<?= e($artist['username']) ?>'s profile picture"
                class="profile-avatar"
            >

        <?php else: ?>

            <div class="profile-initial">
                <?= e(strtoupper(substr($artist['username'], 0, 1))) ?>
            </div>

        <?php endif; ?>


        <div class="profile-details">

            <h1><?= e($artist['username']) ?></h1>

            <p class="profile-bio">
                <?= e(
                    $artist['bio'] ?: 'This artist has not added a bio yet.'
                ) ?>
            </p>

            <p class="profile-joined">
                Member since
                <?= e(date('F Y', strtotime($artist['created_at']))) ?>
            </p>

            <?php if ($isOwnProfile): ?>

                <a href="edit_profile.php" class="edit-profile-button">
                    Edit Profile
                </a>

            <?php endif; ?>

        </div>

    </section>


    <section class="overall-rating" aria-labelledby="artist-rating-title">

        <h2 id="artist-rating-title">Artist Rating</h2>

        <div class="rating-score">

            <strong>
                <?= $totalRatings > 0
                    ? number_format($averageRating, 1)
                    : '—' ?>
            </strong>

            <div
                class="rating-stars"
                aria-label="<?= $totalRatings > 0
                    ? e(number_format($averageRating, 1)) . ' out of 5'
                    : 'No ratings yet' ?>"
            >

                <?php
                $roundedRating = (int) round($averageRating);

                for ($i = 1; $i <= 5; $i++):
                ?>

                    <?= $i <= $roundedRating ? '★' : '☆' ?>

                <?php endfor; ?>

            </div>

            <p class="rating-total">
                <?= $totalRatings ?>
                <?= $totalRatings === 1 ? 'rating' : 'ratings' ?>
            </p>

        </div>

    </section>

    <section class="profile-stats">

        <div class="profile-stat">
            <strong><?= (int) $artist['artwork_count'] ?></strong>
            <span>Artworks</span>
        </div>

        <div class="profile-stat">
            <strong><?= (int) $artist['ratings_received'] ?></strong>
            <span>Ratings Received</span>
        </div>

        <div class="profile-stat">
            <strong><?= (int) $artist['comments_received'] ?></strong>
            <span>Comments Received</span>
        </div>

    </section>

    <section class="gallery-section">

        <div class="gallery-heading">

            <h2>
                <?= $isOwnProfile
                    ? 'My Gallery'
                    : e($artist['username']) . "'s Gallery" ?>
            </h2>

            <?php if ($isOwnProfile): ?>

                <a href="upload.php" class="share-artwork-link">
                    + Share Artwork
                </a>

            <?php endif; ?>

        </div>


        <div class="artist-gallery">

            <?php if (empty($artworks)): ?>

                <div class="empty-gallery">

                    <h3>No artworks yet</h3>

                    <p>
                        <?= $isOwnProfile
                            ? 'Share your first artwork to start building your gallery!'
                            : 'This artist has not shared any artwork yet.' ?>
                    </p>

                    <?php if ($isOwnProfile): ?>

                        <a href="upload.php" class="edit-profile-button">
                            Upload Your First Artwork
                        </a>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <?php foreach ($artworks as $artwork): ?>

                    <article class="artist-artwork-card">

                        <a href="artwork.php?id=<?= (int) $artwork['artwork_id'] ?>">

                            <img
                                src="<?= e($artwork['image_path']) ?>"
                                alt="<?= e($artwork['title']) ?>"
                                class="artist-artwork-image"
                                loading="lazy"
                            >

                            <div class="artist-artwork-info">

                                <span class="artist-artwork-category">
                                    <?= e($artwork['category']) ?>
                                </span>

                                <h3>
                                    <?= e($artwork['title']) ?>
                                </h3>

                                <p class="artist-artwork-rating">

                                    <?php if ((int) $artwork['rating_count'] > 0): ?>

                                        ★ <?= number_format(
                                            (float) $artwork['average_rating'],
                                            1
                                        ) ?>/5

                                        · <?= (int) $artwork['rating_count'] ?>

                                        <?= (int) $artwork['rating_count'] === 1
                                            ? 'rating'
                                            : 'ratings' ?>

                                    <?php else: ?>

                                        No ratings yet

                                    <?php endif; ?>

                                </p>

                            </div>

                        </a>


                    
                        <?php if ($isOwnProfile): ?>

                            <div class="artwork-owner-actions">

                                <a
                                    href="edit_artwork.php?id=<?= (int) $artwork['artwork_id'] ?>"
                                    class="artwork-action edit-action"
                                >
                                    Edit
                                </a>


                                <form
                                    action="delete_artwork.php"
                                    method="POST"
                                    class="delete-artwork-form"
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
                                        class="artwork-action delete-action"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </section>

</main>


<div
    class="delete-modal-overlay"
    id="deleteModal"
    role="presentation"
    aria-hidden="true"
>

    <div
        class="delete-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteModalTitle"
        aria-describedby="deleteModalDescription"
    >

        <div class="delete-modal-icon">
            !
        </div>

        <h2 id="deleteModalTitle">
            Delete Artwork?
        </h2>

        <p id="deleteModalDescription">
            Are you sure you want to permanently delete this artwork?
            Its ratings and comments will also be deleted.
            This action cannot be undone.
        </p>

        <div class="delete-modal-actions">

            <button
                type="button"
                class="modal-cancel"
                id="cancelDelete"
            >
                Cancel
            </button>

            <button
                type="button"
                class="modal-delete"
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
    const deleteModal = document.getElementById('deleteModal');
    const cancelDelete = document.getElementById('cancelDelete');
    const confirmDelete = document.getElementById('confirmDelete');

    let selectedDeleteForm = null;


    document.querySelectorAll('.delete-artwork-form').forEach(form => {

        form.addEventListener('submit', function(event) {

            event.preventDefault();

            selectedDeleteForm = this;

            deleteModal.classList.add('active');
            deleteModal.setAttribute('aria-hidden', 'false');

            cancelDelete.focus();

        });

    });


    function closeDeleteModal() {

        deleteModal.classList.remove('active');
        deleteModal.setAttribute('aria-hidden', 'true');

        selectedDeleteForm = null;

    }


    cancelDelete.addEventListener('click', function() {

        closeDeleteModal();

    });

    confirmDelete.addEventListener('click', function() {

        if (selectedDeleteForm) {

            confirmDelete.disabled = true;
            confirmDelete.textContent = 'Deleting...';

            selectedDeleteForm.submit();

        }

    });

    deleteModal.addEventListener('click', function(event) {

        if (event.target === deleteModal) {

            closeDeleteModal();

        }

    });

    document.addEventListener('keydown', function(event) {

        if (
            event.key === 'Escape' &&
            deleteModal.classList.contains('active')
        ) {

            closeDeleteModal();

        }

    });

</script>

</body>
</html>
