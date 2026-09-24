<?php
/**
 * Map file extensions to icons in assets/images/files/file-types/
 * (docx → word.png, xlsx → excel.png, etc. — not extension.png)
 */

if (!function_exists('get_file_type_icon')) {
    function get_file_type_icon($extension)
    {
        $extension = strtolower(ltrim((string) $extension, '.'));
        $icon_map = [
            'pdf' => 'pdf.png',
            'doc' => 'word.png',
            'docx' => 'word.png',
            'pages' => 'word.png',
            'xls' => 'excel.png',
            'xlsx' => 'excel.png',
            'numbers' => 'excel.png',
            'ppt' => 'powerpoint.png',
            'pptx' => 'powerpoint.png',
            'ptt' => 'powerpoint.png',
            'txt' => 'text.png',
            'zip' => 'zip.png',
            'rar' => 'zip.png',
            'psd' => 'photoshop.png',
            'eps' => 'illustrator.png',
            'ai' => 'illustrator.png',
            'gif' => 'generic.png',
            'png' => 'generic.png',
            'jpg' => 'generic.png',
            'jpeg' => 'generic.png',
            'bmp' => 'generic.png',
            'webp' => 'generic.png',
            'svg' => 'generic.png',
            'mp4' => 'movie.png',
            'avi' => 'movie.png',
            'mov' => 'movie.png',
            'wmv' => 'movie.png',
            'mp3' => 'music.png',
            'wav' => 'music.png',
            'flac' => 'music.png',
            'key' => 'keynote.png',
            'keynote' => 'keynote.png',
            'fw' => 'fireworks.png',
            'fireworks' => 'fireworks.png',
        ];

        return isset($icon_map[$extension]) ? $icon_map[$extension] : 'files.png';
    }
}

if (!function_exists('get_file_type_icon_url')) {
    /**
     * Absolute URL for a file-type icon (uses global $url when available).
     */
    function get_file_type_icon_url($extension)
    {
        global $url;
        $base = isset($url) && $url !== '' ? rtrim((string) $url, '/') . '/' : '/';
        return $base . 'assets/images/files/file-types/' . get_file_type_icon($extension);
    }
}
