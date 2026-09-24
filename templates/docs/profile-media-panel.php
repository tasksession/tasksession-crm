<?php
if (!isset($profileMediaCanView) || !$profileMediaCanView) {
    $msg = isset($profileMediaError) && $profileMediaError !== '' ? $profileMediaError : 'Media is not available for this profile.';
    echo '<div class="alert alert-warning m-3">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
    return;
}
?>
<input type="file" id="fileInput" multiple accept="*/*" style="display:none;">
<button type="button" id="uploadBtn" style="display:none;"></button>
<?php
if (!function_exists('mv_get_highest_share_permission')) {
    function mv_get_highest_share_permission($rawPermissions, $fallback = 'read') {
        $normalizePerm = static function ($permRaw) {
            $perm = strtolower(trim((string)$permRaw));
            if ($perm === 'view_profile') return 'view';
            if ($perm === 'read_profile') return 'read';
            if ($perm === 'write_profile') return 'write';
            if ($perm === 'admin_profile') return 'admin';
            return $perm;
        };
        $normalizedRaw = implode(',', array_map($normalizePerm, explode(',', (string)$rawPermissions)));
        $normalizedFallback = $normalizePerm($fallback);
        if (class_exists('FileManager') && method_exists('FileManager', 'getHighestSharedPermission')) {
            return FileManager::getHighestSharedPermission($normalizedRaw, $normalizedFallback);
        }
        $rank = array('view' => 1, 'read' => 2, 'write' => 3, 'admin' => 4);
        $best = $normalizedFallback;
        if (!isset($rank[$best])) {
            $best = 'read';
        }
        $bestRank = $rank[$best];
        foreach (explode(',', $normalizedRaw) as $token) {
            $perm = strtolower(trim((string)$token));
            if (!isset($rank[$perm])) {
                continue;
            }
            if ($rank[$perm] > $bestRank) {
                $best = $perm;
                $bestRank = $rank[$perm];
            }
        }
        return $best;
    }
}
$__pmCurrentFolderPerm = 'read';
if (!empty($current_folder_id)) {
    $cursor = (int)$current_folder_id;
    $guard = 0;
    while ($cursor > 0 && $guard < 500) {
        $guard++;
        if (isset($profileExtendedFolderSharePermissions) && is_array($profileExtendedFolderSharePermissions) && isset($profileExtendedFolderSharePermissions[$cursor])) {
            $__pmCurrentFolderPerm = mv_get_highest_share_permission((string)$profileExtendedFolderSharePermissions[$cursor], 'read');
            break;
        }
        if (!class_exists('FileFolder') || !method_exists('FileFolder', 'getFolderByIdNoIsolation')) {
            break;
        }
        $cursorObj = FileFolder::getFolderByIdNoIsolation($cursor);
        if (!$cursorObj) {
            break;
        }
        $cursor = isset($cursorObj->parent_folder_id) ? (int)$cursorObj->parent_folder_id : 0;
    }
}
$mvMobileSidebarShowZip = true;
include __DIR__ . '/../../partials/media-vault-global-files-mobile-sidebar.php';
?>

