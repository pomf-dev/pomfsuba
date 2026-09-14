<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

/**
 * Get information about an uploaded image.
 */
function catalog_file_info(?string $fileName): array
{
    $info = [
        'name'   => $fileName !== null ? basename($fileName) : '',
        'width'  => null,
        'height' => null,
        'size'   => null,
    ];

    if ($fileName === null || $fileName === '') {
        return $info;
    }

    $relativePath =
        '/' .
        trim(UPLOAD_URL, '/') .
        '/' .
        ltrim($fileName, '/');

    $filePath =
        rtrim(
            (string) (
                $_SERVER['DOCUMENT_ROOT'] ?? __DIR__
            ),
            '/'
        ) .
        $relativePath;

    if (!is_file($filePath)) {
        return $info;
    }

    $size = filesize($filePath);

    if ($size !== false) {
        $info['size'] = (int) $size;
    }

    $imageSize = @getimagesize($filePath);

    if ($imageSize !== false) {
        $info['width'] = (int) $imageSize[0];
        $info['height'] = (int) $imageSize[1];
    }

    return $info;
}

/**
 * Format a file size like TinyIB/vichan.
 */
function catalog_format_file_size(?int $bytes): string
{
    if ($bytes === null || $bytes < 0) {
        return 'Unknown size';
    }

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    if ($bytes < 1024 * 1024 * 1024) {
        return number_format(
            $bytes / (1024 * 1024),
            1
        ) . ' MB';
    }

    return number_format(
        $bytes / (1024 * 1024 * 1024),
        1
    ) . ' GB';
}

/**
 * Make catalog excerpts short while preserving rendered links.
 */
function catalog_excerpt(string $body, int $length = 180): string
{
    $text = trim(
        preg_replace(
            '/\s+/u',
            ' ',
            strip_tags($body)
        ) ?? ''
    );

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $length) {
        return mb_substr($text, 0, $length, 'UTF-8') . '...';
    }

    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }

    return $text;
}

/**
 * Fetch every board.
 */
$boards = db()
    ->query(
        '
        SELECT
            id,
            slug,
            name,
            description
        FROM boards
        ORDER BY id ASC
        '
    )
    ->fetchAll(PDO::FETCH_ASSOC);

page_header('Catalog');
?>

