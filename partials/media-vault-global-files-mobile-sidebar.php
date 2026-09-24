<?php
/**
 * Mobile Media Vault drawer — same class names as mail inbox (.mobile-filters-sidebar).
 * IDs are prefixed mvMediaVault* to avoid clashing with inbox #mobileFiltersSidebar.
 * Optional: $mvMobileSidebarShowZip (default true) — staff sets false.
 */
if (!isset($mvMobileSidebarShowZip)) {
    $mvMobileSidebarShowZip = true;
}
?>
<div id="mvMediaVaultFiltersOverlay" class="sidebar-overlay" aria-hidden="true"></div>
<div id="mvMediaVaultFiltersSidebar" class="mobile-filters-sidebar hide" aria-hidden="true">
    <div class="mobile-filters-header">
        <h3><?php echo $lang['Files & options'] ?? 'Files & options'; ?></h3>
        <button type="button" class="mobile-filters-close-btn" id="mvMediaVaultFiltersCloseBtn" aria-label="<?php echo $lang['Close'] ?? 'Close'; ?>">
            <?php echo ts_icon('close'); ?>
        </button>
    </div>
    <div class="mobile-filters-content">
        <div class="mobile-filter-section">
            <button type="button" class="mobile-filter-btn" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                <?php echo ts_icon('folder'); ?>
                <span><?php echo $lang['Create Folder'] ?? 'Create folder'; ?></span>
            </button>
        </div>
        <div class="mobile-filter-section">
            <button type="button" class="mobile-filter-btn" id="mvMediaVaultFiltersUploadBtn">
                <?php echo ts_icon('upload'); ?>
                <span><?php echo $lang['Upload new'] ?? 'Upload new'; ?></span>
            </button>
        </div>

        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo empty($fileTypeFilter) ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mvDropdownHrefAllTypes, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('folder'); ?>
                <span><?php echo $lang['All Files'] ?? 'All files'; ?></span>
            </a>
        </div>
        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo $fileTypeFilter === 'image' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mvDropdownHrefImage, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('image'); ?>
                <span><?php echo $lang['Images'] ?? 'Images'; ?></span>
            </a>
        </div>
        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo $fileTypeFilter === 'document' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mvDropdownHrefDocument, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('document-text'); ?>
                <span><?php echo $lang['Documents'] ?? 'Documents'; ?></span>
            </a>
        </div>
        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo ($fileTypeFilter === 'other' || $fileTypeFilter === 'design') ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mvDropdownHrefOther, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('folder', 'mobile-filter-btn-icon'); ?>
                <span><?php echo $lang['Other Files'] ?? 'Other files'; ?></span>
            </a>
        </div>

        <?php if (!empty($mvDropdownHrefSharedMe)): ?>
        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo $scopeFilter === 'shared_with_me' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mvDropdownHrefSharedMe, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('user-group'); ?>
                <span><?php echo $lang['Shared with me'] ?? 'Shared with me'; ?></span>
            </a>
        </div>
        <?php endif; ?>
        <?php if (!empty($mvDropdownHrefRecent)): ?>
        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo $scopeFilter === 'recent' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mvDropdownHrefRecent, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('clock'); ?>
                <span><?php echo $lang['Recent'] ?? 'Recent'; ?></span>
            </a>
        </div>
        <?php endif; ?>

        <div class="mobile-filter-section">
            <button type="button" class="mobile-filter-btn" id="mvMediaVaultFiltersSearchBtn">
                <?php echo ts_icon('search'); ?>
                <span><?php echo $lang['Search'] ?? 'Search'; ?></span>
            </button>
        </div>

        <?php if (!empty($mediaVaultGridToggleUrl) && !empty($mediaVaultTableToggleUrl) && isset($mediaViewType)): ?>
        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo $mediaViewType === 'grid' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mediaVaultGridToggleUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('view-grid'); ?>
                <span><?php echo $lang['Grid View'] ?? 'Grid view'; ?></span>
            </a>
        </div>
        <div class="mobile-filter-section">
            <a class="mobile-filter-btn<?php echo $mediaViewType === 'table' ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($mediaVaultTableToggleUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ts_icon('table'); ?>
                <span><?php echo $lang['Table View'] ?? 'Table view'; ?></span>
            </a>
        </div>
        <?php endif; ?>

        <div class="mobile-filter-section mv-sidebar-bulk-enter">
            <button type="button" class="mobile-filter-btn" id="mvMediaVaultFiltersBulkEnterBtn">
                <?php echo ts_icon('check-circle'); ?>
                <span><?php echo $lang['Bulk Select'] ?? 'Bulk Select'; ?></span>
            </button>
        </div>
        <div class="mobile-filter-section mv-sidebar-bulk-only">
            <button type="button" class="mobile-filter-btn" data-mv-bulk-tab="bulkBackTab">
                <?php echo ts_icon('arrow-left'); ?>
                <span><?php echo $lang['Back'] ?? 'Back'; ?></span>
            </button>
        </div>
        <div class="mobile-filter-section mv-sidebar-bulk-only">
            <button type="button" class="mobile-filter-btn" data-mv-bulk-tab="bulkSelectAllTab">
                <?php echo ts_icon('check-circle'); ?>
                <span id="mvMobileBulkSelectAllLabel">Select all</span>
            </button>
        </div>
        <div class="mobile-filter-section mv-sidebar-bulk-only">
            <button type="button" class="mobile-filter-btn" data-mv-bulk-tab="bulkMoveTab">
                <?php echo ts_icon('arrow-right', 'mobile-filter-btn-icon'); ?>
                <span id="mvMobileBulkMoveLabel">Move</span>
            </button>
        </div>
        <div class="mobile-filter-section mv-sidebar-bulk-only">
            <button type="button" class="mobile-filter-btn" data-mv-bulk-tab="bulkDeleteTab">
                <?php echo ts_icon('delete'); ?>
                <span id="mvMobileBulkDeleteLabel">Delete</span>
            </button>
        </div>
        <?php if (!empty($mvMobileSidebarShowZip)): ?>
        <div class="mobile-filter-section mv-sidebar-bulk-only">
            <button type="button" class="mobile-filter-btn" data-mv-bulk-tab="bulkDownloadZipTab">
                <?php echo ts_icon('download'); ?>
                <span id="mvMobileBulkZipLabel"><?php echo $lang['Download all (ZIP)'] ?? 'Download all (ZIP)'; ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
