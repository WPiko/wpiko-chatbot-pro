jQuery(document).ready(function($) {
    // Auto Delete Settings with license check
    const autoDeleteCheckbox = document.querySelector('input[name="enable_auto_delete"]');
    const daysSelect = document.querySelector('select[name="auto_delete_days"]');

    if (autoDeleteCheckbox && daysSelect) {
        // Check if wpikoChatbotAdmin.license_active is defined and is false
        if (typeof wpikoChatbotAdmin !== 'undefined' && 
            typeof wpikoChatbotAdmin.license_active !== 'undefined' && 
            wpikoChatbotAdmin.license_active === false) {
            // Disable checkbox if license is not active
            autoDeleteCheckbox.checked = false;
            autoDeleteCheckbox.disabled = true;
            daysSelect.disabled = true;
        } else {
            // Normal behavior if license is active
            autoDeleteCheckbox.addEventListener('change', function() {
                daysSelect.disabled = !this.checked;
            });
        }
    }
});