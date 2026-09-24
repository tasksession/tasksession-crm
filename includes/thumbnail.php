<?php
// thumbnail.php - Redirect to secure version

// Redirect to secure thumbnail handler
$secure_url = 'secure_thumbnail.php?' . http_build_query($_GET);
header('Location: ' . $secure_url);
exit;
?>
