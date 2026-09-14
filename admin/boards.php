<?php
session_start();
require dirname(__DIR__) . '/config.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if (!preg_match('/^[a-z0-9]{1,32}$/', $slug)) {
            $message = 'Board slug must contain only lowercase letters and numbers.';
        } elseif ($name === '') {
            $message = 'Board name is required.';
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO boards (slug,name,description) VALUES (?,?,?)');
                $stmt->execute([$slug, $name, $description]);
                $message = 'Board created.';
            } catch (Throwable $e) {
                $message = 'Could not create board: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->prepare('DELETE FROM boards WHERE id=?')->execute([$id]);
        $message = 'Board deleted.';
    }
}

$boards = db()->query('SELECT * FROM boards ORDER BY slug')->fetchAll();

page_header('Manage Boards');
?>
<h2>Manage Boards</h2>

<?php if ($message): ?><div class="alert alert-info"><?= h($message) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <p>
        <input name="slug" maxlength="32" placeholder="b" required>
        <input name="name" maxlength="120" placeholder="Board name" required>
    </p>
    <p><input name="description" maxlength="255" placeholder="Description"></p>
    <button type="submit">Create board</button>
</form>

<ul>
<?php foreach ($boards as $board): ?>
    <li>
        /<?= h($board['slug']) ?>/ - <?= h($board['name']) ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$board['id'] ?>">
            <button type="submit">Delete</button>
        </form>
    </li>
<?php endforeach; ?>
</ul>
<?php page_footer(); ?>
