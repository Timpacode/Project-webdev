// History specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initHistory();
});

function initHistory() {
    // Initialize all filter elements
    const filterButton = document.getElementById('history-filter-button');
    const statusFilter = document.getElementById('history-status-filter');
    const documentTypeFilter = document.getElementById('history-document-type-filter');
    const searchInput = document.getElementById('history-search');
    const startDate = document.getElementById('history-start-date');
    const endDate = document.getElementById('history-end-date');
    const rowsPerPage = document.getElementById('history-rows');

    // Add event listeners
    if (filterButton) {
        filterButton.addEventListener('click', filterHistory);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filterHistory);
    }
    
    if (documentTypeFilter) {
        documentTypeFilter.addEventListener('change', filterHistory);
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterHistory, 300));
    }
    
    if (startDate && endDate) {
        startDate.addEventListener('change', filterHistory);
        endDate.addEventListener('change', filterHistory);
    }
    
    if (rowsPerPage) {
        rowsPerPage.addEventListener('change', function() {
            applyPagination(parseInt(this.value), 1);
        });
    }

    // Apply initial pagination
    const initialRows = parseInt(rowsPerPage?.value || 10);
    applyPagination(initialRows, 1);
}

function filterHistory() {
    console.log('Filtering history...');
    
    const statusFilter = document.getElementById('history-status-filter').value;
    const documentTypeFilter = document.getElementById('history-document-type-filter').value;
    const searchText = document.getElementById('history-search').value.toLowerCase().trim();
    const startDate = document.getElementById('history-start-date').value;
    const endDate = document.getElementById('history-end-date').value;
    const rows = document.querySelectorAll('#history-table tbody tr');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (row.cells.length === 0 || row.classList.contains('empty-state')) return;
        
        const status = row.dataset.status;
        const documentType = row.dataset.documentType;
        const residentName = row.dataset.residentName;
        const dateText = row.querySelector('.date-cell').textContent;
        const rowDate = parseTableDate(dateText);
        
        // Check status filter
        const matchesStatus = statusFilter === 'all' || status === statusFilter;
        
        // Check document type filter - FIXED
        const matchesDocumentType = documentTypeFilter === 'all' || documentType === documentTypeFilter;
        
        // Check search filter - FIXED
        const matchesSearch = searchText === '' || residentName.includes(searchText);
        
        // Check date filter
        let matchesDate = true;
        if (startDate) {
            const start = new Date(startDate);
            start.setHours(0, 0, 0, 0);
            matchesDate = matchesDate && rowDate >= start;
        }
        if (endDate) {
            const end = new Date(endDate);
            end.setHours(23, 59, 59, 999);
            matchesDate = matchesDate && rowDate <= end;
        }
        
        // Show or hide row based on all filters
        if (matchesStatus && matchesDocumentType && matchesSearch && matchesDate) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    console.log(`Found ${visibleCount} visible rows after filtering`);
    
    // Update showing count and apply pagination
    updateShowingCount(visibleCount);
    const rowsPerPage = parseInt(document.getElementById('history-rows').value);
    applyPagination(rowsPerPage, 1);
}

function parseTableDate(dateText) {
    // Parse date in format "Oct 10, 2025 8:27 AM"
    const months = {
        'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
        'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
    };
    
    const parts = dateText.split(' ');
    if (parts.length >= 4) {
        const month = months[parts[0]];
        const day = parseInt(parts[1].replace(',', ''));
        const year = parseInt(parts[2]);
        const timeParts = parts[3].split(':');
        let hours = parseInt(timeParts[0]);
        const minutes = parseInt(timeParts[1]);
        
        // Handle AM/PM
        if (parts[4] === 'PM' && hours < 12) hours += 12;
        if (parts[4] === 'AM' && hours === 12) hours = 0;
        
        return new Date(year, month, day, hours, minutes);
    }
    
    return new Date(); // Fallback to current date
}

function applyPagination(rowsPerPage, currentPage) {
    const allRows = document.querySelectorAll('#history-table tbody tr');
    const visibleRows = Array.from(allRows).filter(row => 
        row.style.display !== 'none' && 
        row.cells.length > 0 && 
        !row.classList.contains('empty-state')
    );
    
    const totalRows = visibleRows.length;
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    
    console.log(`Pagination: Showing ${startIndex + 1}-${Math.min(endIndex, totalRows)} of ${totalRows} rows`);
    
    // Hide all rows first
    allRows.forEach(row => {
        if (!row.classList.contains('empty-state')) {
            row.style.display = 'none';
        }
    });
    
    // Show empty state if no rows
    const emptyState = document.querySelector('.empty-state');
    if (emptyState) {
        emptyState.style.display = totalRows === 0 ? '' : 'none';
    }
    
    // Show rows for current page
    visibleRows.forEach((row, index) => {
        if (index >= startIndex && index < endIndex) {
            row.style.display = '';
        }
    });
    
    const actualStart = totalRows === 0 ? 0 : Math.min(startIndex + 1, totalRows);
    const actualEnd = Math.min(endIndex, totalRows);
    
    updateShowingCount(totalRows, actualStart, actualEnd);
    updatePaginationButtons(currentPage, Math.ceil(totalRows / rowsPerPage));
}

function updateShowingCount(total, start = 0, end = 0) {
    const startElement = document.getElementById('history-start');
    const endElement = document.getElementById('history-end');
    const totalElement = document.getElementById('history-total');
    
    if (startElement) startElement.textContent = start;
    if (endElement) endElement.textContent = end;
    if (totalElement) totalElement.textContent = total;
}

function updatePaginationButtons(currentPage, totalPages) {
    const container = document.getElementById('page-buttons-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    // Don't show pagination if no pages or only one page
    if (totalPages <= 1) return;
    
    // Create page buttons
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.onclick = () => {
            const rows = parseInt(document.getElementById('history-rows').value);
            applyPagination(rows, i);
        };
        container.appendChild(pageBtn);
    }
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showNotification(message, type = 'success') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle"></i>
        <span>${message}</span>
    `;
    
    // Add styles if not present
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 24px;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease;
                max-width: 400px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .notification.success {
                background: linear-gradient(135deg, #28a745, #20c997);
            }
            .notification.error {
                background: linear-gradient(135deg, #dc3545, #e83e8c);
            }
            .notification.info {
                background: linear-gradient(135deg, #17a2b8, #6f42c1);
            }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    document.body.appendChild(notification);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        notification.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 4000);
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('history-search');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        const searchInput = document.getElementById('history-search');
        if (searchInput && document.activeElement === searchInput) {
            searchInput.value = '';
            filterHistory();
        }
    }
});

// Initialize on load
try {
    initHistory();
} catch (error) {
    console.error('Error initializing history:', error);
}