<?php
require_once(__DIR__ . "/../TCPDF/tcpdf.php");

/**
 * Parse style="k:v;..." into lowercase keys.
 */
function private_notes_parse_inline_style($styleStr) {
    $out = array();
    if ($styleStr === null || $styleStr === '') {
        return $out;
    }
    foreach (explode(';', (string)$styleStr) as $chunk) {
        if (strpos($chunk, ':') === false) {
            continue;
        }
        list($k, $v) = explode(':', $chunk, 2);
        $k = trim(strtolower($k));
        $v = trim($v);
        if ($k !== '') {
            $out[$k] = $v;
        }
    }
    return $out;
}

/**
 * Rebuild style string without given keys.
 */
function private_notes_style_except($styles, array $omitKeys) {
    $parts = array();
    foreach ($styles as $k => $v) {
        if (in_array($k, $omitKeys, true)) {
            continue;
        }
        $parts[] = $k . ':' . $v;
    }
    return implode(';', $parts);
}

/**
 * TCPDF's HTML engine uses font-size as the reference for "%" widths on <img>,
 * so percentage widths render tiny. Convert % and px to explicit "mm" widths
 * and wrap images in <div align="..."> so horizontal alignment matches the editor.
 */
function privateNotesPrepareHtmlForTcpdf($html, $contentWidthMm) {
    if ($html === null || trim($html) === '') {
        return $html;
    }
    $contentWidthMm = max(30.0, (float)$contentWidthMm);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapperId = 'private_notes_tcpdf_root';
    $payload = '<div id="' . $wrapperId . '">' . $html . '</div>';
    $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">' . $payload, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$loaded) {
        libxml_clear_errors();
        return $html;
    }
    $root = $dom->getElementById($wrapperId);
    if (!$root) {
        libxml_clear_errors();
        return $html;
    }

    $imgNodes = array();
    foreach ($root->getElementsByTagName('img') as $n) {
        $imgNodes[] = $n;
    }

    foreach ($imgNodes as $img) {
        $isInlineEmoji = trim((string)$img->getAttribute('data-inline-emoji')) === '1';
        if ($isInlineEmoji) {
            // Keep emoji images inline with text; block wrapping would force line breaks.
            continue;
        }

        $styles = private_notes_parse_inline_style($img->getAttribute('style'));
        $dataAlign = strtolower(trim($img->getAttribute('data-img-align')));

        $halign = 'left';
        if ($dataAlign === 'center' || $dataAlign === 'right') {
            $halign = $dataAlign;
        } else {
            $ml = isset($styles['margin-left']) ? strtolower(trim($styles['margin-left'])) : '';
            $mr = isset($styles['margin-right']) ? strtolower(trim($styles['margin-right'])) : '';
            if ($ml === 'auto' && $mr === 'auto') {
                $halign = 'center';
            } elseif ($ml === 'auto' && ($mr === '0' || $mr === '0px' || $mr === '')) {
                $halign = 'right';
            } elseif (($ml === '0' || $ml === '0px' || $ml === '') && $mr === 'auto') {
                $halign = 'left';
            }
        }

        $pct = null;
        $px = null;
        if (!empty($styles['width'])) {
            $w = $styles['width'];
            if (preg_match('/([\d.]+)\s*%/', $w, $m)) {
                $pct = (float)$m[1];
            } elseif (preg_match('/([\d.]+)\s*px/i', $w, $m)) {
                $px = (float)$m[1];
            }
        }
        if ($pct === null && !empty($styles['max-width']) && preg_match('/100\s*%/', $styles['max-width'])) {
            $pct = 100.0;
        }
        if ($pct === null && $img->hasAttribute('width')) {
            $aw = trim($img->getAttribute('width'));
            if (preg_match('/([\d.]+)\s*%/', $aw, $m)) {
                $pct = (float)$m[1];
            } elseif (preg_match('/([\d.]+)/', $aw, $m) && strpos($aw, '%') === false) {
                $px = (float)$m[1];
            }
        }

        $widthMm = $contentWidthMm;
        if ($pct !== null) {
            $widthMm = $contentWidthMm * min(100.0, max(1.0, $pct)) / 100.0;
        } elseif ($px !== null) {
            $widthMm = $px * 25.4 / 96.0;
            if ($widthMm > $contentWidthMm) {
                $widthMm = $contentWidthMm;
            }
        }

        $newStyle = private_notes_style_except($styles, array('width', 'max-width', 'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left'));
        if ($newStyle !== '') {
            $img->setAttribute('style', $newStyle);
        } else {
            $img->removeAttribute('style');
        }
        $img->setAttribute('width', sprintf('%.2fmm', $widthMm));
        $img->removeAttribute('height');

        $parent = $img->parentNode;
        if (!$parent) {
            continue;
        }
        $div = $dom->createElement('div');
        $div->setAttribute('align', $halign);
        $parent->insertBefore($div, $img);
        $div->appendChild($img);
    }

    $blockTags = array('p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote');
    foreach ($blockTags as $tagName) {
        $nodes = array();
        foreach ($root->getElementsByTagName($tagName) as $n) {
            $nodes[] = $n;
        }
        foreach ($nodes as $el) {
            if ($el->hasAttribute('align')) {
                continue;
            }
            $st = private_notes_parse_inline_style($el->getAttribute('style'));
            if (empty($st['text-align'])) {
                continue;
            }
            $ta = strtolower(trim($st['text-align']));
            if (preg_match('/^(center|left|right|justify)\b/', $ta, $am)) {
                $el->setAttribute('align', $am[1]);
            }
        }
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    libxml_clear_errors();
    return $out;
}

