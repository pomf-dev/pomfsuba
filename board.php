<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Cache-Control: post-check=0, pre-check=0',
    false
);

header('Pragma: no-cache');
header('Expires: 0');


/*
 * FILE INFORMATION
 */

function format_file_size(?int $bytes): string
{
    if (
        $bytes === null ||
        $bytes < 0
    ) {
        return 'Unknown size';
    }

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format(
            $bytes / 1024,
            1
        ) . ' KB';
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


/*
 * POST NAME / CAPCODE / COUNTRY FLAG
 */

function render_post_name(
    ?string $name,
    ?string $countryCode = null,
    bool $showFlag = false
): string {

    $name =
        trim(
            (string) $name
        );

    if ($name === '') {
        $name = 'Anonymous';
    }

    $flagHtml = '';

    if ($showFlag) {

        $flagUrl =
            get_country_flag_url(
                $countryCode
            );

        if ($flagUrl !== null) {

            $country =
                strtoupper(
                    (string) $countryCode
                );

            $flagHtml =
                '<img' .
                ' class="country-flag"' .
                ' src="' .
                h($flagUrl) .
                '"' .
                ' alt="' .
                h($country) .
                '"' .
                ' title="' .
                h($country) .
                '"' .
                ' loading="lazy"' .
                '>';
        }
    }

    if ($name === 'pomfIB ## Staff') {

        return
            '<span class="name">' .
            'pomfIB ' .
            $flagHtml .
            '<span class="capcode">## Staff</span>' .
            '</span>';
    }

    return
        '<span class="name">' .
        h($name) .
        $flagHtml .
        '</span>';
}


$slug =
    trim(
        (string) (
            $_GET['b'] ?? ''
        )
    );

$board =
    get_board($slug);

if (!$board) {
    http_response_code(404);
    exit('Board not found.');
}


/*
 * COUNTRY FLAGS
 */

$showCountryFlags =
    strtolower(
        (string) $board['slug']
    ) === 'int';


$stmt = db()->prepare(
    'SELECT t.*,
            p.id AS last_post_id,
            p.created_at AS last_post_at
     FROM threads t
     LEFT JOIN posts p ON p.id = (
         SELECT MAX(p2.id)
         FROM posts p2
         WHERE p2.thread_id = t.id
           AND p2.deleted = 0
     )
     WHERE t.board_id = ?
       AND t.deleted = 0
     ORDER BY t.pinned DESC, t.bumped_at DESC
     LIMIT ' . THREADS_PER_PAGE
);

$stmt->execute([
    $board['id']
]);

$threads =
    $stmt->fetchAll();

page_header(
    '/' . $board['slug'] . '/',
    $board
);
?>

<div class="site-banner">

<a href="/">

<img
    src="/banners/banner.webp"
    alt="pomfIB"
>

</a>

</div>

<div class="board-header">

<?php if (!empty($board['banner'])): ?>

<div class="boardbanner">

    <img
        class="board_image"
        src="<?= h($board['banner']) ?>"
        alt=""
    >

</div>

<?php endif; ?>

<header class="board-title">

<h1>
    /<?= h($board['slug']) ?>/
    -
    <?= h($board['name']) ?>
</h1>

<?php if (!empty($board['description'])): ?>

    <div class="board-description">
        <?= h($board['description']) ?>
    </div>

<?php endif; ?>

</header>

</div>

<hr>

<div class="board-form-area">

<form
    action="/post.php"
    method="post"
    enctype="multipart/form-data"
    class="postform"
>

<input
    type="hidden"
    name="csrf"
    value="<?= h(csrf_token()) ?>"
>

<input
    type="hidden"
    name="board"
    value="<?= h($board['slug']) ?>"
>

<table class="postForm">

    <tr>

        <th class="postblock"></th>

        <td>

            <input
                type="text"
                name="name"
                maxlength="80"
                placeholder="Name"
            >

            <input
                type="text"
                name="subject"
                maxlength="120"
                placeholder="Subject"
                class="subject-input"
            >

            <button
                type="submit"
                class="post-submit"
            >
                Post
            </button>

        </td>

    </tr>

    <tr>

        <th class="postblock"></th>

        <td>

            <textarea
                name="body"
                rows="8"
                maxlength="10000"
                required
                placeholder="Message"
                style="width: 600px; max-width: 100%; box-sizing: border-box;"
            ></textarea>

        </td>

    </tr>

    <tr>

        <th class="postblock"></th>

        <td>

            <input
                type="file"
                name="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
            >

        </td>

    </tr>

    <tr>

        <th class="postblock"></th>

        <td>

            <input
                type="text"
                name="password"
                maxlength="100"
                placeholder="Password"
            >

        </td>

    </tr>

<?php if (!is_admin()): ?>

    <tr>

        <th class="postblock"></th>

        <td>

            <div class="captcha-box">

                <img
                    src="/captcha.php"
                    alt="CAPTCHA"
                    class="captcha-image"
                    title="CAPTCHA"
                >

                <br>

                <input
                    type="text"
                    name="captcha"
                    maxlength="5"
                    autocomplete="off"
                    placeholder="Enter CAPTCHA"
                    required
                >

            </div>

        </td>

    </tr>

<?php endif; ?>

    <tr>

        <th class="postblock"></th>

        <td>

            <button
                type="reset"
                class="clear-button"
            >
                Clear
            </button>

        </td>

    </tr>

</table>

</form>

</div>

<hr>

<div class="board-search-area">

<form
    action="/search.php"
    method="get"
    class="board-search"
>

<input
    type="text"
    name="search"
    placeholder="<?= h($board['slug']) ?> search"
>

<input
    type="hidden"
    name="board"
    value="<?= h($board['slug']) ?>"
>

<input
    type="submit"
    value="Search"
>

</form>

<button
    type="button"
    id="board-refresh"
    class="board-refresh"
>
    Refresh
</button>

</div>

<hr>

<div class="board-threads">

<?php foreach ($threads as $thread): ?>

<?php

$pstmt = db()->prepare(
    'SELECT *
     FROM posts
     WHERE thread_id = ?
       AND deleted = 0
     ORDER BY id ASC
     LIMIT 5'
);

$pstmt->execute([
    $thread['id']
]);

$posts =
    $pstmt->fetchAll();

$countStmt = db()->prepare(
    'SELECT COUNT(*)
     FROM posts
     WHERE thread_id = ?
       AND deleted = 0'
);

$countStmt->execute([
    $thread['id']
]);

$postCount =
    (int) $countStmt->fetchColumn();

$op =
    $posts[0] ?? null;

?>

<div
    class="thread"
    id="thread_<?= (int) $thread['id'] ?>"
    data-board="<?= h($board['slug']) ?>"
>

<?php if ($op): ?>

    <div
        class="post op"
        id="p<?= (int) $op['id'] ?>"
    >

        <p class="intro">

            <input
                type="checkbox"
                class="delete"
                name="delete_<?= (int) $op['id'] ?>"
                id="delete_<?= (int) $op['id'] ?>"
            >

            <label
                for="delete_<?= (int) $op['id'] ?>"
            >

                <?php if (!empty($thread['subject'])): ?>

                    <strong class="subject">
                        <?= h($thread['subject']) ?>
                    </strong>

                <?php endif; ?>

                <?= render_post_name(
                    $op['name'] ?? null,
                    $op['country_code'] ?? null,
                    $showCountryFlags
                ) ?>

                <span class="date">
                    <?= h($op['created_at']) ?>
                </span>

            </label>

           <?php if (!empty($thread['pinned'])): ?>

    <span
        class="thread-icon"
        title="Pinned"
    >
        <img
            src="/img/sticky.gif"
            alt="Pinned"
            class="thread-status-icon"
        >
    </span>

<?php endif; ?>

<?php if (!empty($thread['locked'])): ?>

    <span
        class="thread-icon"
        title="Locked"
    >
        <img
            src="/img/locked.gif"
            alt="Locked"
            class="thread-status-icon"
        >
    </span>

<?php endif; ?>

            <a
                class="post_no"
                id="post_no_<?= (int) $op['id'] ?>"
                href="/<?= rawurlencode($board['slug']) ?>/thread/<?= (int) $thread['id'] ?>#p<?= (int) $op['id'] ?>"
            >
                No.<?= (int) $op['id'] ?>
            </a>

            <?php if (empty($thread['locked'])): ?>

                <a
                    href="#"
                    class="reply-link"
                    data-thread-id="<?= (int) $thread['id'] ?>"
                    data-post-id="<?= (int) $op['id'] ?>"
                >
                    [Reply]
                </a>

            <?php endif; ?>

            <a
                href="#"
                class="report-link"
                data-post-id="<?= (int) $op['id'] ?>"
                title="Report post"
            >
                <img
                    src="/img/report.png"
                    alt="Report"
                    class="report-button"
                >
            </a>

        </p>

<?php if (!empty($op['file_name'])): ?>

        <?php
        $fileInfo =
            get_file_info(
                (string) $op['file_name']
            );
        ?>

        <div class="file">

            <div class="fileinfo">

                <a
                    href="/<?= h(UPLOAD_URL . '/' . $op['file_name']) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <?= h($fileInfo['name']) ?>
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
                        <?= h(format_file_size($fileInfo['size'])) ?>
                        )
                    </span>

                <?php else: ?>

                    <span>
                        (
                        <?= h(format_file_size($fileInfo['size'])) ?>
                        )
                    </span>

                <?php endif; ?>

            </div>

            <a
                href="/<?= h(UPLOAD_URL . '/' . $op['file_name']) ?>"
                target="_blank"
                rel="noopener"
            >

                <img
                    class="post-image"
                    src="/<?= h(UPLOAD_URL . '/' . $op['file_name']) ?>"
                    loading="lazy"
                    alt=""
                >

            </a>

        </div>

