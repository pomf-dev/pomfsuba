<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$boards = db()->query(
    'SELECT *
     FROM boards
     ORDER BY slug'
)->fetchAll();

/*
 * ============================================================
 * LATEST POSTS
 * ============================================================
 */

$recent_posts_stmt = db()->query(
    'SELECT
        p.*,
        t.id AS thread_id,
        t.subject AS thread_subject,
        t.board_id,
        b.slug AS board_slug,
        b.name AS board_name
     FROM posts p
     INNER JOIN threads t
        ON t.id = p.thread_id
     INNER JOIN boards b
        ON b.id = t.board_id
     WHERE p.deleted = 0
       AND t.deleted = 0
     ORDER BY p.id DESC
     LIMIT 20'
);

$recent_posts = $recent_posts_stmt->fetchAll();

/*
 * ============================================================
 * GET POST IMAGE
 * ============================================================
 */

function get_post_image(array $post): ?string
{
    $possible_columns = [
        'filename',
        'file_name',
        'file',
        'image',
        'image_name',
        'image_filename',
        'file_path',
        'image_path',
        'file_url',
        'image_url'
    ];

    foreach ($possible_columns as $column) {

        if (!array_key_exists($column, $post)) {
            continue;
        }

        $value =
            trim(
                (string) $post[$column]
            );

        if ($value === '') {
            continue;
        }

        if (
            $value === '0' ||
            strtoupper($value) === 'NULL'
        ) {
            continue;
        }

        /*
         * Already a complete URL or absolute path.
         */
        if (
            str_starts_with(
                $value,
                'http://'
            ) ||
            str_starts_with(
                $value,
                'https://'
            ) ||
            str_starts_with(
                $value,
                '/'
            )
        ) {
            return $value;
        }

        /*
         * Uploaded files are stored in /uploads/.
         */
        return '/uploads/' .
            rawurlencode(
                basename($value)
            );
    }

    return null;
}

/*
 * ============================================================
 * POST DATE
 * ============================================================
 */

function post_date(array $post): string
{
    if (!empty($post['created_at'])) {
        return (string) $post['created_at'];
    }

    if (!empty($post['date'])) {
        return (string) $post['date'];
    }

    return '';
}

page_header('Home');
?>

<div class="jumbotron">


<h1>
    pomfIB
</h1>

<p class="lead">
    A simple PHP imageboard for anonymous discussion.
</p>


</div>

<div class="alert alert-info">


<strong>
    Boards
</strong>

<ul>

    <?php foreach ($boards as $board): ?>

        <?php
        $board_slug =
            trim(
                (string) $board['slug']
            );
        ?>

        <li>

            <a
                href="/<?= rawurlencode($board_slug) ?>/"
            >
                /<?= h($board_slug) ?>/
            </a>

            -
            <?= h($board['name']) ?>

            <?php if (!empty($board['description'])): ?>

                —
                <?= h($board['description']) ?>

            <?php endif; ?>

        </li>

    <?php endforeach; ?>

</ul>


</div>

<div class="alert">


<strong>
    Formatting
</strong>

<br>

<code>&gt;greentext</code>
·
<code>[pink]pink text[/pink]</code>
·
<code>[spoiler]hidden text[/spoiler]</code>
·
<code>==red text==</code>


</div>

<?php
/*
 * ============================================================
 * LATEST IMAGES
 * ============================================================
 */

$latest_images = [];

foreach ($recent_posts as $post) {

    $image =
        get_post_image($post);

    if ($image !== null) {

        $latest_images[] = [
            'post' => $post,
            'image' => $image
        ];
    }

    if (count($latest_images) >= 12) {
        break;
    }
}
?>

<div class="frontpage-section">


<h2>
    Latest Images
</h2>

<div id="divLatestImages">

    <?php if (!empty($latest_images)): ?>

        <div class="latest-images">

            <?php foreach ($latest_images as $item): ?>

                <?php

                $post =
                    $item['post'];

                $image =
                    $item['image'];

                $board_slug =
                    (string) $post['board_slug'];

                $thread_id =
                    (int) $post['thread_id'];

                $post_id =
                    (int) $post['id'];

                ?>

                <div class="latest-image">

                    <a
                        href="/<?= rawurlencode($board_slug) ?>/thread/<?= $thread_id ?>#p<?= $post_id ?>"
                        title="/<?= h($board_slug) ?>/ No.<?= $post_id ?>"
                    >

                        <img
                            src="<?= h($image) ?>"
                            alt=""
                            loading="lazy"
                        >

                    </a>

                    <div class="latest-image-info">

                        <a
                            href="/<?= rawurlencode($board_slug) ?>/thread/<?= $thread_id ?>#p<?= $post_id ?>"
                        >
                            /<?= h($board_slug) ?>/
                            No.<?= $post_id ?>
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="frontpage-empty">
            No images have been posted yet.
        </div>

    <?php endif; ?>

