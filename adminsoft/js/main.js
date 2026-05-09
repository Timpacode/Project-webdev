// Main JavaScript file - common functionality across all pages

// Global notification function
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    });
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Utility function for making API calls
async function makeApiCall(url, data = {}, method = 'POST') {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
        };
        
        if (method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('API call failed:', error);
        return { success: false, message: 'Network error occurred' };
    }
}

// Form validation utility
function validateForm(formData, requiredFields) {
    const errors = [];
    
    requiredFields.forEach(field => {
        if (!formData.get(field)) {
            errors.push(`${field} is required`);
        }
    });
    
    return errors;
}

// Date formatting utility
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Time formatting utility
function formatTime(dateString) {
    const options = { hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleTimeString(undefined, options);
}

// Initialize tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltipText = e.target.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.position = 'absolute';
    tooltip.style.background = 'rgba(0,0,0,0.8)';
    tooltip.style.color = 'white';
    tooltip.style.padding = '5px 10px';
    tooltip.style.borderRadius = '4px';
    tooltip.style.zIndex = '10000';
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    
    e.target.tooltip = tooltip;
}

function hideTooltip(e) {
    if (e.target.tooltip) {
        e.target.tooltip.remove();
        e.target.tooltip = null;
    }
}

// Auto-save functionality for forms
function initAutoSave(formId, saveUrl, interval = 30000) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    let saveTimeout;
    
    form.addEventListener('input', () => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            saveForm(form, saveUrl);
        }, interval);
    });
}

async function saveForm(form, saveUrl) {
    const formData = new FormData(form);
    
    try {
        const response = await fetch(saveUrl, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Changes saved automatically', 'success');
        }
    } catch (error) {
        console.error('Auto-save failed:', error);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    
    // Add loading states to buttons
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.form && this.form.checkValidity()) {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                this.disabled = true;
            }
        });
    });
    
    // Confirm before leaving page with unsaved changes
    let formChanged = false;
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('input', () => {
            formChanged = true;
        });
    });
    
    window.addEventListener('beforeunload', (e) => {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
});
// AJAX Handler Functions
class AjaxHandler {
    static async makeRequest(url, data = {}, method = 'POST') {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
            };
            
            if (method !== 'GET') {
                const formData = new URLSearchParams();
                for (const key in data) {
                    formData.append(key, data[key]);
                }
                options.body = formData;
            } else {
                const params = new URLSearchParams(data);
                url += '?' + params.toString();
            }
            
            const response = await fetch(url, options);
            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            return { success: false, message: 'Network error occurred' };
        }
    }
    
    // Resident methods
    static async addResident(data) {
        return await this.makeRequest('ajax/add_resident.php', data);
    }
    
    static async updateResident(data) {
        return await this.makeRequest('ajax/update_resident.php', data);
    }
    
    static async deleteResident(residentId) {
        return await this.makeRequest('ajax/delete_resident.php', { resident_id: residentId });
    }
    
    static async getResident(residentId) {
        return await this.makeRequest('ajax/get_resident.php', { id: residentId }, 'GET');
    }
    
    // Request methods
    static async updateRequestStatus(requestId, status, reason = '') {
        return await this.makeRequest('ajax/update_request.php', {
            request_id: requestId,
            status: status,
            reason: reason
        });
    }
    
    static async getRequest(requestId) {
        return await this.makeRequest('ajax/get_request.php', { id: requestId }, 'GET');
    }
    
    // Dashboard methods
    static async getDashboardStats() {
        return await this.makeRequest('ajax/get_dashboard_stats.php', {}, 'GET');
    }
    
    // Document methods
    static async generateDocument(data) {
        return await this.makeRequest('ajax/generate_document.php', data);
    }
    
    // Search methods
    static async searchResidents(term, page = 1, limit = 10) {
        return await this.makeRequest('ajax/search_residents.php', {
            q: term,
            page: page,
            limit: limit
        }, 'GET');
    }
    
    // History methods
    static async getRequestHistory(filters = {}, page = 1, limit = 10) {
        return await this.makeRequest('ajax/get_request_history.php', {
            ...filters,
            page: page,
            limit: limit
        }, 'GET');
    }
}

// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 768 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
});

// Function to add a resident with a simulated loading state
function addResident(residentData) {
    // Show loading indicator (this is what you see as "Loading...")
    console.log("Loading..."); // In a real app, this would update the DOM

    // Return a Promise to handle the asynchronous operation
    return new Promise((resolve, reject) => {
        // Use setTimeout to simulate a server/database delay
        setTimeout(() => {
            try {
                // 1. Get the current residents from localStorage
                const residents = getResidents();

                // 2. Basic validation for required fields
                if (!residentData.complete_address) {
                    throw new Error("Complete Address is required.");
                }
                if (!residentData.contact_number) {
                    throw new Error("Contact Number is required.");
                }

                // 3. Create a new resident object with a unique ID
                const newResident = {
                    id: generateResidentId(), // Generate a unique ID
                    ...residentData // Include all the provided data
                };

                // 4. Add the new resident to the array
                residents.push(newResident);

                // 5. Save the updated array back to localStorage
                localStorage.setItem('residents', JSON.stringify(residents));

                // 6. Log success and hide loading indicator
                console.log("Resident added successfully!");
                resolve(newResident); // Resolve the promise with the new resident data

            } catch (error) {
                // If there's an error, reject the promise
                console.error("Error adding resident:", error.message);
                reject(error);
            }
        }, 2000); // Simulate a 2-second network/database delay
    });
}

// Main JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileToggle = document.querySelector('.mobile-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // Notification function
    window.showNotification = function(message, type = 'success') {
        const notification = document.getElementById('notification');
        const messageEl = document.getElementById('notification-message');
        
        if (notification && messageEl) {
            notification.className = `notification ${type}`;
            messageEl.textContent = message;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    };
    
    // Modal functionality
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'flex';
    };
    
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    };
    
    // Close modals when clicking outside or pressing ESC
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
        
        if (e.target.classList.contains('close')) {
            e.target.closest('.modal').style.display = 'none';
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
});