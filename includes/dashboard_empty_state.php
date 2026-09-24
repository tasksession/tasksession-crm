<?php
/**
 * Dashboard widget empty state (icon + heading).
 */
if (!function_exists('renderDashboardEmptyState')) {
    function renderDashboardEmptyState(string $icon, string $heading, array $options = []): void
    {
        $wrapListItem = (bool) ($options['list_item'] ?? true);
        $extraClass = trim((string) ($options['class'] ?? ''));
        $openWrap = $wrapListItem || $extraClass !== '';

        if ($openWrap) {
            $wrapClass = $wrapListItem ? 'list-group-item border-0 p-0' : '';
            if ($extraClass !== '') {
                $wrapClass = trim($wrapClass . ' ' . $extraClass);
            }
            echo '<div class="' . htmlspecialchars($wrapClass, ENT_QUOTES, 'UTF-8') . '">';
        }

        echo '<div class="empty-state text-center py-4 px-3">';
        echo '<span class="size-6" aria-hidden="true">' . ts_icon($icon) . '</span>';
        echo '<h4 class="title font-size-14 fw-semibold mb-0">';
        echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        echo '</h4>';
        echo '</div>';

        if ($openWrap) {
            echo '</div>';
        }
    }
}
