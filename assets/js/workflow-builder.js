document.addEventListener('DOMContentLoaded', function() {
    const wizardForm = document.getElementById('workflow-wizard-form');
    if (!wizardForm) return;

    const errorMessageDiv = document.getElementById('wizard-error-message');

    // --- Handle Final Form Submission with Fetch API ---
    wizardForm.addEventListener('submit', async function(event) {
        event.preventDefault(); // This line is critical - it stops the browser from navigating to the API page.
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        
        const workflowName = document.getElementById('workflow_name').value;
        if (workflowName.trim() === '') {
            showError('Please enter a workflow name.');
            return;
        }
        hideError();

        submitButton.disabled = true;
        submitButton.textContent = 'Creating...';

        try {
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                // This line performs the redirect correctly.
                window.location.href = result.redirect;
            } else {
                throw new Error(result.message || 'An unknown error occurred.');
            }
        } catch (error) {
            showError(error.message);
            submitButton.disabled = false;
            submitButton.textContent = 'Create & Continue';
        }
    });
    
    // --- Helper functions for error display ---
    function showError(message) {
        errorMessageDiv.textContent = message;
        errorMessageDiv.style.display = 'block';
    }

    function hideError() {
        errorMessageDiv.style.display = 'none';
    }
});