</div>


</div>

<?php
/*
 * ============================================================
 * RECENT POSTS
 * ============================================================
 */

if (!empty($recent_posts)):
?>

<div class="frontpage-section">


<h2>
    Recent Posts
</h2>

<div id="divLatestPosts">

    <?php foreach ($recent_posts as $post): ?>

        <?php

        $board_slug =
            (string) $post['board_slug'];

        $thread_id =
            (int) $post['thread_id'];

        $post_id =
            (int) $post['id'];

        $name =
            trim(
                (string) (
                    $post['name'] ?? ''
                )
            );

        if ($name === '') {
            $name = 'Anonymous';
        }

        /*
         * Thread subject comes from threads.subject.
         */
        $subject =
            trim(
                (string) (
                    $post['thread_subject'] ?? ''
                )
            );

        $body =
            (string) (
                $post['body'] ?? ''
            );

        ?>

        <div class="latest-post">

            <div class="latest-post-header">

                <?php if ($subject !== ''): ?>

                    <span class="latest-post-subject">
                        <?= h($subject) ?>
                    </span>

                <?php endif; ?>

                <span class="latest-post-name">
                    <?= h($name) ?>
                </span>

                <span class="latest-post-board">

                    <a
                        href="/<?= rawurlencode($board_slug) ?>/"
                    >
                        /<?= h($board_slug) ?>/
                    </a>

                </span>

                <a
                    href="/<?= rawurlencode($board_slug) ?>/thread/<?= $thread_id ?>#p<?= $post_id ?>"
                >
                    No.<?= $post_id ?>
                </a>

                <?php if (post_date($post) !== ''): ?>

                    <span class="latest-post-date">

                        <?= h(
                            post_date($post)
                        ) ?>

                    </span>

                <?php endif; ?>

            </div>

            <div class="latest-post-body">

                <?= render_text($body) ?>

            </div>

        </div>

    <?php endforeach; ?>

</div>


</div>

<?php endif; ?>

<style>

/*
 * ============================================================
 * FRONT PAGE
 * ============================================================
 */

.frontpage-section {
    margin-top: 15px;
    padding: 14px;
    border: 1px solid #fbeed5;
    border-radius: 4px;
}

.frontpage-section h2 {
    margin-top: 0;
    margin-bottom: 10px;
}


/*
 * ============================================================
 * LATEST IMAGES
 * ============================================================
 */

.latest-images {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 12px;
}

.latest-image {
    width: 120px;
    text-align: center;
}

.latest-image a {
    display: block;
}

.latest-image img {
    display: block;
    width: 120px;
    height: 120px;
    object-fit: cover;
    margin: 0 auto;
}

.latest-image-info {
    font-size: 11px;
    line-height: 15px;
    margin-top: 3px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}


/*
 * ============================================================
 * RECENT POSTS
 * ============================================================
 */

.latest-post {
    clear: both;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px dotted #bbb;
}

.latest-post:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.latest-post-header {
    margin-bottom: 4px;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.latest-post-subject {
    font-weight: bold;
    margin-right: 7px;
}

.latest-post-name {
    margin-right: 7px;
}

.latest-post-board {
    margin-right: 5px;
}

.latest-post-date {
    margin-left: 7px;
    font-size: 11px;
}

.latest-post-body {
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/*
 * ============================================================
 * MOBILE
 * ============================================================
 */

@media only screen and (max-width: 600px) {

    .latest-images {
        gap: 8px;
    }

    .latest-image {
        width: 90px;
    }

    .latest-image img {
        width: 90px;
        height: 90px;
    }

    .latest-image-info {
        font-size: 10px;
    }

    .frontpage-section {
        padding: 10px;
    }

    .latest-post-date {
        display: block;
        margin-left: 0;
    }

}

</style>

<?php page_footer(); ?>