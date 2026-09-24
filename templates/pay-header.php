<!DOCTYPE html>
<html class="no-js">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php echo $title; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="<?php echo $url; ?>assets/css/theme-vars.php">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700;900&display=swap" rel="stylesheet">
<link href="../assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>

<link href="<?php echo $url; ?>assets/css/frontend-style.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/frontend-style.css'); ?>" rel="stylesheet" type="text/css"/>
<?php 
$default_favicon_path = $url . 'assets/images/favicon.png';
$custom_favicon_file = dirname(__DIR__) . '/' . $img_path . $favicon_image;
if ($favicon_image_check && file_exists($custom_favicon_file)) {
    $favicon_src = getSystemImageUrl($favicon_image) . '&v=' . time() . '&cb=' . rand(1000, 9999);
} else {
    $favicon_src = $default_favicon_path . '?v=' . time() . '&cb=' . rand(1000, 9999);
}
?>
<link rel="icon" href="<?php echo $favicon_src; ?>" sizes="16x16" type="image/png">
</head>
<body>
<?php /* Free edition: no license modal */ ?>
