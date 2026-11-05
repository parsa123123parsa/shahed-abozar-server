<?php
// Start a session with safer cookie settings before any HTML is sent.
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../includes/database.php';

if (isset($_SESSION['student_id'])) {
    header('Location: login.php?login=success');
    exit;
}

if (empty($_SESSION['student_login_csrf'])) {
    $_SESSION['student_login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['student_login_csrf'], $csrfToken)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        try {
            // Expected students columns: id, name, class, username, password_hash.
            $statement = getDatabaseConnection()->prepare(
                'SELECT id, name, class, username, password_hash
                 FROM students
                 WHERE username = :username
                 LIMIT 1'
            );
            $statement->execute(['username' => $username]);
            $student = $statement->fetch();

            if ($student && password_verify($password, $student['password_hash'])) {
                // Rotate the session ID to prevent session fixation.
                session_regenerate_id(true);
                $_SESSION['student_id'] = (int) $student['id'];
                $_SESSION['student_name'] = $student['name'];
                $_SESSION['student_class'] = $student['class'];
                $_SESSION['student_username'] = $student['username'];
                unset($_SESSION['student_login_csrf']);

                header('Location: login.php?login=success');
                exit;
            }

            $error = 'Invalid username or password.';
        } catch (Throwable $exception) {
            // Keep database details out of the page shown to students.
            error_log('Student login error: ' . $exception->getMessage());
            $error = 'Login is temporarily unavailable. Please try again later.';
        }
    }
}

$loginSucceeded = isset($_GET['login']) && $_GET['login'] === 'success'
    && isset($_SESSION['student_id']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student login</title>
</head>
<body>
    <main>
        <h1>Student login</h1>

        <?php if ($loginSucceeded): ?>
            <p role="status">Login successful. You are signed in as <?= htmlspecialchars($_SESSION['student_name'], ENT_QUOTES, 'UTF-8') ?>.</p>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['student_login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <p>
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required>
                </p>
                <p>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </p>
                <button type="submit">Log in</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
