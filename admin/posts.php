<?php
session_start();
require dirname(__DIR__) . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('UPDATE posts SET deleted=1 WHERE id=?')->execute([$id]);
}

$stmt = db()->query(
    'SELECT p.*, b.slug FROM posts p JOIN boards b ON b.id=p.board_id
     ORDER BY p.id DESC LIMIT 100'
);
$posts = $stmt->fetchAll();

page_header('Manage Posts');
?>
<h2>Manage Posts</h2>
<?php foreach ($posts as $post): ?>
    <div class="alert">
        <strong>#<?= (int)$post['id'] ?></strong>
        /<?= h($post['slug']) ?>/ —
        <?= h($post['name']) ?> —
        <?= h($post['created_at']) ?><br>
        <?= render_text($post['body']) ?>
        <?php if (!$post['deleted']): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                <button type="submit">Delete post</button>
            </form>
        <?php else: ?>
            <strong>deleted</strong>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php page_footer(); ?>
