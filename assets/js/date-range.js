// Date Range Filter JavaScript
// Global variables for date filtering
window.currentDateRange = { start: null, end: null };

// Quick range selection function
function selectQuickRange(range) {
    console.log('selectQuickRange called with:', range);
    
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
            console.log('Unknown range:', range);
            return;
    }
    
    // Format dates for URL parameters
    const startDateStr = startDate.toISOString().split('T')[0];
    const endDateStr = endDate.toISOString().split('T')[0];
    
    console.log('Formatted dates:', { startDateStr, endDateStr });
    
    // Update dropdown button text
    updateDateRangeButtonText(range);
    
    // Redirect to filtered view
    redirectWithDateRange(startDateStr, endDateStr);
}

// Custom range selection function
function selectCustomRange() {
    console.log('selectCustomRange called');
    
    const startDate = document.getElementById('customStart').value;
    const endDate = document.getElementById('customEnd').value;
    
    console.log('Custom dates:', { startDate, endDate });
    
    if (!startDate && !endDate) {
        alert('Please select at least one date');
        return;
    }
    
    // Update dropdown button text
    updateDateRangeButtonText('custom');
    
    // Redirect to filtered view
    redirectWithDateRange(startDate, endDate);
}

// Helper function to redirect with date range
function redirectWithDateRange(startDate, endDate) {
    // Get the base URL from the current page
    const currentUrl = new URL(window.location.href);
    const baseUrl = currentUrl.pathname;
    const params = [];
    
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Add existing parameters from current URL
    if (urlParams.get('status')) {
        params.push('status=' + encodeURIComponent(urlParams.get('status')));
    }
    
    if (urlParams.get('search')) {
        params.push('search=' + encodeURIComponent(urlParams.get('search')));
    }
    
    if (urlParams.get('internal')) {
        params.push('internal=' + encodeURIComponent(urlParams.get('internal')));
    }
    
    if (urlParams.get('user_id')) {
        params.push('user_id=' + encodeURIComponent(urlParams.get('user_id')));
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
    
    // Debug logging
    console.log('Date Range Filter:', { startDate, endDate, params, baseUrl });
    
    // Redirect to filtered view
    window.location.href = baseUrl + '?' + params.join('&');
}

// Update dropdown button text
function updateDateRangeButtonText(range) {
    const button = document.getElementById('dateRangeDropdown');
    if (button) {
        switch(range) {
            case 'last7':
                button.innerText = 'Last 7 days';
                break;
            case 'last30':
                button.innerText = 'Last 30 days';
                break;
            case 'last90':
                button.innerText = 'Last 90 days';
                break;
            case 'last6months':
                button.innerText = 'Last 6 months';
                break;
            case 'thisYear':
                button.innerText = 'This Year (Jan - Today)';
                break;
            case 'february2025':
                button.innerText = 'February 2025 (Payment Date)';
                break;
            case 'custom':
                button.innerText = 'Custom Range';
                break;
            default:
                button.innerText = 'Select Date Range';
        }
    }
}

// Set current date values and button text when page loads
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start_date');
    const endDate = urlParams.get('end_date');
    
    if (startDate && endDate) {
        // Set custom date inputs
        const customStart = document.getElementById('customStart');
        const customEnd = document.getElementById('customEnd');
        if (customStart) customStart.value = startDate;
        if (customEnd) customEnd.value = endDate;
        
        // Update button text to show custom range
        updateDateRangeButtonText('custom');
        
        // Store current date range
        window.currentDateRange.start = startDate;
        window.currentDateRange.end = endDate;
    }
});
