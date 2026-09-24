<?php 
/*
 ================================================================================
   Task Session – Project Management System
   File    : payment-footer.php
   Purpose : Payment footer template with JavaScript includes and closing tags
 ================================================================================
*/
$dash_settings = settings::findById(1);
$url = $dash_settings->url;
?>
<script src="<?php echo $url; ?>assets/js/jquery.js" type="text/javascript"></script>
<script type="text/javascript">
    $.base_url = "<?php echo $url; ?>";
</script>
<script src="<?php echo $url; ?>assets/js/bootstrap.bundle.min.js" type="text/javascript"></script>
<script src="<?php echo $url; ?>assets/js/jquery-ui.min.js" type="text/javascript"></script>
<script src="<?php echo $url; ?>assets/js/general.js" type="text/javascript"></script>
<?php
// Task sidebar chat (invoices/payments/profile pages use this footer instead of main-footer)
$__tsFreeNoChat = function_exists('tasksession_is_free_edition') && tasksession_is_free_edition();
if (!$__tsFreeNoChat && !defined('CHAT_VOICE_RECEIPTS_JS_V1')) {
    define('CHAT_VOICE_RECEIPTS_JS_V1', true);
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-receipts.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-receipts.js') . '"></script>' . "\n";
    if (!defined('CHAT_MESSAGE_ACTIONS_JS_V1')) {
        define('CHAT_MESSAGE_ACTIONS_JS_V1', true);
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/js/chat-message-actions.js?v=' . (int)@filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'chat-message-actions.js') . '"></script>' . "\n";
    }
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
}
if (!defined('CHAT_SEEN_MODAL_V1')) {
    define('CHAT_SEEN_MODAL_V1', true);
    include __DIR__ . '/modals/chat-seen-modal.php';
}
if (!defined('CHAT_REACTIONS_MODAL_V1')) {
    define('CHAT_REACTIONS_MODAL_V1', true);
    include __DIR__ . '/modals/chat-reactions-modal.php';
}
?>
<?php
if (!isset($notificationRolePrefix)) {
    $notificationRolePrefix = '';
}
?>
<script>
  var baseUrl = "<?php echo rtrim((isset($url) ? $url : ''), '/') . '/'; ?>";
  window.baseUrl = baseUrl;
  window.notificationRolePrefix = "<?php echo htmlspecialchars((string) $notificationRolePrefix, ENT_QUOTES, 'UTF-8'); ?>";
  if (typeof window.accountStatus === 'undefined' || window.accountStatus === null) {
    window.accountStatus = <?php echo isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 'null'; ?>;
  }
  window.csrfToken = <?php echo json_encode(generate_csrf_token()); ?>;
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
  ajaxUrl: <?php echo json_encode(rtrim((string) $url, '/') . '/ajax/online_presence.php', JSON_UNESCAPED_SLASHES); ?>,
  baseUrl: <?php echo json_encode(rtrim((string) $url, '/') . '/', JSON_UNESCAPED_SLASHES); ?>,
  beepUrl: <?php echo json_encode(rtrim((string) $url, '/') . '/assets/beep/online.mp4', JSON_UNESCAPED_SLASHES); ?>,
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
</body>
<!-- END BODY -->
</html>
