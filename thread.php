<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

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
 * ============================================================
 * FILE INFORMATION HELPERS
 * ============================================================
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
 * ============================================================
 * COUNTRY FLAG HELPER
 * ============================================================
 *
 * Flags are ONLY used on /int/.
 *
 * The function scans /static/flags/ once per request and
 * matches the two-letter country code against the filename.
 *
 * Examples it can recognize:
 *
 * us.png
 * US.png
 * us.gif
 * US.gif
 * us.webp
 * us.svg
 *
 * It also accepts filenames such as:
 *
 * flag-us.png
 * flag_US.png
 *
 * ============================================================
 */

/*
 * ============================================================
 * BASIC THREAD PARAMETERS
 * ============================================================
 */

$boardSlug =
    trim(
        (string) (
            $_GET['b'] ?? ''
        )
    );

$threadId =
    (int) (
        $_GET['id'] ?? 0
    );

if (
    $boardSlug === '' ||
    $threadId < 1
) {
    http_response_code(404);
    exit('Thread not found.');
}


/*
 * ============================================================
 * BOARD
 * ============================================================
 */

$board =
    get_board($boardSlug);

if (!$board) {
    http_response_code(404);
    exit('Board not found.');
}


/*
 * ============================================================
 * FLAGS
 * ============================================================
 *
 * Only /int/ gets country flags.
 * ============================================================
 */

$showCountryFlags =
    strtolower(
        (string) $board['slug']
    ) === 'int';


/*
 * ============================================================
 * REPORT CSRF TOKEN
 * ============================================================
 */

