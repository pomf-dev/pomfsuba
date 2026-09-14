<?php
session_start();
require dirname(__DIR__) . '/config.php';

$message = '';

$count = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();

if ($count > 0) {
    exit('An admin already exists. Delete this file after setup.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
        $message = 'Username must be 3-64 characters.';
    } elseif (strlen($password) < 12) {
        $message = 'Password must be at least 12 characters.';
    } else {
        $stmt = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        exit('Admin created. Delete /admin/setup.php now, then <a href="/admin/login.php">login</a>.');
    }
}

page_header('Create Admin');
?>
<div class="alert alert-info">
    Create the first pomfIB administrator. Delete <code>admin/setup.php</code> immediately after this succeeds.
</div>

<?php if ($message): ?><div class="alert alert-error"><?= h($message) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <p><input name="username" required placeholder="Admin username"></p>
    <p><input type="password" name="password" required placeholder="Password"></p>
    <button type="submit">Create admin</button>
</form>
<?php page_footer(); ?>
