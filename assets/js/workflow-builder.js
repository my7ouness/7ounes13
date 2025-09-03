document.addEventListener('DOMContentLoaded', function() {
    const wizardForm = document.getElementById('workflow-wizard-form');
    if (!wizardForm) return;

    const steps = wizardForm.querySelectorAll('.wizard-step');
    const platformInput = document.getElementById('store_platform_input');
    const errorMessageDiv = document.getElementById('wizard-error-message');
    let currentStep = 1;

    function showStep(stepNumber) {
        steps.forEach(step => {
            step.classList.toggle('active', parseInt(step.dataset.step) === stepNumber);
        });
        currentStep = stepNumber;
    }

    // --- Event Listeners for Navigation ---
    wizardForm.querySelectorAll('.btn-next').forEach(button => {
        button.addEventListener('click', () => {
            // Simple validation for step 1
            const workflowName = document.getElementById('workflow_name').value;
            if (currentStep === 1 && workflowName.trim() === '') {
                showError('Please enter a workflow name.');
                return;
            }
            hideError();
            showStep(currentStep + 1);
        });
    });

    wizardForm.querySelectorAll('.btn-back').forEach(button => {
        button.addEventListener('click', () => {
            hideError();
            showStep(currentStep - 1);
        });
    });

    // --- Event Listeners for Platform Selection ---
    wizardForm.querySelectorAll('.choice-card').forEach(card => {
        card.addEventListener('click', function() {
            // Prevent action on disabled cards
            if (this.getAttribute('onclick')) {
                return;
            }
            // Remove 'selected' from all cards
            wizardForm.querySelectorAll('.choice-card').forEach(c => c.classList.remove('selected'));
            // Add 'selected' to the clicked card
            this.classList.add('selected');
            
            const platform = this.dataset.platform;
            platformInput.value = platform;

            // Show the corresponding form
            wizardForm.querySelectorAll('.platform-form').forEach(form => {
                form.style.display = 'none';
            });
            const formToShow = document.getElementById(platform + '-form');
            if (formToShow) {
                formToShow.style.display = 'block';
            }
        });
    });

    // --- Handle Final Form Submission with Fetch API ---
    wizardForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        
        // Final validation before submitting
        if (platformInput.value === '') {
            showError('You must select a store platform to continue.');
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
                // No alert needed, just redirect
                window.location.href = result.redirect;
            } else {
                throw new Error(result.message || 'An unknown error occurred.');
            }
        } catch (error) {
            showError(error.message);
            submitButton.disabled = false;
            submitButton.textContent = 'Create Workflow & Finish';
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

    // --- Initial State ---
    showStep(1);
});

