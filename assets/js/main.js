document.addEventListener('DOMContentLoaded', function() {
    const setupWizard = document.querySelector('.setup-wizard');
    if (!setupWizard) return;

    const steps = setupWizard.querySelectorAll('.setup-step-content');
    const stepIndicators = setupWizard.querySelectorAll('.setup-progress-step');
    const nextButtons = setupWizard.querySelectorAll('.btn-next');
    const prevButtons = setupWizard.querySelectorAll('.btn-prev');
    let currentStep = 0;

    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            step.classList.toggle('active', index === stepIndex);
        });
        stepIndicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index <= stepIndex);
        });
        currentStep = stepIndex;
    }

    nextButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (currentStep < steps.length - 1) {
                showStep(currentStep + 1);
            }
        });
    });

    prevButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (currentStep > 0) {
                showStep(currentStep - 1);
            }
        });
    });

    showStep(0); // Show the first step initially
});