function private_notes_export_discard_buffers() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

/**
 * Rewrite private-note image URLs to local absolute file paths for export (PDF/Word).
 * TCPDF and simple Word HTML export cannot fetch authenticated proxy URLs (share/private_media.php).
 */
function privateNotesRewriteExportImageSourcesToLocalFiles($html) {
    $html = (string)$html;
    if ($html === '') {
        return $html;
    }
    if (!defined('SITE_ROOT')) {
        return $html;
    }
    $siteRoot = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR);
    if ($siteRoot === '') {
        return $html;
    }

    return preg_replace_callback('/(<img\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']+)(\2)/i', function ($m) use ($siteRoot) {
        $srcRaw = html_entity_decode((string)$m[3], ENT_QUOTES, 'UTF-8');
        $candidate = trim($srcRaw);
        if ($candidate === '') {
            return $m[0];
        }

        $parsed = parse_url($candidate);
        $path = ($parsed && isset($parsed['path'])) ? (string)$parsed['path'] : $candidate;
        $path = str_replace('\\', '/', $path);
        $relative = '';

        // Case 1: src already points to uploads/private-notes/*
        $marker = '/uploads/private-notes/';
        $pos = strpos($path, $marker);
        if ($pos !== false) {
            $relative = ltrim(substr($path, $pos + 1), '/');
        } elseif (strpos($path, 'uploads/private-notes/') === 0) {
            $relative = ltrim($path, '/');
        }

        // Case 2: src is share/private_media.php?src=uploads/private-notes/* (token optional)
        if ($relative === '' && $parsed && !empty($parsed['query']) && strpos($path, '/share/private_media.php') !== false) {
            parse_str((string)$parsed['query'], $q);
            if (!empty($q['src'])) {
                $srcParam = ltrim(str_replace('\\', '/', (string)$q['src']), '/');
                if (strpos($srcParam, 'uploads/private-notes/') === 0) {
                    $relative = $srcParam;
                }
            }
        }

        if ($relative === '' || strpos($relative, 'uploads/private-notes/') !== 0) {
            return $m[0];
        }

        $absPath = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $realBase = realpath($siteRoot . DIRECTORY_SEPARATOR . 'uploads');
        $realFile = realpath($absPath);
        if (!$realBase || !$realFile || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
            return $m[0];
        }

        $safeLocal = str_replace('\\', '/', $realFile);
        return $m[1] . $m[2] . htmlspecialchars($safeLocal, ENT_QUOTES, 'UTF-8') . $m[4];
    }, $html);
}

/**
 * Embed local private-note images as data: URIs for Word HTML export (MS Word often blocks file:// images).
 */