<?php endif; ?>

        <div class="body">

            <?= render_text(
                (string) $op['body']
            ) ?>

        </div>

        <div class="post-clear"></div>

    </div>

<?php endif; ?>


<?php foreach (array_slice($posts, 1) as $post): ?>

    <div
        class="post reply"
        id="reply_<?= (int) $post['id'] ?>"
    >

        <p class="intro">

            <a
                id="<?= (int) $post['id'] ?>"
                class="post_anchor"
            ></a>

            <input
                type="checkbox"
                class="delete"
                name="delete_<?= (int) $post['id'] ?>"
                id="delete_<?= (int) $post['id'] ?>"
            >

            <label
                for="delete_<?= (int) $post['id'] ?>"
            >

                <?= render_post_name(
                    $post['name'] ?? null,
                    $post['country_code'] ?? null,
                    $showCountryFlags
                ) ?>

                <span class="date">
                    <?= h($post['created_at']) ?>
                </span>

            </label>

            <a
                class="post_no"
                id="post_no_<?= (int) $post['id'] ?>"
                onclick="highlightReply(<?= (int) $post['id'] ?>)"
                href="/<?= rawurlencode($board['slug']) ?>/thread/<?= (int) $thread['id'] ?>#<?= (int) $post['id'] ?>"
            >
                No.
            </a>

            <a
                class="post_no"
                onclick="citeReply(<?= (int) $post['id'] ?>)"
                href="#<?= (int) $post['id'] ?>"
            >
                <?= (int) $post['id'] ?>
            </a>

            <?php if (empty($thread['locked'])): ?>

                <a
                    href="#"
                    class="reply-link"
                    data-thread-id="<?= (int) $thread['id'] ?>"
                    data-post-id="<?= (int) $post['id'] ?>"
                >
                    [Reply]
                </a>

            <?php endif; ?>

            <!-- REPORT BUTTON STAYS DIRECTLY AFTER REPLY -->

            <a
                href="#"
                class="report-link"
                data-post-id="<?= (int) $post['id'] ?>"
                title="Report post"
            >
                <img
                    src="/img/report.png"
                    alt="Report"
                    class="report-button"
                >
            </a>

        </p>

