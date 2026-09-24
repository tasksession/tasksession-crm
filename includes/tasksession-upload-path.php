<?php
/**
 * Resolve upload src/file params into a jailed path under uploads/<folder>/.
 */

if (!function_exists('tasksession_allowed_upload_folders')) {
    function tasksession_allowed_upload_folders() {
        return [
            'user-uploads',
            'file-sharing',
            'task-files',
            'email-attachments',
            'profile-pics',
            'system-uploads',
        ];
    }
}

if (!function_exists('tasksession_path_is_inside')) {
    function tasksession_path_is_inside($path, $root) {
        if ($path === false || $path === null || $root === false || $root === null) {
            return false;
        }
        $pathN = strtolower(str_replace('\\', '/', (string) $path));
        $rootN = strtolower(rtrim(str_replace('\\', '/', (string) $root), '/'));
        if ($pathN === $rootN) {
            return true;
        }
        return strpos($pathN, $rootN . '/') === 0;
    }
}

if (!function_exists('tasksession_uploads_root')) {
    function tasksession_uploads_root() {
        $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
        return ($root !== false) ? $root : false;
    }
}

if (!function_exists('tasksession_safe_upload_basename')) {
    function tasksession_safe_upload_basename($name) {
        $name = str_replace(["\0", '\\'], ['', '/'], (string) $name);
        $base = basename($name);
        if ($base === '' || $base === '.' || $base === '..') {
            return '';
        }
        if (strpos($base, '..') !== false) {
            return '';
        }
        return $base;
    }
}

if (!function_exists('tasksession_allowed_upload_subdirs')) {
    /**
     * Nested dirs allowed under an uploads folder. Vault image thumbs live in file-sharing/thumbnails/.
     */
    function tasksession_allowed_upload_subdirs($folder) {
        if ($folder === 'file-sharing') {
            return ['thumbnails'];
        }
        return [];
    }
}

if (!function_exists('tasksession_resolve_upload_request')) {
    /**
     * @param array $params keys: src and/or file (raw request values)
     * @return array{folder:string,filename:string,path:string}|false
     */
    function tasksession_resolve_upload_request(array $params) {
        $uploadsRoot = tasksession_uploads_root();
        if ($uploadsRoot === false) {
            return false;
        }

        $allowed = tasksession_allowed_upload_folders();

        if (!empty($params['src'])) {
            $src = urldecode((string) $params['src']);
            $src = str_replace('\\', '/', $src);
            $uploadsPos = strpos($src, 'uploads/');
            if ($uploadsPos === false) {
                return false;
            }
            $relative = substr($src, $uploadsPos);
            $parts = explode('/', $relative);
            if (count($parts) < 3) {
                return false;
            }
            $folder = $parts[1];
            if (!in_array($folder, $allowed, true)) {
                return false;
            }
            $rest = array_values(array_filter(array_slice($parts, 2), function ($p) {
                return $p !== '';
            }));
            $subdir = '';
            if (count($rest) === 1) {
                $filename = tasksession_safe_upload_basename($rest[0]);
            } elseif (count($rest) === 2) {
                $maybeSub = $rest[0];
                if (!preg_match('/^[A-Za-z0-9_-]+$/', $maybeSub)
                    || !in_array($maybeSub, tasksession_allowed_upload_subdirs($folder), true)) {
                    return false;
                }
                $subdir = $maybeSub;
                $filename = tasksession_safe_upload_basename($rest[1]);
            } else {
                return false;
            }
            if ($filename === '') {
                return false;
            }
            $folderRoot = $uploadsRoot . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($folderRoot)) {
                return false;
            }
            $folderReal = realpath($folderRoot);
            if ($folderReal === false || !tasksession_path_is_inside($folderReal, $uploadsRoot)) {
                return false;
            }
            $candidateDir = $folderReal;
            if ($subdir !== '') {
                $subPath = $folderReal . DIRECTORY_SEPARATOR . $subdir;
                if (is_dir($subPath)) {
                    $subReal = realpath($subPath);
                    if ($subReal === false || !tasksession_path_is_inside($subReal, $folderReal)) {
                        return false;
                    }
                    $candidateDir = $subReal;
                } else {
                    $candidateDir = $subPath;
                }
            }
            $candidate = $candidateDir . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($candidate)) {
                $real = realpath($candidate);
                if ($real === false || !tasksession_path_is_inside($real, $folderReal)) {
                    return false;
                }
                $candidate = $real;
            } else {
                $normalizedCandidate = str_replace('\\', '/', $candidate);
                $normalizedFolder = str_replace('\\', '/', $folderReal);
                if (strpos($normalizedCandidate, '..') !== false
                    || strpos(strtolower($normalizedCandidate), strtolower($normalizedFolder) . '/') !== 0) {
                    return false;
                }
            }
            return [
                'folder' => $folder,
                'filename' => $filename,
                'path' => $candidate,
            ];
        }

        if (isset($params['file']) && $params['file'] !== '') {
            $filename = tasksession_safe_upload_basename($params['file']);
            if ($filename === '') {
                return false;
            }
            $candidate = $uploadsRoot . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($candidate)) {
                $real = realpath($candidate);
                if ($real === false || !tasksession_path_is_inside($real, $uploadsRoot)) {
                    return false;
                }
                return [
                    'folder' => '',
                    'filename' => $filename,
                    'path' => $real,
                ];
            }
            $normalizedCandidate = str_replace('\\', '/', $candidate);
            $normalizedRoot = str_replace('\\', '/', $uploadsRoot);
            if (strpos($normalizedCandidate, '..') !== false
                || strpos(strtolower($normalizedCandidate), strtolower($normalizedRoot) . '/') !== 0) {
                return false;
            }
            return [
                'folder' => '',
                'filename' => $filename,
                'path' => $candidate,
            ];
        }

        return false;
    }
}
