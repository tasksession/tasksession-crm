<?php
/**
 * Image Helper Functions
 * Provides secure ways to display images from the uploads folder
 */

/**
 * Generate a secure image URL that uses the secure image handler
 * @param string $filename The filename in the uploads folder
 * @param string $alt_text Alternative text for the image
 * @param array $attributes Additional HTML attributes
 * @return string HTML img tag with secure URL
 */
function secure_image($filename, $alt_text = '', $attributes = []) {
    if (empty($filename)) {
        return '';
    }
    
    // Sanitize filename
    $filename = basename($filename);
    
    // Generate secure URL
    $secure_url = 'includes/secure_image_handler.php?file=' . urlencode($filename);
    
    // Build attributes string
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        $attr_string .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    
    // Return HTML img tag
    return '<img src="' . htmlspecialchars($secure_url) . '" alt="' . htmlspecialchars($alt_text) . '"' . $attr_string . '>';
}

/**
 * Generate a secure image URL (just the URL, not the full img tag)
 * @param string $filename The filename in the uploads folder
 * @return string Secure URL for the image
 */
function secure_image_url($filename) {
    if (empty($filename)) {
        return '';
    }
    
    // Sanitize filename
    $filename = basename($filename);
    
    // Generate secure URL
    return 'includes/secure_image_handler.php?file=' . urlencode($filename);
}

/**
 * Generate a secure thumbnail URL
 * @param string $filename The filename in the uploads folder
 * @param int $width Width of thumbnail
 * @param int $height Height of thumbnail
 * @return string Secure URL for the thumbnail
 */
function secure_thumbnail_url($filename, $width = 50, $height = 50) {
    if (empty($filename)) {
        return '';
    }
    
    // Sanitize filename
    $filename = basename($filename);
    
    // Generate secure thumbnail URL
    return 'includes/secure_thumbnail.php?src=' . urlencode('uploads/' . $filename) . '&w=' . $width . '&h=' . $height;
}

/**
 * Generate a secure profile picture thumbnail
 * @param string $filename The profile picture filename
 * @param int $width Width of thumbnail
 * @param int $height Height of thumbnail
 * @param string $default_image Default image to use if no profile pic
 * @return string Secure URL for the thumbnail
 */
function secure_profile_thumbnail($filename, $width = 50, $height = 50, $default_image = 'assets/images/upload-img.jpg') {
    if (empty($filename) || !image_exists($filename)) {
        return $default_image;
    }
    
    return secure_thumbnail_url($filename, $width, $height);
}

/**
 * Check if a file exists in the uploads folder
 * @param string $filename The filename to check
 * @return bool True if file exists, false otherwise
 */
function image_exists($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $filename = basename($filename);
    $file_path = 'uploads/' . $filename;
    
    return file_exists($file_path);
}

/**
 * Get image dimensions securely
 * @param string $filename The filename in the uploads folder
 * @return array|false Array with width and height, or false if failed
 */
function get_image_dimensions($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $filename = basename($filename);
    $file_path = 'uploads/' . $filename;
    
    if (!file_exists($file_path)) {
        return false;
    }
    
    $image_info = getimagesize($file_path);
    if ($image_info === false) {
        return false;
    }
    
    return [
        'width' => $image_info[0],
        'height' => $image_info[1],
        'mime' => $image_info['mime']
    ];
}

/**
 * Generate a secure profile picture URL
 * @param string $profile_pic_filename The profile picture filename
 * @param string $default_image Default image to use if no profile pic
 * @return string Secure URL for profile picture
 */
function secure_profile_pic($profile_pic_filename, $default_image = 'assets/images/default-avatar.png') {
    if (empty($profile_pic_filename) || !image_exists($profile_pic_filename)) {
        return $default_image;
    }
    
    return secure_image_url($profile_pic_filename);
}

/**
 * Generate a secure profile picture HTML tag
 * @param string $profile_pic_filename The profile picture filename
 * @param string $alt_text Alternative text
 * @param array $attributes Additional HTML attributes
 * @param string $default_image Default image to use if no profile pic
 * @return string HTML img tag with secure URL
 */
function secure_profile_pic_html($profile_pic_filename, $alt_text = 'Profile Picture', $attributes = [], $default_image = 'assets/images/default-avatar.png') {
    if (empty($profile_pic_filename) || !image_exists($profile_pic_filename)) {
        $attributes['src'] = $default_image;
        $attributes['alt'] = $alt_text;
        
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        
        return '<img' . $attr_string . '>';
    }
    
    return secure_image($profile_pic_filename, $alt_text, $attributes);
}
?> 