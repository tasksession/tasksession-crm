<?php
require_once(__DIR__ . '/../includes/lib-initialize.php');
require_once(__DIR__ . '/../includes/private_notes/permissions.php');

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow, noarchive', true);

$rawQuery = (string)($_SERVER['QUERY_STRING'] ?? '');
$rawQuery = trim($rawQuery);
$token = '';
if ($rawQuery !== '') {
    if (strpos($rawQuery, '=') === false && strpos($rawQuery, '&') === false) {
        $token = urldecode($rawQuery);
    } else {
        $keys = array_keys($_GET);
        if (!empty($keys)) {
            $token = (string)$keys[0];
        }
        if (isset($_GET['token']) && trim((string)$_GET['token']) !== '') {
            $token = trim((string)$_GET['token']);
        }
    }
}

$noteRow = privateNotesGetPublicAccessByToken($database, $token);
$noteTitle = 'Shared note';
$noteContent = '';
$canEdit = false;
$noteId = 0;
$updatedAt = '';
$notFound = false;
$logoSrc = rtrim((string)$url, '/') . '/assets/images/dark-logo.png';

function privateShareBuildProtectedMediaUrl($baseUrl, $token, $relativePath) {
    $baseUrl = rtrim((string)$baseUrl, '/');
    $token = trim((string)$token);
    $relativePath = ltrim(str_replace('\\', '/', (string)$relativePath), '/');
    if ($baseUrl === '' || $token === '' || $relativePath === '') {
        return '';
    }
    return $baseUrl . '/share/private_media.php?token=' . rawurlencode($token) . '&src=' . rawurlencode($relativePath);
}

function privateShareExtractPrivateMediaRelativePath($src) {
    $src = trim((string)$src);
    if ($src === '') {
        return '';
    }
    $src = html_entity_decode($src, ENT_QUOTES, 'UTF-8');
    $path = $src;
    $parsed = parse_url($src);
    if ($parsed && isset($parsed['path'])) {
        $path = (string)$parsed['path'];
        if (strpos($path, '/share/private_media.php') !== false && !empty($parsed['query'])) {
            parse_str((string)$parsed['query'], $q);
            if (!empty($q['src'])) {
                $candidate = ltrim(str_replace('\\', '/', (string)$q['src']), '/');
                if (strpos($candidate, 'uploads/private-notes/') === 0) {
                    return $candidate;
                }
            }
        }
    }
    $path = str_replace('\\', '/', $path);
    $marker = '/uploads/private-notes/';
    $pos = strpos($path, $marker);
    if ($pos !== false) {
        return ltrim(substr($path, $pos + 1), '/');
    }
    if (strpos($path, 'uploads/private-notes/') === 0) {
        return $path;
    }
    return '';
}

function privateShareRewriteContentImageSources($html, $token, $baseUrl) {
    $html = (string)$html;
    if ($html === '') return $html;
    return preg_replace_callback('/(<img\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']+)(\2)/i', function ($m) use ($token, $baseUrl) {
        $src = isset($m[3]) ? (string)$m[3] : '';
        $relative = privateShareExtractPrivateMediaRelativePath($src);
        if ($relative === '') return $m[0];
        $protectedUrl = privateShareBuildProtectedMediaUrl($baseUrl, $token, $relative);
        if ($protectedUrl === '') return $m[0];
        return $m[1] . $m[2] . htmlspecialchars($protectedUrl, ENT_QUOTES, 'UTF-8') . $m[4];
    }, $html);
}

if (!$noteRow) {
    $notFound = true;
} else {
    $noteId = (int)$noteRow['id'];
    $noteTitle = (string)($noteRow['title'] ?? 'Shared note');
    $updatedAt = (string)($noteRow['updated_at'] ?? '');
    $canEdit = ((string)($noteRow['public_share_permission'] ?? 'view') === 'edit');
    if (!empty($noteRow['content'])) {
        $noteContent = (string)decryptString((string)$noteRow['content']);
        $noteContent = privateShareRewriteContentImageSources($noteContent, $token, (string)$url);
    }
}