<style>
    .catalog-page {
        max-width: 1200px;
        margin: 0 auto;
        padding: 12px;
    }

    .catalog-intro {
        margin: 0 0 18px;
        padding: 10px 12px;
        border: 1px solid #ccc;
        background: #f5f5f5;
    }

    .catalog-board {
        margin: 0 0 28px;
    }

    .catalog-board-header {
        margin: 0 0 10px;
        padding: 8px 10px;
        border: 1px solid #bbb;
        background: #eee;
    }

    .catalog-board-header h2 {
        margin: 0;
        font-size: 18px;
    }

    .catalog-board-header h2 a {
        text-decoration: none;
    }

    .catalog-board-description {
        margin-top: 4px;
        color: #555;
        font-size: 12px;
    }

    .catalog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 10px;
    }

    .catalog-thread {
        min-width: 0;
        padding: 8px;
        border: 1px solid #ccc;
        background: #fafafa;
        overflow: hidden;
    }

    .catalog-thread:hover {
        background: #f1f1f1;
    }

    .catalog-thumb {
        float: left;
        width: 120px;
        height: 120px;
        margin: 0 10px 6px 0;
        border: 1px solid #bbb;
        background: #ddd;
        overflow: hidden;
        text-align: center;
    }

    .catalog-thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .catalog-no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        color: #777;
        font-size: 11px;
    }

    .catalog-thread-title {
        margin: 0 0 5px;
        font-weight: bold;
        font-size: 14px;
        overflow-wrap: anywhere;
    }

    .catalog-thread-title a {
        text-decoration: none;
    }

    .catalog-thread-meta {
        margin: 0 0 5px;
        color: #666;
        font-size: 11px;
    }

    .catalog-file {
        margin: 3px 0 6px;
        font-size: 11px;
        overflow-wrap: anywhere;
    }

    .catalog-file a {
        text-decoration: none;
    }

    .catalog-excerpt {
        margin: 5px 0 0;
        font-size: 12px;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .catalog-clear {
        clear: both;
    }

    .catalog-empty {
        padding: 12px;
        border: 1px solid #ccc;
        background: #fafafa;
        color: #666;
    }

    .catalog-status {
        margin-top: 7px;
        color: #777;
        font-size: 11px;
    }

    @media (max-width: 600px) {
        .catalog-grid {
            grid-template-columns: 1fr;
        }

        .catalog-thumb {
            width: 100px;
            height: 100px;
        }
    }
</style>

<main class="catalog-page">

    <div class="catalog-intro">
        <strong>Catalog</strong>
        <div>
            Browse all boards and their latest threads.
        </div>
    </div>

    <?php if (!$boards): ?>

        <div class="catalog-empty">
            No boards have been created yet.
        </div>

    <?php else: ?>

        <?php foreach ($boards as $board): ?>

            <?php
            $boardId = (int) $board['id'];
            $boardSlug = (string) $board['slug'];

            /*
             * Get the newest threads for this board.
             *
             * The OP is the earliest non-deleted post belonging
             * to each thread.
             */
            $threadStmt = db()->prepare(
                '
                SELECT
                    t.id,
                    t.board_id,
                    t.subject,
                    t.created_at,
                    t.bumped_at,
                    t.pinned,
                    t.locked,

                    COUNT(
                        CASE
                            WHEN p.deleted = 0
                            THEN p.id
                        END
                    ) AS post_count,

                    op.id AS op_id,
                    op.body AS op_body,
                    op.file_name AS op_file_name,
                    op.file_original AS op_file_original,
                    op.file_mime AS op_file_mime,
                    op.file_size AS op_file_size,
                    op.created_at AS op_created_at

                FROM threads t

                LEFT JOIN posts p
                    ON p.thread_id = t.id
                    AND p.board_id = t.board_id

                LEFT JOIN posts op
                    ON op.id = (
                        SELECT MIN(op2.id)
                        FROM posts op2
                        WHERE
                            op2.thread_id = t.id
                            AND op2.board_id = t.board_id
                            AND op2.deleted = 0
                    )

                WHERE
                    t.board_id = :board_id
                    AND t.deleted = 0

                GROUP BY
                    t.id,
                    t.board_id,
                    t.subject,
                    t.created_at,
                    t.bumped_at,
                    t.pinned,
                    t.locked,
                    op.id,
                    op.body,
                    op.file_name,
                    op.file_original,
                    op.file_mime,
                    op.file_size,
                    op.created_at

                ORDER BY
                    t.pinned DESC,
                    t.bumped_at DESC,
                    t.id DESC

                LIMIT 30
                '
            );

            $threadStmt->execute([
                ':board_id' => $boardId,
            ]);

            $threads = $threadStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <section class="catalog-board">

                <header class="catalog-board-header">

                    <h2>
                        <a href="/<?= rawurlencode($boardSlug) ?>/">
                            /<?= h($boardSlug) ?>/ -
                            <?= h((string) $board['name']) ?>
                        </a>
                    </h2>

                    <?php if ((string) $board['description'] !== ''): ?>

                        <div class="catalog-board-description">
                            <?= h((string) $board['description']) ?>
                        </div>

                    <?php endif; ?>

                </header>

                <?php if (!$threads): ?>

                    <div class="catalog-empty">
                        No threads on this board.
                    </div>

                <?php else: ?>

                    <div class="catalog-grid">

                        <?php foreach ($threads as $thread): ?>

                            <?php
                            $threadId = (int) $thread['id'];

                            $threadUrl =
                                '/' .
                                rawurlencode($boardSlug) .
                                '/thread/' .
                                $threadId;

                            $opFileName =
                                $thread['op_file_name'] !== null
                                    ? (string) $thread['op_file_name']
                                    : '';

                            $fileInfo = catalog_file_info(
                                $opFileName !== ''
                                    ? $opFileName
                                    : null
                            );

                            $originalName =
                                trim(
                                    (string) (
                                        $thread['op_file_original']
                                        ?? ''
                                    )
                                );

                            if ($originalName === '') {
                                $originalName =
                                    $fileInfo['name'];
                            }

                            $subject =
                                trim(
                                    (string) (
                                        $thread['subject'] ?? ''
                                    )
                                );

                            if ($subject === '') {
                                $subject = 'No subject';
                            }

                            $excerpt = catalog_excerpt(
                                (string) $thread['op_body'],
                                180
                            );

                            $postCount =
                                (int) $thread['post_count'];

                            /*
                             * post_count includes the OP.
                             * Show replies separately, like an imageboard
                             * catalog normally does.
                             */
                            $replyCount = max(
                                0,
                                $postCount - 1
                            );
                            ?>

                            <article class="catalog-thread">

                                <?php if ($opFileName !== ''): ?>

                                    <div class="catalog-thumb">

                                        <a
                                            href="<?= h($threadUrl) ?>"
                                            title="<?= h($subject) ?>"
                                        >
                                            <img
                                                src="/<?= h(
                                                    trim(
                                                        UPLOAD_URL,
                                                        '/'
                                                    ) .
                                                    '/' .
                                                    $opFileName
                                                ) ?>"
                                                alt="<?= h(
                                                    $originalName
                                                ) ?>"
                                                loading="lazy"
                                            >
                                        </a>

                                    </div>

                                <?php else: ?>

                                    <div class="catalog-thumb">

                                        <a
                                            href="<?= h($threadUrl) ?>"
                                            title="<?= h($subject) ?>"
                                        >
                                            <span class="catalog-no-image">
                                                No image
                                            </span>
                                        </a>

                                    </div>

                                <?php endif; ?>

                                <div class="catalog-thread-title">

                                    <a href="<?= h($threadUrl) ?>">
                                        <?php if ((int) $thread['pinned'] === 1): ?>
                                            📌
                                        <?php endif; ?>

                                        <?php if ((int) $thread['locked'] === 1): ?>
                                            🔒
                                        <?php endif; ?>

                                        <?= h($subject) ?>
                                    </a>

                                </div>

                                <div class="catalog-thread-meta">

                                    <?= $replyCount ?>
                                    <?= $replyCount === 1 ? 'reply' : 'replies' ?>

                                    ·

                                    <?= h(
                                        (string) $thread['bumped_at']
                                    ) ?>

                                </div>

                                <?php if ($opFileName !== ''): ?>

                                    <div class="catalog-file">

                                        <a
                                            href="/<?= h(
                                                trim(
                                                    UPLOAD_URL,
                                                    '/'
                                                ) .
                                                '/' .
                                                $opFileName
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <?= h($originalName) ?>
                                        </a>

                                        <?php if (
                                            $fileInfo['width'] !== null &&
                                            $fileInfo['height'] !== null
                                        ): ?>

                                            <span>
                                                (
                                                <?= (int) $fileInfo['width'] ?>
                                                x
                                                <?= (int) $fileInfo['height'] ?>
                                                ,
                                                <?= h(
                                                    catalog_format_file_size(
                                                        $fileInfo['size']
                                                    )
                                                ) ?>
                                                )
                                            </span>

                                        <?php elseif ($fileInfo['size'] !== null): ?>

                                            <span>
                                                (
                                                <?= h(
                                                    catalog_format_file_size(
                                                        $fileInfo['size']
                                                    )
                                                ) ?>
                                                )
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>

                                <?php if ($excerpt !== ''): ?>

                                    <div class="catalog-excerpt">
                                        <?= h($excerpt) ?>
                                    </div>

                                <?php endif; ?>

                                <div class="catalog-clear"></div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                    <div class="catalog-status">
                        Showing up to <?= count($threads) ?> recent
                        <?= count($threads) === 1 ? 'thread' : 'threads' ?>.
                    </div>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<?php page_footer(); ?>
