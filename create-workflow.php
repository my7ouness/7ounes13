<?php
require_once 'includes/header.php';
require_login();
?>

<div class="wizard-container">
    <div id="wizard-error-message" class="alert error" style="display: none; margin-bottom: 20px;"></div>
    
    <form id="workflow-wizard-form" action="api/workflow-builder.php" method="POST">
        <!-- STEP 1: Workflow Name -->
        <div class="wizard-step active" data-step="1">
            <h2>Let's Start with a Name</h2>
            <p>Give your new workflow a name you'll recognize.</p>
            <div class="form-group">
                <label for="workflow_name" style="display:none;">Workflow Name</label>
                <input type="text" id="workflow_name" name="workflow_name" placeholder="e.g., 'My Moroccan E-commerce Brand'" required>
            </div>
            <div class="wizard-nav">
                <a href="workflows.php" class="btn btn-back">Cancel</a>
                <button type="button" class="btn btn-primary btn-next">Next: Connect Store</button>
            </div>
        </div>

        <!-- STEP 2: Store Platform -->
        <div class="wizard-step" data-step="2">
            <h2>Connect Your E-commerce Store</h2>
            <p>Select the platform where you receive your orders.</p>
            <input type="hidden" name="store_platform" id="store_platform_input">
             <div class="choice-grid">
                <div class="choice-card" data-platform="woocommerce">
                    <img src="<?php echo BASE_URL; ?>/assets/images/logos/woocommerce.png" alt="WooCommerce">
                    <h3>WooCommerce</h3>
                </div>
                <div class="choice-card" onclick="alert('Shopify integration is coming soon!')">
                    <img src="https://cdn.shopify.com/shopify-marketing_assets/static/shopify-favicon.png" alt="Shopify" style="border-radius: 5px;">
                    <h3>Shopify</h3>
                </div>
            </div>
            <div id="woocommerce-form" class="platform-form" style="display:none; margin-top:30px;">
                <div class="form-group">
                    <label for="store_name">Store Nickname</label>
                    <input type="text" id="store_name" name="store_name" placeholder="e.g., My Fashion Store">
                </div>
                <div class="form-group">
                    <label for="store_url">Store URL</label>
                    <input type="url" id="store_url" name="store_url" placeholder="https://yourstore.com">
                </div>
                <div class="form-group">
                    <label for="consumer_key">Consumer Key</label>
                    <input type="text" id="consumer_key" name="consumer_key">
                </div>
                <div class="form-group">
                    <label for="consumer_secret">Consumer Secret</label>
                    <input type="password" id="consumer_secret" name="consumer_secret">
                </div>
            </div>
            <div class="wizard-nav">
                <button type="button" class="btn btn-back">Back</button>
                <button type="submit" class="btn btn-primary">Create Workflow & Finish</button>
            </div>
        </div>
        <!-- More steps for Ads, Shipping etc. can be added here in the future -->
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>