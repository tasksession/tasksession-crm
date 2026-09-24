<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : theme-style.php
   Purpose : Allows administrators to customize and update the theme and color settings of the system.
 ================================================================================
*/
ob_start(); 
require_once("../includes/lib-initialize.php");
require_once("../includes/theme-shadow-helpers.php");
$title = "System Setting | ". $syatem_title;
include("../templates/header.php");

if (! $session->isLoggedIn()) {
    redirectTo($url . "index.php");
}
if ($_SESSION['accountStatus'] == 2) {
    redirectTo($url . "client/index.php");
}
if ($_SESSION['accountStatus'] == 3) {
    redirectTo($url . "staff/index.php");
}

$id           = $session->userId;
$user         = User::findById((int)$id);
$username     = $user->firstName;
$email        = $user->email;
$account_stat = $user->status;

require_once(LIB_ROOT . DS . 'ensure-theme-font-schema.php');
require_once(LIB_ROOT . DS . 'google-fonts-curated.php');
ensure_theme_font_schema($db);

// Upload custom font (.woff / .woff2)
if (isset($_POST['upload_custom_font']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $family_css = trim((string)($_POST['custom_font_family'] ?? ''));
    $family_css = preg_replace('/[^a-zA-Z0-9 _-]/', '', $family_css);
    $font_weight = (int)($_POST['custom_font_weight'] ?? 400);
    if ($font_weight < 1 || $font_weight > 1000) {
        $font_weight = 400;
    }
    $is_italic = isset($_POST['custom_font_italic']) ? 1 : 0;
    $file = isset($_FILES['custom_font_file']) ? $_FILES['custom_font_file'] : null;

    $upload_err = '';
    $maxFontBytes = 2 * 1024 * 1024; // 2 MB — webfonts should be small; limits disk abuse
    if ($family_css === '') {
        $upload_err = 'empty_family';
    } elseif (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $upload_err = 'upload_failed';
    } elseif (isset($file['size']) && (int)$file['size'] > $maxFontBytes) {
        $upload_err = 'too_large';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('woff', 'woff2'), true)) {
            $upload_err = 'bad_ext';
        } else {
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            $mime_ok = ($mime === '' || $mime === 'font/woff2' || $mime === 'font/woff'
                || $mime === 'application/font-woff2' || $mime === 'application/font-woff'
                || $mime === 'application/octet-stream' || $mime === 'application/x-font-woff2'
                || $mime === 'application/x-font-woff');
            if (!$mime_ok) {
                $upload_err = 'bad_mime';
            }
        }
    }

    if ($upload_err === '') {
        $dir = SITE_ROOT . DS . 'uploads' . DS . 'custom-fonts';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $family_css);
        if ($base === '') {
            $base = 'font';
        }
        $dest_name = $base . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest_abs = $dir . DS . $dest_name;
        if (!move_uploaded_file($file['tmp_name'], $dest_abs)) {
            $upload_err = 'move_failed';
        } else {
            $rel = 'uploads/custom-fonts/' . $dest_name;
            $orig = substr((string)$file['name'], 0, 250);
            $family_esc = $db->escapeValue($family_css);
            $rel_esc = $db->escapeValue($rel);
            $orig_esc = $db->escapeValue($orig);
            $fw = (int)$font_weight;
            $ii = (int)$is_italic;
            $sql_ins = "INSERT INTO custom_fonts (family_css, font_weight, is_italic, file_path, original_filename) VALUES ('{$family_esc}', {$fw}, {$ii}, '{$rel_esc}', '{$orig_esc}')";
            $ins_res = @mysqli_query($db->connection, $sql_ins);
            if ($ins_res) {
                redirectTo($url . 'admin/theme-style.php?font_upload=1');
                exit;
            }
            @unlink($dest_abs);
            $upload_err = 'db_failed';
        }
    }
    redirectTo($url . 'admin/theme-style.php?font_upload_err=' . rawurlencode($upload_err));
    exit;
}

// Add new color columns if they don't exist
$new_columns = [
    'primary_color' => 'VARCHAR(20) DEFAULT "#007bff"',
    'secondary_color' => 'VARCHAR(20) DEFAULT "#6c757d"',
    'body_bg_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'body_font_color' => 'VARCHAR(20) DEFAULT "#212529"',
    'title_color' => 'VARCHAR(20) DEFAULT "#000000"',
    'border_color' => 'VARCHAR(20) DEFAULT "#dee2e6"',
    'card_body_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'box_shadow' => 'VARCHAR(120) DEFAULT "0px 3px 13px -5px rgb(0 0 0 / 0.1)"',
    'card_border_radius' => 'VARCHAR(20) DEFAULT "12"',
    'title_font_weight' => 'VARCHAR(20) DEFAULT "600"',
    'primary_header_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'secondary_header_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'primary_header_font_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'secondary_header_font_color' => 'VARCHAR(20) DEFAULT "#212529"',
    'primary_button_color' => 'VARCHAR(20) DEFAULT "#007bff"',
    'secondary_button_color' => 'VARCHAR(20) DEFAULT "#6c757d"',
    'border_button_color' => 'VARCHAR(20) DEFAULT "#dee2e6"',
    'primary_button_font_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'secondary_button_font_color' => 'VARCHAR(20) DEFAULT "#212529"',
    'border_button_font_color' => 'VARCHAR(20) DEFAULT "#212529"',
    'button_border_radius' => 'VARCHAR(20) DEFAULT "4px"',
    'chat_card_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'chat_card_secondary_color' => 'VARCHAR(20) DEFAULT "#e6f0f9"',
    'chat_title_color' => 'VARCHAR(20) DEFAULT "#212529"',
    'chat_body_color' => 'VARCHAR(20) DEFAULT "#f5f8fa"',
    'chat_buttons_color' => 'VARCHAR(20) DEFAULT "#0094ff"',
    'chat_buttons_text_color' => 'VARCHAR(20) DEFAULT "#ffffff"',
    'chat_time_text_color' => 'VARCHAR(20) DEFAULT "#757575"',
    'chat_font_size' => 'VARCHAR(32) DEFAULT "14px"',
    'chat_font_weight' => 'VARCHAR(20) DEFAULT "400"'
];

foreach ($new_columns as $column => $definition) {
    $check_column = $db->query("SHOW COLUMNS FROM theme_settings LIKE '$column'");
    if ($check_column->num_rows == 0) {
        $db->query("ALTER TABLE theme_settings ADD COLUMN $column $definition");
    }
}

// Fetch current theme settings
$theme = $db->query("SELECT * FROM theme_settings WHERE id=1 LIMIT 1")->fetch_assoc();
$theme_default_box_shadow = theme_box_shadow_default();
if (is_array($theme) && isset($theme['box_shadow']) && in_array($theme['box_shadow'], array(
    '0 2px 8px rgba(0, 0, 0, 0.05)',
    '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
    '10px 10px 5px 0px rgba(0, 0, 0, 0.75)',
    '10px 10px 5px 0px rgb(0 0 0 / 0.75)'
), true)) {
    $db->query("UPDATE theme_settings SET box_shadow = '" . addslashes($theme_default_box_shadow) . "' WHERE id = 1");
    $theme['box_shadow'] = $theme_default_box_shadow;
}
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if (isset($_GET['font_upload']) && (string)$_GET['font_upload'] === '1') {
    $toast_flash = array('type' => 'success', 'msg' => $lang['Font upload success']);
}
if (!empty($_GET['font_upload_err'])) {
    $ek = (string)$_GET['font_upload_err'];
    $err_lang_keys = array(
        'empty_family' => 'Font upload error empty family',
        'upload_failed' => 'Font upload error failed',
        'bad_ext' => 'Font upload error extension',
        'bad_mime' => 'Font upload error mime',
        'too_large' => 'Font upload error too large',
        'move_failed' => 'Font upload error move',
        'db_failed' => 'Font upload error db',
    );
    $lk = isset($err_lang_keys[$ek]) ? $err_lang_keys[$ek] : 'Font upload error generic';
    $msg = isset($lang[$lk]) ? $lang[$lk] : $lk;
    $toast_flash = array('type' => 'error', 'msg' => $msg);
}

