<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const POMFIB_NAME = 'pomfIB';

const DB_HOST = '127.0.0.1';
const DB_NAME = 'pomfib';
const DB_USER = 'pomfib';
const DB_PASS = 'pomfib_db_password';

const SITE_TITLE = 'pomfIB';

const MAX_FILE_SIZE = 8 * 1024 * 1024;
const THREAD_BUMP_LIMIT = 300;
const POSTS_PER_PAGE = 20;
const THREADS_PER_PAGE = 15;

const UPLOAD_DIR = __DIR__ . '/uploads';
const UPLOAD_URL = 'uploads';

date_default_timezone_set('UTC');

if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn =
        'mysql:host=' .
        DB_HOST .
        ';dbname=' .
        DB_NAME .
        ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false,
        ]
    );

    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (
        !hash_equals(
            $_SESSION['csrf'] ?? '',
            $_POST['csrf'] ?? ''
        )
    ) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function ip_hash(): string
{
    return hash(
        'sha256',
        ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
        'pomfIB-salt-change-me'
    );
}

/*
 * ============================================================
 * COUNTRY LOOKUP
 * ============================================================
 *
 * Used by /int/ to determine the poster's country.
 *
 * The visitor IP is NOT stored by this function.
 * Only the resulting two-letter country code is stored
 * in posts.country_code by post.php.
 *
 * Example results:
 *
 * US
 * CA
 * JP
 * DE
 * GB
 * BR
 *
 * If the lookup fails, NULL is returned and the post
 * continues normally without a flag.
 */
function get_country_code_for_ip(string $ip): ?string
{
    if (
        !filter_var(
            $ip,
            FILTER_VALIDATE_IP
        )
    ) {
        return null;
    }

    $url =
        'https://ipapi.co/' .
        rawurlencode($ip) .
        '/country/';

    $context = stream_context_create([
        'http' => [
            'method' =>
                'GET',

            'timeout' =>
                3,

            'ignore_errors' =>
                true,

            'header' =>
                "User-Agent: pomfIB/1.0\r\n" .
                "Accept: text/plain\r\n",
        ],
    ]);

    $result = @file_get_contents(
        $url,
        false,
        $context
    );

    if ($result === false) {
        return null;
    }

    $country =
        strtoupper(
            trim($result)
        );

    if (
        !preg_match(
            '/^[A-Z]{2}$/',
            $country
        )
    ) {
        return null;
    }

    return $country;
}

/*
 * ============================================================
 * COUNTRY FLAG LOOKUP
 * ============================================================
 *
 * Looks inside:
 *
 * /static/flags/
 *
 * for the KohlNumbra flag matching the country code.
 *
 * Supports the extensions used by the downloaded flags.
 *
 * Examples:
 *
 * US -> /static/flags/us.svg
 * BR -> /static/flags/br.svg
 * UA -> /static/flags/ua.png
 */
function get_country_flag_url(
    ?string $countryCode
): ?string {
    if (
        $countryCode === null ||
        !preg_match(
            '/^[A-Za-z]{2}$/',
            $countryCode
        )
    ) {
        return null;
    }

    $code =
        strtolower(
            $countryCode
        );

    $extensions = [
        'svg',
        'png',
        'gif',
        'webp',
        'jpg',
        'jpeg',
    ];

    foreach ($extensions as $extension) {

        $filename =
            $code .
            '.' .
            $extension;

        $path =
            __DIR__ .
            '/static/flags/' .
            $filename;

        if (is_file($path)) {

            return
                '/static/flags/' .
                $filename;
        }
    }

    return null;
}

/*
 * ============================================================
 * FILE INFORMATION
 * ============================================================
 *
 * Shared file metadata helper used by board.php and thread.php.
 */
function get_file_info(?string $fileName): ?array
{
    if (
        $fileName === null ||
        trim($fileName) === ''
    ) {
        return null;
    }

    $fileName = basename($fileName);

    $path =
        __DIR__ .
        '/uploads/' .
        $fileName;

    if (!is_file($path)) {
        return null;
    }

    $size =
        filesize($path);

    if ($size === false) {
        $size = null;
    }

    $mime =
        null;

    if (
        function_exists('mime_content_type')
    ) {
        $detectedMime =
            @mime_content_type($path);

        if (
            is_string($detectedMime) &&
            $detectedMime !== ''
        ) {
            $mime = $detectedMime;
        }
    }

    return [
        'name' => $fileName,
        'path' => $path,
        'size' => $size,
        'mime' => $mime,
    ];
}


/*
 * ============================================================
 * POST MARKUP RENDERER

 * ============================================================
 *
 * 8chan / LynxChan-style markup:
 *
 * > greentext
 *
 * '''bold'''
 *
 * ''italic''
 *
 * __underline__
 *
 * ~~strikethrough~~
 *
 * ==red text==
 *
 * [spoiler]spoiler[/spoiler]
 *
 * [doom]doom[/doom]
 *
 * [moe]moe[/moe]
 *
 * [code]code[/code]
 *
 * [aa]ASCII art[/aa]
 *
 * (((echo text)))
 *
 * User input is HTML escaped BEFORE markup is processed.
 * This prevents arbitrary HTML injection.
 */