<?php if (!empty($post['file_name'])): ?>

        <?php
        $fileInfo =
            get_file_info(
                (string) $post['file_name']
            );
        ?>

        <div class="file">

            <div class="fileinfo">

                <a
                    href="/<?= h(UPLOAD_URL . '/' . $post['file_name']) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <?= h($fileInfo['name']) ?>
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
                        <?= h(format_file_size($fileInfo['size'])) ?>
                        )
                    </span>

                <?php else: ?>

                    <span>
                        (
                        <?= h(format_file_size($fileInfo['size'])) ?>
                        )
                    </span>

                <?php endif; ?>

            </div>

            <a
                href="/<?= h(UPLOAD_URL . '/' . $post['file_name']) ?>"
                target="_blank"
                rel="noopener"
            >

                <img
                    class="post-image"
                    src="/<?= h(UPLOAD_URL . '/' . $post['file_name']) ?>"
                    loading="lazy"
                    alt=""
                >

            </a>

        </div>

<?php endif; ?>

        <div
            class="body"
            <?php if (!empty($post['file_name'])): ?>
                style="clear:both"
            <?php endif; ?>
        >

            <?= render_text(
                (string) $post['body']
            ) ?>

        </div>

    </div>

<?php endforeach; ?>


<?php if ($postCount > 5): ?>

    <div class="omitted">

        <?= $postCount - 5 ?>
        posts omitted.

        <a
            href="/<?= rawurlencode($board['slug']) ?>/thread/<?= (int) $thread['id'] ?>"
        >
            Click here to view the thread.
        </a>

    </div>

<?php endif; ?>

</div>

<hr>

<?php endforeach; ?>

</div>

<div class="pages">

<a
    href="/<?= rawurlencode($board['slug']) ?>/"
>
    [1]
</a>

<?php if (count($threads) >= THREADS_PER_PAGE): ?>

<a
    href="/<?= rawurlencode($board['slug']) ?>/?page=2"
