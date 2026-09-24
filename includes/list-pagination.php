<?php
/**
 * Shared list pagination helpers (windowed page numbers, same logic as mail/inbox).
 */

if (!function_exists('tasksession_build_list_query')) {
    /**
     * Build a query string; empty-string values become bare flags (?company not ?company=).
     *
     * @param array $params
     * @return string Leading ? or empty string
     */
    function tasksession_build_list_query(array $params = array())
    {
        $bareFlags = array();
        $queryParams = array();
        foreach ($params as $key => $value) {
            if ($value === '' || $value === null) {
                $bareFlags[] = (string) $key;
                continue;
            }
            $queryParams[$key] = $value;
        }
        $parts = $bareFlags;
        $built = http_build_query($queryParams);
        if ($built !== '') {
            $parts[] = $built;
        }
        return $parts ? '?' . implode('&', $parts) : '';
    }
}

if (!function_exists('tasksession_list_pagination_meta')) {
    /**
     * @param int   $totalRows
     * @param int   $offset
     * @param int   $pageRowCount Rows on the current page
     * @param array $queryParams  GET params without page
     * @return array{start_from_b:int,end_val:int,pagination_href:callable}
     */
    function tasksession_list_pagination_meta($totalRows, $offset, $pageRowCount, array $queryParams = array())
    {
        $totalRows = max(0, (int) $totalRows);
        $offset = max(0, (int) $offset);
        $pageRowCount = max(0, (int) $pageRowCount);
        $start_from_b = ($totalRows > 0) ? ($offset + 1) : 0;
        $end_val = ($totalRows > 0) ? min($offset + $pageRowCount, $totalRows) : 0;
        $pagination_href = static function ($pageNum) use ($queryParams) {
            $params = $queryParams;
            $params['page'] = (int) $pageNum;
            return tasksession_build_list_query($params);
        };
        return array(
            'start_from_b' => $start_from_b,
            'end_val' => $end_val,
            'pagination_href' => $pagination_href,
        );
    }
}

if (!function_exists('tasksession_pagination_window')) {
    /**
     * @param int $currentPage
     * @param int $totalPages
     * @param int $radius Pages on each side of current
     * @return array{start:int,end:int}
     */
    function tasksession_pagination_window($currentPage, $totalPages, $radius = 2)
    {
        $currentPage = max(1, (int) $currentPage);
        $totalPages = max(1, (int) $totalPages);
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        return array(
            'start' => max(1, $currentPage - (int) $radius),
            'end' => min($totalPages, $currentPage + (int) $radius),
        );
    }
}

