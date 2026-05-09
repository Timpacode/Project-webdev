// Documents specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initDocuments();
});

function initDocuments() {
    const documentForm = document.getElementById('document-form');
    const requestSelect = document.getElementById('request-select');
    const requestDetails = document.getElementById('request-details');
    
    if (requestSelect) {
        requestSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption.value) {
                // Show request details
                document.getElementById('detail-resident').textContent = selectedOption.dataset.resident;
                document.getElementById('detail-document-type').textContent = selectedOption.dataset.documentType;
                document.getElementById('detail-purpose').textContent = selectedOption.dataset.purpose;
                requestDetails.classList.remove('d-none');
                
                // Update preview
                updateDocumentPreview(selectedOption);
            } else {
                requestDetails.classList.add('d-none');
                resetDocumentPreview();
            }
        });
    }
    
    if (documentForm) {
        documentForm.addEventListener('submit', handleDocumentGeneration);
    }
}

function updateDocumentPreview(option) {
    const preview = document.getElementById('document-preview');
    preview.innerHTML = `
        <div style="text-align: center; padding: 20px;">
            <div style="background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 30px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-file-certificate" style="font-size: 48px; margin-bottom: 15px;"></i>
                <h3 style="margin: 10px 0; font-weight: 600;">${option.dataset.documentType}</h3>
                <p style="margin: 0; opacity: 0.9;">Official Barangay Document</p>
            </div>
            <div style="text-align: left; background: white; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px; margin-bottom: 10px;">
                    <strong>Resident:</strong>
                    <span>${option.dataset.resident}</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px; margin-bottom: 10px;">
                    <strong>Document:</strong>
                    <span>${option.dataset.documentType}</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 10px;">
                    <strong>Purpose:</strong>
                    <span>${option.dataset.purpose}</span>
                </div>
            </div>
        </div>
    `;
}

function resetDocumentPreview() {
    const preview = document.getElementById('document-preview');
    preview.innerHTML = `
        <div style="text-align: center; color: #a0aec0;">
            <i class="fas fa-file-pdf" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
            <p style="font-size: 16px; margin: 0;">Document preview will appear here</p>
            <p style="font-size: 14px; margin: 5px 0 0 0;">Select a request to generate a document</p>
        </div>
    `;
}

function handleDocumentGeneration(e) {
    e.preventDefault();
    
    const requestSelect = document.getElementById('request-select');
    const requestId = requestSelect.value;
    
    if (!requestId) {
        showNotification('Please select a request first', 'error');
        return;
    }
    
    // Show loading state
    const preview = document.getElementById('document-preview');
    const originalContent = preview.innerHTML;
    preview.innerHTML = `
        <div style="text-align: center; color: #4361ee;">
            <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 20px;"></i>
            <h4>Generating Document...</h4>
            <p>Please wait while we create your document</p>
        </div>
    `;
    
    // Generate document via AJAX
    const formData = new FormData();
    formData.append('request_id', requestId);
    
    fetch('../ajax/generate_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            preview.innerHTML = `
                <div style="text-align: center;">
                    <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 30px; border-radius: 10px; margin-bottom: 20px;">
                        <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                        <h3 style="margin: 10px 0; font-weight: 600;">Document Ready!</h3>
                        <p style="margin: 0; opacity: 0.9;">Successfully generated</p>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button class="btn btn-primary" onclick="downloadGeneratedDocument('${data.file_path}')">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button class="btn btn-success" onclick="printDocument()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            `;
            showNotification('Document generated successfully!', 'success');
            
            // Reload page after 2 seconds to show new document in recent list
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            preview.innerHTML = originalContent;
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        preview.innerHTML = originalContent;
        showNotification('Network error occurred', 'error');
        console.error('Error:', error);
    });
}

function downloadGeneratedDocument(filePath) {
    // In a real implementation, this would download the actual file
    showNotification('Downloading document...', 'success');
    // Simulate download
    setTimeout(() => {
        showNotification('Document downloaded successfully!', 'success');
    }, 1000);
}

function printDocument() {
    showNotification('Opening print dialog...', 'success');
    setTimeout(() => {
        window.print();
    }, 500);
}

function downloadDocument(docId) {
    showNotification('Downloading document ID: ' + docId, 'success');
    // Implement actual download logic here
}