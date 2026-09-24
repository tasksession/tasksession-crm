<?php
/**
 * Shared SVG icons (sidebar paths) + card renderer for migration import/export grids.
 */
if (!isset($h) || !is_callable($h)) {
    $h = static function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };
}

$importSvg = static function (string $inner): string {
    $open = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">';
    if (strpos(ltrim($inner), '<') === 0) {
        return $open . $inner . '</svg>';
    }
    return $open . '<path stroke-linecap="round" stroke-linejoin="round" d="' . $inner . '" /></svg>';
};

$D_CLIENTS = 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z';
$D_STAFF = 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z';
$D_ADMIN = $D_STAFF;
$D_PROJECTS = 'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z';
$D_TASKS = 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z';
$D_CHAT = 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z';
$D_INVOICE = 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z';
$D_NOTES = 'M7.5 3.75A1.5 1.5 0 006 5.25v13.5a1.5 1.5 0 001.5 1.5h9A1.5 1.5 0 0018 18.75V8.25L13.5 3.75h-6zM13.5 3.75V8.25H18M9 11.25h6M9 14.25h6M9 17.25h4.5';
$D_LEADS = '<circle cx="12" cy="12" r="4"></circle><circle cx="12" cy="12" r="8"></circle><path stroke-linecap="round" d="M12 2v4M12 18v4M2 12h4M18 12h4"></path>';
$D_COMPANIES = 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h6M9 10.5h6M9 14.25h.008v.008H9v-.008zm4.5-.75h.008v.008H13.5v-.008zm0 3h.008v.008H13.5v-.008z';

$renderMigrationActionCard = static function (
    string $svgInner,
    string $title,
    string $desc,
    string $href,
    bool $enabled,
    ?string $enabledButtonText = null,
    ?string $disabledButtonText = null
) use ($h, $importSvg, $lang): void {
    $titleEsc = $h($title);
    $descEsc = $h($desc);
    $hrefEsc = $h($href);
    $btnLabel = $h($enabledButtonText ?? ($lang['Continue'] ?? 'Continue'));
    $soonLabel = $h($disabledButtonText ?? ($lang['Coming soon'] ?? 'Coming soon'));
    $iconClass = $enabled
        ? 'migration-action-icon migration-action-icon--module'
        : 'migration-action-icon migration-action-icon--module-muted';
    echo '<div class="col-md-6 col-lg-6 mb-3 d-flex' . ($enabled ? '' : ' migration-action-card--disabled') . '">';
    if ($enabled) {
        echo '<a class="migration-action-link w-100" href="' . $hrefEsc . '">';
        echo '<div class="settings-card h-100 w-100"><div class="card-body migration-action-card-body">';
        echo '<div class="' . $iconClass . '" aria-hidden="true">' . $importSvg($svgInner) . '</div>';
        echo '<h3>' . $titleEsc . '</h3>';
        echo '<p class="text-muted small migration-action-card-text mb-0">' . $descEsc . '</p>';
        echo '<span class="btn primary-btn btn-sm align-self-start mt-3">' . $btnLabel . '</span>';
        echo '</div></div></a>';
    } else {
        echo '<div class="settings-card h-100 w-100 opacity-50"><div class="card-body migration-action-card-body">';
        echo '<div class="' . $iconClass . '" aria-hidden="true">' . $importSvg($svgInner) . '</div>';
        echo '<h3>' . $titleEsc . '</h3>';
        echo '<p class="text-muted small migration-action-card-text mb-0">' . $descEsc . '</p>';
        echo '<span class="btn border-btn-a btn-sm align-self-start mt-3" role="presentation">' . $soonLabel . '</span>';
        echo '</div></div>';
    }
    echo '</div>';
};
