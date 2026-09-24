<?php 
/*
 ================================================================================
   Task Session – Project Management System
   File    : main-footer.php
   Purpose : Main footer template with JavaScript includes and closing tags
 ================================================================================
*/
$dash_settings = settings::findById(1);
// Keep aligned public base URL when already set by lib-initialize; only fall back to settings.
if (!isset($url) || trim((string) $url) === '') {
    $url = $dash_settings->url;
    if (function_exists('crm_align_public_url_to_request')) {
        $url = crm_align_public_url_to_request((string) $url);
    }
}
?>
<script src="<?php echo $url; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $url; ?>assets/js/jquery.js" type="text/javascript"></script>
<script>
/* Defer non-critical chrome polls until after window.load + short quiet period
   so dashboard/charts get the connection pool first (esp. shared hosting). */
window.comonAfterPageQuiet = window.comonAfterPageQuiet || function (fn, delayMs) {
  delayMs = typeof delayMs === 'number' ? delayMs : 2000;
  function go() {
    var run = function () { try { fn(); } catch (e) {} };
    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(function () { setTimeout(run, delayMs); }, { timeout: delayMs + 4000 });
    } else {
      setTimeout(run, delayMs);
    }
  }
  if (document.readyState === 'complete') {
    go();
  } else {
    window.addEventListener('load', go);
  }
};
</script>
<?php
$__tsFreeNoChat = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
if (!$__tsFreeNoChat && !defined('CHAT_MESSAGE_ACTIONS_JS_V1')) {
    define('CHAT_MESSAGE_ACTIONS_JS_V1', true);
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-message-actions.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-message-actions.js') . '"></script>' . "\n";
}
if (!$__tsFreeNoChat && !defined('CHAT_VOICE_RECEIPTS_JS_V1')) {
    define('CHAT_VOICE_RECEIPTS_JS_V1', true);
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-receipts.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-receipts.js') . '"></script>' . "\n";
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-voice.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-voice.js') . '"></script>' . "\n";
}
if (!$__tsFreeNoChat && defined('LOAD_TASK_CHAT_JS') && LOAD_TASK_CHAT_JS && !defined('TASK_CHAT_JS_V1')) {
    define('TASK_CHAT_JS_V1', true);
    echo '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/css/lightbox.css?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'css' . DS . 'lightbox.css') . '" data-chat-lightbox-css="1">' . "\n";
    if (!defined('CHAT_MEDIA_ALBUM_JS_V1')) {
        define('CHAT_MEDIA_ALBUM_JS_V1', true);
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-media-album.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-media-album.js') . '" type="text/javascript"></script>' . "\n";
    }
    if (!defined('CHAT_LIGHTBOX_NAV_JS_V1')) {
        define('CHAT_LIGHTBOX_NAV_JS_V1', true);
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-lightbox-nav.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-lightbox-nav.js') . '" type="text/javascript"></script>' . "\n";
    }
    if (!defined('TASK_CHAT_DROPPER_JS_V1')) {
        define('TASK_CHAT_DROPPER_JS_V1', true);
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/task_chat_dropper.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'task_chat_dropper.js') . '" type="text/javascript"></script>' . "\n";
    }
    if (!defined('CHAT_EMOTICONS_JS_V1')) {
        define('CHAT_EMOTICONS_JS_V1', true);
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/emoticons.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'emoticons.js') . '" type="text/javascript"></script>' . "\n";
    }
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/task-chat.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'task-chat.js') . '" type="text/javascript"></script>' . "\n";
    include __DIR__ . '/partials/chat-speech-assets.php';
}
?>
<script type="text/javascript">
    $.base_url = "<?php echo $url; ?>";
