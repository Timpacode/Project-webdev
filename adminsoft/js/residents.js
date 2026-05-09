// Check if user can manage residents
function canManageResidents() {
    if (CURRENT_USER_ROLE === 'Staff') {
        showStaffPermissionModal('Staff members cannot add, edit, or delete residents. Please contact your administrator.');
        return false;
    }
    return true;
}

// Use this in your resident management pages
document.addEventListener('DOMContentLoaded', function() {
    // Add resident button
    document.getElementById('add-resident-btn')?.addEventListener('click', function(e) {
        if (!canManageResidents()) {
            e.preventDefault();
        }
    });
    
    // Edit resident buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-resident')) {
            if (!canManageResidents()) {
                e.preventDefault();
            }
        }
    });
    
    // Delete resident buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-resident')) {
            if (!canManageResidents()) {
                e.preventDefault();
            }
        }
    });
});