// Mobile menu functionality
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileNav = document.getElementById('mobileNav');
const closeMobileMenu = document.getElementById('closeMobileMenu');

mobileMenuBtn.addEventListener('click', function() {
    mobileNav.style.display = 'flex';
    document.body.style.overflow = 'hidden';
});

closeMobileMenu.addEventListener('click', function() {
    mobileNav.style.display = 'none';
    document.body.style.overflow = 'auto';
});

// Close mobile menu when clicking on a link
document.querySelectorAll('.mobile-nav a').forEach(link => {
    link.addEventListener('click', function() {
        mobileNav.style.display = 'none';
        document.body.style.overflow = 'auto';
    });
});

// Show request form when PROCEED buttons are clicked
document.querySelectorAll('.proceed-btn').forEach(button => {
    button.addEventListener('click', function() {
        const documentType = this.getAttribute('data-document');
        const documentTypeId = this.getAttribute('data-document-id');
        document.getElementById('documentType').value = documentType;
        document.getElementById('documentTypeId').value = documentTypeId;
        document.getElementById('requestForm').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });
});

// Close form when X button is clicked
document.getElementById('closeForm').addEventListener('click', function() {
    document.getElementById('requestForm').style.display = 'none';
    document.body.style.overflow = 'auto';
});

// Close form when clicking outside the form
document.getElementById('requestForm').addEventListener('click', function(e) {
    if (e.target === this) {
        document.getElementById('requestForm').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// Form submission with database integration
// Form submission with database integration
document.getElementById('documentRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate all required fields before submission (except purpose)
    let isValid = true;
    const requiredFields = this.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    // Skip purpose field validation since it's not required
    // Only validate other required fields
    
    if (!isValid) {
        showNotification('Please fill in all required fields correctly.', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('.submit-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;
    
    // Get form data
    const formData = new FormData(this);
    
    // Add additional fields
    formData.append('birthdate', document.getElementById('birthdate').value);
    formData.append('occupation', document.getElementById('occupation').value);
    
    // Submit form data to process_request.php using AJAX
    fetch('process_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(data => {
        // Extract request code from response
        const requestCode = data.match(/REQ-[A-Z0-9-]+/) ? data.match(/REQ-[A-Z0-9-]+/)[0] : null;
        
        if (requestCode) {
            // Show success modal with request code
            showSuccessModal(requestCode, data);
        } else {
            showNotification(data, 'success');
        }
        
        // Reset form
        document.getElementById('documentRequestForm').reset();
        
        // Close form
        document.getElementById('requestForm').style.display = 'none';
        document.body.style.overflow = 'auto';
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    })
    .catch(error => {
        // Show error message
        showNotification('There was an error submitting your request. Please try again or contact support.', 'error');
        
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        console.error('Error submitting request:', error);
    });
});

// Show success modal with request code
function showSuccessModal(requestCode, message) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        padding: 20px;
    `;
    
    modal.innerHTML = `
        <div class="success-modal" style="background: white; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; position: relative; text-align: center;">
            <button onclick="this.parentElement.parentElement.remove()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #888; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&times;</button>
            
            <div style="width: 80px; height: 80px; background: #4CAF50; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: white; font-size: 2rem;">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h2 style="color: #4CAF50; margin-bottom: 15px;">Request Submitted Successfully!</h2>
            <p style="color: #666; margin-bottom: 20px; line-height: 1.6;">${message}</p>
            
            <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 25px; border: 2px dashed #0088FF;">
                <h3 style="color: #0088FF; margin-bottom: 10px;">Your Request Code</h3>
                <div style="font-size: 1.5rem; font-weight: bold; color: #333; background: white; padding: 15px; border-radius: 8px; border: 2px solid #0088FF; margin: 10px 0;">
                    ${requestCode}
                </div>
                <p style="color: #666; font-size: 14px; margin: 10px 0 0 0;">
                    <i class="fas fa-info-circle" style="color: #0088FF;"></i>
                    Save this code to track your request
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button onclick="copyToClipboard('${requestCode}')" style="padding: 12px 25px; background: #0088FF; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-copy"></i>
                    Copy Code
                </button>
                <button onclick="trackRequestWithCode('${requestCode}')" style="padding: 12px 25px; background: #4CAF50; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                    <i class="fas fa-search"></i>
                    Track Now
                </button>
            </div>
            
            <div style="margin-top: 25px; padding: 15px; background: #e8f5e8; border-radius: 8px; border-left: 4px solid #4CAF50;">
                <i class="fas fa-envelope" style="color: #4CAF50; margin-right: 10px;"></i>
                <strong>Check your email!</strong>
                <p style="margin: 8px 0 0 0; color: #2e7d32; font-size: 14px;">We've sent a confirmation email with your request details. You'll receive another email when your document is ready for pickup.</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Request code copied to clipboard!', 'success');
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        showNotification('Failed to copy request code', 'error');
    });
}

// Track request with specific code
function trackRequestWithCode(requestCode) {
    // Close success modal
    document.querySelector('.success-modal')?.parentElement?.remove();
    
    // Track the request
    trackRequestDirect(requestCode);
}

// Direct track request function
function trackRequestDirect(requestCode) {
    if (!requestCode) {
        showTrackRequestModal();
        return;
    }
    
    // Show loading state
    showNotification('Searching for your request...', 'success');
    
    // Fetch request status from server
   // In the trackRequestDirect function, update the fetch URL:
fetch('track_request.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'request_code=' + encodeURIComponent(requestCode)
})
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showRequestDetails(data.request, data.history || []);
        } else {
            showNotification(data.message || 'Request not found. Please check your request code.', 'error');
        }
    })
    .catch(error => {
        console.error('Error tracking request:', error);
        showNotification('Error tracking request. Please try again.', 'error');
    });
}

