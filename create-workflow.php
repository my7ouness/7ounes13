<?php
require_once 'includes/header.php';
require_login();
?>

<div class="wizard-container" style="max-width: 600px;">
    <div id="wizard-error-message" class="alert error" style="display: none; margin-bottom: 20px;"></div>
    
    <form id="workflow-wizard-form" action="api/workflow-builder.php" method="POST">
        <div class="wizard-step active">
            <h2>Let's Start with a Name</h2>
            <p>Give your new workflow a name you'll recognize. You'll add connections in the next step.</p>
            <div class="form-group">
                <label for="workflow_name" style="display:none;">Workflow Name</label>
                <input type="text" id="workflow_name" name="workflow_name" placeholder="e.g., 'My Moroccan E-commerce Brand'" required>
            </div>
            <div class="wizard-nav" style="justify-content: flex-end;">
                 <a href="workflows.php" class="btn btn-secondary" style="margin-right: 10px;">Cancel</a>
                <button type="submit" class="btn btn-primary">Create & Continue</button>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?><?php