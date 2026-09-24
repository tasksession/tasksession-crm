/**
 * Task Date Range Filter JavaScript
 * Handles date range filtering functionality for Kanban board
 */

// Global variables for date filtering
window.currentDateRange = { start: null, end: null };

/**
 * Quick range selection function
 * @param {string} range - The range type (last7, last30, last90, etc.)
 */
function selectQuickRange(range) {
    let startDate, endDate;
    const today = new Date();
    
    switch(range) {
        case 'last7':
            startDate = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
            endDate = today;
            break;
        case 'last30':
            startDate = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
            endDate = today;
            break;
        case 'last90':
            startDate = new Date(today.getTime() - (90 * 24 * 60 * 60 * 1000));
            endDate = today;
            break;
        case 'last6months':
            startDate = new Date(today.getFullYear(), today.getMonth() - 6, today.getDate());
            endDate = today;
            break;
        case 'thisYear':
            startDate = new Date(today.getFullYear(), 0, 1); // January 1st of current year
            endDate = today;
            break;
        case 'february2025':
            startDate = new Date(2025, 1, 1); // February 1st, 2025
            endDate = new Date(2025, 1, 28); // February 28th, 2025
            break;
        default:
            return;
    }
    
    // Format dates for URL parameters
    const startDateStr = startDate.toISOString().split('T')[0];
    const endDateStr = endDate.toISOString().split('T')[0];
    
    // Redirect to filtered view
    redirectWithDateRange(startDateStr, endDateStr);
}

/**
 * Custom range selection function
 */
function selectCustomRange() {
    const startDate = document.getElementById('customStart').value;
    const endDate = document.getElementById('customEnd').value;
    
    if (!startDate && !endDate) {
        alert('Please select at least one date');
        return;
    }
    
    // Redirect to filtered view
    redirectWithDateRange(startDate, endDate);
}

/**
 * Helper function to redirect with date range
 * @param {string} startDate - Start date in YYYY-MM-DD format
 * @param {string} endDate - End date in YYYY-MM-DD format
 */
function redirectWithDateRange(startDate, endDate) {
    // Determine base URL based on current page, preserving directory structure
    let baseUrl = 'kanban.php';
    const currentPath = window.location.pathname;
    
    if (currentPath.includes('all-tasks.php')) {
        baseUrl = 'all-tasks.php';
    } else if (currentPath.includes('task.php')) {
        baseUrl = 'task.php';
    } else if (currentPath.includes('company-profile.php')) {
        baseUrl = 'company-profile.php';
    } else if (currentPath.includes('profile.php')) {
        baseUrl = 'profile.php';
    } else if (currentPath.includes('/invoices')) {
        baseUrl = 'invoices';
    }
    
    // Extract the directory from the current path
    const pathParts = currentPath.split('/').filter(part => part); // Remove empty parts
    const fileName = pathParts[pathParts.length - 1]; // Last part is the filename
    const directory = pathParts[pathParts.length - 2]; // Second to last is the directory
    
    // Only add directory prefix if we're in a subdirectory and baseUrl doesn't already include it
    if (directory && ['admin', 'staff', 'client'].includes(directory) && !baseUrl.startsWith(directory + '/')) {
        baseUrl = directory + '/' + baseUrl;
    }
    
    // Make the URL absolute to prevent relative path issues
    // Dynamically determine the project root path
    if (!baseUrl.startsWith('/')) {
        // Get the current path and extract the project root
        const currentPath = window.location.pathname;
        const pathParts = currentPath.split('/').filter(part => part);
        
        // Find the project root by looking for admin, staff, or client directories
        let projectRoot = '';
        for (let i = 0; i < pathParts.length; i++) {
            if (['admin', 'staff', 'client'].includes(pathParts[i])) {
                // Found the role directory, everything before it is the project root
                projectRoot = pathParts.slice(0, i).join('/');
                break;
            }
        }
        
        // If we found a project root, use it; otherwise, assume root directory
        if (projectRoot) {
            baseUrl = '/' + projectRoot + '/' + baseUrl;
        } else {
            baseUrl = '/' + baseUrl;
        }
    }
    
    let url = baseUrl + '?';
    const params = [];
    
    // Read current URL parameters to preserve existing filters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Preserve existing parameters
    if (urlParams.get('all_tasks') === '1') {
        params.push('all_tasks=1');
    }
    
    if (urlParams.get('status')) {
        params.push('status=' + encodeURIComponent(urlParams.get('status')));
    }
    
    if (urlParams.get('search')) {
        params.push('search=' + encodeURIComponent(urlParams.get('search')));
    }
    
    if (urlParams.get('internal') === '1') {
        params.push('internal=1');
    }

    if (urlParams.get('archive') === '1') {
        params.push('archive=1');
    }

    if (urlParams.get('sort_order')) {
        params.push('sort_order=' + encodeURIComponent(urlParams.get('sort_order')));
    }
    
    if (urlParams.get('projectId')) {
        params.push('projectId=' + encodeURIComponent(urlParams.get('projectId')));
    }

    // Invoice list filters
    if (urlParams.get('recurring') === '1') {
        params.push('recurring=1');
    }
    if (urlParams.get('view')) {
        params.push('view=' + encodeURIComponent(urlParams.get('view')));
    }
    if (urlParams.get('id')) {
        params.push('id=' + encodeURIComponent(urlParams.get('id')));
    }
    
    // For profile page, preserve user_id and tab parameters
    if (urlParams.get('user_id')) {
        params.push('user_id=' + encodeURIComponent(urlParams.get('user_id')));
    }

    if (urlParams.get('company_id')) {
        params.push('company_id=' + encodeURIComponent(urlParams.get('company_id')));
    }
    
    if (urlParams.get('tab')) {
        params.push('tab=' + encodeURIComponent(urlParams.get('tab')));
    }
    
    // Add date parameters
    if (startDate) {
        params.push('start_date=' + encodeURIComponent(startDate));
    }
    if (endDate) {
        params.push('end_date=' + encodeURIComponent(endDate));
    }
    
    // Redirect to filtered view
    window.location.href = url + params.join('&');
}

