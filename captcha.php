<?php
declare(strict_types=1);

session_start();

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('PHP GD extension is required.');
}

$width = 180;
$height = 55;
$length = 5;

$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

$code = '';

for ($i = 0; $i < $length; $i++) {
    $code .= $characters[random_int(0, strlen($characters) - 1)];
}

$_SESSION['captcha_code'] = $code;

$image = imagecreatetruecolor($width, $height);

$background = imagecolorallocate($image, 245, 245, 245);
$gray = imagecolorallocate($image, 130, 130, 130);
$lightGray = imagecolorallocate($image, 205, 205, 205);

imagefilledrectangle(
    $image,
    0,
    0,
    $width,
    $height,
    $background
);

/*
 * Background noise.
 */
for ($i = 0; $i < 550; $i++) {
    $x = random_int(0, $width - 1);
    $y = random_int(0, $height - 1);

    $noise = imagecolorallocate(
        $image,
        random_int(180, 230),
        random_int(180, 230),
        random_int(180, 230)
    );

    imagesetpixel($image, $x, $y, $noise);
}

/*
 * Interference lines.
 */
for ($i = 0; $i < 8; $i++) {
    imageline(
        $image,
        random_int(0, $width - 1),
        random_int(0, $height - 1),
        random_int(0, $width - 1),
        random_int(0, $height - 1),
        $lightGray
    );
}

/*
 * Locate an available TrueType font.
 */
$fontCandidates = [
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
    '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
];

$font = null;

foreach ($fontCandidates as $candidate) {
    if (is_file($candidate)) {
        $font = $candidate;
        break;
    }
}

/*
 * Draw CAPTCHA text.
 */
if (
    $font !== null &&
    function_exists('imagettftext')
) {
    $fontSize = 27;
    $characterWidth = 32;

    for ($i = 0; $i < $length; $i++) {
        $character = $code[$i];

        $x = 7 + ($i * $characterWidth);
        $y = random_int(35, 44);
        $angle = random_int(-15, 15);

        $textColor = imagecolorallocate(
            $image,
            random_int(15, 55),
            random_int(15, 55),
            random_int(15, 55)
        );

        imagettftext(
            $image,
            $fontSize,
            $angle,
            $x,
            $y,
            $textColor,
            $font,
            $character
        );
    }
} else {
    /*
     * GD built-in font fallback.
     */
    for ($i = 0; $i < $length; $i++) {
        imagestring(
            $image,
            5,
            14 + ($i * 32),
            random_int(15, 25),
            $code[$i],
            $gray
        );
    }
}

/*
 * Foreground noise.
 */
for ($i = 0; $i < 120; $i++) {
    $x = random_int(0, $width - 1);
    $y = random_int(0, $height - 1);

    imagesetpixel(
        $image,
        $x,
        $y,
        $gray
    );
}

/*
 * Border.
 */
imagerectangle(
    $image,
    0,
    0,
    $width - 1,
    $height - 1,
    $gray
);

/*
 * Disable browser caching.
 */
header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Cache-Control: post-check=0, pre-check=0',
    false
);

header('Pragma: no-cache');
header('Expires: 0');

header('Content-Type: image/png');

imagepng($image);

imagedestroy($image);