function render_text(string $text): string
{
    $text = h($text);

    $protected = [];

    $protect = function (string $html) use (&$protected): string {
        $key =
            "\x01POMFIB_PROTECTED_" .
            count($protected) .
            "\x02";

        $protected[$key] = $html;

        return $key;
    };

    $text = preg_replace_callback(
        '/\[code\](.*?)\[\/code\]/is',
        function ($m) use ($protect) {

            return $protect(
                '<pre class="markup-code">' .
                $m[1] .
                '</pre>'
            );
        },
        $text
    );

    $text = preg_replace_callback(
        '/\[aa\](.*?)\[\/aa\]/is',
        function ($m) use ($protect) {

            return $protect(
                '<pre class="markup-aa">' .
                $m[1] .
                '</pre>'
            );
        },
        $text
    );

    $lines = preg_split(
        "/\r\n|\r|\n/",
        $text
    );

    $out = [];

    foreach ($lines as $line) {

        if (
            preg_match(
                '/^(&gt;)(.*)$/',
                $line,
                $m
            )
        ) {
            $line =
                '<span class="markup-greentext">' .
                $m[1] .
                $m[2] .
                '</span>';
        }

        $line = preg_replace(
            '/==(.+?)==/',
            '<span class="markup-red">$1</span>',
            $line
        );

        $line = preg_replace(
            "/'''(.*?)'''/",
            '<strong>$1</strong>',
            $line
        );

        $line = preg_replace(
            "/''(.*?)''/",
            '<em>$1</em>',
            $line
        );

        $line = preg_replace(
            '/__(.*?)__/',
            '<u>$1</u>',
            $line
        );

        $line = preg_replace(
            '/~~(.*?)~~/',
            '<del>$1</del>',
            $line
        );

        $line = preg_replace(
            '/\[spoiler\](.*?)\[\/spoiler\]/is',
            '<span class="markup-spoiler">$1</span>',
            $line
        );

        $line = preg_replace(
            '/\[doom\](.*?)\[\/doom\]/is',
            '<span class="markup-doom">$1</span>',
            $line
        );

        $line = preg_replace(
            '/\[moe\](.*?)\[\/moe\]/is',
            '<span class="markup-moe">$1</span>',
            $line
        );

        $line = preg_replace(
            '/\(\(\((.*?)\)\)\)/',
            '<span class="markup-echo">((($1)))</span>',
            $line
        );

        $line = strtr(
            $line,
            $protected
        );

        $out[] = $line;
    }

    return implode(
        "<br>\n",
        $out
    );
}

function board_exists(string $board): bool
{
    $stmt = db()->prepare(
        'SELECT 1
         FROM boards
         WHERE slug = ?
         LIMIT 1'
    );

    $stmt->execute([
        $board
    ]);

    return (bool) $stmt->fetchColumn();
}

function get_board(string $board): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM boards
         WHERE slug = ?
         LIMIT 1'
    );

    $stmt->execute([
        $board
    ]);

    return $stmt->fetch() ?: null;
}

function post_file(array $file): ?array
{
    if (
        ($file['error'] ?? UPLOAD_ERR_NO_FILE)
        === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if (
        ($file['error'] ?? 0)
        !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Upload failed.'
        );
    }

    if (
        ($file['size'] ?? 0)
        > MAX_FILE_SIZE
    ) {
        throw new RuntimeException(
            'File is too large. Maximum is 8 MB.'
        );
    }

    $tmp = $file['tmp_name'];

    $mime =
        (new finfo(FILEINFO_MIME_TYPE))
        ->file($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException(
            'Only JPG, PNG, GIF and WebP images are allowed.'
        );
    }

    $name =
        bin2hex(random_bytes(12)) .
        '.' .
        $allowed[$mime];

    $destination =
        UPLOAD_DIR .
        '/' .
        $name;

    if (
        !move_uploaded_file(
            $tmp,
            $destination
        )
    ) {
        throw new RuntimeException(
            'Could not save uploaded file.'
        );
    }

    return [
        'name' =>
            $name,

        'mime' =>
            $mime,

        'size' =>
            (int) $file['size'],

        'original' =>
            basename(
                (string) $file['name']
            ),
    ];
}

function is_admin(): bool
{
    return !empty(
        $_SESSION['admin_id']
    );
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('/admin/login.php');
    }
}

function page_header(
    string $title,
    ?array $board = null
): void {
    ?>
    <!doctype html>

    <html lang="en">

    <head>

        <meta charset="utf-8">

        <meta
            name="viewport"
            content="width=device-width,initial-scale=1"
        >

        <title>
            <?= h($title) ?> - <?= h(SITE_TITLE) ?>
        </title>

        <link
            rel="stylesheet"
            href="/style.css"
        >

        <script
            src="/js/pomfib.js"
            defer
        ></script>

    </head>

    <body
        class="<?= $board ? 'board-page' : 'site-page' ?>"
    >

    <div class="grill-wrapper">

    <div class="container">

    <nav>
    <ul>

        <li>
            <a href="/">
                home
            </a>
        </li>

        <li>
            <a href="/catalog.php">
                catalog
            </a>
        </li>

        <?php if ($board): ?>

            <li>
                <a
                    href="/<?= rawurlencode((string) $board['slug']) ?>/"
                >
                    /<?= h($board['slug']) ?>/
                </a>
            </li>

        <?php endif; ?>

        <li>
            <a href="/rules.php">
                rules
            </a>
        </li>

        <li>
            <a href="/admin/">
                admin
            </a>
        </li>

    </ul>
    </nav>

    <?php
}

function page_footer(): void
{
    ?>

    <nav>

        <ul>

            <li>
                <?= h(SITE_TITLE) ?>
                <?= date('Y') ?>
            </li>

            <li>
                <a href="/admin/">
                    staff
                </a>
            </li>

        </ul>

    </nav>

    </div>

    </div>

<footer class="site-footer">
    <a href="https://github.com/pomf-dev/pomfsuba" target="_blank" rel="noopener noreferrer">
        pomfsuba on GitHub
    </a>
</footer>
    </body>

    </html>

    <?php
}

