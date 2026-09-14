<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

verify_csrf();

$boardSlug =
    trim(
        (string) (
            $_POST['board'] ?? ''
        )
    );

$threadId =
    (int) (
        $_POST['thread_id'] ?? 0
    );

$name =
    trim(
        (string) (
            $_POST['name'] ?? ''
        )
    );

$subject =
    trim(
        (string) (
            $_POST['subject'] ?? ''
        )
    );

$body =
    trim(
        (string) (
            $_POST['body'] ?? ''
        )
    );


/*
 * ============================================================
 * ADMIN CAPCODE
 * ============================================================
 *
 * Administrators automatically post as:
 *
 *     pomfIB ## Staff
 *
 * The submitted name is ignored for admin posts so regular
 * users cannot obtain the staff capcode simply by entering
 * the same name.
 * ============================================================
 */

if (is_admin()) {

    $name =
        'pomfIB ## Staff';

} else {

    if ($name === '') {
        $name = 'Anonymous';
    }
}


if (
    $name === '' ||
    mb_strlen($name) > 80
) {
    exit('Invalid name.');
}


if (
    mb_strlen($body) < 1 ||
    mb_strlen($body) > 10000
) {
    exit(
        'Post must be 1-10000 characters.'
    );
}


if (
    mb_strlen($subject) > 120
) {
    exit('Subject is too long.');
}


/*
 * ============================================================
 * BOARD
 * ============================================================
 */

$board =
    get_board($boardSlug);

if (!$board) {
    exit('Board does not exist.');
}


/*
 * ============================================================
 * COUNTRY CODE
 * ============================================================
 *
 * Country flags are ONLY enabled on /int/.
 *
 * The country lookup happens before the database transaction
 * so an external API request never holds a database lock.
 *
 * If the lookup fails for any reason, the post continues
 * normally and country_code remains NULL.
 * ============================================================
 */

$countryCode = null;

if (
    strtolower(
        (string) (
            $board['slug'] ?? ''
        )
    ) === 'int'
) {

    $countryCode =
        get_country_code_for_ip(
            (string) (
                $_SERVER['REMOTE_ADDR'] ?? ''
            )
        );
}


$pdo = db();


try {

    $pdo->beginTransaction();


    /*
     * ========================================================
     * EXISTING THREAD
     * ========================================================
     */

    if ($threadId > 0) {

        $stmt =
            $pdo->prepare(
                'SELECT *
                 FROM threads
                 WHERE id = ?
                   AND board_id = ?
                   AND deleted = 0
                 FOR UPDATE'
            );

        $stmt->execute([
            $threadId,
            $board['id']
        ]);

        $thread =
            $stmt->fetch();

        if (!$thread) {

            throw new RuntimeException(
                'Thread not found.'
            );
        }


        if (
            (int) $thread['locked'] === 1
        ) {

            throw new RuntimeException(
                'Thread is locked.'
            );
        }


    /*
     * ========================================================
     * NEW THREAD
     * ========================================================
     */

    } else {

        if ($subject === '') {
            $subject = 'No subject';
        }


        $stmt =
            $pdo->prepare(
                'INSERT INTO threads
                    (board_id, subject)
                 VALUES
                    (?, ?)'
            );

        $stmt->execute([
            $board['id'],
            $subject
        ]);


        $threadId =
            (int) $pdo->lastInsertId();
    }


    /*
     * ========================================================
     * FILE UPLOAD
     * ========================================================
     */

    $file =
        post_file(
            $_FILES['file']
            ?? [
                'error' =>
                    UPLOAD_ERR_NO_FILE
            ]
        );


    /*
     * ========================================================
     * INSERT POST
     * ========================================================
     *
     * country_code is stored only for /int/.
     * All other boards receive NULL.
     * ========================================================
     */

    $stmt =
        $pdo->prepare(
            'INSERT INTO posts
            (
                thread_id,
                board_id,
                name,
                body,
                file_name,
                file_original,
                file_mime,
                file_size,
                ip_hash,
                country_code
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )'
        );


    $stmt->execute([
        $threadId,
        $board['id'],
        $name,
        $body,
        $file['name'] ?? null,
        $file['original'] ?? null,
        $file['mime'] ?? null,
        $file['size'] ?? null,
        ip_hash(),
        $countryCode
    ]);


    /*
     * ========================================================
     * BUMP THREAD
     * ========================================================
     */

    $pdo->prepare(
        'UPDATE threads
         SET bumped_at = NOW()
         WHERE id = ?'
    )->execute([
        $threadId
    ]);


    /*
     * ========================================================
     * COMMIT
     * ========================================================
     */

    $pdo->commit();


    /*
     * ========================================================
     * REDIRECT
     * ========================================================
     */

    redirect(
        '/thread.php?b=' .
        rawurlencode($boardSlug) .
        '&id=' .
        $threadId
    );


} catch (Throwable $e) {

    if (
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }


    http_response_code(400);


    echo
        '<!doctype html>' .
        '<html>' .
        '<body>' .
        '<h1>Error</h1>' .
        '<p>' .
        h($e->getMessage()) .
        '</p>' .
        '<p>' .
        '<a href="javascript:history.back()">' .
        'Go back' .
        '</a>' .
        '</p>' .
        '</body>' .
        '</html>';
}