if (!function_exists('tasksession_render_list_pagination')) {
    /**
     * @param array $meta from tasksession_list_pagination_meta()
     * @param int   $totalRows
     * @param int   $totalPages
     * @param int   $currentPage
     * @param bool  $showNav
     */
    function tasksession_render_list_pagination(array $meta, $totalRows, $totalPages, $currentPage, $showNav = true)
    {
        global $lang;
        $pagination_href = $meta['pagination_href'];
        $currentPage = max(1, (int) $currentPage);
        $totalPages = max(1, (int) $totalPages);
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        $prevPage = max(1, $currentPage - 1);
        $nextPage = min($totalPages, $currentPage + 1);
        $window = tasksession_pagination_window($currentPage, $totalPages, 2);
        $startPage = $window['start'];
        $endPage = $window['end'];
        ?>
        <div class="row pagination-box">
            <div class="col-md-6 resilts-txt">
                <?php echo htmlspecialchars($lang['Showing'] ?? 'Showing', ENT_QUOTES, 'UTF-8'); ?>
                <span class="start_val"><?php echo (int) $meta['start_from_b']; ?></span>
                <?php echo htmlspecialchars($lang['to'] ?? 'to', ENT_QUOTES, 'UTF-8'); ?>
                <span class="end_val"><?php echo (int) $meta['end_val']; ?></span>
                <?php echo htmlspecialchars($lang['of'] ?? 'of', ENT_QUOTES, 'UTF-8'); ?>
                <?php echo (int) $totalRows; ?>
                <?php echo htmlspecialchars($lang['entries'] ?? 'entries', ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="col-md-6">
                <?php if ($showNav && $totalPages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-end">
                            <li class="page-item<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $currentPage <= 1 ? '#' : htmlspecialchars($pagination_href($prevPage), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                    <span class="sr-only"><?php echo htmlspecialchars($lang['Previous'] ?? 'Previous', ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </li>
                            <?php if ($startPage > 1) : ?>
                                <li class="page-item<?php echo $currentPage === 1 ? ' active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($pagination_href(1), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                                </li>
                                <?php if ($startPage > 2) : ?>
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php for ($p = $startPage; $p <= $endPage; $p++) : ?>
                                <li class="page-item<?php echo $p === $currentPage ? ' active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($pagination_href($p), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $p; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($endPage < $totalPages) : ?>
                                <?php if ($endPage < $totalPages - 1) : ?>
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                <?php endif; ?>
                                <li class="page-item<?php echo $currentPage === $totalPages ? ' active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($pagination_href($totalPages), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $totalPages; ?></a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $currentPage >= $totalPages ? '#' : htmlspecialchars($pagination_href($nextPage), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                    <span class="sr-only"><?php echo htmlspecialchars($lang['Next'] ?? 'Next', ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('tasksession_render_windowed_pagination_box')) {
    /**
     * Profile-style pagination footer with windowed page numbers and ellipsis.
     *
     * @param callable(int $page): string $hrefForPage Returns full href (e.g. ?page=2&user_id=1)
     */
    function tasksession_render_windowed_pagination_box($totalRecords, $pageSize, $currentPage, callable $hrefForPage, $radius = 2)
    {
        global $lang;
        $totalRecords = max(0, (int) $totalRecords);
        $pageSize = max(1, (int) $pageSize);
        $currentPage = max(1, (int) $currentPage);
        $totalPages = max(1, (int) ceil($totalRecords / $pageSize));
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        if ($totalRecords <= $pageSize) {
            return;
        }
        $startFrom = ($currentPage - 1) * $pageSize + 1;
        $endAt = min($currentPage * $pageSize, $totalRecords);
        $prevPage = max(1, $currentPage - 1);
        $nextPage = min($totalPages, $currentPage + 1);
        $window = tasksession_pagination_window($currentPage, $totalPages, $radius);
        $startPage = $window['start'];
        $endPage = $window['end'];
        ?>
        <div class="row pagination-box">
            <div class="col-md-6 resilts-txt">
                <?php
                if ($totalRecords > 0) {
                    echo htmlspecialchars($lang['Showing'] ?? 'Showing', ENT_QUOTES, 'UTF-8');
                    echo ' <span class="start_val">' . (int) $startFrom . '</span> ';
                    echo htmlspecialchars($lang['to'] ?? 'to', ENT_QUOTES, 'UTF-8');
                    echo ' <span class="end_val">' . (int) $endAt . '</span> ';
                    echo htmlspecialchars($lang['of'] ?? 'of', ENT_QUOTES, 'UTF-8');
                    echo ' <span class="total_val">' . (int) $totalRecords . '</span> ';
                    echo htmlspecialchars($lang['entries'] ?? 'entries', ENT_QUOTES, 'UTF-8');
                }
                ?>
            </div>
            <div class="col-md-6">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-end">
                        <li class="page-item<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage <= 1 ? '#' : htmlspecialchars($hrefForPage($prevPage), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                                <span class="sr-only"><?php echo htmlspecialchars($lang['Previous'] ?? 'Previous', ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </li>
                        <?php if ($startPage > 1) : ?>
                            <li class="page-item<?php echo $currentPage === 1 ? ' active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($hrefForPage(1), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                            </li>
                            <?php if ($startPage > 2) : ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php for ($p = $startPage; $p <= $endPage; $p++) : ?>
                            <li class="page-item<?php echo $p === $currentPage ? ' active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($hrefForPage($p), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($endPage < $totalPages) : ?>
                            <?php if ($endPage < $totalPages - 1) : ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item<?php echo $currentPage === $totalPages ? ' active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($hrefForPage($totalPages), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $totalPages; ?></a>
                            </li>
                        <?php endif; ?>
                        <li class="page-item<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $currentPage >= $totalPages ? '#' : htmlspecialchars($hrefForPage($nextPage), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                                <span class="sr-only"><?php echo htmlspecialchars($lang['Next'] ?? 'Next', ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php
    }
}
