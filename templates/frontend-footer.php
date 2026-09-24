
    <?php if (empty($is_login_page)) { ?>
    <script src="assets/js/jquery.js" type="text/javascript"></script>
    <script src="assets/js/bootstrap.js" type="text/javascript"></script>
    <?php } ?>
	<?php
	$additionalJsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'additional.js';
	$additionalJsV = is_file($additionalJsPath) ? filemtime($additionalJsPath) : time();
	?>
	<script src="assets/js/additional.js?v=<?php echo (int) $additionalJsV; ?>" type="text/javascript"></script>
	<link rel="stylesheet" type="text/css" href="<?php echo $url; ?>assets/css/theme-vars.php">
</body>
</html>