>
    [Next]
</a>

<?php endif; ?>

</div>


<div
    id="report-modal"
    class="report-modal"
    style="display:none;"
>

<div class="report-box">

<div class="report-header">

    Report Post

    <button
        type="button"
        id="report-close"
        class="report-close"
        aria-label="Close"
    >
        ×
    </button>

</div>

<form
    action="/report.php"
    method="post"
    id="report-form"
>

    <input
        type="hidden"
        name="csrf"
        value="<?= h(csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="board"
        value="<?= h($board['slug']) ?>"
    >

    <input
        type="hidden"
        name="post_id"
        id="report-post-id"
        value=""
    >

    <label for="report-reason">
        Reason
    </label>

    <textarea
        name="reason"
        id="report-reason"
        rows="5"
        maxlength="1000"
        required
        placeholder="Explain why this post should be reported..."
    ></textarea>

    <div class="report-actions">

        <button
            type="submit"
            class="report-submit"
        >
            Submit Report
        </button>

        <button
            type="button"
            id="report-cancel"
            class="report-cancel"
        >
            Cancel
        </button>

    </div>

</form>

</div>

</div>


<form
    action="/post.php"
    method="post"
    enctype="multipart/form-data"
    id="quick-reply"
    class="quick-reply"
    style="display:none;"
>

<table>

<tr>

    <th colspan="2">

        <span class="handle">

            <button
                type="button"
                class="quick-reply-close"
                aria-label="Close"
            >
                ×
            </button>

            Quick Reply

        </span>

    </th>

</tr>

<tr>

    <td colspan="2">

        <input
            type="text"
            name="name"
            maxlength="80"
            placeholder="Name"
        >

    </td>

</tr>

<tr>

    <td colspan="2">

        <input
            type="text"
            name="subject"
            maxlength="120"
            placeholder="Subject"
        >

    </td>

</tr>

<tr>

    <td colspan="2">

        <textarea
            name="body"
            rows="8"
            maxlength="10000"
            required
            placeholder="Comment"
        ></textarea>

    </td>

</tr>

<tr>

    <td colspan="2">

        <input
            type="file"
            name="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
        >

    </td>

</tr>

<tr>

    <td>

        <input
            type="text"
            name="password"
            maxlength="100"
            placeholder="Password"
        >

    </td>

    <td class="submit">

        <button type="submit">
            Reply
        </button>

    </td>

</tr>

</table>

<?php if (!is_admin()): ?>

<input
    type="hidden"
    name="captcha"
    class="quick-reply-captcha"
    value=""
>

<?php endif; ?>

<input
    type="hidden"
    name="csrf"
    value="<?= h(csrf_token()) ?>"
>

<input
    type="hidden"
    name="board"
    value="<?= h($board['slug']) ?>"
>

<input
    type="hidden"
    name="thread_id"
    class="quick-reply-thread"
    value=""
>

</form>


<style>

.board-header {
    width: 100%;
    max-width: 750px;
    margin: 0 auto;
    text-align: center;
}

.board-title {
    text-align: center;
}

.board-title h1 {
    text-align: center;
    margin-left: auto;
    margin-right: auto;
}

.board-description {
    text-align: center;
}

.boardbanner {
    text-align: center;
    margin-bottom: 8px;
}

.board_image {
    max-width: 100%;
    height: auto;
}

.site-banner {
    text-align: center;
    margin: 5px 0 15px;
}

.site-banner img {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 0 auto;
}

.board-form-area {
    width: 100%;
    max-width: 750px;
    margin: 0 auto;
    text-align: center;
}

.postform {
    width: fit-content;
    max-width: 100%;
    margin-left: auto;
    margin-right: auto;
    text-align: left;
}

.postForm {
    margin-left: auto;
    margin-right: auto;
}

.postForm td {
    white-space: nowrap;
}

.postForm input[type="text"] {
    box-sizing: border-box;
}

.postForm input[name="name"] {
    width: 150px;
    max-width: 100%;
}

.postForm .subject-input {
    width: 300px;
    max-width: 100%;
    margin-left: 4px;
}

.post-submit {
    width: 80px;
    min-width: 80px;
    height: 28px;
    margin-left: 5px;
    padding: 3px 14px;
    box-sizing: border-box;
    cursor: pointer;
}

.clear-button {
    cursor: pointer;
}

.captcha-box {
    margin-top: 4px;
}

.captcha-image {
    display: block;
    width: 180px;
    height: 55px;
    margin-bottom: 4px;
    cursor: default;
    border: 1px solid #aaa;
}

.captcha-box input[name="captcha"] {
    width: 180px;
    max-width: 100%;
    box-sizing: border-box;
}