/**
 * Toggle date range card visibility with smooth animation
 */
function toggleDateRangeCard() {
    const dateRangeCard = document.querySelector('.date-range.card');
    if (dateRangeCard) {
        if (dateRangeCard.classList.contains('show')) {
            // Hide card with animation
            dateRangeCard.classList.remove('show');
            setTimeout(() => {
                dateRangeCard.style.display = 'none';
            }, 300); // Wait for animation to complete
        } else {
            // Show card with animation
            dateRangeCard.style.display = 'block';
            // Small delay to ensure display is set before adding show class
            setTimeout(() => {
                dateRangeCard.classList.add('show');
            }, 10);
        }
    }
}

/**
 * Clear date range filter
 */
function clearDateRange() {
    // Clear the date inputs
    const customStart = document.getElementById('customStart');
    const customEnd = document.getElementById('customEnd');
    if (customStart) customStart.value = '';
    if (customEnd) customEnd.value = '';
    
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Remove date parameters
    urlParams.delete('start_date');
    urlParams.delete('end_date');
    
    // Build the new URL
    const baseUrl = window.location.pathname;
    const remainingParams = urlParams.toString();
    
    // Redirect to the cleaned URL
    if (remainingParams) {
        window.location.href = baseUrl + '?' + remainingParams;
    } else {
        window.location.href = baseUrl;
    }
}


/**
 * Initialize date range functionality
 * Call this function when the page loads
 */
function initDateRangeFilter() {
    // Set current date values and button text when page loads
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start_date');
    const endDate = urlParams.get('end_date');
    
    if (startDate && endDate) {
        // Set custom date inputs
        const customStart = document.getElementById('customStart');
        const customEnd = document.getElementById('customEnd');
        
        if (customStart) customStart.value = startDate;
        if (customEnd) customEnd.value = endDate;
        
        // Custom range is set from URL parameters
        
        // Store current date range
        window.currentDateRange.start = startDate;
        window.currentDateRange.end = endDate;
    }
    
    // No need for dropdown positioning since we're using a card now
    
    // Make sure functions are available globally
    window.selectQuickRange = selectQuickRange;
    window.selectCustomRange = selectCustomRange;
    window.toggleDateRangeCard = toggleDateRangeCard;
    window.clearDateRange = clearDateRange;
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initDateRangeFilter);