if (
    empty($_SESSION['report_csrf']) ||
    !is_string($_SESSION['report_csrf'])
) {
    $_SESSION['report_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}

$reportCsrf =
    $_SESSION['report_csrf'];


/*
 * ============================================================
 * REPORT HANDLER
 * ============================================================
 */

$reportMessage = '';
$reportMessageType = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (
        isset($_POST['action']) &&
        $_POST['action'] === 'report'
    )
) {

    $submittedCsrf =
        (string) (
            $_POST['report_csrf'] ?? ''
        );

    if (
        $submittedCsrf === '' ||
        !hash_equals(
            $reportCsrf,
            $submittedCsrf
        )
    ) {

        $reportMessage =
            'Invalid report request. Please refresh the page and try again.';

        $reportMessageType =
            'error';

    } else {

        $reportPostId =
            (int) (
                $_POST['post_id'] ?? 0
            );

        $reportThreadId =
            (int) (
                $_POST['thread_id'] ?? 0
            );

        $reportReason =
            trim(
                (string) (
                    $_POST['reason'] ?? ''
                )
            );

        $reportDetails =
            trim(
                (string) (
                    $_POST['details'] ?? ''
                )
            );

        $allowedReasons = [
            'spam',
            'illegal',
            'harassment',
            'sexual_content',
            'malware',
            'personal_information',
            'other'
        ];

        $validPost = false;

        if (
            $reportPostId > 0 &&
            $reportThreadId === $threadId
        ) {

            $verifyReport =
                db()->prepare(
                    'SELECT id
                     FROM posts
                     WHERE id = ?
                       AND thread_id = ?
                       AND deleted = 0
                     LIMIT 1'
                );

            $verifyReport->execute([
                $reportPostId,
                $threadId
            ]);

            $validPost =
                (bool) $verifyReport->fetchColumn();
        }

        if (
            strlen($reportDetails) > 2000
        ) {
            $reportDetails =
                substr(
                    $reportDetails,
                    0,
                    2000
                );
        }

        if (!$validPost) {

            $reportMessage =
                'The post could not be found.';

            $reportMessageType =
                'error';

        } elseif (
            !in_array(
                $reportReason,
                $allowedReasons,
                true
            )
        ) {

            $reportMessage =
                'Please select a valid report reason.';

            $reportMessageType =
                'error';

        } else {

            $lastReport =
                (int) (
                    $_SESSION['last_report_time'] ?? 0
                );

            if (
                $lastReport > 0 &&
                time() - $lastReport < 10
            ) {

                $reportMessage =
                    'Please wait a few seconds before submitting another report.';

                $reportMessageType =
                    'error';

            } else {

                try {

                    db()->exec(
                        'CREATE TABLE IF NOT EXISTS reports (
                            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                            board_id BIGINT UNSIGNED NOT NULL,
                            thread_id BIGINT UNSIGNED NOT NULL,
                            post_id BIGINT UNSIGNED NOT NULL,
                            reason VARCHAR(64) NOT NULL,
                            details TEXT NULL,
                            status VARCHAR(32) NOT NULL DEFAULT "open",
                            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (id),
                            INDEX idx_reports_board (board_id),
                            INDEX idx_reports_thread (thread_id),
                            INDEX idx_reports_post (post_id),
                            INDEX idx_reports_status (status),
                            INDEX idx_reports_created (created_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                    );

                    $reportStmt =
                        db()->prepare(
                            'INSERT INTO reports
                                (
                                    board_id,
                                    thread_id,
                                    post_id,
                                    reason,
                                    details,
                                    status
                                )
                             VALUES
                                (
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    "open"
                                )'
                        );

                    $reportStmt->execute([
                        (int) $board['id'],
                        $threadId,
                        $reportPostId,
                        $reportReason,
                        $reportDetails !== ''
                            ? $reportDetails
                            : null
                    ]);

                    $_SESSION['last_report_time'] =
                        time();

                    $reportMessage =
                        'Report submitted. Thank you.';

                    $reportMessageType =
                        'success';

                } catch (
                    Throwable $e
                ) {

                    $reportMessage =
                        'The report could not be submitted. Please try again later.';

                    $reportMessageType =
                        'error';
                }
            }
        }
    }
}


/*
 * ============================================================
 * THREAD
 * ============================================================
 */

$stmt = db()->prepare(
    'SELECT *
     FROM threads
     WHERE id = ?
       AND board_id = ?
     LIMIT 1'
);

$stmt->execute([
    $threadId,
    $board['id']
]);

$thread =
    $stmt->fetch();

if (!$thread) {
    http_response_code(404);
    exit('Thread not found.');
}


/*
 * ============================================================
 * POSTS
 * ============================================================
 */

$stmt = db()->prepare(
    'SELECT *
     FROM posts
     WHERE thread_id = ?
       AND deleted = 0
     ORDER BY id ASC'
);

$stmt->execute([
    $threadId
]);

$posts =
    $stmt->fetchAll();


/*
 * ============================================================
 * PAGE HEADER
 * ============================================================
 */

page_header(
    $board['name'] .
    ' - Thread ' .
    $threadId,
    $board
);
?>

<style>

/*
 * ============================================================
 * THREAD PAGE
 * ============================================================
 */

.thread-page {
    width: 100%;
    display: block;
}


/*
 * ============================================================
 * SITE BANNER
 * ============================================================
 */

.site-banner {
    text-align: center;
    margin: 15px 0 20px;
}

.site-banner h1 {
    margin: 0;
}

.site-banner p {
    margin: 5px 0;
}


/*
 * ============================================================
 * BOARD HEADER
 * ============================================================
 */

.board-header {
    text-align: center;
    margin: 15px 0;
}

.board-header h2 {
    margin: 0;
}

.board-header a {
    text-decoration: none;
}

.thread-meta {
    text-align: center;
    margin: 8px 0 15px;
    font-size: 12px;
}

.thread-controls {
    text-align: center;
    margin: 10px 0 15px;
}

.thread-controls button {
    margin: 0 3px;
}

.thread-controls .auto-reload-active {
    font-weight: bold;
}


/*
 * ============================================================
 * THREAD POSTS
 * ============================================================
 */

.thread-posts {
    width: 100%;
    display: block;
}

.thread-posts .post {
    position: relative;
    box-sizing: border-box;
    text-align: left;
    vertical-align: top;
}


/*
 * ============================================================
 * OP
 * ============================================================
 */

.thread-posts .post.op {
    display: flow-root;

    width: 100%;
    max-width: none;

    margin: 8px 0;
    padding: 8px;

    background: transparent;
    border: 0;

    text-align: left;

    clear: both;
    float: none;
}


/*
 * ============================================================
 * REPLIES
 * ============================================================
 */

.thread-posts .post.reply {
    display: table;

    width: auto;
    max-width: 95%;

    margin: 8px 0;
    padding: 8px;

    background: #e6e6ff;
    border: 1px solid #aaa;

    box-sizing: border-box;

    text-align: left;

    clear: both;
}


/*
 * ============================================================
 * POST HEADER
 * ============================================================
 */

.thread-posts .post-header {
    display: block;

    width: 100%;

    margin: 0 0 8px;
    padding: 0;

    text-align: left;

    clear: both;
}

.thread-posts .post-header label {
    cursor: pointer;
}

.post-delete {
    margin-right: 3px;
    vertical-align: middle;
}

.post-name {
    font-weight: bold;
}


/*
 * ============================================================
 * COUNTRY FLAG
 * ============================================================
 *
 * Only rendered for /int/.
 * ============================================================
 */

.country-flag {
    display: inline-block;

    width: 18px;
    height: 12px;

    margin-left: 4px;

    vertical-align: middle;

    object-fit: cover;

    border: 0;
}


/*
 * ============================================================
 * STAFF CAPCODE
 * ============================================================
 */

.staff-capcode {
    color: #c6a0f6;
    font-weight: bold;
}


/*
 * ============================================================
 * POST DATE
 * ============================================================
 */

.post-date {
    margin-left: 5px;
}

.post_no {
    display: inline;
    margin-left: 3px;
    margin-right: 5px;
    cursor: pointer;
}

.reply-link {
    cursor: pointer;
}

.report-link {
    margin-left: 3px;
    cursor: pointer;
}


/*
 * ============================================================
 * POST CONTENT
 * ============================================================
 */

.thread-posts .post-content {
    display: flow-root;

    width: 100%;
    max-width: none;

    margin: 0;
    padding: 0;

    text-align: left;
}


/*
 * ============================================================
 * FILE INFORMATION
 * ============================================================
 */

.thread-posts .post-content .fileinfo {
    display: block;

    width: auto;

    margin: 0 0 3px;
    padding: 0;

    font-size: 10px;
    line-height: 14px;

    text-align: left;

    word-break: break-all;
}

.thread-posts .post-content .fileinfo a {
    text-decoration: underline;
}

.thread-posts .post-content .fileinfo span {
    white-space: normal;
}


/*
 * ============================================================
 * POST IMAGE
 * ============================================================
 */

.thread-posts .post-content .post-image {
    display: block;

    float: left;

    width: auto;
    max-width: 250px;

    margin: 0 15px 8px 0;
    padding: 0;

    clear: none;

    text-align: left;
}

.thread-posts .post-content .post-image a {
    display: inline-block;

    width: auto;

    margin: 0;
    padding: 0;

    vertical-align: top;
}

.thread-posts .post-content .post-image img {
    display: block;

    width: auto;
    height: auto;

    max-width: 250px;
    max-height: 250px;

    margin: 0;
    padding: 0;

    cursor: zoom-in;
}


/*
 * ============================================================
 * POST BODY
 * ============================================================
 */

.thread-posts .post-content .post-body {
    display: block;

    width: auto;
    max-width: none;

    margin: 0;
    padding: 0;

    clear: none;
    float: none;

    text-align: left;

    word-wrap: break-word;
    overflow-wrap: anywhere;
}


/*
 * ============================================================
 * EXPANDED IMAGE
 * ============================================================
 */

.post-image img.expanded {
    width: auto !important;
    height: auto !important;

    max-width: none !important;
    max-height: none !important;

    cursor: zoom-out;

    position: relative;
    z-index: 10;
}


/*
 * ============================================================
 * HIGHLIGHT
 * ============================================================
 */

.post-highlight {
    outline: 2px solid #f00;
    outline-offset: 2px;
}


/*
 * ============================================================
 * REPORT
 * ============================================================
 */

.report-dialog {
    position: fixed;

    left: 50%;
    top: 50%;

    transform: translate(
        -50%,
        -50%
    );

    width: 360px;
    max-width: calc(100vw - 20px);

    z-index: 50000;

    background: #e6e6ff;
    border: 1px solid #777;

    box-shadow:
        0 4px 20px rgba(0, 0, 0, .35);

    padding: 0;

    box-sizing: border-box;
}

.report-dialog-header {
    padding: 5px 8px;

    background: #d9d9f2;

    border-bottom: 1px solid #999;

    font-weight: bold;

    cursor: move;
}

.report-dialog-body {
    padding: 10px;
}

.report-dialog-body label {
    display: block;

    margin-bottom: 4px;

    font-weight: bold;
}

.report-dialog-body select,
.report-dialog-body textarea {
    display: block;

    width: 100%;
    max-width: 100%;

    box-sizing: border-box;

    margin-bottom: 10px;
}

.report-dialog-body textarea {
    min-height: 80px;

    resize: vertical;
}

.report-dialog-buttons {
    text-align: right;
}

.report-dialog-buttons button {
    margin-left: 5px;
}

.report-close {
    float: right;

    border: 0;
    background: transparent;

    font-size: 18px;
    line-height: 16px;

    padding: 0 3px;

    cursor: pointer;
}

.report-overlay {
    position: fixed;

    inset: 0;

    z-index: 49999;

    background: rgba(0, 0, 0, .25);
}

.report-message {
    width: 100%;
    max-width: 750px;

    margin: 10px auto;

    padding: 7px 10px;

    box-sizing: border-box;

    border: 1px solid #999;

    text-align: center;

    font-weight: bold;
}

.report-message.success {
    background: #ddf2dd;
}

.report-message.error {
    background: #f2dddd;
}


/*
 * ============================================================
 * LOCKED THREAD
 * ============================================================
 */

.thread-locked {
    text-align: center;

    margin: 20px auto;

    font-weight: bold;
}


/*
 * ============================================================
 * REPLY FORM
 * ============================================================
 */

.reply-form-container {
    width: 100%;
    max-width: 750px;

    margin: 20px auto;

    clear: both;
}

.reply-form {
    width: fit-content;
    max-width: 100%;

    margin-left: auto;
    margin-right: auto;
}

.reply-form textarea {
    width: 600px;
    max-width: 100%;

    box-sizing: border-box;
}

.reply-form input[type="text"] {
    width: 300px;
    max-width: 100%;

    box-sizing: border-box;
}

.reply-form input[type="file"] {
    max-width: 100%;
}

.reply-form button {
    margin-right: 5px;
}


/*
 * ============================================================
 * MARKUP
 * ============================================================
 */

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


/*
 * ============================================================
 * MOBILE
 * ============================================================
 */

@media (max-width: 700px) {

    .thread-posts .post.op {
        width: 100%;
        max-width: 100%;

        padding: 6px;
    }

    .thread-posts .post.reply {
        display: table;

        max-width: 98%;
    }

    .thread-posts .post-content .post-image {
        max-width: 200px;

        margin-right: 10px;
        margin-bottom: 6px;
    }

    .thread-posts .post-content .post-image img {
        max-width: 200px;
        max-height: 200px;
    }

    .reply-form {
        width: 100%;
    }

    .reply-form textarea {
        width: 100%;
    }

    .reply-form input[type="text"] {
        width: 100%;
    }

    .report-dialog {
        width: calc(100vw - 20px);
    }
}

@media (max-width: 400px) {

    .thread-posts .post-content .post-image {
        max-width: 150px;

        margin-right: 8px;
    }

    .thread-posts .post-content .post-image img {
        max-width: 150px;
        max-height: 150px;
    }
}

</style>

<div class="thread-page">

<?php if ($reportMessage !== ''): ?>


<div
    class="report-message <?= h($reportMessageType) ?>"
>
    <?= h($reportMessage) ?>
</div>


<?php endif; ?>

<div class="site-banner">


<h1>
    <?= h(SITE_TITLE) ?>
</h1>

<p>
    anonymous imageboard
</p>


</div>

<div class="board-header">


<h2>

    <a
        href="/<?= rawurlencode($board['slug']) ?>/"
    >
        /<?= h($board['slug']) ?>/
    </a>

</h2>

<div class="thread-meta">

    Thread #<?= (int) $thread['id'] ?>

</div>

<div class="thread-controls">

    <button
        type="button"
        id="thread-refresh"
    >
        Refresh
    </button>

    <button
        type="button"
        id="thread-auto-reload"
    >
        Auto Reload: OFF
    </button>

</div>


</div>

<div
    id="thread-posts"
    class="thread-posts"
>

<?php foreach (
    $posts
    as $index => $post
): ?>


<div
    class="post <?= $index === 0 ? 'op' : 'reply' ?>"
    id="p<?= (int) $post['id'] ?>"
    data-post-id="<?= (int) $post['id'] ?>"
>

    <div class="post-header">

        <input
            type="checkbox"
            class="post-delete"
            name="delete_<?= (int) $post['id'] ?>"
            id="delete_<?= (int) $post['id'] ?>"
        >

        <label
            for="delete_<?= (int) $post['id'] ?>"
        >

            <span class="post-name">

                <?= h(
                    !empty($post['name'])
                        ? (string) $post['name']
                        : 'Anonymous'
                ) ?>

                <?php if (
                    $showCountryFlags &&
                    !empty($post['country_code'])
                ): ?>

                    <?php
                    $flagUrl =
                        get_country_flag_url(
                            (string) $post['country_code']
                        );
                    ?>

                    <?php if ($flagUrl !== null): ?>

                        <img
                            class="country-flag"
                            src="<?= h($flagUrl) ?>"
                            alt="<?= h(
                                strtoupper(
                                    (string) $post['country_code']
                                )
                            ) ?>"
                            title="<?= h(
                                strtoupper(
                                    (string) $post['country_code']
                                )
                            ) ?>"
                            loading="lazy"
                        >

                    <?php endif; ?>

                <?php endif; ?>


                <?php if (
                    isset($post['capcode']) &&
                    (string) $post['capcode'] === 'Staff'
                ): ?>

                    <span class="staff-capcode">
                        ## Staff
                    </span>

                <?php endif; ?>

            </span>

        </label>

        <span class="post-date">
            <?= h(
                (string) $post['created_at']
            ) ?>
        </span>

        <a
            class="post_no"
            id="post_no_<?= (int) $post['id'] ?>"
            href="#p<?= (int) $post['id'] ?>"
        >
            No.<?= (int) $post['id'] ?>
        </a>

        <?php if (empty($thread['locked'])): ?>

            <a
                href="#reply"
                class="reply-link"
                data-thread-id="<?= (int) $thread['id'] ?>"
                data-post-id="<?= (int) $post['id'] ?>"
            >
                [Reply]
            </a>

        <?php endif; ?>

        <a
            href="#"
            class="report-link"
            data-post-id="<?= (int) $post['id'] ?>"
            data-thread-id="<?= (int) $thread['id'] ?>"
        >
            [Report]
        </a>

    </div>


    <div class="post-content">

        <?php
        /*
         * Use file_name first.
         *
         * Keep the image column as a fallback so older
         * posts/images continue working.
         */
        $imageName = '';

        if (!empty($post['file_name'])) {

            $imageName =
                (string) $post['file_name'];

        } elseif (!empty($post['image'])) {

            $imageName =
                (string) $post['image'];
        }
        ?>


        <?php if ($imageName !== ''): ?>

            <?php
            /*
             * Get filename, dimensions and file size.
             */
            $fileInfo =
                get_file_info(
                    $imageName
                );
            ?>

            <div class="post-image">

                <!--
                     File information intentionally appears
                     ABOVE the image.
                -->
                <div class="fileinfo">

                    <a
                        href="/<?= h(
                            UPLOAD_URL .
                            '/' .
                            $imageName
                        ) ?>"
                        target="_blank"
                        rel="noopener"
                    >
                        <?= h(
                            $fileInfo['name']
                        ) ?>
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
                                format_file_size(
                                    $fileInfo['size']
                                )
                            ) ?>
                            )
                        </span>

                    <?php else: ?>

                        <span>
                            (
                            <?= h(
                                format_file_size(
                                    $fileInfo['size']
                                )
                            ) ?>
                            )
                        </span>

                    <?php endif; ?>

                </div>


                <a
                    href="/<?= h(
                        UPLOAD_URL .
                        '/' .
                        $imageName
                    ) ?>"
                    target="_blank"
                    rel="noopener"
                >

                    <img
                        src="/<?= h(
                            UPLOAD_URL .
                            '/' .
                            $imageName
                        ) ?>"
                        alt=""
                        loading="lazy"
                    >

                </a>

            </div>

        <?php endif; ?>


        <div class="post-body">

            <?= render_text(
                (string) $post['body']
            ) ?>

        </div>

    </div>