// Handle form submission
if (isset($_POST['update_theme'])) {
    // General colors
    $primary_color = addslashes($_POST['primary_color']);
    $secondary_color = addslashes($_POST['secondary_color']);
    $body_bg_color = addslashes($_POST['body_bg_color']);
    $body_font_color = addslashes($_POST['body_font_color']);
    $title_color = addslashes($_POST['title_color']);
    $border_color = addslashes($_POST['border_color']);
    $card_body_color = addslashes($_POST['card_body_color']);
    $box_shadow = addslashes(theme_box_shadow_sanitize($_POST['box_shadow'] ?? ''));
    $card_border_radius_in = trim((string)($_POST['card_border_radius'] ?? theme_card_border_radius_default()));
    $card_border_radius = addslashes((string)max(0, min(50, (int)$card_border_radius_in)));
    $title_font_weight_in = trim((string)($_POST['title_font_weight'] ?? '600'));
    $title_font_weight = in_array($title_font_weight_in, array('600', '800', '900'), true)
        ? addslashes($title_font_weight_in) : addslashes('600');
    $primary_header_color = addslashes($_POST['primary_header_color']);
    $secondary_header_color = addslashes($_POST['secondary_header_color']);
    $primary_header_font_color = addslashes($_POST['primary_header_font_color']);
    $secondary_header_font_color = addslashes($_POST['secondary_header_font_color']);
    
    // Sidebar colors
    $sidebar_bg_color = addslashes($_POST['sidebar_bg_color']);
    $sidebar_link_color = addslashes($_POST['sidebar_link_color']);
    $sidebar_active_bg_color = addslashes($_POST['sidebar_active_bg_color']);
    $sidebar_active_color = addslashes($_POST['sidebar_active_color']);
    
    // Button colors
    $primary_button_color = addslashes($_POST['primary_button_color']);
    $secondary_button_color = addslashes($_POST['secondary_button_color']);
    $border_button_color = addslashes($_POST['border_button_color']);
    $primary_button_font_color = addslashes($_POST['primary_button_font_color']);
    $secondary_button_font_color = addslashes($_POST['secondary_button_font_color']);
    $border_button_font_color = addslashes($_POST['border_button_font_color']);
    $button_border_radius = addslashes($_POST['button_border_radius']);

    $chat_card_color = addslashes($_POST['chat_card_color']);
    $chat_card_secondary_color = addslashes(isset($_POST['chat_card_secondary_color']) ? $_POST['chat_card_secondary_color'] : '#e6f0f9');
    $chat_title_color = addslashes($_POST['chat_title_color']);
    $chat_body_color = addslashes($_POST['chat_body_color']);
    $chat_buttons_color = addslashes($_POST['chat_buttons_color']);
    $chat_buttons_text_color = addslashes($_POST['chat_buttons_text_color']);
    $chat_time_text_color = addslashes($_POST['chat_time_text_color']);
    $chat_font_size_in = trim((string)($_POST['chat_font_size'] ?? '14px'));
    if (preg_match('/^[0-9]{1,3}(\.[0-9]+)?$/', $chat_font_size_in)) {
        $chat_font_size_in .= 'px';
    }
    $chat_font_size = preg_match('/^[0-9]{1,3}(\.[0-9]+)?(px|rem|em|pt|%)$/', $chat_font_size_in) ? addslashes($chat_font_size_in) : '14px';
    $chat_font_weight_in = trim((string)($_POST['chat_font_weight'] ?? '400'));
    $chat_font_weight = preg_match('/^(normal|bold|bolder|lighter|(100|200|300|400|500|600|700|800|900))$/', $chat_font_weight_in)
        ? addslashes($chat_font_weight_in) : '400';

    $choice = isset($_POST['ui_font_choice']) ? trim((string)$_POST['ui_font_choice']) : 'default';
    $weights_raw = isset($_POST['ui_google_font_weights']) ? trim((string)$_POST['ui_google_font_weights']) : '400;700;900';
    $weights_raw = preg_replace('/[^0-9;]/', '', $weights_raw);
    if ($weights_raw === '') {
        $weights_raw = '400;700;900';
    }

    $ui_font_source = 'default';
    $ui_google_font_family = 'Lato';
    $ui_google_font_weights = '400;700;900';
    $ui_websafe_plain = '';
    $ui_custom_font_id_sql = 'NULL';

    if ($choice === 'default') {
        $ui_font_source = 'default';
        $ui_google_font_family = 'Lato';
        $ui_google_font_weights = '400;700;900';
    } elseif (strpos($choice, 'websafe:') === 0) {
        $wkey = substr($choice, 8);
        $wmap = theme_fonts_websafe_presets_by_key();
        if (isset($wmap[$wkey])) {
            $ui_font_source = 'websafe';
            $ui_websafe_plain = $wmap[$wkey]['stack'];
            $ui_google_font_family = 'Lato';
            $ui_google_font_weights = '400;700;900';
        }
    } elseif (strpos($choice, 'google:') === 0) {
        $gfam = substr($choice, 7);
        $glist = google_fonts_curated_list();
        if (in_array($gfam, $glist, true)) {
            $ui_font_source = 'google';
            $ui_google_font_family = $gfam;
            $ui_google_font_weights = $weights_raw;
        }
    } elseif (strpos($choice, 'custom:') === 0) {
        $cid = (int)substr($choice, 7);
        if ($cid > 0) {
            $chk = $db->query('SELECT id FROM custom_fonts WHERE id=' . $cid . ' LIMIT 1');
            if ($chk && $chk->num_rows > 0) {
                $ui_font_source = 'custom';
                $ui_google_font_family = 'Lato';
                $ui_google_font_weights = '400;700;900';
                $ui_custom_font_id_sql = (string)$cid;
            }
        }
    }

    $ui_font_source_esc = addslashes($ui_font_source);
    $ui_google_font_family_esc = addslashes($ui_google_font_family);
    $ui_google_font_weights_esc = addslashes($ui_google_font_weights);
    $ui_websafe_stack_esc = ($ui_font_source === 'websafe' && $ui_websafe_plain !== '') ? addslashes($ui_websafe_plain) : '';

    $sql = "UPDATE theme_settings SET 
        primary_color = '{$primary_color}',
        secondary_color = '{$secondary_color}',
        body_bg_color = '{$body_bg_color}',
        body_font_color = '{$body_font_color}',
        title_color = '{$title_color}',
        border_color = '{$border_color}',
        card_body_color = '{$card_body_color}',
        box_shadow = '{$box_shadow}',
        card_border_radius = '{$card_border_radius}',
        title_font_weight = '{$title_font_weight}',
        primary_header_color = '{$primary_header_color}',
        secondary_header_color = '{$secondary_header_color}',
        primary_header_font_color = '{$primary_header_font_color}',
        secondary_header_font_color = '{$secondary_header_font_color}',
        sidebar_bg_color = '{$sidebar_bg_color}',
        sidebar_link_color = '{$sidebar_link_color}',
        sidebar_active_bg_color = '{$sidebar_active_bg_color}',
        sidebar_active_color = '{$sidebar_active_color}',
        primary_button_color = '{$primary_button_color}',
        secondary_button_color = '{$secondary_button_color}',
        border_button_color = '{$border_button_color}',
        primary_button_font_color = '{$primary_button_font_color}',
        secondary_button_font_color = '{$secondary_button_font_color}',
        border_button_font_color = '{$border_button_font_color}',
        button_border_radius = '{$button_border_radius}',
        chat_card_color = '{$chat_card_color}',
        chat_card_secondary_color = '{$chat_card_secondary_color}',
        chat_title_color = '{$chat_title_color}',
        chat_body_color = '{$chat_body_color}',
        chat_buttons_color = '{$chat_buttons_color}',
        chat_buttons_text_color = '{$chat_buttons_text_color}',
        chat_time_text_color = '{$chat_time_text_color}',
        chat_font_size = '{$chat_font_size}',
        chat_font_weight = '{$chat_font_weight}',
        ui_font_source = '{$ui_font_source_esc}',
        ui_google_font_family = '{$ui_google_font_family_esc}',
        ui_google_font_weights = '{$ui_google_font_weights_esc}',
        ui_websafe_stack = '{$ui_websafe_stack_esc}',
        ui_custom_font_id = {$ui_custom_font_id_sql}
        WHERE id = 1";
    $db->query($sql);

    // Refresh theme settings after update
    $theme = $db->query("SELECT * FROM theme_settings WHERE id=1 LIMIT 1")->fetch_assoc();
    $toast_flash = array('type' => 'success', 'msg' => 'Theme settings updated successfully!');
}

// Handle reset to default values
if (isset($_POST['reset_theme'])) {
    // Default values - EXACTLY matching installer values
    $default_values = [
        'primary_color' => '#0094ff',
        'secondary_color' => '#10b981',
        'body_bg_color' => '#f5f8fa',
        'body_font_color' => '#212529',
        'title_color' => '#000000',
        'border_color' => '#e1e1e1',
        'card_body_color' => '#ffffff',
        'box_shadow' => theme_box_shadow_default(),
        'card_border_radius' => theme_card_border_radius_default(),
        'title_font_weight' => theme_title_font_weight_default(),
        'primary_header_color' => '#ffffff',
        'secondary_header_color' => '#ffffff',
        'primary_header_font_color' => '#212529',
        'secondary_header_font_color' => '#212529',
        'sidebar_bg_color' => '#0f1d40',
        'sidebar_link_color' => '#e0e6eb',
        'sidebar_active_bg_color' => '#2d4071',
        'sidebar_active_color' => '#ffffff',
        'primary_button_color' => '#0094ff',
        'secondary_button_color' => '#f1f6fa',
        'border_button_color' => '#dee2e6',
        'primary_button_font_color' => '#ffffff',
        'secondary_button_font_color' => '#212529',
        'border_button_font_color' => '#212529',
        'button_border_radius' => '4',
        'chat_card_color' => '#ffffff',
        'chat_card_secondary_color' => '#e6f0f9',
        'chat_title_color' => '#212529',
        'chat_body_color' => '#f5f8fa',
        'chat_buttons_color' => '#0094ff',
        'chat_buttons_text_color' => '#ffffff',
        'chat_time_text_color' => '#757575',
        'chat_font_size' => '14px',
        'chat_font_weight' => '400',
        'ui_font_source' => 'default',
        'ui_google_font_family' => 'Lato',
        'ui_google_font_weights' => '400;700;900',
        'ui_websafe_stack' => '',
        'ui_custom_font_id' => null,
    ];

    // Build the SQL update statement
    $update_parts = [];
    foreach ($default_values as $column => $value) {
        if ($value === null) {
            $update_parts[] = "$column = NULL";
        } else {
            $update_parts[] = "$column = '" . addslashes((string)$value) . "'";
        }
    }
    $sql = "UPDATE theme_settings SET " . implode(', ', $update_parts) . " WHERE id = 1";
    
    $db->query($sql);

    // Refresh theme settings after reset
    $theme = $db->query("SELECT * FROM theme_settings WHERE id=1 LIMIT 1")->fetch_assoc();
    $toast_flash = array('type' => 'info', 'msg' => 'Theme settings have been reset to default values!');
}

$custom_fonts_rows = array();
$cfq = $db->query('SELECT id, family_css, font_weight, is_italic FROM custom_fonts ORDER BY id DESC');
if ($cfq) {
    while ($row = $cfq->fetch_assoc()) {
        $custom_fonts_rows[] = $row;
    }
}