// Show track request modal
function showTrackRequestModal() {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        padding: 20px;
    `;
    
    modal.innerHTML = `
        <div class="track-input-modal" style="background: white; border-radius: 15px; padding: 30px; max-width: 500px; width: 100%; position: relative;">
            <button onclick="this.parentElement.parentElement.remove()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #888; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&times;</button>
            
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #0088FF, #0055AA); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: white; font-size: 1.5rem;">
                    <i class="fas fa-search"></i>
                </div>
                <h2 style="color: #0088FF; margin-bottom: 10px;">Track Your Request</h2>
                <p style="color: #666;">Enter your request code to check the status</p>
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #444;">Request Code</label>
                <input type="text" id="trackRequestInput" style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease;" placeholder="e.g., REQ-20241201-001">
                <p style="color: #666; font-size: 14px; margin-top: 8px;">
                    <i class="fas fa-info-circle" style="color: #0088FF;"></i>
                    Enter the request code you received after submission
                </p>
            </div>
            
            <button onclick="submitTrackRequest()" style="width: 100%; padding: 15px; background: linear-gradient(135deg, #0088FF, #0055AA); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: background 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px;">
                <i class="fas fa-search"></i>
                Track Request
            </button>
            
            <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                <p>Don't have your code? <a href="mailto:info@barangay.gov.ph" style="color: #0088FF; text-decoration: none;">Contact us</a></p>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focus on input field
    setTimeout(() => {
        const input = document.getElementById('trackRequestInput');
        if (input) input.focus();
    }, 100);
}

// Submit track request from modal
function submitTrackRequest() {
    const input = document.getElementById('trackRequestInput');
    const requestCode = input?.value.trim();
    
    if (!requestCode) {
        showNotification('Please enter your request code.', 'error');
        return;
    }
    
    // Close the input modal
    document.querySelector('.track-input-modal')?.parentElement?.remove();
    
    // Track the request
    trackRequestDirect(requestCode);
}

// Track request function (shows modal instead of prompt)
function trackRequest() {
    showTrackRequestModal();
}

// Show notification function
function showNotification(message, type = 'success') {
    const notification = document.getElementById('successMessage');
    const messageText = notification.querySelector('.message-text');
    
    messageText.textContent = message;
    notification.className = 'success-message';
    
    if (type === 'error') {
        notification.classList.add('error');
        notification.querySelector('i').className = 'fas fa-exclamation-circle';
    } else {
        notification.querySelector('i').className = 'fas fa-check-circle';
    }
    
    notification.style.display = 'flex';
    notification.style.animation = 'slideInRight 0.5s ease';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.5s ease';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 500);
    }, 5000);
}

// Smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            const headerHeight = document.querySelector('header').offsetHeight;
            const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        }
    });
});