try {
    $settingsObj = settings::findById(1);
    $mobileLogo = $settingsObj ? ($settingsObj->mobile_logo ?? null) : null;
    $defaultLogo = $settingsObj ? ($settingsObj->logo ?? null) : null;
    if ($mobileLogo && file_exists(__DIR__ . '/../uploads/system-uploads/' . $mobileLogo)) {
        $logoSrc = getSystemImageUrl($mobileLogo);
    } elseif ($defaultLogo && file_exists(__DIR__ . '/../uploads/system-uploads/' . $defaultLogo)) {
        $logoSrc = getSystemImageUrl($defaultLogo);
    }
} catch (Exception $e) {
    /* keep default logo */
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title><?php echo htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    // Favicon via secure handler (direct /uploads/ is blocked by .htaccess)
    $default_favicon_path = (string) $url . 'assets/images/favicon.png';
    $custom_favicon_file = dirname(__DIR__) . '/' . (string) $img_path . (string) $favicon_image;
    if (!empty($favicon_image_check) && file_exists($custom_favicon_file)) {
        $favicon_src = getSystemImageUrl((string) $favicon_image) . '&v=' . time() . '&cb=' . rand(1000, 9999);
    } else {
        $favicon_src = $default_favicon_path . '?v=' . time() . '&cb=' . rand(1000, 9999);
    }
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_src, ENT_QUOTES, 'UTF-8'); ?>" sizes="16x16" type="image/png">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/css/theme-vars.php', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/css/bootstrap.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/css/style.min.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/css/rich-text.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="public-share-doc">
<div class="page-container">
    <div class="container-fluid px-0">
        <div class="row bg-grey g-0 mx-0">
            <div class="col-md-12 project-tabs">
                <div class="row">
                    <div class="project-tabs-header">
                        <div class="d-flex">
                            <div class="main-heading d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex align-items-center public-doc-logo-row">
                                    <img src="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="payment-page-logo" />
                                    <?php if (!$notFound): ?>
                                    <span class="public-doc-logo-divider" aria-hidden="true"></span>
                                    <span class="badge rounded-pill public-doc-media-badge<?php echo $canEdit ? ' public-doc-media-badge--edit' : ''; ?>" title="<?php echo htmlspecialchars($canEdit ? 'You can edit and view this document.' : 'You can view this document only.', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($canEdit ? 'Edit & view access' : 'View access only', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center justify-content-end flex-grow-1 flex-md-grow-0 gap-2 gap-md-3">
                                    <?php if (!$notFound): ?>
                                    <div id="publicDocSaveStatus" class="grey font-size-12 text-end public-doc-save-status"></div>
                                    <?php endif; ?>
                                <div class="dropdown">
                                    <button class="btn border-btn-a dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="dropdown-toggle-icon h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-4.5-6L12 15m0 0-4.5-4.5M12 15V3" />
                                        </svg>
                                        Export
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/share/export_doc.php?token=' . rawurlencode($token) . '&format=word'); ?>">Export as Word</a></li>
                                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/share/export_doc.php?token=' . rawurlencode($token) . '&format=pdf'); ?>" target="_blank" rel="noopener">Export as PDF</a></li>
                                    </ul>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-0 mx-0 public-share-doc-body">
            <div class="col-md-12 px-0">
                <?php if ($notFound): ?>
                    <div class="alert alert-danger text-center mt-4">Access Restricted. This shared link is invalid or no longer available.</div>
                <?php else: ?>
                    <div class="row g-0 mx-0 public-share-doc-editor-fill">
                        <div class="col-lg-12 px-0 bg-white private-note notes-editor-loading" id="publicDocEditorPane">
                            <div class="px-3 pt-2">
                                <form id="publicDocForm" onsubmit="return false;">
                                    <div class="form-group mb-2">
                                        <textarea name="note_content" id="publicDocTextarea" class="form-control notes-textarea" <?php echo $canEdit ? '' : 'readonly'; ?>><?php echo htmlspecialchars($noteContent, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <textarea name="title" class="note-title-under-editor" rows="1" readonly><?php echo htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>
                        window.PRIVATE_NOTE_PUBLIC = {
                            noteId: <?php echo (int)$noteId; ?>,
                            token: <?php echo json_encode($token); ?>,
                            canEdit: <?php echo $canEdit ? 'true' : 'false'; ?>,
                            ajaxUrl: <?php echo json_encode(rtrim((string)$url, '/') . '/ajax/private_notes/autosave.php'); ?>,
                            updatedAt: <?php echo json_encode($updatedAt); ?>
                        };
                    </script>
                    <?php $richEditorV = @filemtime(__DIR__ . '/../assets/js/rich-editor.js') ?: time(); ?>
                    <script src="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/js/rich-editor.js?v=' . (int)$richEditorV, ENT_QUOTES, 'UTF-8'); ?>"></script>
                    <script src="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/js/private-note-public-share.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars(rtrim((string)$url, '/') . '/assets/js/bootstrap.bundle.min.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
