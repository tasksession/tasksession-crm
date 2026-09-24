<?php

function privateNotesBuildPublicUrlFromRelativePath($baseUrl, $relativePath)
{
    return rtrim((string)$baseUrl, '/') . '/' . ltrim((string)$relativePath, '/');
}

/**
 * Image URL via share/private_media.php (avoids direct /uploads/… 403 on strict hosts / bot rules).
 *
 * @param string $baseUrl Site $url
 * @param string $relativePath e.g. uploads/private-notes/1/thumbs/x.jpg
 * @param string $publicToken optional public share token
 */
function privateNotesPrivateMediaUrl($baseUrl, $relativePath, $publicToken = '')
{
    $rel = str_replace('\\', '/', ltrim(trim((string)$relativePath), '/'));
    if ($rel === '') {
        return '';
    }
    $q = ['src' => $rel];
    if (trim((string)$publicToken) !== '') {
        $q['token'] = trim((string)$publicToken);
    }
    return rtrim((string)$baseUrl, '/') . '/share/private_media.php?' . http_build_query($q);
}

function privateNotesThumbRelativePath($relativePath)
{
    $normalized = str_replace('\\', '/', (string)$relativePath);
    $dir = trim((string)dirname($normalized), '/.');
    $name = (string)basename($normalized);
    if ($dir === '' || $dir === '.') {
        return 'thumbs/' . $name;
    }
    return $dir . '/thumbs/' . $name;
}

function privateNotesEnsureThumbForRelativePath($relativePath, $size = 320)
{
    $size = max(80, min(640, (int)$size));
    $relativePath = str_replace('\\', '/', trim((string)$relativePath));
    if ($relativePath === '') {
        return '';
    }

    $srcAbs = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
    if (!is_file($srcAbs)) {
        return '';
    }

    $thumbRel = privateNotesThumbRelativePath($relativePath);
    $thumbAbs = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($thumbRel, '/'));
    $thumbDir = dirname($thumbAbs);
    if (!is_dir($thumbDir)) {
        @mkdir($thumbDir, 0755, true);
    }

    $srcMtime = @filemtime($srcAbs) ?: 0;
    $thumbMtime = @filemtime($thumbAbs) ?: 0;
    if (is_file($thumbAbs) && $thumbMtime >= $srcMtime) {
        return $thumbRel;
    }

    if (!extension_loaded('gd')) {
        return '';
    }

    $imageInfo = @getimagesize($srcAbs);
    if (!$imageInfo || empty($imageInfo[2])) {
        return '';
    }

    $type = (int)$imageInfo[2];
    $src = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = @imagecreatefromjpeg($srcAbs);
            break;
        case IMAGETYPE_PNG:
            $src = @imagecreatefrompng($srcAbs);
            break;
        case IMAGETYPE_GIF:
            $src = @imagecreatefromgif($srcAbs);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($srcAbs);
            }
            break;
        default:
            $src = null;
    }
    if (!$src) {
        return '';
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    if ($srcW <= 0 || $srcH <= 0) {
        imagedestroy($src);
        return '';
    }

    $cropSide = min($srcW, $srcH);
    $srcX = (int)floor(($srcW - $cropSide) / 2);
    $srcY = (int)floor(($srcH - $cropSide) / 2);

    $dst = imagecreatetruecolor($size, $size);
    if (!$dst) {
        imagedestroy($src);
        return '';
    }

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $cropSide, $cropSide);

    $saved = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $saved = @imagejpeg($dst, $thumbAbs, 84);
            break;
        case IMAGETYPE_PNG:
            $saved = @imagepng($dst, $thumbAbs, 6);
            break;
        case IMAGETYPE_GIF:
            $saved = @imagegif($dst, $thumbAbs);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $saved = @imagewebp($dst, $thumbAbs, 82);
            }
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);

    if (!$saved) {
        return '';
    }
    return $thumbRel;
}