// Add scroll effect to header
window.addEventListener('scroll', function() {
    const header = document.querySelector('header');
    if (window.scrollY > 100) {
        header.style.boxShadow = '0 2px 15px rgba(0,0,0,0.15)';
    } else {
        header.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    }
});

// Character counter for textarea
const purposeTextarea = document.getElementById('purpose');
const charCount = document.querySelector('.char-count');

if (purposeTextarea && charCount) {
    purposeTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = `${length}/500 characters`;
        
        if (length > 500) {
            charCount.style.color = '#f44336';
        } else {
            charCount.style.color = '#666';
        }
    });
}

// Scroll to top functionality
const scrollToTopBtn = document.getElementById('scrollToTop');
if (scrollToTopBtn) {
    scrollToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Show/hide scroll to top button
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            scrollToTopBtn.style.display = 'flex';
        } else {
            scrollToTopBtn.style.display = 'none';
        }
    });
}

// Help button functionality
const helpBtn = document.getElementById('helpBtn');
if (helpBtn) {
    helpBtn.addEventListener('click', function() {
        showNotification('Need help? Contact us at (072) 123-4567 or visit the barangay hall during office hours.', 'success');
    });
}

// Track request button functionality
const trackRequestBtn = document.getElementById('trackRequestBtn');
if (trackRequestBtn) {
    trackRequestBtn.addEventListener('click', trackRequest);
}

// Enhanced form validation
// Enhanced form validation
document.querySelectorAll('#documentRequestForm input, #documentRequestForm textarea, #documentRequestForm select').forEach(input => {
    input.addEventListener('blur', function() {
        // Skip validation for purpose field since it's not required
        if (this.id !== 'purpose') {
            validateField(this);
        }
    });
    
    input.addEventListener('input', function() {
        clearFieldError(this);
    });
});

function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';
    
    // Clear previous error
    clearFieldError(field);
    
    // Required field validation
    if (field.hasAttribute('required') && value === '') {
        isValid = false;
        errorMessage = 'This field is required';
    }
    
    // Email validation
    if (field.type === 'email' && value !== '') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
    }
    
    // Phone number validation
    if (field.name === 'contactNumber' && value !== '') {
        const phoneRegex = /^09[0-9]{9}$/;
        if (!phoneRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid Philippine phone number (09XXXXXXXXX)';
        }
    }
    
    if (!isValid) {
        showFieldError(field, errorMessage);
    }
    
    return isValid;
}

function showFieldError(field, message) {
    field.style.borderColor = '#f44336';
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Add error message
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.style.color = '#f44336';
    errorElement.style.fontSize = '14px';
    errorElement.style.marginTop = '5px';
    errorElement.textContent = message;
    
    field.parentNode.appendChild(errorElement);
}