<?php if ($current_folder_id): ?>
<div class="row">
    <div class="col-md-12 project-tabs breadcrumbs">
        <div class="row">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo htmlspecialchars(profile_media_build_url(array('folder' => null, 'page' => 1)), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">
                            Root
                        </a>
                    </li>
                    <?php foreach ($breadcrumb as $crumb): ?>
                    <li class="breadcrumb-item">
                        <a href="<?php echo htmlspecialchars(profile_media_build_url(array('folder' => (int) $crumb->id, 'page' => 1)), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars((string) $crumb->name, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="file-content file-sharing-container<?php echo $mediaViewType === 'table' ? ' file-sharing-container--table-view' : ''; ?>">
    <div class="table-content">
        <div class="simple-upload-zone" id="simpleUploadZone">
            <div class="simple-upload-content">
                <div id="uploadProgressModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div>
                            <div class="upload-progress-state">
                                <div class="modal-header border-0 text-center pb-0 display-block">
                                    <div class="upload-icon-container mb-3">
                                        <div class="upload-icon-wrapper">
                                            <?php echo ts_icon('upload', 'upload-icon'); ?>
                                        </div>
                                    </div>
                                    <h4 class="modal-title fw-bold text-dark mb-2">Uploading Files</h4>
                                    <p class="text-muted mb-4">Please wait while we process your files...</p>
                                </div>
                                <div class="modal-body text-center">
                                    <div class="upload-progress-container">
                                        <div class="progress-wrapper mb-4">
                                            <div class="progress custom-progress">
                                                <div class="progress-bar custom-progress-bar" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <div class="progress-percentage">0%</div>
                                        </div>
                                        <div class="upload-status-container">
                                            <div class="upload-status-icon mb-2">
                                                <div class="spinner-border text-primary" role="status" aria-label="Loading"></div>
                                            </div>
                                            <div class="current-file text-center text-muted">Preparing upload...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="upload-results-state" style="display: none;">
                                <div class="modal-header border-0 text-center pb-0 display-block">
                                    <div class="success-icon-container mb-3">
                                        <div class="success-icon-wrapper">
                                            <?php echo ts_icon('check-circle', 'success-icon'); ?>
                                        </div>
                                    </div>
                                    <h4 class="modal-title fw-bold text-success mb-2">Upload Completed!</h4>
                                    <p class="text-muted mb-3">Your files have been successfully uploaded</p>
                                </div>
                                <div class="modal-body">
                                    <div class="upload-results-container">
                                        <div class="upload-success-section mb-4">
                                            <ul class="uploaded-files-list"></ul>
                                        </div>
                                        <div class="upload-errors-section" style="display: none;">
                                            <ul class="error-files-list"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="drag-drop-overlay" id="dragDropOverlay"></div>
        </div>

        <div class="file-grid" id="fileGrid">
            <?php if ($mediaViewType === 'table' && (!empty($folders) || !empty($files))): ?>
                <?php
                $current_project_id = 0;
                $projectId = 0;
                $hideEditPermissionOption = true;
                include __DIR__ . '/../../partials/admin-media-project-table.php';
                ?>
            <?php else: ?>
            <?php if (!empty($folders)): ?>
                <div class="folders-section">
                    <div class="row">
                        <?php foreach ($folders as $folder): ?>
                            <?php
                            $__pmFolderAccessType = isset($folder->access_type) ? strtolower(trim((string) $folder->access_type)) : '';
                            $__pmIsSharedRecipient = ($__pmFolderAccessType === 'shared');
                            $__pmFolderPerm = mv_get_highest_share_permission((string) ($folder->shared_permissions ?? ''), 'read');
                            $__pmCanManageFolder = $__pmIsSharedRecipient ? in_array($__pmFolderPerm, array('write', 'admin'), true) : true;
                            $__pmCanShareFolder = $__pmIsSharedRecipient ? ($__pmFolderPerm === 'admin') : true;
                            $__pmCanDeleteFolder = $__pmCanManageFolder;
                            ?>
                            <div class="col-md-4 col-sm-4 col-12 mb-3">
                                <div class="file-item folder-item" data-id="<?php echo (int) $folder->id; ?>" data-type="folder" data-created-by="<?php echo (int) ($folder->created_by ?? 0); ?>" data-access-type="<?php echo htmlspecialchars((string) ($folder->access_type ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-shared-permissions="<?php echo htmlspecialchars((string) ($folder->shared_permissions ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php include __DIR__ . '/../../partials/media-folder-share-grid-badge.php'; ?>
                                    <div class="file-checkbox" style="display:none;" draggable="false">
                                        <input data-index="<?php echo (int) $folder->id; ?>" value="<?php echo (int) $folder->id; ?>" name="btSelectItem" type="checkbox" class="file-select-checkbox" data-bulk-kind="folder" draggable="false">
                                    </div>
                                    <div class="folder-icon">
                                        <?php echo ts_icon('folder'); ?>
                                    </div>
                                    <div class="folder-content">
                                        <div class="folder-header">
                                            <h6 class="folder-name mb-0"><?php echo htmlspecialchars((string) ($folder->name ?? ''), ENT_QUOTES, 'UTF-8'); ?></h6>
                                        </div>
                                        <div class="folder-details">
                                            <span class="folder-stats"><?php echo (int) ($folder->file_count ?? 0); ?> files, <?php echo (int) ($folder->subfolder_count ?? 0); ?> subfolders</span>
                                            <span class="folder-owner">Created by <?php echo htmlspecialchars((string) ($folder->created_by_name ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                    <div class="file-actions-dropdown">
                                        <div class="dropdown">
                                            <button class="btn-dots dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="openFolder(<?php echo (int) $folder->id; ?>); return false;">
                                                        <?php echo ts_icon('folder', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Open Folder'] ?? 'Open Folder', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php if ($__pmCanManageFolder): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="renameFolder(<?php echo (int) $folder->id; ?>, <?php echo htmlspecialchars(json_encode((string) ($folder->name ?? '')), ENT_QUOTES, 'UTF-8'); ?>); return false;">
                                                        <?php echo ts_icon('edit', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Rename'] ?? 'Rename', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php if ($__pmCanShareFolder): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="shareFolder(<?php echo (int) $folder->id; ?>, <?php echo htmlspecialchars(json_encode((string) ($folder->name ?? '')), ENT_QUOTES, 'UTF-8'); ?>); return false;">
                                                        <?php echo ts_icon('share', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Share'] ?? 'Share', ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($lang['Edit Share'] ?? 'Edit Share', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php if ($__pmCanDeleteFolder): ?>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteFolder(<?php echo (int) $folder->id; ?>); return false;">
                                                        <?php echo ts_icon('delete', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Delete Folder'] ?? 'Delete Folder', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($files)): ?>
                <div class="files-section">
                    <h5 class="section-title">
                        <?php echo htmlspecialchars($lang['Files'] ?? 'Files', ENT_QUOTES, 'UTF-8'); ?>
                        (<?php echo (int) $totalFiles; ?> total, page <?php echo (int) $currentPage; ?> of <?php echo (int) $totalPages; ?>)
                    </h5>
                    <div class="row">
                        <?php foreach ($files as $file): ?>
                            <?php
                            $__pmFileAccessType = isset($file->access_type) ? strtolower(trim((string) $file->access_type)) : '';
                            $__pmIsFileSharedRecipient = ($__pmFileAccessType === 'shared');
                            $__pmFilePerm = mv_get_highest_share_permission((string) ($file->shared_permissions ?? ''), 'read');
                            $__pmCanManageFileActions = $__pmIsFileSharedRecipient
                                ? (in_array($__pmFilePerm, array('write', 'admin'), true) || in_array($__pmCurrentFolderPerm, array('write', 'admin'), true))
                                : true;
                            $__pmCanShareFileActions = $__pmIsFileSharedRecipient
                                ? ($__pmFilePerm === 'admin' || $__pmCurrentFolderPerm === 'admin')
                                : true;
                            ?>
                            <div class="col-md-4 col-sm-4 col-12 mb-3">
                                <div class="file-item file-item-file"
                                     data-id="<?php echo (int) $file->id; ?>"
                                     data-type="file"
                                     data-gdrive-id="<?php echo htmlspecialchars((string) ($file->google_drive_file_id ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                     data-owner="<?php echo (int) ($file->uploaded_by ?? 0); ?>"
                                     data-access-type="<?php echo htmlspecialchars((string) ($file->access_type ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                     data-shared-permissions="<?php echo htmlspecialchars((string) ($file->shared_permissions ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                     draggable="true">
                                    <div class="file-checkbox" style="display:none;">
                                        <input data-index="<?php echo (int) $file->id; ?>" value="<?php echo (int) $file->id; ?>" name="btSelectItem" type="checkbox" class="file-select-checkbox" data-bulk-kind="file">
                                    </div>
                                    <div class="file-icon">
                                        <?php echo profile_media_get_file_icon($file); ?>
                                        <?php $recipientsMap = $fileRecipientsMap ?? array(); include __DIR__ . '/../../partials/media-vault-file-share-grid-badge.php'; ?>
                                    </div>
                                    <div class="file-info">
                                        <h6 class="file-name"><?php echo htmlspecialchars((string) ($file->original_filename ?? 'File'), ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <div class="file-date">
                                            <p class="file-meta"><?php echo htmlspecialchars(FileManager::formatFileSize((int) ($file->file_size ?? 0)), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="file-date"><?php echo !empty($file->created_at) ? htmlspecialchars(date('M j, Y', strtotime((string) $file->created_at)), ENT_QUOTES, 'UTF-8') : ''; ?></p>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($lang['Uploaded by'] ?? 'Uploaded by', ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($file->uploaded_by_name ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                    <div class="file-actions-dropdown">
                                        <div class="dropdown">
                                            <button class="btn-dots dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="downloadFile(<?php echo (int) $file->id; ?>); return false;">
                                                        <?php echo ts_icon('download', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Download'] ?? 'Download', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php if ($__pmCanManageFileActions): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="openMoveDialogForSingleFile(<?php echo (int) $file->id; ?>); return false;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 me-2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                                        </svg><?php echo htmlspecialchars($lang['Move'] ?? 'Move', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="previewFile(<?php echo (int) $file->id; ?>); return false;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 me-2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12S5.25 5.25 12 5.25 21.75 12 21.75 12 18.75 18.75 12 18.75 2.25 12 2.25 12Z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75A3.75 3.75 0 1 0 12 8.25a3.75 3.75 0 0 0 0 7.5Z" />
                                                        </svg><?php echo htmlspecialchars($lang['Preview'] ?? 'Preview', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php if ($__pmCanManageFileActions): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="renameFile(<?php echo (int) $file->id; ?>); return false;">
                                                        <?php echo ts_icon('edit', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Rename'] ?? 'Rename', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php if ($__pmCanShareFileActions): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="shareFile(<?php echo (int) $file->id; ?>); return false;">
                                                        <?php echo ts_icon('share', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Share'] ?? 'Share', ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($lang['Edit Share'] ?? 'Edit Share', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteFile(<?php echo (int) $file->id; ?>); return false;">
                                                        <?php echo ts_icon('delete', 'w-4 me-2 tasksession-timer-log-menu-ico'); ?><?php echo htmlspecialchars($lang['Delete'] ?? 'Delete', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($folders) && empty($files)): ?>
            <div class="empty-state">
                <span class="size-6"><?php echo ts_icon('folder'); ?></span>
                <h4><?php echo htmlspecialchars($lang['No files or folders'] ?? 'No files or folders', ENT_QUOTES, 'UTF-8'); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($lang['This folder is empty. Upload files or create a new folder to get started.'] ?? 'This folder is empty. Upload files or create a new folder to get started.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="row pagination-box">
    <div class="col-md-6 resilts-txt">
        Showing <span class="start_val"><?php echo (int) ($offset + 1); ?></span> to <span class="end_val"><?php echo (int) min($offset + $filesPerPage, $totalFiles); ?></span> of <span class="total_val"><?php echo (int) $totalFiles; ?></span> entries
    </div>
    <div class="col-md-6">
        <nav aria-label="Page navigation"><ul class="pagination justify-content-end">
            <?php for ($pi = 1; $pi <= $totalPages; $pi++): ?>
            <li class="page-item<?php echo $pi === (int) $currentPage ? ' active' : ''; ?>">
                <a class="page-link" href="<?php echo htmlspecialchars(profile_media_build_url(array('page' => $pi)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $pi; ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <form id="createFolderForm" action="javascript:void(0);" method="post">
                <div class="modal-body form-group">
                    <input type="hidden" name="folder_id" value="<?php echo $current_folder_id ? (int) $current_folder_id : 0; ?>">
                    <div class="mb-3">
                        <label for="folderName" class="form-label">Folder Name *</label>
                        <input type="text" class="form-control" id="folderName" name="folder_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="folderDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="folderDescription" name="folder_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn primary-btn">Create Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>
