
<?php
session_start();

require_once __DIR__ . '/config/database.php';

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3–50 characters and contain only letters, numbers, or underscores.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $check = $pdo->prepare(
                'SELECT user_id FROM users WHERE username = ? OR email = ?'
            );
            $check->execute([$username, $email]);

            if ($check->fetch()) {
                $errors[] = 'That username or email is already registered.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    'INSERT INTO users (username, email, password)
                     VALUES (?, ?, ?)'
                );
                $insert->execute([
                    $username,
                    $email,
                    $hashed_password
                ]);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['username'] = $username;

                header('Location: home.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Art Space</title>
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

        .form-group {
            margin-bottom: 18px;
        }

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

        .auth-container .btn {
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

        .auth-switch a {
            color: #a78bfa;
        }

        .error-list {
            margin: 15px 0;
            padding: 12px 12px 12px 32px;
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
    <p class="eyebrow">YOUR CREATIVE JOURNEY STARTS HERE</p>
    <h1>Create your account</h1>
    <p class="auth-intro">
        Join a community where artists share, discover, and inspire.
    </p>

    <?php if (!empty($errors)): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <div class="form-group">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                minlength="3"
                maxlength="50"
                pattern="[a-zA-Z0-9_]+"
                value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="username"
                required
            >
        </div>

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
                minlength="8"
                autocomplete="new-password"
                required
            >
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Create Account
        </button>
    </form>

    <p class="auth-switch">
        Already have an account?
        <a href="login.php">Log in</a>
    </p>
</main>

</body>
</html>
