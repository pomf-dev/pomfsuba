<?php
session_start();
require dirname(__DIR__) . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/');
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($action === 'pin') {
    db()->prepare('UPDATE threads SET pinned = 1 - pinned WHERE id=?')->execute([$id]);
} elseif ($action === 'lock') {
    db()->prepare('UPDATE threads SET locked = 1 - locked WHERE id=?')->execute([$id]);
} elseif ($action === 'delete') {
    db()->prepare('UPDATE threads SET deleted=1 WHERE id=?')->execute([$id]);
}

redirect('/admin/');
