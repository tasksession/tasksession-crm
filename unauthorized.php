<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : unauthorized.php
   Purpose : Unauthorized access page
 ================================================================================
*/
require_once(__DIR__ . "/includes/initialize.php");
require_once(LIB_ROOT . DS . 'system_helpers.php');

$title = ($lang['Unauthorized'] ?? 'Unauthorized') . ' | ' . $syatem_title;

// Smart dashboard link when session exists
$dashboardUrl = $url . 'index.php';
if (isset($_SESSION['accountStatus'])) {
    switch ((int) $_SESSION['accountStatus']) {
        case 1:
            $dashboardUrl = $url . 'admin/index.php';
            break;
        case 3:
            $dashboardUrl = $url . 'staff/index.php';
            break;
        case 2:
            $dashboardUrl = $url . 'client/index.php';
            break;
    }
}

$custom_favicon_file = SITE_ROOT . DS . $img_path . $favicon_image;
if ($favicon_image_check && file_exists($custom_favicon_file)) {
    $favicon_src = getSystemImageUrl($favicon_image);
} else {
    $favicon_src = $url . 'assets/images/favicon.png';
}

$stylePath = SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'style.min.css';
$styleVer = file_exists($stylePath) ? filemtime($stylePath) : time();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(substr($system_language ?? 'en', 0, 5)); ?>" class="no-js">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($url); ?>assets/css/theme-vars.php">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($url); ?>assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
    <link href="<?php echo htmlspecialchars($url); ?>assets/css/style.min.css?v=<?php echo (int) $styleVer; ?>" rel="stylesheet" type="text/css"/>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_src); ?>" sizes="16x16" type="image/png">
    <style>
        .unauthorized-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background: var(--body-bg-color);
            color: var(--body-font-color);
            font-family: 'Lato', system-ui, sans-serif;
        }
        .unauthorized-shell {
            width: 100%;
            max-width: 460px;
        }
        .unauthorized-card {
            background: var(--card-body-color);
            border: 1px solid var(--border-color);
            border-radius: calc(var(--button-border-radius) * 1.5);
            padding: 2.25rem 1.75rem 2rem;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
        }
        .unauthorized-icon-wrap {
            width: 88px;
            height: 88px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--main-content-bg);
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        .unauthorized-icon-wrap svg {
            width: 44px;
            height: 44px;
        }
        .unauthorized-title {
            color: var(--title-color);
            font-weight: 900;
            font-size: 1.5rem;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .unauthorized-lead {
            color: var(--body-font-color);
            opacity: 0.88;
            font-size: 0.95rem;
            line-height: 1.55;
            margin-bottom: 1.75rem;
        }
        .unauthorized-code {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.35rem 0.65rem;
            border-radius: var(--button-border-radius);
            border: 1px solid var(--border-color);
            color: var(--border-button-font-color);
            background: var(--body-bg-color);
            margin-bottom: 1rem;
        }
        .unauthorized-actions {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .unauthorized-actions a.btn {
            width: 100%;
            min-height: 42px;
            text-decoration: none !important;
        }
        .unauthorized-logo {
            display: block;
            margin: 0 auto 1.5rem;
            max-height: 48px;
            width: auto;
            object-fit: contain;
        }
        .unauthorized-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--body-font-color);
            opacity: 0.65;
        }
    </style>
</head>
<body class="unauthorized-page">
    <div class="unauthorized-shell">
        <div class="unauthorized-card widget-card">
            <div class="text-center">
                <?php if (!empty($logo) && file_exists(SITE_ROOT . DS . 'uploads' . DS . 'system-uploads' . DS . $logo)) : ?>
                    <img src="<?php echo htmlspecialchars(getSystemImageUrl($logo)); ?>" alt="" class="unauthorized-logo"/>
                <?php else : ?>
                    <img src="<?php echo htmlspecialchars($url); ?>assets/images/svg/logo.svg" alt="" class="unauthorized-logo"/>
                <?php endif; ?>

                <div class="unauthorized-icon-wrap" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>

                <span class="unauthorized-code">403</span>
                <h1 class="unauthorized-title"><?php echo htmlspecialchars($lang['Access Denied'] ?? 'Access Denied'); ?></h1>
                <p class="unauthorized-lead mb-0">
                    <?php echo htmlspecialchars($lang['You do not have permission to access this page.'] ?? "You don't have permission to access this page. Contact your administrator if you think this is a mistake."); ?>
                </p>
            </div>

            <div class="unauthorized-actions mt-4">
                <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn primary-btn justify-content-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <?php echo htmlspecialchars($lang['Go to dashboard'] ?? 'Go to dashboard'); ?>
                </a>
            </div>

            <?php if (!empty($copy_rights)) : ?>
                <p class="unauthorized-footer mb-0"><?php echo $copy_rights; ?></p>
            <?php endif; ?>
        </div>
    </div>
    <script src="<?php echo htmlspecialchars($url); ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