.board-search-area {
    width: 100%;
    margin: 0;
    text-align: left;
}

.board-search {
    display: inline-block;
    margin: 0;
    text-align: left;
}

.board-refresh {
    margin-left: 6px;
}

.board-threads {
    width: 100%;
    margin: 0;
    padding: 0;
    text-align: left;
}

.thread {
    width: 100%;
    margin: 0 0 10px;
    padding: 0;
    text-align: left;
}

.post {
    margin-bottom: 5px;
    scroll-margin-top: 20px;
}

.thread > .post.op {
    display: table;
    width: auto;
    max-width: 95%;
    background: #e6e6ff;
    padding: 8px;
    margin-bottom: 5px;
    box-sizing: border-box;
}


/*
 * WAKABA-STYLE REPLIES
 *
 * Replies intentionally have only a small 4px gap.
 * Any old <br> directly after a reply is hidden.
 */

.thread > .post.reply {
    display: block;
    width: fit-content;
    max-width: 95%;
    margin: 0 0 4px 0;
    padding: 8px;
    background: #e6e6ff;
    border: 1px solid #aaa;
    box-sizing: border-box;
    text-align: left;
    clear: both;
}

/*
 * Prevent an extra line from appearing between replies.
 */

.thread > .post.reply + br {
    display: none !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}

.post_anchor {
    display: block;
    position: relative;
    top: -20px;
    visibility: hidden;
}

.thread > .post.reply .intro {
    display: block;
    margin: 0 0 4px 0;
    padding: 0;
    line-height: 18px;
    white-space: normal;
}

.thread > .post.reply .intro label {
    cursor: pointer;
}

.thread > .post.reply .delete {
    margin-right: 3px;
    vertical-align: middle;
}

.thread > .post.reply .file {
    display: block;
    float: left;
    width: auto;
    max-width: 250px;
    margin: 0 15px 8px 0;
    box-sizing: border-box;
}

.thread > .post.reply .fileinfo {
    display: block;
    clear: both;
    font-size: 10px;
    line-height: 14px;
    margin-bottom: 3px;
    text-align: left;
    word-break: break-all;
}

.thread > .post.reply .post-image {
    display: block;
    width: auto !important;
    height: auto !important;
    max-width: 250px !important;
    max-height: 400px !important;
    cursor: zoom-in;
}

.thread > .post.reply .body {
    display: block;
    word-wrap: break-word;
    overflow: visible;
    min-height: 18px;
}

.thread > .post.reply .post-clear {
    clear: both;
}

.thread > .post.reply .subject {
    margin-right: 6px;
}

.subject {
    margin-right: 6px;
}

.name {
    margin-right: 6px;
}


/*
 * ADMIN CAPCODE
 */

.capcode {
    color: #f00;
    font-weight: bold;
    margin-left: 3px;
}


/*
 * COUNTRY FLAGS
 */

.country-flag {
    display: inline-block;
    height: 12px;
    margin-left: 4px;
    vertical-align: middle;
    object-fit: cover;
    border: 0;
}


/*
 * DATE / POST NUMBER
 */

.date {
    margin-right: 6px;
}

.post_no {
    display: inline;
    margin-left: 3px;
    margin-right: 5px;
    cursor: pointer;
}

.reply-link,
.report-link {
    cursor: pointer;
    margin-left: 3px;
}


/*
 * REPORT BUTTON
 */

.report-link {
    display: inline-block;
    vertical-align: middle;
}

.report-button {
    display: inline-block;
    width: auto;
    height: 16px;
    max-width: 100%;
    vertical-align: middle;
    cursor: pointer;
}

.report-button:hover {
    opacity: 0.8;
}


/*
 * OP FILE
 */

.thread > .post.op .file {
    float: left !important;
    width: auto !important;
    max-width: 250px !important;
    margin-right: 15px;
    margin-bottom: 8px;
    box-sizing: border-box;
}

.fileinfo {
    display: block;
    clear: both;
    font-size: 10px;
    line-height: 14px;
    margin-bottom: 3px;
    text-align: left;
    word-break: break-all;
}

.thread > .post.op .post-image {
    display: block;
    width: auto !important;
    height: auto !important;
    max-width: 250px !important;
    max-height: 400px !important;
    cursor: zoom-in;
}

.body {
    word-wrap: break-word;
    overflow: visible;
}

.post-clear {
    clear: both;
}

.omitted {
    clear: both;
    margin-top: 8px;
    margin-bottom: 8px;
}


/*
 * REPORT MODAL
 */

.report-modal {
    position: fixed;
    inset: 0;
    z-index: 20000;
    background: rgba(0, 0, 0, .45);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 80px;
    box-sizing: border-box;
}