</script>
<?php
if (empty($__tsFreeNoChat) && !defined('CHAT_SEEN_MODAL_V1')) {
    define('CHAT_SEEN_MODAL_V1', true);
    include __DIR__ . '/modals/chat-seen-modal.php';
}
if (empty($__tsFreeNoChat) && !defined('CHAT_REACTIONS_MODAL_V1')) {
    define('CHAT_REACTIONS_MODAL_V1', true);
    include __DIR__ . '/modals/chat-reactions-modal.php';
}
?>
<?php
if (!empty($GLOBALS['loadEmailThreadLightbox'])) {
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/lightbox.min.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'lightbox.min.js') . '" type="text/javascript"></script>' . "\n";
}
?>
<?php
if (!function_exists('comon_page_asset_enabled') && defined('LIB_ROOT')) {
    require_once LIB_ROOT . DS . 'page_assets.php';
}
// Exclude jquery-ui.min.js on inbox and new (compose) pages
$isEmailPage = isset($_SERVER['REQUEST_URI']) && (
    strpos($_SERVER['REQUEST_URI'], '/mail/inbox') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/mail/new') !== false ||
    preg_match('#/mail/inbox(?:\.php)?([?#]|$)#', $_SERVER['REQUEST_URI']) ||
    preg_match('#/mail/new(?:\.php)?([?#]|$)#', $_SERVER['REQUEST_URI'])
);
$loadJqueryUiJs = (!$isEmailPage) && (!function_exists('comon_page_asset_enabled') || comon_page_asset_enabled('jquery_ui'));
if ($loadJqueryUiJs) {
    echo '<script src="' . $url . 'assets/js/jquery-ui.min.js" type="text/javascript"></script>';
}
?>
<?php 
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$actual_link = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$actual_link = strtok($actual_link,'?');
$trash_page = "{$url}admin/trash";
$client_page = "{$url}admin/clients";
$staff_page = "{$url}admin/staff";
$setting_page = "{$url}admin/email-setting";
$admin_page = "{$url}admin/dashboard";
$staffb_page = "{$url}client/dashboard";
$clientb_page = "{$url}staff/dashboard";
$excludeSemanticPages = array(
    $trash_page,
    $client_page,
    $staff_page,
    $setting_page,
    $admin_page,
    $staffb_page,
    $clientb_page,
    "{$url}admin/trash",
    "{$url}admin/clients",
    "{$url}admin/staff",
    "{$url}admin/email-setting",
    "{$url}admin/dashboard",
    "{$url}client/dashboard",
    "{$url}staff/dashboard",
);
// Also exclude semantic.min.js on inbox and new (compose) pages
$isEmailPageForSemantic = isset($_SERVER['REQUEST_URI']) && (
    strpos($_SERVER['REQUEST_URI'], '/mail/inbox') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/mail/new') !== false ||
    preg_match('#/mail/inbox(?:\.php)?([?#]|$)#', $_SERVER['REQUEST_URI']) ||
    preg_match('#/mail/new(?:\.php)?([?#]|$)#', $_SERVER['REQUEST_URI'])
);
if (!in_array($actual_link, $excludeSemanticPages, true) && !$isEmailPageForSemantic){ ?>
<script src="<?php echo $url; ?>assets/js/semantic.min.js"></script>
<?php } ?>
<script src="<?php echo $url; ?>assets/js/general.js?var=<?php echo rand();?>"></script>
<?php
if (!isset($notificationRolePrefix)) {
    $notificationRolePrefix = '';
}
?>
<script>
  var baseUrl = "<?php echo rtrim((isset($url) ? $url : dirname($_SERVER['SCRIPT_NAME'])), '/') . '/'; ?>";
  window.baseUrl = baseUrl;
  window.notificationRolePrefix = "<?php echo htmlspecialchars((string) $notificationRolePrefix, ENT_QUOTES, 'UTF-8'); ?>";
  if (typeof window.accountStatus === 'undefined' || window.accountStatus === null) {
    window.accountStatus = <?php echo isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 'null'; ?>;
  }
  window.csrfToken = <?php echo json_encode(generate_csrf_token()); ?>;
  window.tasksessionCsrfHeaders = function (headers) {
    headers = headers || {};
    if (window.csrfToken) {
      headers['X-CSRF-TOKEN'] = window.csrfToken;
    }
    return headers;
  };
  window.tasksessionAppendCsrf = function (body) {
    if (!window.csrfToken) {
      return body;
    }
    if (body && typeof body.append === 'function' && typeof body.set !== 'function') {
      body.append('csrf_token', window.csrfToken);
    } else if (body && typeof body.set === 'function') {
      body.set('csrf_token', window.csrfToken);
    }
    return body;
  };
  window.tasksessionSanitizeHtml = function (html) {
    if (html == null) {
      return '';
    }
    html = String(html);
    if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
      return window.DOMPurify.sanitize(html);
    }
    var wrap = document.createElement('div');
    wrap.innerHTML = html;
    var blocked = { SCRIPT: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, LINK: 1, META: 1, BASE: 1, FORM: 1 };
    var walk = wrap.querySelectorAll('*');
    for (var i = walk.length - 1; i >= 0; i--) {
      var el = walk[i];
      if (blocked[el.tagName]) {
        el.parentNode && el.parentNode.removeChild(el);
        continue;
      }
      if (el.attributes) {
        for (var a = el.attributes.length - 1; a >= 0; a--) {
          var name = el.attributes[a].name;
          var val = el.attributes[a].value || '';
          if (/^on/i.test(name) || /javascript:/i.test(val) || /data:/i.test(name + val) && /script/i.test(val)) {
            el.removeAttribute(name);
          }
        }
      }
    }
    return wrap.innerHTML;
  };
  <?php
  $__pushPrefEnabled = true;
  if (isset($session) && is_object($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) {
      $__pushUser = User::findById((int) $session->userId);
      if ($__pushUser && isset($__pushUser->push_notifications_enabled)) {
          $__pushPrefEnabled = (int) $__pushUser->push_notifications_enabled === 1;
      }
  }
  ?>
  window.pushNotificationsEnabled = <?php echo $__pushPrefEnabled ? 'true' : 'false'; ?>;
</script>
<?php include dirname(__DIR__) . '/includes/timer-footer.php'; ?>
<script src="<?php echo $url; ?>assets/js/notifications.js?v=<?php echo (int)@filemtime(dirname(__DIR__) . '/assets/js/notifications.js'); ?>"></script>
<?php
$presenceToastAcct = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
if (
    isset($session) && is_object($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()
    && ($presenceToastAcct === 1 || $presenceToastAcct === 3)
) {
    $__presenceToastJs = dirname(__DIR__) . '/assets/js/online-presence-toast.js';
    $__dashPresence = (isset($dash_settings) && is_object($dash_settings)) ? $dash_settings : null;
    $__presenceToastOn = !$__dashPresence || !isset($__dashPresence->presence_toast_enabled) || (int) $__dashPresence->presence_toast_enabled === 1;
    $__presenceBeepOn = !$__dashPresence || !isset($__dashPresence->presence_beep_enabled) || (int) $__dashPresence->presence_beep_enabled === 1;
    $__presenceStaffOn = !$__dashPresence || !isset($__dashPresence->presence_staff_toast_enabled) || (int) $__dashPresence->presence_staff_toast_enabled === 1;
    ?>
<script>
window.PRESENCE_TOAST = {
  ajaxUrl: (window.baseUrl || <?php echo json_encode(rtrim((string) $url, '/') . '/', JSON_UNESCAPED_SLASHES); ?>) + 'ajax/online_presence.php',
  baseUrl: window.baseUrl || <?php echo json_encode(rtrim((string) $url, '/') . '/', JSON_UNESCAPED_SLASHES); ?>,
  beepUrl: (window.baseUrl || <?php echo json_encode(rtrim((string) $url, '/') . '/', JSON_UNESCAPED_SLASHES); ?>) + 'assets/beep/online.mp4',
  pollMs: 45000,
  enabled: true,
  toastEnabled: <?php echo $__presenceToastOn ? 'true' : 'false'; ?>,
  beepEnabled: <?php echo $__presenceBeepOn ? 'true' : 'false'; ?>,
  staffToastEnabled: <?php echo $__presenceStaffOn ? 'true' : 'false'; ?>
};
</script>
<script src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>assets/js/online-presence-toast.js?v=<?php echo (int) @filemtime($__presenceToastJs); ?>"></script>
    <?php
}
?>
<?php
$__mediaReplaceModal = dirname(__DIR__) . '/templates/modals/media-replace-modal.php';
$__mediaReplaceJs = SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'media-replace-modal.js';
if (!(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())
    && is_file($__mediaReplaceModal) && is_file($__mediaReplaceJs)) {
    include $__mediaReplaceModal;
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/media-replace-modal.js?v=' . (int) @filemtime($__mediaReplaceJs) . '"></script>' . "\n";
}
?>
<?php
$attendanceSessionPromptInclude = false;
$attendanceSessionPromptShowLogin = false;
$attendanceSessionPromptNeedsCheckout = false;
$attendanceSessionPromptCanCheckin = false;
$attendanceSessionPromptCanCheckout = false;

if (isset($session) && is_object($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()
    && isset($dash_settings) && is_object($dash_settings) && !empty($dash_settings->module_attendance)
    && !(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())) {
    $attendancePromptAcct = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;
    $attendancePromptUserDisabled = false;
    // Per-user disable applies to admins (1) and staff (3); clients (2) never get this prompt path.
    if (($attendancePromptAcct === 1 || $attendancePromptAcct === 3) && class_exists('User')) {
        $attendancePromptUserRow = User::findById((int)$session->userId);
        $attendancePromptUserDisabled = $attendancePromptUserRow && isset($attendancePromptUserRow->attendance_disabled) && (int)$attendancePromptUserRow->attendance_disabled === 1;
    }
    if (!$attendancePromptUserDisabled && ($attendancePromptAcct === 1 || $attendancePromptAcct === 3)) {
        global $connect, $lang;
        if (!empty($connect)) {
            $spPath = dirname(__DIR__) . '/includes/permissions.php';
            $spSessionPath = dirname(__DIR__) . '/includes/attendance/session-prompts.php';
            if (is_file($spPath) && is_file($spSessionPath)) {
                require_once $spPath;
                require_once $spSessionPath;
                ensure_user_permissions($connect);
                // Match includes/attendance/bootstrap.php: admins bypass role_permissions for attendance APIs.
                $attendanceSessionPromptIsAdmin = ($attendancePromptAcct === 1);
                $attendanceSessionPromptCanCheckin = $attendanceSessionPromptIsAdmin || has_permission('attendance_checkin');
                $attendanceSessionPromptCanCheckout = $attendanceSessionPromptIsAdmin || has_permission('attendance_checkout');
                if ($attendanceSessionPromptCanCheckin || $attendanceSessionPromptCanCheckout) {
                    $uidPrompt = (int)$session->userId;
                    if ($attendanceSessionPromptCanCheckin
                        && !empty($_SESSION['attendance_login_checkin_prompt'])
                        && attendance_user_eligible_for_checkin_prompt($connect, $uidPrompt)
                        && attendance_user_needs_checkin_today($connect, $uidPrompt)) {
                        $attendanceSessionPromptShowLogin = true;
                    }
                    if ($attendanceSessionPromptCanCheckout && attendance_user_needs_checkout_today($connect, $uidPrompt)) {
                        $attendanceSessionPromptNeedsCheckout = true;
                    }
                    $attendanceSessionPromptInclude = $attendanceSessionPromptShowLogin || $attendanceSessionPromptCanCheckout;
                }
            }
        }
    }
}

if (!empty($attendanceSessionPromptInclude)) {
    include dirname(__DIR__) . '/includes/attendance/session-prompt-modals.php';
}
if (isset($session) && is_object($session) && method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) {
    $__browserPushGlobalOn = !function_exists('comon_browser_push_globally_enabled') || comon_browser_push_globally_enabled(isset($dash_settings) ? $dash_settings : null);
    $__pushSubscribePhp = dirname(__DIR__) . '/real-chat/push_subscribe.php';
    if ($__browserPushGlobalOn && is_file($__pushSubscribePhp)) {
    if (!defined('BROWSER_NOTIFICATIONS_MODAL_V1')) {
        define('BROWSER_NOTIFICATIONS_MODAL_V1', true);
        include __DIR__ . '/modals/browser-notifications-modal.php';
    }
    $__pushNotifJs = dirname(__DIR__) . '/assets/js/push-notifications.js';
    if (!empty($_SESSION['push_fresh_login'])) {
        unset($_SESSION['push_fresh_login']);
        echo '<script>window.PUSH_FRESH_LOGIN = true;</script>' . "\n";
    }
    if (is_file($__pushNotifJs)) {
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/push-notifications.js?v=' . (int) @filemtime($__pushNotifJs) . '"></script>' . "\n";
    }
    }
}
if (isset($GLOBALS['comon_before_body_close_html']) && is_string($GLOBALS['comon_before_body_close_html']) && $GLOBALS['comon_before_body_close_html'] !== '') {
    echo $GLOBALS['comon_before_body_close_html'];
}
unset($GLOBALS['comon_before_body_close_html']);
?>
<script>
(function () {
  function stripModalPad() {
    if (!document.body) return;
    document.body.style.removeProperty('padding-right');
    document.documentElement.style.removeProperty('padding-right');
    document.querySelectorAll('.modal-backdrop, .modal.show, .modal.showing').forEach(function (el) {
      el.style.removeProperty('padding-right');
    });
  }
  document.addEventListener('show.bs.modal', function () { requestAnimationFrame(stripModalPad); });
  document.addEventListener('shown.bs.modal', stripModalPad);
})();
</script>
</body>
</html>