function privateNotesRewriteExportImagesToDataUrisForWord($html) {
    $html = (string)$html;
    if ($html === '') {
        return $html;
    }
    if (!defined('SITE_ROOT')) {
        return $html;
    }
    $siteRoot = rtrim((string)SITE_ROOT, DIRECTORY_SEPARATOR);
    if ($siteRoot === '') {
        return $html;
    }

    return preg_replace_callback('/(<img\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']+)(\2)/i', function ($m) use ($siteRoot) {
        $srcRaw = html_entity_decode((string)$m[3], ENT_QUOTES, 'UTF-8');
        $candidate = trim($srcRaw);
        if ($candidate === '') {
            return $m[0];
        }

        // Already embedded
        if (stripos($candidate, 'data:') === 0) {
            return $m[0];
        }

        $pathFs = '';
        if (preg_match('#^file:///#i', $candidate)) {
            $pathFs = urldecode(substr($candidate, 8));
        } elseif (preg_match('#^file://#i', $candidate)) {
            $pathFs = urldecode(substr($candidate, 7));
        } elseif ($candidate !== '' && ($candidate[0] === '/' || preg_match('#^[A-Za-z]:[/\\\\]#', $candidate))) {
            $pathFs = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        }

        if ($pathFs === '' || !is_file($pathFs)) {
            return $m[0];
        }

        $realBase = realpath($siteRoot . DIRECTORY_SEPARATOR . 'uploads');
        $realFile = realpath($pathFs);
        if (!$realBase || !$realFile || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
            return $m[0];
        }

        $ext = strtolower((string)pathinfo($realFile, PATHINFO_EXTENSION));
        $mime = 'application/octet-stream';
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'gif') {
            $mime = 'image/gif';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } elseif ($ext === 'bmp') {
            $mime = 'image/bmp';
        }

        $bin = @file_get_contents($realFile);
        if ($bin === false || $bin === '') {
            return $m[0];
        }

        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($bin);
        return $m[1] . $m[2] . htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') . $m[4];
    }, $html);
}

function private_notes_unicode_codepoints($str) {
    $out = array();
    if ($str === null || $str === '') {
        return $out;
    }
    $chars = preg_split('//u', (string)$str, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) {
        return $out;
    }
    foreach ($chars as $ch) {
        $ucs4 = mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8');
        if ($ucs4 === false || strlen($ucs4) !== 4) {
            continue;
        }
        $code = unpack('N', $ucs4);
        if (!is_array($code) || !isset($code[1])) {
            continue;
        }
        $out[] = strtolower(dechex((int)$code[1]));
    }
    return $out;
}

/**
 * Replace most emoji graphemes with Twemoji images for reliable TCPDF output.
 */
function private_notes_replace_known_emoji_for_pdf($html) {
    if ($html === null || $html === '') {
        return $html;
    }

    return preg_replace_callback('/\X/u', function ($m) {
        $cluster = (string)$m[0];
        if ($cluster === '') {
            return $cluster;
        }

        // Quick gate: convert only likely emoji graphemes.
        if (!preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}]/u', $cluster)) {
            return $cluster;
        }

        $codes = private_notes_unicode_codepoints($cluster);
        if (empty($codes)) {
            return $cluster;
        }

        // Twemoji generally does not use FE0F in filenames.
        $codes = array_values(array_filter($codes, function ($hex) {
            return $hex !== 'fe0f';
        }));
        if (empty($codes)) {
            return $cluster;
        }

        $src = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/' . implode('-', $codes) . '.png';
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" width="14" height="14" data-inline-emoji="1" style="display:inline-block;vertical-align:-2px;line-height:1;margin:0 1px;">';
    }, (string)$html);
}

function privateNotesExportAsWord($noteTitle, $noteHtml) {
    private_notes_export_discard_buffers();
    $noteHtml = privateNotesRewriteExportImagesToDataUrisForWord((string)$noteHtml);
    $safeFilename = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim((string)$noteTitle));
    if ($safeFilename === '') {
        $safeFilename = 'private_note';
    }
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '.doc"');
    echo "<html><head><meta charset='UTF-8'><title>" . htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') . "</title></head><body>";
    echo $noteHtml;
    echo "</body></html>";
}