.report-box {
    width: 360px;
    max-width: calc(100vw - 20px);
    background: #e6e6ff;
    border: 1px solid #777;
    box-sizing: border-box;
    padding: 0;
    text-align: left;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .35);
}

.report-header {
    position: relative;
    padding: 4px 28px 4px 8px;
    background: #d9d9f2;
    border-bottom: 1px solid #aaa;
    font-weight: bold;
}

.report-close {
    position: absolute;
    top: 1px;
    right: 3px;
    border: 0;
    background: transparent;
    font-size: 18px;
    line-height: 18px;
    cursor: pointer;
}

#report-form {
    padding: 10px;
}

#report-form label {
    display: block;
    margin-bottom: 4px;
    font-weight: bold;
}

#report-reason {
    display: block;
    width: 100%;
    max-width: 100%;
    min-height: 90px;
    box-sizing: border-box;
    resize: vertical;
    margin-bottom: 8px;
}

.report-actions {
    display: flex;
    gap: 5px;
    justify-content: flex-end;
}

.report-submit,
.report-cancel {
    cursor: pointer;
}


/*
 * QUICK REPLY
 */

.quick-reply {
    position: fixed;
    right: 5%;
    top: 5%;
    width: 250px !important;
    max-width: 250px !important;
    min-width: 250px !important;
    z-index: 10000;
    margin: 0;
    padding: 0;
    text-align: left;
}

.quick-reply table {
    width: 250px !important;
    max-width: 250px !important;
    min-width: 250px !important;
    table-layout: fixed;
    border-collapse: collapse;
    margin: 0;
    background: #e6e6ff;
    overflow: hidden;
    border: 1px solid #aaa;
}

.quick-reply th,
.quick-reply td {
    margin: 0;
    padding: 0;
}

.quick-reply th {
    text-align: center;
    padding: 2px 0;
    border: 1px solid #222;
    background: #d9d9f2;
}

.quick-reply .handle {
    display: block;
    width: 100%;
    min-height: 18px;
    line-height: 18px;
    cursor: move;
}

.quick-reply-close {
    float: right;
    border: 0;
    background: transparent;
    padding: 0 5px;
    margin: 0;
    font-size: 18px;
    line-height: 18px;
    cursor: pointer;
}

.quick-reply input[type="text"],
.quick-reply input[type="file"],
.quick-reply textarea {
    display: block;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box;
    margin: 0 0 1px 0;
    font-size: 10pt;
}

.quick-reply input[type="text"],
.quick-reply textarea {
    padding: 2px;
}

.quick-reply input[type="file"] {
    padding: 5px 2px;
}

.quick-reply textarea {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    resize: vertical;
}

.quick-reply td.submit {
    width: 1%;
    white-space: nowrap;
    text-align: right;
    padding-right: 4px;
}

.quick-reply td.submit button {
    width: 100%;
    min-width: 58px;
    white-space: nowrap;
}

.quick-reply::after {
    content: "";
    display: block;
    clear: both;
}

.markup-greentext {
    color: #789922;
}

.markup-red {
    color: #d14;
}

.markup-doom {
    color: #b30000;
    font-weight: bold;
}

.markup-moe {
    color: #ff69b4;
    font-weight: bold;
    text-shadow:
        0 0 2px rgba(255, 105, 180, .35);
}

.markup-echo {
    color: #777;
}

.markup-spoiler {
    color: transparent;
    background: #000;
    cursor: pointer;
    padding: 0 2px;
    border-radius: 1px;
}

.markup-spoiler:hover {
    color: #fff;
}

.markup-spoiler-demo {
    background: #000;
    color: #000;
    padding: 0 3px;
}

.markup-code {
    display: block;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.3;
    background: #eee;
    border: 1px solid #aaa;
    padding: 8px;
    margin: 4px 0;
    overflow-x: auto;
    text-align: left;
    text-shadow: none;
}

.markup-aa {
    display: block;
    white-space: pre;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.1;
    margin: 4px 0;
    overflow-x: auto;
    text-align: left;
    text-shadow: none;
}

.quick-reply input[type="file"] {
    max-width: 100%;
    width: 100%;
    overflow: hidden;
}

.quick-reply form {
    width: 250px !important;
    max-width: 250px !important;
    min-width: 250px !important;
    box-sizing: border-box;
    overflow: hidden;
}


/*
 * MOBILE
 */

