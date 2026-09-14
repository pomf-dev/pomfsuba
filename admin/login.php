<?php
session_start();
require dirname(__DIR__) . '/config.php';

if (is_admin()) redirect('/admin/');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        redirect('/admin/');
    }

    $error = 'Invalid username or password.';
}

page_header('Admin Login');
?>
<div class="jumbotron">
    <h1>Admin</h1>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <p><input type="text" name="username" required placeholder="Username"></p>
    <p><input type="password" name="password" required placeholder="Password"></p>
    <button type="submit">Login</button>
</form>
<?php page_footer(); ?>