function privateNotesExportAsPdf($noteTitle, $noteHtml) {
    private_notes_export_discard_buffers();
    $prevDisplayErrors = ini_get('display_errors');
    @ini_set('display_errors', '0');
    $prevReporting = error_reporting();
    error_reporting($prevReporting & ~E_WARNING & ~E_NOTICE);
    $tcpdfWarningHandler = function ($errno, $errstr, $errfile = '', $errline = 0) {
        $isWarning = ($errno === E_WARNING || $errno === E_NOTICE || $errno === E_USER_WARNING || $errno === E_USER_NOTICE);
        $fromTcpdf = (is_string($errfile) && stripos(str_replace('\\', '/', $errfile), '/includes/tcpdf/tcpdf.php') !== false);
        if ($isWarning && $fromTcpdf) {
            return true;
        }
        return false;
    };
    set_error_handler($tcpdfWarningHandler, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

    try {
    $safeFilename = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim((string)$noteTitle));
    if ($safeFilename === '') {
        $safeFilename = 'private_note';
    }
    $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('TaskSession');
    $pdf->SetAuthor('TaskSession');
    $pdf->SetTitle($noteTitle);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $margins = $pdf->getMargins();
    $contentWidthMm = (float)$pdf->getPageWidth() - (float)$margins['left'] - (float)$margins['right'];

    $bodyHtml = private_notes_replace_known_emoji_for_pdf((string)$noteHtml);
    $bodyHtml = privateNotesPrepareHtmlForTcpdf($bodyHtml, $contentWidthMm);

    $fontsDir = realpath(__DIR__ . '/../TCPDF/fonts');
    $hasDejavu = false;
    $hasSegoeEmoji = false;
    $hasSegoeSymbol = false;
    if ($fontsDir !== false) {
        $hasSegoeSymbol = is_file($fontsDir . DIRECTORY_SEPARATOR . 'seguisym.php')
            && is_file($fontsDir . DIRECTORY_SEPARATOR . 'seguisym.z')
            && is_file($fontsDir . DIRECTORY_SEPARATOR . 'seguisym.ctg.z');
        $hasSegoeEmoji = is_file($fontsDir . DIRECTORY_SEPARATOR . 'seguiemj.php')
            && is_file($fontsDir . DIRECTORY_SEPARATOR . 'seguiemj.z')
            && is_file($fontsDir . DIRECTORY_SEPARATOR . 'seguiemj.ctg.z');
        $hasDejavu = is_file($fontsDir . DIRECTORY_SEPARATOR . 'dejavusans.php')
            && is_file($fontsDir . DIRECTORY_SEPARATOR . 'dejavusans.z')
            && is_file($fontsDir . DIRECTORY_SEPARATOR . 'dejavusans.ctg.z');
    }
    // Prefer Segoe UI Symbol for emoji-like symbols in TCPDF (more reliable than color emoji fonts).
    $baseFont = $hasSegoeSymbol
        ? 'seguisym'
        : ($hasSegoeEmoji ? 'seguiemj' : ($hasDejavu ? 'dejavusans' : 'helvetica'));

    $pdfHtml = '<style>
        body { font-family: ' . $baseFont . '; font-size: 11pt; line-height: 1.45; color: #111; }
        p, div { margin: 0 0 8px 0; padding: 0; }
        h1, h2, h3, h4, h5, h6 { margin: 10px 0 6px 0; line-height: 1.2; }
        ul, ol { margin: 0 0 8px 18px; padding: 0; }
        li { margin: 0 0 4px 0; }
        blockquote { margin: 6px 0; padding: 6px 10px; border-left: 2px solid #ddd; }
        img { vertical-align: middle; }
    </style>' . $bodyHtml;

    $pdf->SetFont($baseFont, '', 11);
    $pdf->writeHTML($pdfHtml, true, false, true, false, '');
    $pdf->Output($safeFilename . '.pdf', 'I');
    } finally {
        restore_error_handler();
        error_reporting($prevReporting);
        if ($prevDisplayErrors !== false) {
            @ini_set('display_errors', (string)$prevDisplayErrors);
        }
    }
}
