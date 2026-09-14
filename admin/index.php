<?php
session_start();
require dirname(__DIR__) . '/config.php';
require_admin();

$boards = db()->query('SELECT * FROM boards ORDER BY slug')->fetchAll();
$threads = db()->query(
    'SELECT t.*, b.slug FROM threads t JOIN boards b ON b.id=t.board_id
     WHERE t.deleted=0 ORDER BY t.bumped_at DESC LIMIT 50'
)->fetchAll();

page_header('Admin');
?>
<h2>pomfIB Admin</h2>

<div class="alert alert-info">
    <a href="/admin/boards.php">Manage boards</a> |
    <a href="/admin/posts.php">Manage posts</a> |
    <a href="/admin/logout.php">Logout</a>
</div>

<h3>Boards</h3>
<ul>
<?php foreach ($boards as $board): ?>
    <li>/<?= h($board['slug']) ?>/ - <?= h($board['name']) ?></li>
<?php endforeach; ?>
</ul>

<h3>Recent threads</h3>
<?php foreach ($threads as $thread): ?>
    <div class="alert">
        <a href="/thread.php?b=<?= h($thread['slug']) ?>&id=<?= (int)$thread['id'] ?>">
            /<?= h($thread['slug']) ?>/ #<?= (int)$thread['id'] ?> <?= h($thread['subject']) ?>
        </a>
        <form method="post" action="/admin/thread_action.php">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$thread['id'] ?>">
            <button name="action" value="pin">Pin</button>
            <button name="action" value="lock">Lock</button>
            <button name="action" value="delete">Delete</button>
        </form>
    </div>
<?php endforeach; ?>
<?php page_footer(); ?>
