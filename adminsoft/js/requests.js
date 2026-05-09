document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const detailsModal = document.getElementById('details-modal');
    const rejectReasonModal = document.getElementById('reject-reason-modal');
    const emailStatusModal = document.getElementById('email-status-modal');
    const closeButtons = document.querySelectorAll('.close');
    const cancelRejectBtn = document.getElementById('cancel-reject-btn');
    const closeEmailStatusBtn = document.getElementById('close-email-status');
    
    // Filter and search elements
    const statusFilter = document.getElementById('request-status-filter');
    const searchInput = document.getElementById('request-search');
    
    // Action buttons
    const viewButtons = document.querySelectorAll('.view-request');
    const approveButtons = document.querySelectorAll('.approve-request');
    const rejectButtons = document.querySelectorAll('.reject-request');
    const completeButtons = document.querySelectorAll('.complete-request');
    
    // Reject modal elements
    const rejectReasonSelect = document.getElementById('reject-reason-select');
    const rejectReasonOther = document.getElementById('reject-reason-other');
    const confirmRejectBtn = document.getElementById('confirm-reject-btn');
    
    // Current request ID for actions
    let currentRequestId = null;
    let currentRequestEmail = null;

    // Initialize event listeners
    initEventListeners();
    initFilterAndSearch();

    function initEventListeners() {
        // Modal close events
        closeButtons.forEach(btn => {
            btn.addEventListener('click', closeAllModals);
        });
        
        cancelRejectBtn.addEventListener('click', closeAllModals);
        closeEmailStatusBtn.addEventListener('click', closeAllModals);
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === detailsModal) closeAllModals();
            if (event.target === rejectReasonModal) closeAllModals();
            if (event.target === emailStatusModal) closeAllModals();
        });
        
        // View request details
        viewButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                viewRequestDetails(requestId);
            });
        });
        
        // Approve request
        approveButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                const residentEmail = this.getAttribute('data-email');
                console.log('Approve clicked - Request ID:', requestId, 'Email:', residentEmail);
                approveRequest(requestId, residentEmail);
            });
        });
        
        // Reject request - open reason modal
        rejectButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                const residentEmail = this.getAttribute('data-email');
                console.log('Reject clicked - Request ID:', requestId, 'Email:', residentEmail);
                openRejectReasonModal(requestId, residentEmail);
            });
        });
        
        // Complete request
        completeButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const requestId = this.getAttribute('data-id');
                const residentEmail = this.getAttribute('data-email');
                console.log('Complete clicked - Request ID:', requestId, 'Email:', residentEmail);
                completeRequest(requestId, residentEmail);
            });
        });
        
        // Reject reason select change
        rejectReasonSelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                rejectReasonOther.classList.remove('d-none');
            } else {
                rejectReasonOther.classList.add('d-none');
            }
        });
        
        // Confirm reject
        confirmRejectBtn.addEventListener('click', function() {
            let reason = rejectReasonSelect.value;
            if (reason === 'Other') {
                reason = rejectReasonOther.value.trim();
                if (!reason) {
                    alert('Please specify the rejection reason.');
                    return;
                }
            }
            console.log('Confirm reject - Request ID:', currentRequestId, 'Reason:', reason);
            rejectRequest(currentRequestId, currentRequestEmail, reason);
        });
    }

    function initFilterAndSearch() {
        // Status filter
        statusFilter.addEventListener('change', filterRequests);
        
        // Search input
        searchInput.addEventListener('input', filterRequests);
    }

    function filterRequests() {
        const statusFilterValue = statusFilter.value;
        const searchValue = searchInput.value.toLowerCase();
        const rows = document.querySelectorAll('.request-row');
        
        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            const rowText = row.textContent.toLowerCase();
            
            const statusMatch = statusFilterValue === 'all' || status === statusFilterValue;
            const searchMatch = rowText.includes(searchValue);
            
            if (statusMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function viewRequestDetails(requestId) {
        console.log('View details for request:', requestId);
        
        // Show loading state
        document.getElementById('modal-body').innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div class="spinner"></div>
                <p>Loading request details...</p>
            </div>
        `;
        detailsModal.style.display = 'flex';
        
        fetch(`../api/get_request.php?id=${requestId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Request details response:', data);
                if (data.success && data.request) {
                    const request = data.request;
                    const history = data.history || [];
                    
                    // Format dates safely
                    const formatDate = (dateString) => {
                        if (!dateString) return 'N/A';
                        try {
                            const date = new Date(dateString);
                            return isNaN(date.getTime()) ? 'Invalid Date' : date.toLocaleDateString();
                        } catch (e) {
                            return 'Invalid Date';
                        }
                    };

                    const formatDateTime = (dateString) => {
                        if (!dateString) return 'N/A';
                        try {
                            const date = new Date(dateString);
                            return isNaN(date.getTime()) ? 'Invalid Date' : date.toLocaleString();
                        } catch (e) {
                            return 'Invalid Date';
                        }
                    };

                    let modalBody = `
                        <div class="resident-details">
                            <div class="detail-group">
                                <div class="detail-label">Request Code</div>
                                <div class="detail-value">${escapeHtml(request.request_code || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Resident Name</div>
                                <div class="detail-value">${escapeHtml(request.full_name || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Resident Code</div>
                                <div class="detail-value">${escapeHtml(request.resident_code || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Contact Number</div>
                                <div class="detail-value">${escapeHtml(request.contact_number || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Email (Request)</div>
                                <div class="detail-value">${escapeHtml(request.resident_email || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Email (Profile)</div>
                                <div class="detail-value">${escapeHtml(request.resident_profile_email || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Document Type</div>
                                <div class="detail-value">${escapeHtml(request.document_type || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Base Fee</div>
                                <div class="detail-value">₱${parseFloat(request.base_fee || 0).toFixed(2)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Fee Amount</div>
                                <div class="detail-value">₱${parseFloat(request.fee_amount || 0).toFixed(2)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Fee Paid</div>
                                <div class="detail-value">${request.fee_paid ? 'Yes' : 'No'}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Purpose</div>
                                <div class="detail-value">${escapeHtml(request.purpose || 'N/A')}</div>
                            </div>
                    `;

                    // Add specific purpose if available
                    if (request.specific_purpose) {
                        modalBody += `
                            <div class="detail-group">
                                <div class="detail-label">Specific Purpose</div>
                                <div class="detail-value">${escapeHtml(request.specific_purpose)}</div>
                            </div>
                        `;
                    }

                    modalBody += `
                            <div class="detail-group">
                                <div class="detail-label">Urgency Level</div>
                                <div class="detail-value">${escapeHtml(request.urgency_level || 'N/A')}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Request Date</div>
                                <div class="detail-value">${formatDateTime(request.request_date)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Created At</div>
                                <div class="detail-value">${formatDateTime(request.created_at)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span class="status status-${(request.status || 'pending').toLowerCase()}">
                                        ${escapeHtml(request.status || 'Pending')}
                                    </span>
                                </div>
                            </div>
                    `;

                    // Add processed date if available
                    if (request.processed_date) {
                        modalBody += `
                            <div class="detail-group">
                                <div class="detail-label">Processed Date</div>
                                <div class="detail-value">${formatDateTime(request.processed_date)}</div>
                            </div>
                        `;
                    }

                    // Add processed by if available
                    if (request.processed_by_name) {
                        modalBody += `
                            <div class="detail-group">
                                <div class="detail-label">Processed By</div>
                                <div class="detail-value">${escapeHtml(request.processed_by_name)}</div>
                            </div>
                        `;
                    }

                    // Add rejection reason if available
                    if (request.rejection_reason) {
                        modalBody += `
                            <div class="detail-group">
                                <div class="detail-label">Rejection Reason</div>
                                <div class="detail-value">${escapeHtml(request.rejection_reason)}</div>
                            </div>
                        `;
                    }

                    modalBody += `</div>`; // Close resident-details div

                    // Add request history if available
                    if (history.length > 0) {
                        modalBody += `
                            <div style="margin-top: 20px;">
                                <h4>Request History</h4>
                                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                                    ${history.map(item => `
                                        <div style="padding: 10px; border-bottom: 1px solid #eee; background: ${history.indexOf(item) % 2 === 0 ? '#f9f9f9' : 'white'};">
                                            <strong>${formatDateTime(item.change_date)}</strong><br>
                                            <strong>Action:</strong> ${escapeHtml(item.admin_name || 'System')}: ${escapeHtml(item.old_status || 'N/A')} → ${escapeHtml(item.new_status)}<br>
                                            ${item.notes ? `<strong>Notes:</strong> <em>${escapeHtml(item.notes)}</em>` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    } else {
                        modalBody += `
                            <div style="margin-top: 20px; text-align: center; color: #666;">
                                <p>No history available for this request.</p>
                            </div>
                        `;
                    }

                    document.getElementById('modal-body').innerHTML = modalBody;
                } else {
                    document.getElementById('modal-body').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #d32f2f;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i>
                            <h4>Error Loading Request Details</h4>
                            <p>${escapeHtml(data.message || 'Unknown error occurred')}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('modal-body').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #d32f2f;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i>
                        <h4>Error Loading Request Details</h4>
                        <p>${escapeHtml(error.message)}</p>
                        <p style="font-size: 12px; margin-top: 10px;">Check console for more details.</p>
                    </div>
                `;
            });
    }

    function openRejectReasonModal(requestId, residentEmail) {
        currentRequestId = requestId;
        currentRequestEmail = residentEmail;
        rejectReasonSelect.value = 'No complete information in the database';
        rejectReasonOther.value = '';
        rejectReasonOther.classList.add('d-none');
        rejectReasonModal.style.display = 'flex';
    }

    function approveRequest(requestId, residentEmail) {
        if (!confirm('Are you sure you want to approve this request?')) return;
        
        updateRequestStatus(requestId, 'approved', residentEmail);
    }

    function rejectRequest(requestId, residentEmail, reason) {
        updateRequestStatus(requestId, 'rejected', residentEmail, reason);
    }

    function completeRequest(requestId, residentEmail) {
        if (!confirm('Are you sure you want to mark this request as completed?')) return;
        
        updateRequestStatus(requestId, 'completed', residentEmail);
    }

    function updateRequestStatus(requestId, status, residentEmail, reason = '') {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('status', status);
        if (reason) {
            formData.append('reason', reason);
        }

        fetch('../ajax/update_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Request updated successfully!', 'success');
                closeAllModals();
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Error updating request: ' + error.message, 'error');
        });
    }

    function showEmailStatus(data) {
        let emailStatusHtml = '';
        
        if (data.email_sent) {
            emailStatusHtml = `
                <div class="email-status success">
                    <h4>✅ Email Sent Successfully!</h4>
                    <p><strong>Recipient:</strong> ${data.resident_name} (${data.resident_email})</p>
                    <p><strong>Notification ID:</strong> ${data.notification_id}</p>
                    <div class="email-details">
                        ${data.email_message}
                    </div>
                </div>
            `;
        } else {
            emailStatusHtml = `
                <div class="email-status info">
                    <h4>📝 Request Updated</h4>
                    <p><strong>Recipient:</strong> ${data.resident_name} (${data.resident_email})</p>
                    <p><strong>Status:</strong> ${data.email_message}</p>
                    <p><em>The request has been updated successfully. Email notification has been logged.</em></p>
                </div>
            `;
        }
        
        document.getElementById('email-status-body').innerHTML = emailStatusHtml;
        emailStatusModal.style.display = 'flex';
    }

    function closeAllModals() {
        detailsModal.style.display = 'none';
        rejectReasonModal.style.display = 'none';
        emailStatusModal.style.display = 'none';
    }

    function showNotification(message, type) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Trigger animation
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return 'N/A';
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
document.addEventListener('DOMContentLoaded', function () {
    loadRequests();

    // Function to load requests dynamically
    function loadRequests() {
        fetch('../ajax/get_request.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('requests-container').innerHTML = data;

                // Attach event listeners for view buttons
                document.querySelectorAll('.view-btn').forEach(button => {
                    button.addEventListener('click', function () {
                        const requestId = this.dataset.id;
                        viewRequestDetails(requestId);
                    });
                });

                // Attach event listeners for reject buttons
                document.querySelectorAll('.reject-btn').forEach(button => {
                    button.addEventListener('click', function () {
                        const requestId = this.dataset.id;
                        rejectRequest(requestId);
                    });
                });
            })
            .catch(error => console.error('Error loading requests:', error));
    }

    // Function to view request details
    function viewRequestDetails(requestId) {
        fetch('../ajax/get_request_details.php?id=' + requestId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('request-details-modal');
                    modal.querySelector('.modal-body').innerHTML = `
                        <p><strong>Request Code:</strong> ${data.request.request_code}</p>
                        <p><strong>Resident Name:</strong> ${data.request.resident_name}</p>
                        <p><strong>Document Type:</strong> ${data.request.document_type}</p>
                        <p><strong>Status:</strong> ${data.request.status}</p>
                        <p><strong>Purpose:</strong> ${data.request.purpose}</p>
                        <p><strong>Urgency Level:</strong> ${data.request.urgency_level}</p>
                        <p><strong>Request Date:</strong> ${data.request.request_date}</p>
                    `;
                    modal.style.display = 'block';
                } else {
                    alert('Error loading request details: ' + data.message);
                }
            })
            .catch(error => console.error('Error fetching request details:', error));
    }

    // Function to handle request rejection
    function rejectRequest(requestId) {
        const reason = prompt('Enter rejection reason:');
        if (reason) {
            fetch('../ajax/update_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ request_id: requestId, action: 'reject', reason: reason })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Request rejected successfully.');
                        loadRequests(); // Reload requests dynamically
                    } else {
                        alert('Failed to reject request: ' + data.message);
                    }
                })
                .catch(error => console.error('Error rejecting request:', error));
        }
    }

    // Close modal when clicking the close button
    document.getElementById('close-modal-btn').addEventListener('click', function () {
        document.getElementById('request-details-modal').style.display = 'none';
    });
});