@media only screen and (max-width: 600px) {

    .site-banner {
        margin-left: 5px;
        margin-right: 5px;
    }

    .site-banner img {
        width: 100%;
    }

    .board-header {
        max-width: 100%;
    }

    .board-form-area {
        max-width: 100%;
    }

    .postForm td {
        white-space: normal;
    }

    .postForm input[name="name"] {
        width: 120px;
    }

    .postForm .subject-input {
        width: 180px;
    }

    .post-submit {
        width: 75px;
        min-width: 75px;
    }

    .thread > .post.reply {
        max-width: calc(100vw - 20px);
        margin-bottom: 4px;
    }

    .thread > .post.reply .file {
        max-width: 200px;
    }

    .thread > .post.reply .post-image {
        max-width: 200px !important;
        max-height: 350px !important;
    }

    .thread > .post.op .post-image {
        max-width: 200px !important;
        max-height: 350px !important;
    }

    .report-box {
        width: 360px;
        max-width: calc(100vw - 10px);
    }

    .quick-reply {
        right: 5px;
        top: 5px;
        width: 300px;
        max-width: calc(100vw - 10px);
    }

}

</style>


<script>
(function () {

    var refreshButton =
        document.getElementById('board-refresh');

    var quickReply =
        document.getElementById('quick-reply');


    /*
     * REPORT SYSTEM
     */

    var reportModal =
        document.getElementById('report-modal');

    var reportPostId =
        document.getElementById('report-post-id');

    var reportReason =
        document.getElementById('report-reason');

    var reportClose =
        document.getElementById('report-close');

    var reportCancel =
        document.getElementById('report-cancel');


    function showReport(postId) {

        if (!reportModal || !reportPostId) {
            return;
        }

        reportPostId.value = postId;

        if (reportReason) {
            reportReason.value = '';
        }

        reportModal.style.display = 'flex';

        if (reportReason) {
            reportReason.focus();
        }
    }


    function hideReport() {

        if (!reportModal) {
            return;
        }

        reportModal.style.display = 'none';
    }


    document.querySelectorAll('.report-link').forEach(
        function (link) {

            link.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();

                    var postId =
                        this.getAttribute('data-post-id');

                    if (!postId) {
                        return;
                    }

                    showReport(postId);
                }
            );

        }
    );


    if (reportClose) {

        reportClose.addEventListener(
            'click',
            function () {
                hideReport();
            }
        );

    }


    if (reportCancel) {

        reportCancel.addEventListener(
            'click',
            function () {
                hideReport();
            }
        );

    }


    if (reportModal) {

        reportModal.addEventListener(
            'click',
            function (event) {

                if (event.target === reportModal) {
                    hideReport();
                }

            }
        );

    }


    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                reportModal &&
                reportModal.style.display !== 'none'
            ) {
                hideReport();
            }

        }
    );


    /*
     * BOARD REFRESH
     */

    if (refreshButton) {

        refreshButton.addEventListener(
            'click',
            function () {

                var url =
                    window.location.pathname +
                    window.location.search;

                url +=
                    (
                        url.indexOf('?') === -1
                            ? '?'
                            : '&'
                    ) +
                    '_=' +
                    Date.now();

                window.location.replace(url);

            }
        );

    }


    /*
     * QUICK REPLY
     */

    if (quickReply) {

        var quickReplyThread =
            quickReply.querySelector(
                '.quick-reply-thread'
            );

        var quickReplyBody =
            quickReply.querySelector(
                'textarea[name="body"]'
            );

        var quickReplyName =
            quickReply.querySelector(
                'input[name="name"]'
            );

        var quickReplySubject =
            quickReply.querySelector(
                'input[name="subject"]'
            );

        var closeButton =
            quickReply.querySelector(
                '.quick-reply-close'
            );

        var originalForm =
            document.querySelector(
                'form.postform'
            );

        var originalCaptcha =
            originalForm
                ? originalForm.querySelector(
                    'input[name="captcha"]'
                )
                : null;

        var quickReplyCaptcha =
            quickReply.querySelector(
                '.quick-reply-captcha'
            );


        function syncCaptcha() {

            if (
                originalCaptcha &&
                quickReplyCaptcha
            ) {

                quickReplyCaptcha.value =
                    originalCaptcha.value;

            }

        }


        function showQuickReply(threadId, postId) {

            if (!threadId) {
                return;
            }

            quickReplyThread.value =
                threadId;


            if (originalForm) {

                var originalName =
                    originalForm.querySelector(
                        'input[name="name"]'
                    );

                var originalSubject =
                    originalForm.querySelector(
                        'input[name="subject"]'
                    );

                var originalBody =
                    originalForm.querySelector(
                        'textarea[name="body"]'
                    );


                if (originalName) {
                    quickReplyName.value =
                        originalName.value;
                }


                if (originalSubject) {
                    quickReplySubject.value =
                        originalSubject.value;
                }


                if (
                    originalBody &&
                    !quickReplyBody.value
                ) {
                    quickReplyBody.value =
                        originalBody.value;
                }

            }


            syncCaptcha();

            quickReply.style.display =
                'block';


            if (postId) {

                var target =
                    document.getElementById(
                        'reply_' + postId
                    );

                if (!target) {

                    target =
                        document.getElementById(
                            'p' + postId
                        );

                }


                if (target) {

                    target.style.outline =
                        '2px solid #aaa';


                    setTimeout(
                        function () {

                            target.style.outline =
                                '';

                        },
                        1000
                    );


                    try {

                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                    } catch (e) {

                        target.scrollIntoView();

                    }

                }

            }


            quickReplyBody.focus();

        }


        function hideQuickReply() {

            quickReply.style.display =
                'none';

        }


        document.querySelectorAll(
            '.reply-link'
        ).forEach(
            function (link) {

                link.addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();


                        var threadId =
                            this.getAttribute(
                                'data-thread-id'
                            );


                        var postId =
                            this.getAttribute(
                                'data-post-id'
                            );


                        showQuickReply(
                            threadId,
                            postId
                        );

                    }
                );

            }
        );


        if (closeButton) {

            closeButton.addEventListener(
                'click',
                function () {
                    hideQuickReply();
                }
            );

        }


        if (originalForm) {

            var originalName =
                originalForm.querySelector(
                    'input[name="name"]'
                );

            var originalSubject =
                originalForm.querySelector(
                    'input[name="subject"]'
                );

            var originalBody =
                originalForm.querySelector(
                    'textarea[name="body"]'
                );


            if (originalName) {

                originalName.addEventListener(
                    'input',
                    function () {

                        quickReplyName.value =
                            this.value;

                    }
                );

            }


            if (originalSubject) {

                originalSubject.addEventListener(
                    'input',
                    function () {

                        quickReplySubject.value =
                            this.value;

                    }
                );

            }


            if (originalBody) {

                originalBody.addEventListener(
                    'input',
                    function () {

                        if (
                            document.activeElement !==
                            quickReplyBody
                        ) {

                            quickReplyBody.value =
                                this.value;

                        }

                    }
                );

            }


            if (originalCaptcha) {

                originalCaptcha.addEventListener(
                    'input',
                    function () {

                        syncCaptcha();

                    }
                );

            }


            quickReplyBody.addEventListener(
                'input',
                function () {

                    originalBody.value =
                        this.value;

                }
            );


            quickReplyName.addEventListener(
                'input',
                function () {

                    if (originalName) {

                        originalName.value =
                            this.value;

                    }

                }
            );


            quickReplySubject.addEventListener(
                'input',
                function () {

                    if (originalSubject) {

                        originalSubject.value =
                            this.value;

                    }

                }
            );

        }


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape' &&
                    quickReply.style.display !== 'none'
                ) {
                    hideQuickReply();
                }

            }
        );


        var handle =
            quickReply.querySelector(
                '.handle'
            );


        if (handle) {

            var dragging = false;

            var offsetX = 0;

            var offsetY = 0;


            handle.addEventListener(
                'mousedown',
                function (event) {

                    if (
                        event.target === closeButton
                    ) {
                        return;
                    }


                    dragging = true;


                    var rect =
                        quickReply.getBoundingClientRect();


                    offsetX =
                        event.clientX -
                        rect.left;


                    offsetY =
                        event.clientY -
                        rect.top;


                    quickReply.style.right =
                        'auto';


                    quickReply.style.left =
                        rect.left + 'px';


                    quickReply.style.top =
                        rect.top + 'px';


                    event.preventDefault();

                }
            );


            document.addEventListener(
                'mousemove',
                function (event) {

                    if (!dragging) {
                        return;
                    }


                    var left =
                        event.clientX -
                        offsetX;


                    var top =
                        event.clientY -
                        offsetY;


                    var maxLeft =
                        window.innerWidth -
                        quickReply.offsetWidth;


                    var maxTop =
                        window.innerHeight -
                        quickReply.offsetHeight;


                    left =
                        Math.max(
                            0,
                            Math.min(
                                left,
                                maxLeft
                            )
                        );


                    top =
                        Math.max(
                            0,
                            Math.min(
                                top,
                                maxTop
                            )
                        );


                    quickReply.style.left =
                        left + 'px';


                    quickReply.style.top =
                        top + 'px';

                }
            );


            document.addEventListener(
                'mouseup',
                function () {

                    dragging = false;

                }
            );

        }

    }


    window.addEventListener(
        'pageshow',
        function (event) {

            if (event.persisted) {
                window.location.reload();
            }

        }
    );

})();
</script>

<?php page_footer(); ?>