$cur_font_choice = 'default';
$tsrc = isset($theme['ui_font_source']) ? (string)$theme['ui_font_source'] : 'default';
if ($tsrc === 'default') {
    $cur_font_choice = 'default';
} elseif ($tsrc === 'google') {
    $cur_font_choice = 'google:' . trim((string)($theme['ui_google_font_family'] ?? 'Lato'));
} elseif ($tsrc === 'websafe') {
    $stk = trim((string)($theme['ui_websafe_stack'] ?? ''));
    foreach (theme_fonts_websafe_presets_by_key() as $wk => $wm) {
        if ($wm['stack'] === $stk) {
            $cur_font_choice = 'websafe:' . $wk;
            break;
        }
    }
} elseif ($tsrc === 'custom') {
    $cid = isset($theme['ui_custom_font_id']) ? (int)$theme['ui_custom_font_id'] : 0;
    if ($cid > 0) {
        $cur_font_choice = 'custom:' . $cid;
    }
}
$cur_google_weights = htmlspecialchars((string)($theme['ui_google_font_weights'] ?? '400;700;900'), ENT_QUOTES, 'UTF-8');

$wmap_fonts = theme_fonts_websafe_presets_by_key();
$cur_font_display_label = $lang['Font default Lato'];
if ($cur_font_choice === 'default') {
    $cur_font_display_label = $lang['Font default Lato'];
} elseif (strpos($cur_font_choice, 'websafe:') === 0) {
    $wk = substr($cur_font_choice, 8);
    $cur_font_display_label = isset($wmap_fonts[$wk]) ? $wmap_fonts[$wk]['label'] : $lang['Select Font'];
} elseif (strpos($cur_font_choice, 'google:') === 0) {
    $cur_font_display_label = substr($cur_font_choice, 7);
} elseif (strpos($cur_font_choice, 'custom:') === 0) {
    $cid_disp = (int)substr($cur_font_choice, 7);
    $cur_font_display_label = $lang['Select Font'];
    foreach ($custom_fonts_rows as $cfr) {
        if ((int)$cfr['id'] === $cid_disp) {
            $itd = !empty($cfr['is_italic']) ? ' (italic)' : '';
            $cur_font_display_label = $cfr['family_css'] . ' — ' . $cfr['font_weight'] . $itd;
            break;
        }
    }
}
?>

