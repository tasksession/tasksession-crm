<?php
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: private, no-cache, must-revalidate');
include_once('../../includes/lib-initialize.php'); // adjust path if needed
require_once(dirname(__DIR__, 2) . '/includes/theme-shadow-helpers.php');

if (method_exists($db, 'querySoft')) {
    $themeResult = $db->querySoft('SELECT * FROM theme_settings WHERE id=1 LIMIT 1');
} else {
    $themeResult = @mysqli_query($db->connection, 'SELECT * FROM theme_settings WHERE id=1 LIMIT 1');
}
$theme = ($themeResult && method_exists($themeResult, 'fetch_assoc')) ? $themeResult->fetch_assoc() : null;
if (!is_array($theme)) {
    $theme = [];
}

$buttonRadius = isset($theme['button_border_radius']) ? (int)$theme['button_border_radius'] : 50;
if ($buttonRadius < 0) {
    $buttonRadius = 50;
}
$cardRadius = isset($theme['card_border_radius']) ? (int)$theme['card_border_radius'] : (int)theme_card_border_radius_default();
if ($cardRadius < 0) {
    $cardRadius = (int)theme_card_border_radius_default();
}
$titleFontWeight = isset($theme['title_font_weight']) ? (string)$theme['title_font_weight'] : theme_title_font_weight_default();
if (!in_array($titleFontWeight, array('600', '800', '900'), true)) {
    $titleFontWeight = theme_title_font_weight_default();
}
?>
:root {
    /* General Colors */
    --primary-color: <?php echo htmlspecialchars((string)($theme['primary_color'] ?? '#0088ff')); ?>;
    --secondary-color: <?php echo htmlspecialchars((string)($theme['secondary_color'] ?? '#6c757d')); ?>;
    --body-bg-color: <?php echo htmlspecialchars((string)($theme['body_bg_color'] ?? '#ffffff')); ?>;
    --body-font-color: <?php echo htmlspecialchars((string)($theme['body_font_color'] ?? '#212529')); ?>;
    --title-color: <?php echo htmlspecialchars((string)($theme['title_color'] ?? '#212529')); ?>;
    --border-color: <?php echo htmlspecialchars((string)($theme['border_color'] ?? '#dee2e6')); ?>;

    /* Widget counter numbers (semantic slots; default = title color) */
    --counter-color: var(--title-color);
    --counter-color-success: var(--title-color);
    --counter-color-warning: var(--title-color);
    --counter-color-danger: var(--title-color);
    --counter-color-info: var(--title-color);
    --card-body-color: <?php echo htmlspecialchars((string)($theme['card_body_color'] ?? '#ffffff')); ?>;
    --box-shadow: <?php
    $boxShadow = theme_box_shadow_sanitize($theme['box_shadow'] ?? theme_box_shadow_default());
    echo htmlspecialchars($boxShadow, ENT_QUOTES, 'UTF-8');
    ?>;
    --card-border-radius: <?php echo $cardRadius; ?>px;
    --title-font-weight: <?php echo $titleFontWeight; ?>;

    /* Sidebar Colors */
    --sidebar-bg: <?php echo htmlspecialchars((string)($theme['sidebar_bg_color'] ?? '#0b1f4a')); ?>;
    --sidebar-link-color: <?php echo htmlspecialchars((string)($theme['sidebar_link_color'] ?? '#ffffff')); ?>;
    --sidebar-active-bg: <?php echo htmlspecialchars((string)($theme['sidebar_active_bg_color'] ?? '#1b2f5a')); ?>;
    --sidebar-active-color: <?php echo htmlspecialchars((string)($theme['sidebar_active_color'] ?? '#ffffff')); ?>;

    /* Button Colors */
    --primary-button-color: <?php echo htmlspecialchars((string)($theme['primary_button_color'] ?? '#0d6efd')); ?>;
    --secondary-button-color: <?php echo htmlspecialchars((string)($theme['secondary_button_color'] ?? '#6c757d')); ?>;
    --border-button-color: <?php echo htmlspecialchars((string)($theme['border_button_color'] ?? '#ced4da')); ?>;
    --primary-button-font-color: <?php echo htmlspecialchars((string)($theme['primary_button_font_color'] ?? '#ffffff')); ?>;
    --secondary-button-font-color: <?php echo htmlspecialchars((string)($theme['secondary_button_font_color'] ?? '#ffffff')); ?>;
    --border-button-font-color: <?php echo htmlspecialchars((string)($theme['border_button_font_color'] ?? '#212529')); ?>;
    --button-border-radius: <?php echo $buttonRadius; ?>px;

    /* Header Colors */
    --header-bg: <?php echo htmlspecialchars((string)($theme['header_bg_color'] ?? '#ffffff')); ?>;
    --header-link-color: <?php echo htmlspecialchars((string)($theme['header_link_color'] ?? '#212529')); ?>;
    --primary-header-color: <?php echo htmlspecialchars((string)($theme['primary_header_color'] ?? '#0d6efd')); ?>;
    --secondary-header-color: <?php echo htmlspecialchars((string)($theme['secondary_header_color'] ?? '#6c757d')); ?>;
    --primary-header-font-color: <?php echo htmlspecialchars((string)($theme['primary_header_font_color'] ?? '#ffffff')); ?>;
    --secondary-header-font-color: <?php echo htmlspecialchars((string)($theme['secondary_header_font_color'] ?? '#ffffff')); ?>;
    
    /* Main Content */
    --main-content-bg: <?php echo htmlspecialchars((string)($theme['main_content_bg_color'] ?? '#f8f9fa')); ?>;

    /* Chat (messages / chatting.php) */
    --chat-card-bg: <?php echo htmlspecialchars((string)($theme['chat_card_color'] ?? '#ffffff')); ?>;
    --chat-card-bg-secondary: <?php echo htmlspecialchars((string)($theme['chat_card_secondary_color'] ?? '#e6f0f9')); ?>;
    --chat-body-bg: <?php echo htmlspecialchars((string)($theme['chat_body_color'] ?? '#f5f8fa')); ?>;
    --chat-title-color: <?php echo htmlspecialchars((string)($theme['chat_title_color'] ?? '#212529')); ?>;
    --chat-buttons-color: <?php echo htmlspecialchars((string)($theme['chat_buttons_color'] ?? ($theme['primary_button_color'] ?? '#0094ff'))); ?>;
    --chat-buttons-text-color: <?php echo htmlspecialchars((string)($theme['chat_buttons_text_color'] ?? ($theme['primary_button_font_color'] ?? '#ffffff'))); ?>;
    --chat-time-text-color: <?php echo htmlspecialchars((string)($theme['chat_time_text_color'] ?? '#757575')); ?>;
    --chat-font-size: <?php echo htmlspecialchars((string)($theme['chat_font_size'] ?? '14px')); ?>;
    --chat-font-weight: <?php echo htmlspecialchars((string)($theme['chat_font_weight'] ?? '400')); ?>;
}

/* Keep login button at full opacity while disabled (avoid "faded" look) */
#loginSubmitBtn:disabled,
#loginSubmitBtn[disabled] {
    opacity: 1 !important;
}