</div>


<?php endforeach; ?>

</div>

<?php if (empty($thread['locked'])): ?>


<div
    id="reply"
    class="reply-form-container"
>

    <form
        action="/post.php"
        method="post"
        enctype="multipart/form-data"
        class="reply-form"
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
            name="thread_id"
            value="<?= (int) $thread['id'] ?>"
        >

        <table class="postForm">

            <tr>

                <th class="postblock"></th>

                <td>

                    <input
                        type="text"
                        id="reply-name"
                        name="name"
                        maxlength="80"
                        placeholder="Name"
                    >

                </td>

            </tr>

            <tr>

                <th class="postblock"></th>

                <td>

                    <textarea
                        id="reply-body"
                        name="body"
                        rows="8"
                        maxlength="10000"
                        required
                        placeholder="Message"
                    ></textarea>

                </td>

            </tr>

            <tr>

                <th class="postblock"></th>

                <td>

                    <input
                        type="file"
                        id="reply-file"
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
                        id="reply-password"
                        name="password"
                        maxlength="100"
                        placeholder="Password"
                    >

                </td>

            </tr>

            <tr>

                <th class="postblock"></th>

                <td>

                    <button type="submit">
                        Reply
                    </button>

                    <button type="reset">
                        Clear
                    </button>

                </td>

            </tr>

        </table>

    </form>