<div class="page-container">
		<div class="container-fluid">
			<div class="row row-eq-height">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content" style="padding-bottom:0;">
						<?php include('../templates/top-header.php'); ?>
							<div class="row system-wrap h-100">
								<?php include("../templates/system-nav.php"); ?>
            <!-- Main Content -->
            <div class="col-md-9 ss-right h-100">
                    <h2 class="page-title mb-4"><?php echo $lang['Theme Style Settings']; ?></h2>

                    <form method="post" action="" id="themeForm" enctype="multipart/form-data">
                        <!-- Modern Tabs Navigation -->
                        <ul class="nav nav-tabs" id="themeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true"><?php echo $lang['General']; ?></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="sidebar-tab" data-bs-toggle="tab" data-bs-target="#sidebar" type="button" role="tab" aria-controls="sidebar" aria-selected="false"><?php echo $lang['Sidebar']; ?></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="headers-tab" data-bs-toggle="tab" data-bs-target="#headers" type="button" role="tab" aria-controls="headers" aria-selected="false"><?php echo $lang['Headers']; ?></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="buttons-tab" data-bs-toggle="tab" data-bs-target="#buttons" type="button" role="tab" aria-controls="buttons" aria-selected="false"><?php echo $lang['Buttons']; ?></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="chatting-tab" data-bs-toggle="tab" data-bs-target="#chatting" type="button" role="tab" aria-controls="chatting" aria-selected="false"><?php echo $lang['Chatting']; ?></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="fonts-tab" data-bs-toggle="tab" data-bs-target="#fonts" type="button" role="tab" aria-controls="fonts" aria-selected="false"><?php echo $lang['Fonts']; ?></button>
                            </li>
                        </ul>

                        <div class="tab-content" id="themeTabsContent">
                            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                <?php $current_box_shadow = theme_box_shadow_sanitize($theme['box_shadow'] ?? theme_box_shadow_default()); ?>
                                <div class="row g-4 theme-settings-cards-row">
                                    <div class="col-md-4">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Primary Color']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="primary_color" class="form-label"><?php echo $lang['Primary Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" value="<?php echo htmlspecialchars($theme['primary_color'] ?? '#007bff'); ?>">
                                                        <input type="text" class="form-control color-text" id="primary_color_text" value="<?php echo htmlspecialchars($theme['primary_color'] ?? '#007bff'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="secondary_color" class="form-label"><?php echo $lang['Secondary Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color" value="<?php echo htmlspecialchars($theme['secondary_color'] ?? '#6c757d'); ?>">
                                                        <input type="text" class="form-control color-text" id="secondary_color_text" value="<?php echo htmlspecialchars($theme['secondary_color'] ?? '#6c757d'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="title_color" class="form-label"><?php echo $lang['Title Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="title_color" name="title_color" value="<?php echo htmlspecialchars($theme['title_color'] ?? '#000000'); ?>">
                                                        <input type="text" class="form-control color-text" id="title_color_text" value="<?php echo htmlspecialchars($theme['title_color'] ?? '#000000'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label for="title_font_weight" class="form-label"><?php echo $lang['Title Font Weight']; ?></label>
                                                    <select class="form-control" id="title_font_weight" name="title_font_weight">
                                                        <?php
                                                        $cur_title_weight = (string)($theme['title_font_weight'] ?? '600');
                                                        foreach (array('600', '800', '900') as $tw) :
                                                        ?>
                                                        <option value="<?php echo $tw; ?>"<?php echo $cur_title_weight === $tw ? ' selected' : ''; ?>><?php echo $tw; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Body Background Color']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="body_bg_color" class="form-label"><?php echo $lang['Body Background Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="body_bg_color" name="body_bg_color" value="<?php echo htmlspecialchars($theme['body_bg_color'] ?? '#ffffff'); ?>">
                                                        <input type="text" class="form-control color-text" id="body_bg_color_text" value="<?php echo htmlspecialchars($theme['body_bg_color'] ?? '#ffffff'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="body_font_color" class="form-label"><?php echo $lang['Body Font Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="body_font_color" name="body_font_color" value="<?php echo htmlspecialchars($theme['body_font_color'] ?? '#212529'); ?>">
                                                        <input type="text" class="form-control color-text" id="body_font_color_text" value="<?php echo htmlspecialchars($theme['body_font_color'] ?? '#212529'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group mb-0">
                                                    <label for="border_color" class="form-label"><?php echo $lang['Border Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="border_color" name="border_color" value="<?php echo htmlspecialchars($theme['border_color'] ?? '#dee2e6'); ?>">
                                                        <input type="text" class="form-control color-text" id="border_color_text" value="<?php echo htmlspecialchars($theme['border_color'] ?? '#dee2e6'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Card Body Color']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="card_body_color" class="form-label"><?php echo $lang['Card Body Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="card_body_color" name="card_body_color" value="<?php echo htmlspecialchars($theme['card_body_color'] ?? '#ffffff'); ?>">
                                                        <input type="text" class="form-control color-text" id="card_body_color_text" value="<?php echo htmlspecialchars($theme['card_body_color'] ?? '#ffffff'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="box_shadow_display" class="form-label"><?php echo $lang['Box Shadow']; ?></label>
                                                    <div class="color-picker-wrapper shadow-picker-wrapper" id="shadowFieldTrigger" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="boxShadowModal" title="<?php echo htmlspecialchars($lang['Shadow Configure'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <span class="shadow-preview-swatch" aria-hidden="true">
                                                            <span class="shadow-preview-swatch-inner" id="shadow_field_swatch"></span>
                                                        </span>
                                                        <input type="text" class="form-control color-text" id="box_shadow_display" value="<?php echo htmlspecialchars($current_box_shadow, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                                    </div>
                                                    <input type="hidden" name="box_shadow" id="box_shadow" value="<?php echo htmlspecialchars($current_box_shadow, ENT_QUOTES, 'UTF-8'); ?>">
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label for="card_border_radius" class="form-label"><?php echo $lang['Card Border Radius']; ?></label>
                                                    <input type="number" class="form-control" id="card_border_radius" name="card_border_radius" value="<?php echo htmlspecialchars(str_replace('px', '', $theme['card_border_radius'] ?? theme_card_border_radius_default())); ?>" placeholder="e.g., 10, 12, 0" min="0" max="50">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="sidebar" role="tabpanel" aria-labelledby="sidebar-tab">
                                <div class="row g-4 theme-settings-cards-row">
                                    <div class="col-md-6">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Sidebar']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="sidebar_bg_color" class="form-label"><?php echo $lang['Sidebar Background Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="sidebar_bg_color" name="sidebar_bg_color" value="<?php echo htmlspecialchars($theme['sidebar_bg_color']); ?>">
                                                        <input type="text" class="form-control color-text" id="sidebar_bg_color_text" value="<?php echo htmlspecialchars($theme['sidebar_bg_color']); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group mb-0">
                                                    <label for="sidebar_link_color" class="form-label"><?php echo $lang['Sidebar Link Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="sidebar_link_color" name="sidebar_link_color" value="<?php echo htmlspecialchars($theme['sidebar_link_color']); ?>">
                                                        <input type="text" class="form-control color-text" id="sidebar_link_color_text" value="<?php echo htmlspecialchars($theme['sidebar_link_color']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Sidebar Active Text Color']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="sidebar_active_bg_color" class="form-label"><?php echo $lang['Sidebar Active Background Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="sidebar_active_bg_color" name="sidebar_active_bg_color" value="<?php echo htmlspecialchars($theme['sidebar_active_bg_color']); ?>">
                                                        <input type="text" class="form-control color-text" id="sidebar_active_bg_color_text" value="<?php echo htmlspecialchars($theme['sidebar_active_bg_color']); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group mb-0">
                                                    <label for="sidebar_active_color" class="form-label"><?php echo $lang['Sidebar Active Text Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="sidebar_active_color" name="sidebar_active_color" value="<?php echo htmlspecialchars($theme['sidebar_active_color']); ?>">
                                                        <input type="text" class="form-control color-text" id="sidebar_active_color_text" value="<?php echo htmlspecialchars($theme['sidebar_active_color']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="headers" role="tabpanel" aria-labelledby="headers-tab">
                                <div class="row g-4 theme-settings-cards-row">
                                    <div class="col-md-6">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars(preg_replace('/\s+Color$/', '', $lang['Primary Header Color'])); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="primary_header_color" class="form-label"><?php echo $lang['Primary Header Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="primary_header_color" name="primary_header_color" value="<?php echo htmlspecialchars($theme['primary_header_color'] ?? '#222d32'); ?>">
                                                        <input type="text" class="form-control color-text" id="primary_header_color_text" value="<?php echo htmlspecialchars($theme['primary_header_color'] ?? '#222d32'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group mb-0">
                                                    <label for="primary_header_font_color" class="form-label"><?php echo $lang['Primary Header Font Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="primary_header_font_color" name="primary_header_font_color" value="<?php echo htmlspecialchars($theme['primary_header_font_color'] ?? '#ffffff'); ?>">
                                                        <input type="text" class="form-control color-text" id="primary_header_font_color_text" value="<?php echo htmlspecialchars($theme['primary_header_font_color'] ?? '#ffffff'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars(preg_replace('/\s+Color$/', '', $lang['Secondary Header Color'])); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="secondary_header_color" class="form-label"><?php echo $lang['Secondary Header Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="secondary_header_color" name="secondary_header_color" value="<?php echo htmlspecialchars($theme['secondary_header_color'] ?? '#ffffff'); ?>">
                                                        <input type="text" class="form-control color-text" id="secondary_header_color_text" value="<?php echo htmlspecialchars($theme['secondary_header_color'] ?? '#ffffff'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group mb-0">
                                                    <label for="secondary_header_font_color" class="form-label"><?php echo $lang['Secondary Header Font Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="secondary_header_font_color" name="secondary_header_font_color" value="<?php echo htmlspecialchars($theme['secondary_header_font_color'] ?? '#212529'); ?>">
                                                        <input type="text" class="form-control color-text" id="secondary_header_font_color_text" value="<?php echo htmlspecialchars($theme['secondary_header_font_color'] ?? '#212529'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="buttons" role="tabpanel" aria-labelledby="buttons-tab">
                                <?php
                                $cur_button_radius = (int)str_replace('px', '', $theme['button_border_radius'] ?? '4');
                                $button_theme_rows = array(
                                    array(
                                        'key' => 'primary',
                                        'title' => preg_replace('/\s+Color$/', '', $lang['Primary Button Color']),
                                        'preview_text' => 'Primary',
                                        'btn_class' => 'primary-btn',
                                        'color_label' => $lang['Primary Button Color'],
                                        'font_label' => $lang['Primary Button Font Color'],
                                        'color_default' => '#007bff',
                                        'font_default' => '#ffffff',
                                    ),
                                    array(
                                        'key' => 'secondary',
                                        'title' => preg_replace('/\s+Color$/', '', $lang['Secondary Button Color']),
                                        'preview_text' => 'Secondary',
                                        'btn_class' => 'btn-secondary',
                                        'color_label' => $lang['Secondary Button Color'],
                                        'font_label' => $lang['Secondary Button Font Color'],
                                        'color_default' => '#6c757d',
                                        'font_default' => '#212529',
                                    ),
                                    array(
                                        'key' => 'border',
                                        'title' => preg_replace('/\s+Color$/', '', $lang['Border Button Color']),
                                        'preview_text' => 'Border',
                                        'btn_class' => 'border-btn-a',
                                        'color_label' => $lang['Border Button Color'],
                                        'font_label' => $lang['Border Button Font Color'],
                                        'color_default' => '#dee2e6',
                                        'font_default' => '#212529',
                                    ),
                                );
                                ?>
                                <div class="row g-4 theme-buttons-cards-row">
                                    <?php foreach ($button_theme_rows as $button_row) :
                                        $color_field = $button_row['key'] . '_button_color';
                                        $font_field = $button_row['key'] . '_button_font_color';
                                        $color_value = htmlspecialchars($theme[$color_field] ?? $button_row['color_default']);
                                        $font_value = htmlspecialchars($theme[$font_field] ?? $button_row['font_default']);
                                    ?>
                                    <div class="col-md-4">
                                        <div class="settings-card theme-button-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($button_row['title']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="theme-button-preview mb-3">
                                                    <button type="button" class="btn w-100 <?php echo htmlspecialchars($button_row['btn_class']); ?> theme-button-preview-btn" id="preview_<?php echo htmlspecialchars($button_row['key']); ?>_btn" data-button-key="<?php echo htmlspecialchars($button_row['key']); ?>">
                                                        <?php echo htmlspecialchars($button_row['preview_text']); ?>
                                                    </button>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="<?php echo htmlspecialchars($color_field); ?>" class="form-label"><?php echo htmlspecialchars($button_row['color_label']); ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="<?php echo htmlspecialchars($color_field); ?>" name="<?php echo htmlspecialchars($color_field); ?>" value="<?php echo $color_value; ?>">
                                                        <input type="text" class="form-control color-text" id="<?php echo htmlspecialchars($color_field); ?>_text" value="<?php echo $color_value; ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="<?php echo htmlspecialchars($font_field); ?>" class="form-label"><?php echo htmlspecialchars($button_row['font_label']); ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="<?php echo htmlspecialchars($font_field); ?>" name="<?php echo htmlspecialchars($font_field); ?>" value="<?php echo $font_value; ?>">
                                                        <input type="text" class="form-control color-text" id="<?php echo htmlspecialchars($font_field); ?>_text" value="<?php echo $font_value; ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label for="<?php echo $button_row['key'] === 'primary' ? 'button_border_radius' : 'button_border_radius_' . htmlspecialchars($button_row['key']); ?>" class="form-label"><?php echo $lang['Button Border Radius']; ?></label>
                                                    <input type="number" class="form-control button-border-radius-input" id="<?php echo $button_row['key'] === 'primary' ? 'button_border_radius' : 'button_border_radius_' . htmlspecialchars($button_row['key']); ?>"<?php echo $button_row['key'] === 'primary' ? ' name="button_border_radius"' : ''; ?> value="<?php echo (int)$cur_button_radius; ?>" placeholder="e.g., 4, 8, 0" min="0" max="50">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="chatting" role="tabpanel" aria-labelledby="chatting-tab">
                                <div class="row g-4 theme-settings-cards-row">
                                    <div class="col-md-4">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Chat Card Color']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="chat_card_color" class="form-label"><?php echo $lang['Chat Card Color Primary']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="chat_card_color" name="chat_card_color" value="<?php echo htmlspecialchars($theme['chat_card_color'] ?? '#ffffff'); ?>">
                                                        <input type="text" class="form-control color-text" id="chat_card_color_text" value="<?php echo htmlspecialchars($theme['chat_card_color'] ?? '#ffffff'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="chat_card_secondary_color" class="form-label"><?php echo $lang['Chat Card Color Secondary']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="chat_card_secondary_color" name="chat_card_secondary_color" value="<?php echo htmlspecialchars($theme['chat_card_secondary_color'] ?? '#e6f0f9'); ?>">
                                                        <input type="text" class="form-control color-text" id="chat_card_secondary_color_text" value="<?php echo htmlspecialchars($theme['chat_card_secondary_color'] ?? '#e6f0f9'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group mb-0">
                                                    <label for="chat_body_color" class="form-label"><?php echo $lang['Chat Body Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="chat_body_color" name="chat_body_color" value="<?php echo htmlspecialchars($theme['chat_body_color'] ?? '#f5f8fa'); ?>">
                                                        <input type="text" class="form-control color-text" id="chat_body_color_text" value="<?php echo htmlspecialchars($theme['chat_body_color'] ?? '#f5f8fa'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Chat Title Color']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="chat_title_color" class="form-label"><?php echo $lang['Chat Title Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="chat_title_color" name="chat_title_color" value="<?php echo htmlspecialchars($theme['chat_title_color'] ?? '#212529'); ?>">
                                                        <input type="text" class="form-control color-text" id="chat_title_color_text" value="<?php echo htmlspecialchars($theme['chat_title_color'] ?? '#212529'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group">
                                                    <label for="chat_time_text_color" class="form-label"><?php echo $lang['Chat Time Text Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="chat_time_text_color" name="chat_time_text_color" value="<?php echo htmlspecialchars($theme['chat_time_text_color'] ?? '#757575'); ?>">
                                                        <input type="text" class="form-control color-text" id="chat_time_text_color_text" value="<?php echo htmlspecialchars($theme['chat_time_text_color'] ?? '#757575'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="chat_font_size" class="form-label"><?php echo $lang['Chat Font Size']; ?></label>
                                                    <input type="text" class="form-control" id="chat_font_size" name="chat_font_size" value="<?php echo htmlspecialchars($theme['chat_font_size'] ?? '14px'); ?>" placeholder="14px" maxlength="16">
                                                    <small class="form-text text-muted"><?php echo htmlspecialchars($lang['Chat Font Size help']); ?></small>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label for="chat_font_weight" class="form-label"><?php echo $lang['Chat Font Weight']; ?></label>
                                                    <input type="text" class="form-control" id="chat_font_weight" name="chat_font_weight" value="<?php echo htmlspecialchars($theme['chat_font_weight'] ?? '400'); ?>" placeholder="400" maxlength="12">
                                                    <small class="form-text text-muted"><?php echo htmlspecialchars($lang['Chat Font Weight help']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="settings-card theme-settings-type-card mb-0">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Chat Buttons Color']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="color-picker-group">
                                                    <label for="chat_buttons_color" class="form-label"><?php echo $lang['Chat Buttons Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="chat_buttons_color" name="chat_buttons_color" value="<?php echo htmlspecialchars($theme['chat_buttons_color'] ?? '#0094ff'); ?>">
                                                        <input type="text" class="form-control color-text" id="chat_buttons_color_text" value="<?php echo htmlspecialchars($theme['chat_buttons_color'] ?? '#0094ff'); ?>">
                                                    </div>
                                                </div>
                                                <div class="color-picker-group mb-0">
                                                    <label for="chat_buttons_text_color" class="form-label"><?php echo $lang['Chat Buttons Text Color']; ?></label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" class="form-control form-control-color" id="chat_buttons_text_color" name="chat_buttons_text_color" value="<?php echo htmlspecialchars($theme['chat_buttons_text_color'] ?? '#ffffff'); ?>">
                                                        <input type="text" class="form-control color-text" id="chat_buttons_text_color_text" value="<?php echo htmlspecialchars($theme['chat_buttons_text_color'] ?? '#ffffff'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="fonts" role="tabpanel" aria-labelledby="fonts-tab">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="fontFamilyDropdownBtn"><?php echo $lang['Select Font']; ?></label>
                                            <div class="dropdown w-100" id="fontFamilyDropdown">
                                                <button class="btn border-btn dropdown-toggle w-100 text-start" type="button" id="fontFamilyDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span id="selectedFontText"><?php echo htmlspecialchars($cur_font_display_label); ?></span>
                                                </button>
                                                <ul class="dropdown-menu w-100" style="max-height:320px;overflow-y:auto;" id="fontDropdownMenu">
                                                    <li class="px-3 py-2" style="position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border-color);">
                                                        <input type="text" id="fontSearchInput" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Font dropdown search placeholder']); ?>" autocomplete="off" style="width: 100%;">
                                                    </li>
                                                    <li class="px-3 py-1" id="noFontsFound" style="display: none; color: #999; font-style: italic;">
                                                        <?php echo htmlspecialchars($lang['No fonts match search']); ?>
                                                    </li>
                                                    <div id="fontOptionsList">
                                                        <li><h6 class="dropdown-header mb-0"><?php echo htmlspecialchars($lang['Font source default']); ?></h6></li>
                                                        <?php
                                                        $s0 = strtolower($lang['Font default Lato'] . ' default lato google');
                                                        ?>
                                                        <li class="font-option-item" data-search-text="<?php echo htmlspecialchars($s0); ?>">
                                                            <a class="dropdown-item font-option<?php echo $cur_font_choice === 'default' ? ' active' : ''; ?>" href="#" data-value="default">
                                                                <div class="font-option-title"><?php echo htmlspecialchars($lang['Font default Lato']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($lang['Font source default']); ?></small>
                                                            </a>
                                                        </li>
                                                        <li><h6 class="dropdown-header mb-0"><?php echo htmlspecialchars($lang['Font source websafe']); ?></h6></li>
                                                        <?php foreach ($wmap_fonts as $wk => $wmeta) {
                                                            $val = 'websafe:' . $wk;
                                                            $st = strtolower($wmeta['label'] . ' ' . $lang['Font source websafe'] . ' web safe');
                                                            $act = ($cur_font_choice === $val) ? ' active' : '';
                                                            ?>
                                                        <li class="font-option-item" data-search-text="<?php echo htmlspecialchars($st); ?>">
                                                            <a class="dropdown-item font-option<?php echo $act; ?>" href="#" data-value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <div class="font-option-title"><?php echo htmlspecialchars($wmeta['label']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($lang['Font source websafe']); ?></small>
                                                            </a>
                                                        </li>
                                                        <?php } ?>
                                                        <li><h6 class="dropdown-header mb-0"><?php echo htmlspecialchars($lang['Font source google']); ?></h6></li>
                                                        <?php foreach (google_fonts_curated_list() as $gname) {
                                                            $val = 'google:' . $gname;
                                                            $st = strtolower($gname . ' google font');
                                                            $act = ($cur_font_choice === $val) ? ' active' : '';
                                                            ?>
                                                        <li class="font-option-item" data-search-text="<?php echo htmlspecialchars($st); ?>">
                                                            <a class="dropdown-item font-option<?php echo $act; ?>" href="#" data-value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <div class="font-option-title"><?php echo htmlspecialchars($gname); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($lang['Font source google']); ?></small>
                                                            </a>
                                                        </li>
                                                        <?php } ?>
                                                        <li><h6 class="dropdown-header mb-0"><?php echo htmlspecialchars($lang['Font source uploaded']); ?></h6></li>
                                                        <?php
                                                        if (empty($custom_fonts_rows)) {
                                                            ?>
                                                        <li class="font-empty-msg px-3 py-2 text-muted small"><?php echo htmlspecialchars($lang['No custom fonts yet']); ?></li>
                                                            <?php
                                                        } else {
                                                            foreach ($custom_fonts_rows as $cfr) {
                                                                $cid = (int)$cfr['id'];
                                                                $val = 'custom:' . $cid;
                                                                $it = !empty($cfr['is_italic']) ? ' (italic)' : '';
                                                                $title = $cfr['family_css'] . ' — ' . $cfr['font_weight'] . $it;
                                                                $st = strtolower($cfr['family_css'] . ' ' . $cfr['font_weight'] . ' uploaded custom woff woff2' . $it);
                                                                $act = ($cur_font_choice === $val) ? ' active' : '';
                                                                ?>
                                                        <li class="font-option-item" data-search-text="<?php echo htmlspecialchars($st); ?>">
                                                            <a class="dropdown-item font-option<?php echo $act; ?>" href="#" data-value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <div class="font-option-title"><?php echo htmlspecialchars($title); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($lang['Font source uploaded']); ?></small>
                                                            </a>
                                                        </li>
                                                                <?php
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </ul>
                                                <input type="hidden" name="ui_font_choice" id="ui_font_choice" value="<?php echo htmlspecialchars($cur_font_choice, ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </div>
                                        <div class="mt-3" id="googleWeightsWrap">
                                            <label for="ui_google_font_weights" class="form-label"><?php echo $lang['Google font weights']; ?></label>
                                            <input type="text" class="form-control" name="ui_google_font_weights" id="ui_google_font_weights" value="<?php echo $cur_google_weights; ?>" placeholder="400;700;900">
                                            <small class="form-text text-muted"><?php echo $lang['Google font weights help']; ?></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <div class="form-group">
                                            <div class="settings-card">
                                                <div class="card-header">
                                                    <h4>
                                                        <?php echo ts_icon('upload', 'card-icon'); ?>
                                                        <?php echo htmlspecialchars($lang['Upload custom font']); ?>
                                                    </h4>
                                                    <p><?php echo htmlspecialchars($lang['Upload custom font help']); ?></p>
                                                </div>
                                                <div class="card-body">
                                                    <div id="customFontUploadFields">
                                                        <div class="mb-2">
                                                            <label class="form-label" for="custom_font_family"><?php echo $lang['Custom font family name']; ?></label>
                                                            <input type="text" class="form-control" name="custom_font_family" id="custom_font_family" maxlength="120" pattern="[A-Za-z0-9 _-]+" title="<?php echo htmlspecialchars($lang['Custom font family name']); ?>">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label" for="custom_font_weight"><?php echo $lang['Custom font weight']; ?></label>
                                                            <input type="number" class="form-control" name="custom_font_weight" id="custom_font_weight" value="400" min="1" max="1000" step="1">
                                                        </div>
                                                        <div class="permission-item d-flex col-gap align-items-center theme-font-italic-toggle">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" type="checkbox" name="custom_font_italic" id="custom_font_italic" value="1">
                                                                <label class="tgl-btn" for="custom_font_italic"></label>
                                                            </div>
                                                            <label for="custom_font_italic" class="permission-label mb-0"><?php echo $lang['Custom font italic']; ?></label>
                                                        </div>
                                                        <div class="mb-3 theme-font-file-picker">
                                                            <label class="form-label" for="custom_font_file"><?php echo $lang['Custom font file']; ?></label>
                                                            <div class="theme-font-file-picker-inner d-flex flex-wrap align-items-center gap-2">
                                                                <input type="file" name="custom_font_file" id="custom_font_file" class="theme-font-file-input-sr" accept=".woff,.woff2,font/woff,font/woff2,application/font-woff,application/font-woff2">
                                                                <label for="custom_font_file" class="btn border-btn mb-0 flex-shrink-0"><?php echo htmlspecialchars($lang['Font file choose']); ?></label>
                                                                <span class="text-muted small text-truncate flex-grow-1" id="custom_font_file_name" style="min-width: 0;" data-empty="<?php echo htmlspecialchars($lang['Font file none'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lang['Font file none']); ?></span>
                                                            </div>
                                                        </div>
                                                        <button type="submit" name="upload_custom_font" value="1" class="btn primary-btn" id="upload_custom_font_btn"><?php echo $lang['Upload font']; ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex col-gap-10">
                            <button type="submit" name="update_theme" class="btn primary-btn">
                               <?php echo $lang['Save Changes']; ?>
                            </button>
                            <button type="submit" name="reset_theme" class="btn btn-secondary ms-2" onclick="return confirm('Are you sure you want to reset all theme settings to default values? This action cannot be undone.')">
                               <?php echo $lang['Reset to Default']; ?>
                            </button>
                        </div>
                    </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="boxShadowModal" tabindex="-1" aria-labelledby="boxShadowModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered box-shadow-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="boxShadowModalLabel"><?php echo $lang['Box Shadow']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang['Close']); ?>">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body modal-height">
                <div class="shadow-builder-panel" id="shadowBuilderPanel" data-initial-shadow="<?php echo htmlspecialchars($current_box_shadow, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="shadow-builder-layout">
                        <div class="shadow-builder-controls">
                    <div class="shadow-control-row">
                        <label for="shadow_preset" class="shadow-control-label"><?php echo $lang['Shadow Preset']; ?></label>
                        <select class="form-control shadow-preset-select" id="shadow_preset">
                            <option value="custom"><?php echo $lang['Shadow Preset Custom']; ?></option>
                            <option value="none"><?php echo $lang['Shadow Preset None']; ?></option>
                            <option value="soft"><?php echo $lang['Shadow Preset Soft']; ?></option>
                            <option value="medium"><?php echo $lang['Shadow Preset Medium']; ?></option>
                            <option value="strong"><?php echo $lang['Shadow Preset Strong']; ?></option>
                            <option value="default"><?php echo isset($lang['Shadow Preset Default']) ? $lang['Shadow Preset Default'] : 'Default'; ?></option>
                        </select>
                    </div>
                    <div class="shadow-control-row">
                        <div class="shadow-control-head">
                            <label for="shadow_x" class="shadow-control-label"><?php echo $lang['Shadow Horizontal']; ?></label>
                            <span class="shadow-control-value" id="shadow_x_val">0px</span>
                        </div>
                        <input type="range" class="shadow-range" id="shadow_x" min="0" max="50" step="1" value="0">
                    </div>
                    <div class="shadow-control-row">
                        <div class="shadow-control-head">
                            <label for="shadow_y" class="shadow-control-label"><?php echo $lang['Shadow Vertical']; ?></label>
                            <span class="shadow-control-value" id="shadow_y_val">3px</span>
                        </div>
                        <input type="range" class="shadow-range" id="shadow_y" min="0" max="50" step="1" value="3">
                    </div>
                    <div class="shadow-control-row">
                        <div class="shadow-control-head">
                            <label for="shadow_blur" class="shadow-control-label"><?php echo $lang['Shadow Blur']; ?></label>
                            <span class="shadow-control-value" id="shadow_blur_val">13px</span>
                        </div>
                        <input type="range" class="shadow-range" id="shadow_blur" min="0" max="60" step="1" value="13">
                    </div>
                    <div class="shadow-control-row">
                        <div class="shadow-control-head">
                            <label for="shadow_spread" class="shadow-control-label"><?php echo $lang['Shadow Spread']; ?></label>
                            <span class="shadow-control-value" id="shadow_spread_val">-5px</span>
                        </div>
                        <input type="range" class="shadow-range" id="shadow_spread" min="-20" max="30" step="1" value="-5">
                    </div>
                    <div class="shadow-control-row">
                        <label for="shadow_color" class="shadow-control-label"><?php echo $lang['Shadow Color']; ?></label>
                        <div class="color-picker-wrapper shadow-color-picker">
                            <input type="color" class="form-control form-control-color" id="shadow_color" value="#000000">
                            <input type="text" class="form-control color-text" id="shadow_color_text" value="#000000">
                        </div>
                    </div>
                    <div class="shadow-control-row">
                        <div class="shadow-control-head">
                            <label for="shadow_opacity" class="shadow-control-label"><?php echo $lang['Shadow Opacity']; ?></label>
                            <span class="shadow-control-value" id="shadow_opacity_val">0.1</span>
                        </div>
                        <input type="range" class="shadow-range" id="shadow_opacity" min="0" max="1" step="0.05" value="0.1">
                    </div>
                    <div class="shadow-control-row shadow-inset-row shadow-control-row-last">
                        <label for="shadow_inset" class="shadow-control-label mb-0"><?php echo $lang['Shadow Inset']; ?></label>
                        <div class="checkbox-wrapper-6">
                            <input class="tgl tgl-light" id="shadow_inset" type="checkbox">
                            <label class="tgl-btn" for="shadow_inset"></label>
                        </div>
                    </div>
                        </div>
                        <div class="shadow-builder-preview-col">
                            <label class="shadow-preview-label"><?php echo $lang['Shadow Preview']; ?></label>
                            <div class="shadow-preview-wrap">
                                <div class="shadow-preview-card" id="shadow_preview_card">
                                    <span><?php echo $lang['Shadow Preview']; ?></span>
                                </div>
                            </div>
                            <small class="form-text text-muted shadow-css-output" id="box_shadow_css_preview"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn primary-btn" data-bs-dismiss="modal"><?php echo $lang['Done']; ?></button>
            </div>
        </div>
    </div>
</div>
<style>
.theme-font-italic-toggle {
    margin-top: 15px;
    margin-bottom: 15px;
}
.theme-font-file-picker-inner {
    position: relative;
    border: 1px solid var(--border-color);
    background: var(--card-body-color);
    border-radius: var(--button-border-radius, 8px);
    padding: 10px 12px;
    gap: 10px;
}
/* Hide native file control (project uses Bootstrap 4 sr-only, not visually-hidden) */
.theme-font-file-picker input.theme-font-file-input-sr,
.theme-font-file-picker input[type="file"]#custom_font_file {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
    opacity: 0 !important;
}
.theme-font-file-picker label[for="custom_font_file"].border-btn {
    cursor: pointer;
}
.box-shadow-builder-wrap {
    margin-top: 8px;
}
.shadow-picker-wrapper {
    cursor: pointer;
}
.shadow-picker-wrapper:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
    border-radius: 4px;
}
.shadow-preview-swatch {
    width: 45px;
    height: 45px;
    min-width: 45px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    background: var(--body-bg-color);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}
.shadow-preview-swatch-inner {
    width: 26px;
    height: 26px;
    border-radius: 3px;
    background: var(--card-body-color);
    border: 1px solid var(--border-color);
}
.box-shadow-modal-dialog {
    max-width: 820px;
    width: calc(100% - 2rem);
}
.shadow-builder-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 28px;
    align-items: start;
}
.shadow-builder-controls {
    min-width: 0;
}
.shadow-builder-preview-col {
    position: sticky;
    top: 0;
    min-width: 0;
}
.shadow-preview-label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: var(--title-color);
    margin-bottom: 12px;
}
.shadow-builder-panel {
    border: none;
    border-radius: 0;
    background: transparent;
    padding: 0;
    max-width: none;
}
.shadow-control-row {
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}
.shadow-control-row-last {
    border-bottom: none;
}
.shadow-control-row:last-of-type {
    border-bottom: 1px solid var(--border-color);
}
.shadow-builder-controls .shadow-control-row-last {
    border-bottom: none;
}
.shadow-control-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.shadow-control-label {
    font-size: 14px;
    font-weight: 500;
    color: var(--title-color);
    margin-bottom: 0;
}
.shadow-control-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary-color);
}
.shadow-range {
    width: 100%;
    height: 6px;
    margin: 4px 0 0;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    background: transparent;
    accent-color: var(--primary-color);
}
.shadow-range:focus {
    outline: none;
}
.shadow-range::-webkit-slider-runnable-track {
    height: 6px;
    border-radius: 999px;
    background: var(--border-color);
}
.shadow-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    margin-top: -5px;
    border-radius: 50%;
    background: var(--primary-color);
    border: 2px solid var(--card-body-color);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
    cursor: pointer;
}
.shadow-range::-moz-range-track {
    height: 6px;
    border-radius: 999px;
    background: var(--border-color);
    border: 0;
}
.shadow-range::-moz-range-thumb {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--primary-color);
    border: 2px solid var(--card-body-color);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
    cursor: pointer;
}
.shadow-color-picker {
    margin-top: 8px;
}
.shadow-inset-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.shadow-preset-select {
    margin-top: 8px;
    font-size: 14px;
}
.shadow-preview-wrap {
    padding: 0;
}
.shadow-preview-card {
    min-height: 220px;
    width: 100%;
    border-radius: 10px;
    background: var(--body-bg-color);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--body-font-color);
    font-size: 13px;
    font-weight: 500;
}
.shadow-css-output {
    display: block;
    margin-top: 12px;
    word-break: break-word;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}
@media (max-width: 767px) {
    .shadow-builder-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .shadow-builder-preview-col {
        position: static;
    }
    .shadow-preview-card {
        min-height: 120px;
    }
}
.shadow-builder-panel.is-none .shadow-range,
.shadow-builder-panel.is-none .shadow-color-picker,
.shadow-builder-panel.is-none .shadow-inset-row {
    opacity: 0.45;
    pointer-events: none;
}
.theme-buttons-cards-row,
.theme-settings-cards-row {
    align-items: flex-start;
}
.theme-button-type-card.settings-card,
.theme-settings-type-card.settings-card {
    margin-bottom: 0;
    height: auto;
}
.theme-button-preview {
    width: 100%;
}
.theme-button-preview .theme-button-preview-btn {
    width: 100%;
    min-width: 0;
    justify-content: center;
    pointer-events: none;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Color picker functionality
    const colorFields = [
        'primary_color',
        'secondary_color',
        'body_bg_color',
        'body_font_color',
        'title_color',
        'border_color',
        'card_body_color',
        'primary_header_color',
        'secondary_header_color',
        'primary_header_font_color',
        'secondary_header_font_color',
        'sidebar_bg_color',
        'sidebar_link_color',
        'sidebar_active_bg_color',
        'sidebar_active_color',
        'primary_button_color',
        'secondary_button_color',
        'border_button_color',
        'primary_button_font_color',
        'secondary_button_font_color',
        'border_button_font_color',
        'chat_card_color',
        'chat_card_secondary_color',
        'chat_title_color',
        'chat_body_color',
        'chat_buttons_color',
        'chat_buttons_text_color',
        'chat_time_text_color'
    ];

    colorFields.forEach(function(fieldId) {
        const colorInput = document.getElementById(fieldId);
        const textInput = document.getElementById(fieldId + '_text');
        if (!colorInput || !textInput) return;

        colorInput.addEventListener('input', function() {
            textInput.value = colorInput.value;
            updateThemeButtonPreviews();
        });

        textInput.addEventListener('input', function() {
            if(/^#([0-9A-Fa-f]{3,8})$/.test(textInput.value)) {
                colorInput.value = textInput.value;
                updateThemeButtonPreviews();
            }
        });
    });

    function updateThemeButtonPreviews() {
        var radiusInput = document.querySelector('input[name="button_border_radius"]');
        var radius = (radiusInput ? radiusInput.value : '4') + 'px';
        var previewConfigs = [
            {
                key: 'primary',
                bgField: 'primary_button_color',
                fontField: 'primary_button_font_color',
                useBorderStyle: false
            },
            {
                key: 'secondary',
                bgField: 'secondary_button_color',
                fontField: 'secondary_button_font_color',
                useBorderStyle: false
            },
            {
                key: 'border',
                bgField: 'border_button_color',
                fontField: 'border_button_font_color',
                useBorderStyle: true
            }
        ];

        previewConfigs.forEach(function(config) {
            var btn = document.getElementById('preview_' + config.key + '_btn');
            var bgInput = document.getElementById(config.bgField);
            var fontInput = document.getElementById(config.fontField);
            if (!btn || !bgInput || !fontInput) {
                return;
            }

            btn.style.borderRadius = radius;
            btn.style.color = fontInput.value;

            if (config.useBorderStyle) {
                btn.style.background = 'transparent';
                btn.style.border = '1px solid ' + bgInput.value;
            } else {
                btn.style.background = bgInput.value;
                btn.style.border = 'none';
            }
        });
    }

    var buttonRadiusInputs = document.querySelectorAll('.button-border-radius-input');
    buttonRadiusInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            buttonRadiusInputs.forEach(function(other) {
                if (other !== input) {
                    other.value = input.value;
                }
            });
            updateThemeButtonPreviews();
        });
    });

    updateThemeButtonPreviews();

    (function initBoxShadowBuilder() {
        var panel = document.getElementById('shadowBuilderPanel');
        var hiddenInput = document.getElementById('box_shadow');
        var displayInput = document.getElementById('box_shadow_display');
        var fieldSwatch = document.getElementById('shadow_field_swatch');
        var fieldTrigger = document.getElementById('shadowFieldTrigger');
        var shadowModalEl = document.getElementById('boxShadowModal');
        var previewCard = document.getElementById('shadow_preview_card');
        var cssPreview = document.getElementById('box_shadow_css_preview');
        var presetSelect = document.getElementById('shadow_preset');
        if (!panel || !hiddenInput || !previewCard) {
            return;
        }

        var shadowModal = shadowModalEl && typeof bootstrap !== 'undefined' ? bootstrap.Modal.getOrCreateInstance(shadowModalEl) : null;

        if (fieldTrigger && shadowModal) {
            fieldTrigger.addEventListener('click', function() {
                shadowModal.show();
            });
            fieldTrigger.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    shadowModal.show();
                }
            });
        }

        var controls = {
            x: document.getElementById('shadow_x'),
            y: document.getElementById('shadow_y'),
            blur: document.getElementById('shadow_blur'),
            spread: document.getElementById('shadow_spread'),
            opacity: document.getElementById('shadow_opacity'),
            color: document.getElementById('shadow_color'),
            colorText: document.getElementById('shadow_color_text'),
            inset: document.getElementById('shadow_inset')
        };
        var valueLabels = {
            x: document.getElementById('shadow_x_val'),
            y: document.getElementById('shadow_y_val'),
            blur: document.getElementById('shadow_blur_val'),
            spread: document.getElementById('shadow_spread_val'),
            opacity: document.getElementById('shadow_opacity_val')
        };

        var presets = {
            none: { none: true, x: 0, y: 0, blur: 0, spread: 0, color: '#000000', opacity: 0.75, inset: false },
            soft: { none: false, x: 0, y: 4, blur: 12, spread: 0, color: '#000000', opacity: 0.08, inset: false },
            medium: { none: false, x: 0, y: 10, blur: 15, spread: -3, color: '#000000', opacity: 0.1, inset: false },
            strong: { none: false, x: 10, y: 10, blur: 5, spread: 0, color: '#000000', opacity: 0.75, inset: false },
            default: { none: false, x: 0, y: 3, blur: 13, spread: -5, color: '#000000', opacity: 0.1, inset: false }
        };

        function hexToRgb(hex) {
            hex = (hex || '#000000').replace('#', '');
            if (hex.length === 3) {
                hex = hex.split('').map(function(c) { return c + c; }).join('');
            }
            var n = parseInt(hex, 16);
            if (isNaN(n)) {
                return { r: 0, g: 0, b: 0 };
            }
            return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
        }

        function rgbToHex(r, g, b) {
            return '#' + [r, g, b].map(function(v) {
                var h = Math.max(0, Math.min(255, v)).toString(16);
                return h.length === 1 ? '0' + h : h;
            }).join('');
        }

        function parseLength(val, fallback) {
            if (typeof val !== 'string') {
                return fallback;
            }
            var n = parseInt(val, 10);
            return isNaN(n) ? fallback : n;
        }

        function parseBoxShadow(css) {
            var defaults = { none: false, x: 0, y: 3, blur: 13, spread: -5, color: '#000000', opacity: 0.1, inset: false };
            if (!css || String(css).trim().toLowerCase() === 'none') {
                defaults.none = true;
                return defaults;
            }

            // Do not split on commas — rgba() contains commas (e.g. rgba(0, 0, 0, 0.5)).
            var str = String(css).trim();
            if (/^inset\s/i.test(str)) {
                defaults.inset = true;
                str = str.replace(/^inset\s/i, '');
            }

            var rgba = str.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+))?\s*\)/i);
            if (!rgba) {
                rgba = str.match(/rgb\(\s*(\d+)\s+(\d+)\s+(\d+)\s*\/\s*([\d.]+)\s*\)/i);
            }
            if (rgba) {
                defaults.color = rgbToHex(parseInt(rgba[1], 10), parseInt(rgba[2], 10), parseInt(rgba[3], 10));
                defaults.opacity = rgba[4] !== undefined ? formatShadowOpacity(rgba[4]) : 1;
                str = str.replace(/rgba?\([^)]+\)/i, '').trim();
                str = str.replace(/rgb\(\s*\d+\s+\d+\s+\d+\s*\/\s*[\d.]+\s*\)/i, '').trim();
            } else {
                var hex = str.match(/#([0-9a-f]{3,8})/i);
                if (hex) {
                    defaults.color = '#' + hex[1];
                    defaults.opacity = 1;
                    str = str.replace(/#[0-9a-f]{3,8}/i, '').trim();
                }
            }

            var parts = str.trim().split(/\s+/).filter(Boolean);
            if (parts.length >= 1) defaults.x = parseLength(parts[0], defaults.x);
            if (parts.length >= 2) defaults.y = parseLength(parts[1], defaults.y);
            if (parts.length >= 3) defaults.blur = parseLength(parts[2], defaults.blur);
            if (parts.length >= 4) defaults.spread = parseLength(parts[3], defaults.spread);
            return defaults;
        }

        function formatShadowOpacity(val) {
            var n = Math.max(0, Math.min(1, parseFloat(val) || 0));
            return Math.round(n * 100) / 100;
        }

        function buildBoxShadow(state) {
            if (state.none) {
                return 'none';
            }
            var rgb = hexToRgb(state.color);
            var inset = state.inset ? 'inset ' : '';
            var opacity = formatShadowOpacity(state.opacity);
            return inset + state.x + 'px ' + state.y + 'px ' + state.blur + 'px ' + state.spread + 'px rgb(' + rgb.r + ' ' + rgb.g + ' ' + rgb.b + ' / ' + opacity + ')';
        }

        function getStateFromControls() {
            return {
                none: presetSelect && presetSelect.value === 'none',
                x: controls.x ? (parseInt(controls.x.value, 10) || 0) : 0,
                y: controls.y ? (parseInt(controls.y.value, 10) || 0) : 0,
                blur: controls.blur ? (parseInt(controls.blur.value, 10) || 0) : 0,
                spread: controls.spread ? (parseInt(controls.spread.value, 10) || 0) : 0,
                opacity: controls.opacity ? formatShadowOpacity(controls.opacity.value) : 0.1,
                color: (controls.color && controls.color.value) ? controls.color.value : '#000000',
                inset: !!(controls.inset && controls.inset.checked)
            };
        }

        function applyStateToControls(state) {
            if (controls.x) controls.x.value = state.x;
            if (controls.y) controls.y.value = state.y;
            if (controls.blur) controls.blur.value = state.blur;
            if (controls.spread) controls.spread.value = state.spread;
            if (controls.opacity) controls.opacity.value = formatShadowOpacity(state.opacity);
            if (controls.color) controls.color.value = state.color;
            if (controls.colorText) {
                controls.colorText.value = state.color;
            }
            if (controls.inset) {
                controls.inset.checked = !!state.inset;
            }
            panel.classList.toggle('is-none', !!state.none);
        }

        function setPresetSelectValue(key) {
            if (!presetSelect) {
                return;
            }
            if (presetSelect.querySelector('option[value="' + key + '"]')) {
                presetSelect.value = key;
            } else {
                presetSelect.value = 'custom';
            }
        }

        function updateValueLabels(state) {
            if (valueLabels.x) valueLabels.x.textContent = state.x + 'px';
            if (valueLabels.y) valueLabels.y.textContent = state.y + 'px';
            if (valueLabels.blur) valueLabels.blur.textContent = state.blur + 'px';
            if (valueLabels.spread) valueLabels.spread.textContent = state.spread + 'px';
            if (valueLabels.opacity) {
                valueLabels.opacity.textContent = Number(state.opacity).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
            }
        }

        function updateFieldDisplay(state, css) {
            if (fieldSwatch) {
                fieldSwatch.style.boxShadow = state.none ? 'none' : css;
            }
            if (displayInput) {
                if (state.none) {
                    var noneOpt = presetSelect ? presetSelect.querySelector('option[value="none"]') : null;
                    displayInput.value = noneOpt ? noneOpt.textContent : 'none';
                    return;
                }
                if (presetSelect && presetSelect.value !== 'custom') {
                    var selectedOpt = presetSelect.options[presetSelect.selectedIndex];
                    displayInput.value = selectedOpt ? selectedOpt.textContent : css;
                    return;
                }
                displayInput.value = css;
            }
        }

        function syncShadowOutput() {
            var state = getStateFromControls();
            var css = buildBoxShadow(state);
            hiddenInput.value = css;
            panel.setAttribute('data-initial-shadow', css);
            previewCard.style.boxShadow = state.none ? 'none' : css;
            if (cssPreview) {
                cssPreview.textContent = css;
            }
            updateValueLabels(state);
            updateFieldDisplay(state, css);
            panel.classList.toggle('is-none', !!state.none);
        }

        function matchPreset(state) {
            if (state.none) return 'none';
            var key;
            for (key in presets) {
                if (key === 'none') continue;
                var p = presets[key];
                if (p.x === state.x && p.y === state.y && p.blur === state.blur && p.spread === state.spread &&
                    Math.abs(p.opacity - state.opacity) < 0.001 && p.color.toLowerCase() === state.color.toLowerCase() && !!p.inset === !!state.inset) {
                    return key;
                }
            }
            return 'custom';
        }

        var initial = parseBoxShadow(panel.getAttribute('data-initial-shadow') || hiddenInput.value);
        applyStateToControls(initial);
        setPresetSelectValue(matchPreset(initial));
        syncShadowOutput();

        ['x', 'y', 'blur', 'spread', 'opacity'].forEach(function(key) {
            if (!controls[key]) {
                return;
            }
            controls[key].addEventListener('input', function() {
                if (presetSelect) presetSelect.value = 'custom';
                syncShadowOutput();
            });
        });

        if (controls.inset) {
            controls.inset.addEventListener('change', function() {
                if (presetSelect) presetSelect.value = 'custom';
                syncShadowOutput();
            });
        }

        if (controls.color) {
            controls.color.addEventListener('input', function() {
                if (controls.colorText) controls.colorText.value = controls.color.value;
                if (presetSelect) presetSelect.value = 'custom';
                syncShadowOutput();
            });
        }

        if (controls.colorText) {
            controls.colorText.addEventListener('input', function() {
                if (/^#([0-9A-Fa-f]{3,8})$/.test(controls.colorText.value)) {
                    controls.color.value = controls.colorText.value;
                    if (presetSelect) presetSelect.value = 'custom';
                    syncShadowOutput();
                }
            });
        }

        if (presetSelect) {
            presetSelect.addEventListener('change', function() {
                var presetKey = presetSelect.value;
                if (presetKey === 'custom') {
                    syncShadowOutput();
                    return;
                }
                var preset = presets[presetKey];
                if (!preset) return;
                applyStateToControls(preset);
                syncShadowOutput();
            });
        }

        var themeForm = document.getElementById('themeForm');
        if (themeForm) {
            themeForm.addEventListener('submit', function() {
                syncShadowOutput();
            });
        }

        if (shadowModalEl) {
            shadowModalEl.addEventListener('hidden.bs.modal', function() {
                syncShadowOutput();
            });
        }
    })();

    // Tab persistence
    const lastTab = localStorage.getItem('themeSettingsActiveTab');
    if (lastTab) {
        const tabTrigger = document.querySelector('[data-bs-target="' + lastTab + '"]');
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
    }

    const tabLinks = document.querySelectorAll('#themeTabs button[data-bs-toggle="tab"]');
    tabLinks.forEach(function(tabLink) {
        tabLink.addEventListener('shown.bs.tab', function(event) {
            localStorage.setItem('themeSettingsActiveTab', event.target.getAttribute('data-bs-target'));
        });
    });

    var fontHidden = document.getElementById('ui_font_choice');
    var gWrap = document.getElementById('googleWeightsWrap');
    function syncWeightsVisibility() {
        if (!fontHidden || !gWrap) {
            return;
        }
        gWrap.style.display = fontHidden.value.indexOf('google:') === 0 ? 'block' : 'none';
    }
    syncWeightsVisibility();

    var fontSearchInput = document.getElementById('fontSearchInput');
    var fontDropdownMenu = document.getElementById('fontDropdownMenu');
    if (fontSearchInput && fontDropdownMenu) {
        var searchSticky = fontDropdownMenu.querySelector('li.px-3.py-2');
        if (searchSticky) {
            searchSticky.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
        fontSearchInput.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        fontSearchInput.addEventListener('input', function(e) {
            var searchTerm = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.font-empty-msg').forEach(function(el) {
                el.style.display = searchTerm ? 'none' : '';
            });
            var items = document.querySelectorAll('#fontOptionsList .font-option-item');
            var visibleCount = 0;
            items.forEach(function(option) {
                var searchText = (option.getAttribute('data-search-text') || '').toLowerCase();
                if (searchText.indexOf(searchTerm) !== -1) {
                    option.style.display = '';
                    visibleCount++;
                } else {
                    option.style.display = 'none';
                }
            });
            var noFontsFound = document.getElementById('noFontsFound');
            if (noFontsFound) {
                noFontsFound.style.display = (visibleCount === 0 && searchTerm !== '') ? 'block' : 'none';
            }
            var headerLis = fontDropdownMenu.querySelectorAll('#fontOptionsList > li');
            headerLis.forEach(function(li) {
                var h = li.querySelector('.dropdown-header');
                if (!h) {
                    return;
                }
                if (searchTerm === '') {
                    li.style.display = '';
                    return;
                }
                var any = false;
                for (var n = li.nextElementSibling; n; n = n.nextElementSibling) {
                    if (n.querySelector && n.querySelector('.dropdown-header')) {
                        break;
                    }
                    if (n.classList.contains('font-option-item') && n.style.display !== 'none') {
                        any = true;
                        break;
                    }
                }
                li.style.display = any ? '' : 'none';
            });
        });
        var fontFamilyDropdown = document.getElementById('fontFamilyDropdown');
        if (fontFamilyDropdown) {
            fontFamilyDropdown.addEventListener('hidden.bs.dropdown', function() {
                fontSearchInput.value = '';
                document.querySelectorAll('.font-empty-msg').forEach(function(el) {
                    el.style.display = '';
                });
                document.querySelectorAll('#fontOptionsList .font-option-item').forEach(function(op) {
                    op.style.display = '';
                });
                fontDropdownMenu.querySelectorAll('#fontOptionsList > li').forEach(function(li) {
                    li.style.display = '';
                });
                var nf = document.getElementById('noFontsFound');
                if (nf) {
                    nf.style.display = 'none';
                }
            });
        }
    }

    document.querySelectorAll('.font-option').forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            e.preventDefault();
            if (!fontHidden) {
                return;
            }
            var val = opt.getAttribute('data-value');
            if (!val) {
                return;
            }
            fontHidden.value = val;
            var titleEl = opt.querySelector('.font-option-title');
            var st = titleEl ? titleEl.textContent : opt.textContent;
            var selText = document.getElementById('selectedFontText');
            if (selText) {
                selText.textContent = (st || '').trim();
            }
            document.querySelectorAll('.font-option').forEach(function(o) {
                o.classList.remove('active');
            });
            opt.classList.add('active');
            syncWeightsVisibility();
            var ddBtn = document.getElementById('fontFamilyDropdownBtn');
            if (ddBtn && typeof bootstrap !== 'undefined') {
                var dd = bootstrap.Dropdown.getOrCreateInstance(ddBtn);
                dd.hide();
            }
        });
    });
    function clearUploadRequired() {
        var f = document.getElementById('custom_font_file');
        var fam = document.getElementById('custom_font_family');
        if (fam) {
            fam.required = false;
        }
        if (f) {
            f.required = false;
        }
    }
    var saveThemeBtn = document.querySelector('button[name="update_theme"]');
    if (saveThemeBtn) {
        saveThemeBtn.addEventListener('click', clearUploadRequired);
    }
    var resetThemeBtn = document.querySelector('button[name="reset_theme"]');
    if (resetThemeBtn) {
        resetThemeBtn.addEventListener('click', clearUploadRequired);
    }
    var uploadBtn = document.getElementById('upload_custom_font_btn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            var f = document.getElementById('custom_font_file');
            var fam = document.getElementById('custom_font_family');
            if (fam) {
                fam.required = true;
            }
            if (f) {
                f.required = true;
            }
        });
    }
    var customFontFile = document.getElementById('custom_font_file');
    var customFontFileName = document.getElementById('custom_font_file_name');
    if (customFontFile && customFontFileName) {
        var emptyFontLabel = customFontFileName.getAttribute('data-empty') || '';
        customFontFile.addEventListener('change', function() {
            if (customFontFile.files && customFontFile.files.length) {
                customFontFileName.textContent = customFontFile.files[0].name;
            } else {
                customFontFileName.textContent = emptyFontLabel;
            }
        });
    }
});
</script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>