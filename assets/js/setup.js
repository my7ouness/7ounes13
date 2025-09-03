document.addEventListener('DOMContentLoaded', function() {
    const modalOverlay = document.getElementById('modal-overlay');
    const mainModal = document.getElementById('main-modal');

    if (!modalOverlay || !mainModal) return;

    function openModal(templateId) {
        const template = document.getElementById(templateId);
        if (template) {
            mainModal.innerHTML = '';
            mainModal.appendChild(template.content.cloneNode(true));
            modalOverlay.style.display = 'flex';
            if (templateId === 'template-form-facebook') {
                attachFacebookFormHandlers();
            }
        }
    }

    function closeModal() {
        modalOverlay.style.display = 'none';
        mainModal.innerHTML = '';
    }

    // Use event delegation for dynamically added elements
    document.body.addEventListener('click', function(event) {
        const addConnectionBtn = event.target.closest('#add-connection-btn, .add-node-btn');
        const closeButton = event.target.closest('.close-button');
        const choiceCard = event.target.closest('.choice-card');

        if (addConnectionBtn) {
            openModal('template-select-type');
            return;
        }
        if (closeButton) {
            closeModal();
            return;
        }
        if (choiceCard) {
            // Prevent action on disabled cards (like Shopify)
            if (choiceCard.getAttribute('onclick')) return;
            const nextTemplateId = choiceCard.dataset.nextTemplate;
            if (nextTemplateId) openModal(nextTemplateId);
            return;
        }
        if (event.target === modalOverlay) {
            closeModal();
        }
    });

    // --- Specific Logic for the Facebook Connection Form ---
    function attachFacebookFormHandlers() {
        const testBtn = document.getElementById('test-connection-btn');
        const fetchBtn = document.getElementById('fetch-campaigns-btn');
        const resultsDiv = document.getElementById('connection-test-results');

        testBtn.addEventListener('click', async () => {
            const accountId = document.getElementById('fb_account_id').value;
            const accessToken = document.getElementById('fb_access_token').value;
            
            if (!accountId || !accessToken) {
                resultsDiv.innerHTML = 'Ad Account ID and Access Token are required.';
                resultsDiv.style.color = 'red';
                resultsDiv.style.display = 'block';
                return;
            }

            resultsDiv.innerHTML = 'Testing...';
            resultsDiv.style.color = 'inherit';
            resultsDiv.style.display = 'block';
            testBtn.disabled = true;
            fetchBtn.style.display = 'none';

            const formData = new FormData();
            formData.append('mode', 'test');
            formData.append('ad_account_id', accountId);
            formData.append('access_token', accessToken);

            try {
                const response = await fetch(`${BASE_URL}/api/facebook-handler.php`, { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    resultsDiv.innerHTML = result.message;
                    resultsDiv.style.color = 'green';
                    fetchBtn.style.display = 'block';
                    testBtn.style.display = 'none';
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                resultsDiv.innerHTML = `Error: ${error.message}`;
                resultsDiv.style.color = 'red';
            } finally {
                testBtn.disabled = false;
            }
        });

        fetchBtn.addEventListener('click', async () => {
            const accountId = document.getElementById('fb_account_id').value;
            const accessToken = document.getElementById('fb_access_token').value;
            const campaignListContainer = document.getElementById('campaign-list-container');
            
            fetchBtn.disabled = true;
            fetchBtn.textContent = 'Fetching...';
            campaignListContainer.innerHTML = '<p>Loading campaigns...</p>';

            const formData = new FormData();
            formData.append('mode', 'fetch');
            formData.append('ad_account_id', accountId);
            formData.append('access_token', accessToken);
            
            try {
                const response = await fetch(`${BASE_URL}/api/facebook-handler.php`, { method: 'POST', body: formData });
                const result = await response.json();

                if (!result.success) throw new Error(result.message);
                
                // CRITICAL FIX: Handle the "no campaigns found" case gracefully
                if (result.campaigns.length === 0) {
                    campaignListContainer.innerHTML = '<p style="text-align:center; color: var(--text-light);">No campaigns were found in this ad account. You can still save the connection.</p>';
                } else {
                    let checkboxesHTML = '';
                    result.campaigns.forEach(campaign => {
                        checkboxesHTML += `
                            <div class="form-group-checkbox">
                                <input type="checkbox" name="campaigns[]" value="${campaign.id}" id="campaign-${campaign.id}">
                                <label for="campaign-${campaign.id}">${campaign.name} (${campaign.effective_status})</label>
                            </div>
                        `;
                    });
                    campaignListContainer.innerHTML = checkboxesHTML;
                }
                
                // Transition to the next step
                document.getElementById('fb-step-1').style.display = 'none';
                document.getElementById('fb-step-2').style.display = 'block';

            } catch (error) {
                alert('Error fetching campaigns: ' . error.message);
                fetchBtn.disabled = false;
                fetchBtn.textContent = 'Next: Fetch Campaigns';
            }
        });
    }
});