</div>


<?php else: ?>


<div class="thread-locked">

    This thread is locked.

</div>


<?php endif; ?>

</div>

<!-- =========================================================
     REPORT DIALOG
     ========================================================= -->

<div
    id="report-overlay"
    class="report-overlay"
    style="display:none;"
></div>

<div
    id="report-dialog"
    class="report-dialog"
    style="display:none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="report-dialog-title"
>


<div
    class="report-dialog-header"
    id="report-dialog-title"
>

    <button
        type="button"
        class="report-close"
        id="report-close"
        aria-label="Close"
    >
        ×
    </button>

    Report Post

</div>

<div class="report-dialog-body">

    <form
        method="post"
        action=""
        id="report-form"
    >

        <input
            type="hidden"
            name="action"
            value="report"
        >

        <input
            type="hidden"
            name="report_csrf"
            value="<?= h($reportCsrf) ?>"
        >

        <input
            type="hidden"
            name="board"
            value="<?= h($board['slug']) ?>"
        >

        <input
            type="hidden"
            name="thread_id"
            value="<?= (int) $thread['id'] ?>"
        >

        <input
            type="hidden"
            name="post_id"
            id="report-post-id"
            value=""
        >

        <label
            for="report-reason"
        >
            Reason
        </label>

        <select
            name="reason"
            id="report-reason"
            required
        >

            <option value="">
                Select a reason...
            </option>

            <option value="spam">
                Spam
            </option>

            <option value="illegal">
                Illegal content
            </option>

            <option value="harassment">
                Harassment
            </option>

            <option value="sexual_content">
                Sexual content
            </option>

            <option value="malware">
                Malware / malicious content
            </option>

            <option value="personal_information">
                Personal information
            </option>

            <option value="other">
                Other
            </option>

        </select>


        <label
            for="report-details"
        >
            Additional details
        </label>

        <textarea
            name="details"
            id="report-details"
            maxlength="2000"
            placeholder="Optional details..."
        ></textarea>


        <div class="report-dialog-buttons">

            <button
                type="button"
                id="report-cancel"
            >
                Cancel
            </button>

            <button
                type="submit"
            >
                Submit Report
            </button>

        </div>

    </form>

