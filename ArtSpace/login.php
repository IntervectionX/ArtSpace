
<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, username, email, password
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['username'] = $user['username'];

            header('Location: home.php');
            exit;
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In | Art Space</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .auth-container {
            max-width: 460px;
            margin: 65px auto;
            padding: 35px;
            background: #17131f;
            border: 1px solid #30283b;
            border-radius: 16px;
        }

        .auth-container h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 30px;
            margin: 8px 0;
        }

        .auth-intro {
            color: #aaa2b8;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #40354c;
            background: #0d0b12;
            color: #f5f1ff;
            font: inherit;
        }

        .form-group input:focus {
            outline: 2px solid #a78bfa;
            border-color: transparent;
        }

        .auth-container button {
            width: 100%;
            border: none;
            cursor: pointer;
            font: inherit;
            font-weight: 600;
        }

        .auth-switch {
            margin-top: 22px;
            text-align: center;
            color: #aaa2b8;
            font-size: 14px;
        }

        .auth-switch a { color: #a78bfa; }

        .error-message {
            margin: 15px 0;
            padding: 12px;
            border-radius: 8px;
            background: #40202b;
            color: #ffc5d0;
            font-size: 13px;
        }

        @media (max-width: 520px) {
            .auth-container {
                margin: 35px 16px;
                padding: 25px;
            }
        }
    </style>
</head>
<body>

<header class="navbar">
    <a href="index.php" class="logo">Art<span>Space.</span></a>
    <a href="index.php" class="login-link">← Back to home</a>
</header>

<main class="auth-container">
    <p class="eyebrow">WELCOME BACK, ARTIST</p>
    <h1>Log in to Art Space</h1>
    <p class="auth-intro">
        Sign in to discover art and share your creativity.
    </p>

    <?php if ($error !== ''): ?>
        <div class="error-message" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label for="email">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="255"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="email"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Log In
        </button>
    </form>

    <p class="auth-switch">
        Don't have an account?
        <a href="register.php">Create an account</a>
    </p>
</main>

</body>
</html>
