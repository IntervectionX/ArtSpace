
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

$userStmt = $pdo->prepare(
    'SELECT username, profile_picture
     FROM users
     WHERE user_id = ?'
);
$userStmt->execute([$currentUserId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

$currentUsername = $currentUser['username']
    ?? ($_SESSION['username'] ?? 'Artist');
$currentProfilePic = $currentUser['profile_picture'] ?? '';
$currentInitial = strtoupper(substr($currentUsername, 0, 1));

// Create CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$categories = [
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


$users = [];

if ($search !== '') {
    $userSearchStmt = $pdo->prepare(
        'SELECT
            u.user_id,
            u.username,
            u.bio,
            u.profile_picture,
            u.created_at,
            (SELECT COUNT(*)
             FROM artworks a
             WHERE a.user_id = u.user_id) AS artwork_count
         FROM users u
         WHERE u.username LIKE ?
         ORDER BY u.username ASC'
    );

    $searchTerm = '%' . $search . '%';
    $userSearchStmt->execute([$searchTerm]);
    $users = $userSearchStmt->fetchAll(PDO::FETCH_ASSOC);
}

$sql = '
    SELECT
        a.artwork_id,
        a.user_id AS artwork_owner_id,
        a.title,
        a.description,
        a.category,
        a.image_path,
        a.created_at,
        u.user_id AS artist_id,
        u.username,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COUNT(DISTINCT r.rating_id) AS rating_count
    FROM artworks a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN ratings r ON a.artwork_id = r.artwork_id
';

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(
        a.title LIKE ?
        OR a.description LIKE ?
        OR u.username LIKE ?
    )';

    $searchTerm = '%' . $search . '%';

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category !== '' && in_array($category, $categories, true)) {
    $conditions[] = 'a.category = ?';
    $params[] = $category;
} elseif ($category !== '') {
    $category = '';
}

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= '
    GROUP BY a.artwork_id
    ORDER BY a.created_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Explore Art | Art Space</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .feed {
            padding: 45px 8% 80px;
        }

        .welcome {
            margin-bottom: 35px;
        }

        .welcome h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(30px, 4vw, 44px);
            overflow-wrap: anywhere;
        }

        .welcome p {
            color: #aaa2b8;
            margin-top: 8px;
        }

        .feed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 22px;
        }

        .work-card {
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 13px;
            overflow: hidden;
            transition: transform 0.2s, border-color 0.2s;
        }

        .work-card:hover {
            transform: translateY(-4px);
            border-color: #665080;
        }

        .artwork-link {
            display: block;
            overflow: hidden;
            background: #25202f;
        }

        .work-image {
            width: 100%;
            height: 230px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }

        .work-card:hover .work-image {
            transform: scale(1.03);
        }

        .work-info {
            padding: 17px;
        }

        .work-info h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 19px;
            overflow-wrap: anywhere;
        }

        .artwork-title {
            color: #f5f1ff;
            text-decoration: none;
        }

        .artwork-title:hover {
            color: #a78bfa;
        }

        .artwork-link:focus-visible,
        .artwork-title:focus-visible {
            outline: 2px solid #a78bfa;
            outline-offset: 3px;
        }

        .work-info p {
            color: #aaa2b8;
            font-size: 14px;
            margin-top: 6px;
            overflow-wrap: anywhere;
        }

        .artist-link {
            color: #c4b5fd;
            text-decoration: none;
            font-weight: 500;
        }

        .artist-link:hover {
            color: #a78bfa;
            text-decoration: underline;
        }

        .work-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
            color: #c4b5fd;
            font-size: 12px;
        }

        .search-panel {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(150px, 220px) auto auto;
            gap: 12px;
            margin: 28px 0;
            padding: 18px;
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 13px;
        }

        .search-panel input,
        .search-panel select {
            width: 100%;
            min-width: 0;
            padding: 12px;
            border: 1px solid #40344f;
            border-radius: 8px;
            background: #100e15;
            color: #f5f1ff;
            font: inherit;
        }

        .search-panel input:focus,
        .search-panel select:focus {
            outline: 2px solid #8b5cf6;
        }

        .filter-button,
        .clear-button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 11px 15px;
            border-radius: 8px;
            text-decoration: none;
            font: inherit;
            cursor: pointer;
            white-space: nowrap;
        }

        .filter-button {
            border: 0;
            background: #8b5cf6;
            color: white;
        }

        .filter-button:hover {
            background: #7c3aed;
        }

        .clear-button {
            border: 1px solid #51435f;
            color: #ddd6fe;
            background: transparent;
        }

        .clear-button:hover {
            background: #25202f;
        }

        .results-label {
            margin: 0 0 18px;
            color: #aaa2b8;
            font-size: 14px;
        }

        .empty-feed {
            padding: 40px 20px;
            border: 1px dashed #51435f;
            border-radius: 13px;
            text-align: center;
            color: #aaa2b8;
        }

        .user-results-section {
            margin: 35px 0 45px;
        }

        .user-results-section h2 {
            margin-bottom: 18px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
        }

        .user-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 16px;
        }

        .user-result-card {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            padding: 17px;
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 12px;
            text-decoration: none;
            transition: transform .2s, border-color .2s;
        }

        .user-result-card:hover {
            transform: translateY(-3px);
            border-color: #8b5cf6;
        }

        .user-result-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            overflow: hidden;
            border: 2px solid #8b5cf6;
            border-radius: 50%;
            background: #25202f;
            color: #c4b5fd;
            font-size: 20px;
            font-weight: 700;
        }

        .user-result-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-result-info {
            min-width: 0;
        }

        .user-result-info h3 {
            margin: 0;
            color: #f5f1ff;
            font-size: 16px;
            overflow-wrap: anywhere;
        }

        .user-result-info p {
            margin-top: 5px;
            color: #aaa2b8;
            font-size: 13px;
        }

        .feed-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 15px;
            min-width: 0;
        }

        .username {
            color: #f5f1ff;
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
            text-decoration: none;
        }

        .username:hover {
            color: #c4b5fd;
        }

        .nav-profile-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            min-width: 40px;
            overflow: hidden;
            border: 2px solid #8b5cf6;
            border-radius: 50%;
            background: #25202f;
            color: #c4b5fd;
            font-size: 17px;
            font-weight: 700;
            line-height: 1;
            text-decoration: none;
            transition: border-color 0.2s, transform 0.2s;
        }

        .nav-profile-icon img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .nav-profile-icon span {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-profile-icon:hover {
            border-color: #c4b5fd;
            transform: scale(1.05);
        }

        .nav-profile-icon:focus-visible {
            outline: 2px solid #c4b5fd;
            outline-offset: 4px;
        }

        @media (max-width: 850px) {
            .search-panel {
                grid-template-columns: 1fr 1fr;
            }

            .search-panel input {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 600px) {
            .feed {
                padding: 35px 5%;
            }

            .feed-actions {
                gap: 10px;
            }

            .nav-profile-icon {
                width: 36px;
                height: 36px;
                min-width: 36px;
            }

            .search-panel {
                grid-template-columns: 1fr;
            }

            .search-panel input {
                grid-column: auto;
            }
        }

        @media (max-width: 400px) {
            .feed-actions {
                gap: 7px;
            }

            .username {
                max-width: 85px;
                font-size: 12px;
            }

            .nav-profile-icon {
                width: 34px;
                height: 34px;
                min-width: 34px;
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

    <div class="feed-actions">

        <a
            href="profile.php?id=<?= $currentUserId ?>"
            class="username"
            title="View your profile"
        ><?= e($currentUsername) ?></a>

        <a
            href="profile.php?id=<?= $currentUserId ?>"
            class="nav-profile-icon"
            title="View your profile"
            aria-label="View your profile"
        >
            <?php if (!empty($currentProfilePic)): ?>
                <img
                    src="<?= e($currentProfilePic) ?>"
                    alt="Your profile picture"
                >
            <?php else: ?>
                <span><?= e($currentInitial) ?></span>
            <?php endif; ?>
        </a>

        <a href="logout.php" class="login-link">Log out</a>

    </div>

</header>


<main class="feed">

    <section class="welcome">

        <p class="eyebrow">YOUR CREATIVE COMMUNITY</p>

        <h1>Welcome back, <?= e($currentUsername) ?>.</h1>

        <p>
            Discover new creations and get inspired by fellow artists.
        </p>

        <div style="margin-top: 20px;">
            <a href="upload.php" class="btn btn-primary">
                + Share your artwork
            </a>
        </div>

    </section>


    <section>

        <div class="section-heading">

            <div>
                <p class="eyebrow">THE COMMUNITY GALLERY</p>
                <h2>Explore artworks</h2>
            </div>

            <p class="section-description">
                Find creations that inspire you.
            </p>

        </div>


        <form class="search-panel" method="GET" action="home.php">

            <input
                type="search"
                name="search"
                placeholder="Search titles, descriptions, or artists..."
                value="<?= e($search) ?>"
                aria-label="Search artworks and users"
            >

            <select name="category" aria-label="Filter by category">

                <option value="">All categories</option>

                <?php foreach ($categories as $categoryOption): ?>

                    <option
                        value="<?= e($categoryOption) ?>"
                        <?= $category === $categoryOption ? 'selected' : '' ?>
                    >
                        <?= e($categoryOption) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="filter-button">
                Search
            </button>

            <a href="home.php" class="clear-button">
                Clear
            </a>

        </form>


        <?php if ($search !== ''): ?>

            <section class="user-results-section">

                <h2>
                    People
                    (<?= count($users) ?>)
                </h2>

                <?php if (!empty($users)): ?>

                    <div class="user-results-grid">

                        <?php foreach ($users as $user): ?>

                            <a
                                href="profile.php?id=<?= (int) $user['user_id'] ?>"
                                class="user-result-card"
                            >

                                <div class="user-result-avatar">

                                    <?php if (!empty($user['profile_picture'])): ?>

                                        <img
                                            src="<?= e($user['profile_picture']) ?>"
                                            alt="<?= e($user['username']) ?>'s profile picture"
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        <?= e(strtoupper(substr($user['username'], 0, 1))) ?>

                                    <?php endif; ?>

                                </div>

                                <div class="user-result-info">

                                    <h3><?= e($user['username']) ?></h3>

                                    <p>
                                        <?= (int) $user['artwork_count'] ?>
                                        <?= (int) $user['artwork_count'] === 1
                                            ? 'artwork'
                                            : 'artworks' ?>
                                    </p>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p class="results-label">
                        No users found matching "<?= e($search) ?>".
                    </p>

                <?php endif; ?>

            </section>

        <?php endif; ?>


        <p class="results-label">

            <?= count($artworks) ?>
            <?= count($artworks) === 1 ? 'artwork' : 'artworks' ?> found

        </p>


        <?php if (empty($artworks)): ?>

            <div class="empty-feed">

                <h2>No artworks found</h2>

                <p>
                    Try another search term or choose a different category.
                </p>

                <div style="margin-top: 20px;">

                    <a href="home.php" class="btn btn-primary">
                        View all artworks
                    </a>

                </div>

            </div>

        <?php else: ?>

            <div class="feed-grid">

                <?php foreach ($artworks as $artwork): ?>

                    <article class="work-card">

                        <a
                            href="artwork.php?id=<?= (int) $artwork['artwork_id'] ?>"
                            class="artwork-link"
                            aria-label="View <?= e($artwork['title']) ?>"
                        >

                            <img
                                class="work-image"
                                src="<?= e($artwork['image_path']) ?>"
                                alt="<?= e($artwork['title']) ?>"
                                loading="lazy"
                            >

                        </a>


                        <div class="work-info">

                            <h2>

                                <a
                                    href="artwork.php?id=<?= (int) $artwork['artwork_id'] ?>"
                                    class="artwork-title"
                                >
                                    <?= e($artwork['title']) ?>
                                </a>

                            </h2>


                            <p>
                                By

                                <a
                                    href="profile.php?id=<?= (int) $artwork['artist_id'] ?>"
                                    class="artist-link"
                                >
                                    <?= e($artwork['username']) ?>
                                </a>
                            </p>


                            <p><?= e($artwork['category']) ?></p>


                            <div class="work-meta">

                                <span>
                                    ★ <?= number_format(
                                        (float) $artwork['average_rating'],
                                        1
                                    ) ?>

                                    (<?= (int) $artwork['rating_count'] ?>)
                                </span>

                                <span>
                                    <?= e(date(
                                        'M j, Y',
                                        strtotime($artwork['created_at'])
                                    )) ?>
                                </span>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

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