</div>


</div>

<script>

(function () {

    'use strict';


    /*
     * ========================================================
     * THREAD CONTROLS
     * ========================================================
     */

    var AUTO_RELOAD_INTERVAL =
        10000;

    var autoReloadTimer =
        null;

    var autoReloadEnabled =
        false;

    var replyBody =
        document.getElementById(
            'reply-body'
        );

    var refreshButton =
        document.getElementById(
            'thread-refresh'
        );

    var autoReloadButton =
        document.getElementById(
            'thread-auto-reload'
        );


    function refreshThread()
    {
        var hash =
            window.location.hash;

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

        if (hash) {
            url += hash;
        }

        window.location.replace(url);
    }


    function stopAutoReload()
    {
        if (autoReloadTimer) {

            clearTimeout(
                autoReloadTimer
            );

            autoReloadTimer =
                null;
        }
    }


    function scheduleAutoReload()
    {
        stopAutoReload();

        if (!autoReloadEnabled) {
            return;
        }

        autoReloadTimer =
            setTimeout(
                function () {

                    if (
                        replyBody &&
                        (
                            document.activeElement ===
                                replyBody ||
                            replyBody.value
                                .trim() !== ''
                        )
                    ) {

                        scheduleAutoReload();

                        return;
                    }

                    refreshThread();

                },
                AUTO_RELOAD_INTERVAL
            );
    }


    function updateAutoReloadButton()
    {
        if (!autoReloadButton) {
            return;
        }

        if (autoReloadEnabled) {

            autoReloadButton.textContent =
                'Auto Reload: ON';

            autoReloadButton.classList.add(
                'auto-reload-active'
            );

        } else {

            autoReloadButton.textContent =
                'Auto Reload: OFF';

            autoReloadButton.classList.remove(
                'auto-reload-active'
            );
        }
    }


    function toggleAutoReload()
    {
        autoReloadEnabled =
            !autoReloadEnabled;

        updateAutoReloadButton();

        if (autoReloadEnabled) {
            scheduleAutoReload();
        } else {
            stopAutoReload();
        }
    }


    /*
     * ========================================================
     * HASH HIGHLIGHT
     * ========================================================
     */

    function highlightPostFromHash()
    {
        var hash =
            window.location.hash;

        if (
            !hash ||
            !/^#p\d+$/.test(hash)
        ) {
            return;
        }

        var target =
            document.getElementById(
                hash.substring(1)
            );

        if (!target) {
            return;
        }

        document
            .querySelectorAll(
                '.post-highlight'
            )
            .forEach(
                function (post) {

                    post.classList.remove(
                        'post-highlight'
                    );

                }
            );

        target.classList.add(
            'post-highlight'
        );

        setTimeout(
            function () {

                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

            },
            50
        );
    }


    /*
     * ========================================================
     * REPORT DIALOG
     * ========================================================
     */

    var reportDialog =
        document.getElementById(
            'report-dialog'
        );

    var reportOverlay =
        document.getElementById(
            'report-overlay'
        );

    var reportClose =
        document.getElementById(
            'report-close'
        );

    var reportCancel =
        document.getElementById(
            'report-cancel'
        );

    var reportPostId =
        document.getElementById(
            'report-post-id'
        );

    var reportReason =
        document.getElementById(
            'report-reason'
        );

    var reportDetails =
        document.getElementById(
            'report-details'
        );


    function openReportDialog(postId)
    {
        if (
            !reportDialog ||
            !reportOverlay ||
            !reportPostId
        ) {
            return;
        }

        reportPostId.value =
            postId;

        if (reportReason) {
            reportReason.value =
                '';
        }

        if (reportDetails) {
            reportDetails.value =
                '';
        }

        reportOverlay.style.display =
            'block';

        reportDialog.style.display =
            'block';

        if (reportReason) {
            reportReason.focus();
        }
    }


    function closeReportDialog()
    {
        if (reportDialog) {
            reportDialog.style.display =
                'none';
        }

        if (reportOverlay) {
            reportOverlay.style.display =
                'none';
        }
    }


    /*
     * ========================================================
     * REPORT BUTTONS
     * ========================================================
     */

    document.addEventListener(
        'click',
        function (event) {

            var reportLink =
                event.target.closest(
                    '.report-link'
                );

            if (!reportLink) {
                return;
            }

            event.preventDefault();

            var postId =
                reportLink.getAttribute(
                    'data-post-id'
                );

            if (!postId) {
                return;
            }

            openReportDialog(
                postId
            );
        }
    );


    if (reportClose) {

        reportClose.addEventListener(
            'click',
            function () {
                closeReportDialog();
            }
        );

    }


    if (reportCancel) {

        reportCancel.addEventListener(
            'click',
            function () {
                closeReportDialog();
            }
        );

    }


    if (reportOverlay) {

        reportOverlay.addEventListener(
            'click',
            function () {
                closeReportDialog();
            }
        );

    }


    /*
     * Escape closes report dialog.
     */
    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key !== 'Escape') {
                return;
            }

            if (
                reportDialog &&
                reportDialog.style.display !== 'none'
            ) {
                closeReportDialog();

                return;
            }

            document
                .querySelectorAll(
                    '.post-image img.expanded'
                )
                .forEach(
                    function (image) {

                        image.classList.remove(
                            'expanded'
                        );

                    }
                );
        }
    );


    /*
     * ========================================================
     * REFRESH
     * ========================================================
     */

    if (refreshButton) {

        refreshButton.addEventListener(
            'click',
            function () {
                refreshThread();
            }
        );

    }


    if (autoReloadButton) {

        autoReloadButton.addEventListener(
            'click',
            function () {
                toggleAutoReload();
            }
        );

    }


    /*
     * ========================================================
     * REPLY LINKS
     * ========================================================
     */

    document.addEventListener(
        'click',
        function (event) {

            var replyLink =
                event.target.closest(
                    '.reply-link'
                );

            if (!replyLink) {
                return;
            }

            event.preventDefault();

            var postId =
                replyLink.getAttribute(
                    'data-post-id'
                );

            if (
                !replyBody ||
                !postId
            ) {
                return;
            }

            var quote =
                '>>' +
                postId;

            if (
                replyBody.value
                    .trim() === ''
            ) {

                replyBody.value =
                    quote +
                    '\n\n';

            } else {

                replyBody.value =
                    quote +
                    '\n' +
                    replyBody.value;
            }

            var reply =
                document.getElementById(
                    'reply'
                );

            if (reply) {

                reply.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

            }

            replyBody.focus();

            replyBody.selectionStart =
                replyBody.value.length;

            replyBody.selectionEnd =
                replyBody.value.length;

        }
    );


    /*
     * ========================================================
     * POST NUMBER LINKS
     * ========================================================
     */

    document.addEventListener(
        'click',
        function (event) {

            var postNo =
                event.target.closest(
                    'a.post_no'
                );

            if (!postNo) {
                return;
            }

            var href =
                postNo.getAttribute(
                    'href'
                );

            if (
                href &&
                /^#p\d+$/.test(href)
            ) {

                event.preventDefault();

                history.pushState(
                    null,
                    '',
                    href
                );

                highlightPostFromHash();
            }
        }
    );


    /*
     * ========================================================
     * IMAGE EXPANSION
     * ========================================================
     */

    document.addEventListener(
        'click',
        function (event) {

            var image =
                event.target.closest(
                    '.post-image img'
                );

            if (!image) {
                return;
            }

            event.preventDefault();

            image.classList.toggle(
                'expanded'
            );
        }
    );


    /*
     * ========================================================
     * HASH EVENTS
     * ========================================================
     */

    window.addEventListener(
        'hashchange',
        function () {
            highlightPostFromHash();
        }
    );


    window.addEventListener(
        'pageshow',
        function (event) {

            if (event.persisted) {

                window.location.reload();

                return;
            }

            highlightPostFromHash();

        }
    );


    document.addEventListener(
        'visibilitychange',
        function () {

            if (
                document.visibilityState ===
                'visible'
            ) {

                highlightPostFromHash();

            }

        }
    );


    /*
     * ========================================================
     * INITIAL STATE
     * ========================================================
     */

    updateAutoReloadButton();

    highlightPostFromHash();

})();

</script>

<?php page_footer(); ?>