function clearFieldError(field) {
    field.style.borderColor = '#e1e5e9';
    
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Enhanced request details display with status tracking
function showRequestDetails(request, history = []) {
    const statusColors = {
        'pending': '#ff9800',
        'approved': '#2196f3', 
        'completed': '#4caf50',
        'rejected': '#f44336'
    };
    
    const statusText = {
        'pending': 'Pending Review',
        'approved': 'Approved - Processing',
        'completed': 'Ready for Pickup',
        'rejected': 'Rejected'
    };
    
    const statusIcons = {
        'pending': 'fas fa-clock',
        'approved': 'fas fa-check-circle',
        'completed': 'fas fa-file-download',
        'rejected': 'fas fa-times-circle'
    };
    
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        padding: 20px;
    `;
    
    // Build timeline HTML
    let timelineHTML = '';
    if (history && history.length > 0) {
        timelineHTML = `
            <div style="margin-top: 25px;">
                <h3 style="color: #0088FF; margin-bottom: 15px; border-bottom: 2px solid #0088FF; padding-bottom: 8px;">
                    <i class="fas fa-history"></i> Request Timeline
                </h3>
                <div style="max-height: 200px; overflow-y: auto;">
        `;
        
        history.forEach((item, index) => {
            const date = new Date(item.change_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            timelineHTML += `
                <div style="display: flex; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: ${index < history.length - 1 ? '1px solid #e1e5e9' : 'none'};">
                    <div style="width: 12px; height: 12px; background: #0088FF; border-radius: 50%; margin-right: 15px; margin-top: 4px;"></div>
                    <div style="flex: 1;">
                        <div style="font-weight: bold; color: #333; margin-bottom: 5px;">
                            ${item.action.charAt(0).toUpperCase() + item.action.slice(1)}
                            ${item.old_status ? ` from ${item.old_status}` : ''}
                            ${item.new_status ? ` to ${item.new_status}` : ''}
                        </div>
                        <div style="color: #666; font-size: 14px; margin-bottom: 5px;">${item.notes || 'No notes provided'}</div>
                        <div style="color: #999; font-size: 12px;">
                            <i class="fas fa-calendar"></i> ${date}
                            ${item.admin_name ? ` • <i class="fas fa-user"></i> ${item.admin_name}` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        timelineHTML += `
                </div>
            </div>
        `;
    }
    
    modal.innerHTML = `
        <div class="track-request-modal" style="background: white; border-radius: 15px; padding: 30px; max-width: 600px; width: 100%; position: relative; max-height: 80vh; overflow-y: auto;">
            <button onclick="this.parentElement.parentElement.remove()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #888; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&times;</button>
            
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #0088FF, #0055AA); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: white; font-size: 1.5rem;">
                    <i class="fas fa-file-contract"></i>
                </div>
                <h2 style="color: #0088FF; margin-bottom: 10px;">Request Status</h2>
                <p style="color: #666;">Tracking your document request</p>
            </div>
            
            <!-- Status Badge -->
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="display: inline-flex; align-items: center; gap: 10px; background: ${statusColors[request.status]}; color: white; padding: 10px 20px; border-radius: 25px; font-weight: bold;">
                    <i class="${statusIcons[request.status]}"></i>
                    ${statusText[request.status]}
                </div>
            </div>
            
            <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Request Code:</strong>
                    <span style="font-family: monospace; font-weight: bold;">${request.request_code}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Document Type:</strong>
                    <span>${request.document_type}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Request Date:</strong>
                    <span>${request.request_date_formatted || new Date(request.request_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Applicant Name:</strong>
                    <span>${request.resident_name}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Contact Number:</strong>
                    <span>${request.resident_contact}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Email:</strong>
                    <span>${request.resident_email}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Address:</strong>
                    <span style="text-align: right;">${request.resident_address}</span>
                </div>
                ${request.fee_amount > 0 ? `
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Fee:</strong>
                    <span>₱${parseFloat(request.fee_amount).toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <strong>Payment Status:</strong>
                    <span style="color: ${request.fee_paid ? '#4caf50' : '#ff9800'}; font-weight: bold;">
                        ${request.fee_paid ? 'Paid' : 'Pending Payment'}
                    </span>
                </div>
                ` : `
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <strong>Fee:</strong>
                    <span style="color: #4caf50; font-weight: bold;">Free</span>
                </div>
                `}
                ${request.processed_date ? `
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e1e5e9;">
                    <strong>Processed Date:</strong>
                    <span>${request.processed_date_formatted || new Date(request.processed_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                ` : ''}
                ${request.pickup_date ? `
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <strong>Pickup Date:</strong>
                    <span>${request.pickup_date_formatted || new Date(request.pickup_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                ` : ''}
                ${request.rejection_reason ? `
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e5e9;">
                    <strong>Rejection Reason:</strong>
                    <div style="color: #f44336; margin-top: 8px; font-style: italic;">${request.rejection_reason}</div>
                </div>
                ` : ''}
            </div>
            
            <!-- Purpose Section -->
            <div style="background: #f0f7ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #0088FF;">
                <strong style="color: #0088FF; display: block; margin-bottom: 8px;">Purpose of Request:</strong>
                <p style="color: #333; margin: 0;">${request.purpose}</p>
                ${request.specific_purpose ? `
                <div style="margin-top: 10px;">
                    <strong style="color: #0088FF;">Specific Purpose:</strong>
                    <p style="color: #333; margin: 5px 0 0 0;">${request.specific_purpose}</p>
                </div>
                ` : ''}
            </div>
            
            <!-- Status Messages -->
            ${request.status === 'completed' ? `
            <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; border-left: 4px solid #4caf50; margin-bottom: 20px;">
                <i class="fas fa-check-circle" style="color: #4caf50; margin-right: 10px;"></i>
                <strong>Your document is ready for pickup!</strong>
                <p style="margin: 10px 0 0 0; color: #2e7d32;">
                    Please visit the barangay hall with your valid ID to collect your document.
                    ${request.pickup_date ? `Your document was ready on ${request.pickup_date_formatted || new Date(request.pickup_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}.` : ''}
                </p>
            </div>
            ` : ''}
            
            ${request.status === 'approved' ? `
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3; margin-bottom: 20px;">
                <i class="fas fa-check-circle" style="color: #2196f3; margin-right: 10px;"></i>
                <strong>Your request has been approved!</strong>
                <p style="margin: 10px 0 0 0; color: #1565c0;">
                    Your document is being processed. You will be notified when it's ready for pickup.
                    ${request.processed_date ? `Processing started on ${request.processed_date_formatted || new Date(request.processed_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}.` : ''}
                </p>
            </div>
            ` : ''}
            
            ${request.status === 'pending' ? `
            <div style="background: #fff3e0; padding: 15px; border-radius: 8px; border-left: 4px solid #ff9800; margin-bottom: 20px;">
                <i class="fas fa-clock" style="color: #ff9800; margin-right: 10px;"></i>
                <strong>Your request is pending review</strong>
                <p style="margin: 10px 0 0 0; color: #ef6c00;">
                    Our staff is reviewing your request. This usually takes 1-3 business days.
                    ${request.request_date ? `Submitted on ${request.request_date_formatted || new Date(request.request_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}.` : ''}
                </p>
            </div>
            ` : ''}
            
            ${request.status === 'rejected' ? `
            <div style="background: #ffebee; padding: 15px; border-radius: 8px; border-left: 4px solid #f44336; margin-bottom: 20px;">
                <i class="fas fa-times-circle" style="color: #f44336; margin-right: 10px;"></i>
                <strong>Request Rejected</strong>
                <p style="margin: 10px 0 0 0; color: #c62828;">
                    ${request.rejection_reason ? request.rejection_reason : 'Please contact the barangay office for more information.'}
                </p>
            </div>
            ` : ''}
            
            ${timelineHTML}
            
            <div style="text-align: center; color: #666; font-size: 14px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e1e5e9;">
                <p><i class="fas fa-phone"></i> For questions, contact: (072) 123-4567</p>
                <p><i class="fas fa-envelope"></i> Email: info@barangay.gov.ph</p>
                <p><i class="fas fa-clock"></i> Office Hours: Mon-Fri, 8:00 AM - 5:00 PM</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Keyboard navigation for form
document.addEventListener('keydown', function(e) {
    // Close form with Escape key
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('div[style*="position: fixed"]');
        modals.forEach(modal => {
            if (modal.style.display !== 'none') {
                modal.remove();
            }
        });
        
        if (document.getElementById('requestForm').style.display === 'flex') {
            document.getElementById('requestForm').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
});

// Add CSS for field errors
const errorStyles = document.createElement('style');
errorStyles.textContent = `
    .field-error {
        color: #f44336;
        font-size: 14px;
        margin-top: 5px;
        animation: fadeIn 0.3s ease;
    }
    
    .service-details {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
        font-size: 14px;
    }
    
    .service-fee {
        color: #0088FF;
        font-weight: bold;
    }
    
    .service-duration {
        color: #666;
    }
    
    .tracking-section {
        background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);
        padding: 40px;
        border-radius: 15px;
        margin-top: 50px;
        text-align: center;
    }
    
    .tracking-content h3 {
        color: #0088FF;
        margin-bottom: 15px;
        font-size: 1.5rem;
    }
    
    .tracking-content p {
        color: #666;
        margin-bottom: 25px;
        font-size: 1.1rem;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Success modal button hover effects */
    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    /* Input focus effects */
    input:focus {
        outline: none;
        border-color: #0088FF !important;
        box-shadow: 0 0 0 3px rgba(0, 136, 255, 0.1);
    }
`;
document.head.appendChild(errorStyles);

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Hide scroll to top button initially
    const scrollToTopBtn = document.getElementById('scrollToTop');
    if (scrollToTopBtn) {
        scrollToTopBtn.style.display = 'none';
    }
    
    console.log('ABSM System initialized successfully');
});