<div class="wrap">
    <h1>Order Management Settings</h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="#oms-general-settings" class="nav-tab nav-tab-active">General</a>
        <a href="#oms-courier-settings" class="nav-tab">Couriers</a>
        <a href="#oms-invoice-settings" class="nav-tab">Invoice</a>
        <a href="#oms-tools" class="nav-tab">Tools</a>
    </h2>

    <div id="oms-general-settings" class="oms-tab-content active">
        <form method="post" action="options.php">
            <?php
            settings_fields('oms_settings_group');
            do_settings_sections('oms-settings');
            submit_button();
            ?>
        </form>
    </div>

    <div id="oms-courier-settings" class="oms-tab-content">
        <div id="courier-list-container">
            <!-- Courier list will be loaded here by JS -->
        </div>

        <div class="oms-card" id="add-courier-card">
            <h2 id="add-courier-heading">Add New Courier</h2>
            <div id="courier-form-fields">
                <input type="hidden" id="courier-id">
                <div class="oms-form-group">
                    <label for="courier-name">Custom Name</label>
                    <input type="text" id="courier-name" placeholder="e.g., Steadfast (My Shop)">
                </div>
                <div class="oms-form-group">
                    <label for="courier-type">Courier Type</label>
                    <select id="courier-type">
                        <option value="">-- Select Type --</option>
                        <option value="steadfast">Steadfast</option>
                        <option value="pathao">Pathao</option>
                    </select>
                </div>
                
                <!-- Steadfast Fields -->
                <div id="steadfast-fields" class="courier-type-fields" style="display: none;">
                    <div class="oms-form-group"><label for="steadfast-api-key">API Key</label><input type="text" id="steadfast-api-key"></div>
                    <div class="oms-form-group"><label for="steadfast-secret-key">Secret Key</label><input type="text" id="steadfast-secret-key"></div>
                    <div class="oms-form-group"><label>Auto-send on 'Ready to Ship'</label><label class="oms-switch"><input type="checkbox" id="steadfast-auto-send" value="yes"><span class="oms-slider round"></span></label></div>
                    <div class="oms-form-group"><label>Webhook URL</label><input type="text" id="steadfast-webhook-url" readonly></div>
                </div>

                <!-- Pathao Fields -->
                <div id="pathao-fields" class="courier-type-fields" style="display: none;">
                    <div class="oms-form-group"><label for="pathao-client-id">Client ID</label><input type="text" id="pathao-client-id"></div>
                    <div class="oms-form-group"><label for="pathao-client-secret">Client Secret</label><input type="text" id="pathao-client-secret"></div>
                    <div class="oms-form-group"><label for="pathao-email">Pathao Email</label><input type="email" id="pathao-email"></div>
                    <div class="oms-form-group"><label for="pathao-password">Pathao Password</label><input type="password" id="pathao-password"></div>
                    <div class="oms-form-group"><label for="pathao-default-store">Default Store ID</label><input type="text" id="pathao-default-store"></div>
                    <div class="oms-form-group"><label>Auto-send on 'Ready to Ship'</label><label class="oms-switch"><input type="checkbox" id="pathao-auto-send" value="yes"><span class="oms-slider round"></span></label></div>
                     <div class="oms-form-group"><label>Webhook URL</label><input type="text" id="pathao-webhook-url" readonly></div>
                </div>
            </div>
            <button class="button button-primary" id="save-courier-btn">Save Courier</button>
            <button class="button button-secondary" id="cancel-edit-btn" style="display: none;">Cancel Edit</button>
            <span id="courier-save-spinner" class="spinner"></span>
        </div>
        <div id="oms-courier-save-response" class="oms-response-message" style="display:none; margin-top: 15px;"></div>
    </div>
    
    <div id="oms-invoice-settings" class="oms-tab-content">
        <form method="post" action="options.php">
            <?php
            settings_fields('oms_invoice_settings_group');
            do_settings_sections('oms-invoice-settings');
            submit_button();
            ?>
        </form>
    </div>

    <div id="oms-tools" class="oms-tab-content">
        <div class="oms-card">
            <h2>Pathao Data Sync</h2>
            <p>If Pathao has added new delivery locations, run this tool to download the latest cities, zones, and areas to your local database. This makes the order details page load much faster.</p>
            <div class="oms-form-group">
                <label for="oms-pathao-sync-courier">Select Pathao Account to Sync With</label>
                <select id="oms-pathao-sync-courier">
                    <option value="">-- Select a Pathao Courier --</option>
                    <?php
                    $all_couriers = OMS_Helpers::get_couriers();
                    foreach ($all_couriers as $c) {
                        if ($c['type'] === 'pathao') {
                            echo '<option value="' . esc_attr($c['id']) . '">' . esc_html($c['name']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <button class="button button-secondary" id="oms-sync-pathao-locations">Sync Pathao Locations Now</button>
            <div id="oms-sync-status">Status: Idle</div>
            <div id="oms-sync-progress-bar-container"><div id="oms-sync-progress-bar"></div></div>
        </div>

        <div class="oms-card">
            <h2>Clear Plugin Cache</h2>
            <p>If you've updated your API key or courier history seems outdated, click here to clear all cached data from this plugin (Pathao tokens, courier success rates, etc.).</p>
            <button class="button button-secondary" id="oms-clear-cache-btn">Clear Plugin Cache Now</button>
            <div id="oms-cache-status">Status: Idle</div>
        </div>
    </div>

    <script type="text/template" id="courier-item-template">
        <div class="oms-card courier-item" data-id="{{id}}">
            <div class="courier-item-details">
                <h3>{{name}} <span class="courier-type-badge type-{{type}}">{{type}}</span></h3>
            </div>
            <div class="courier-item-actions">
                <button class="button button-secondary edit-courier-btn">Edit</button>
                <button class="button button-link-delete delete-courier-btn">Delete</button>
            </div>
        </div>
    </script>
</div>
<script>
jQuery(document).ready(function($) {
    // --- Setup ---
    const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
    const settingsNonce = "<?php echo wp_create_nonce('oms_settings_nonce'); ?>";
    let couriers = <?php echo json_encode(OMS_Helpers::get_couriers()); ?>;

    // --- Tab Functionality ---
    const tabs = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
    const tabContents = document.querySelectorAll('.oms-tab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', e => {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            tab.classList.add('nav-tab-active');
            const target = tab.getAttribute('href');
            tabContents.forEach(content => {
                const parentForm = content.closest('form');
                if (content.id === target.substring(1)) {
                    content.style.display = 'block';
                    if (parentForm) parentForm.style.display = 'block';
                } else {
                    content.style.display = 'none';
                    if (parentForm) parentForm.style.display = 'none';
                }
            });
        });
    });
    
    document.querySelector('.nav-tab-active')?.click();

    // --- Courier Management UI ---
    const $courierListContainer = $('#courier-list-container');
    const courierItemTemplate = $('#courier-item-template').html();
    const $saveCourierBtn = $('#save-courier-btn');
    const $courierTypeSelect = $('#courier-type');
    const $courierIdField = $('#courier-id');
    const $courierNameField = $('#courier-name');
    const $addCourierHeading = $('#add-courier-heading');
    const $cancelEditBtn = $('#cancel-edit-btn');
    const $responseEl = $('#oms-courier-save-response');
    const $spinner = $('#courier-save-spinner');

    function renderCourierList() {
        $courierListContainer.html('');
        if (couriers.length > 0) {
            couriers.forEach(c => {
                const html = courierItemTemplate.replace(/{{id}}/g, c.id)
                                                .replace(/{{name}}/g, c.name)
                                                .replace(/{{type}}/g, c.type);
                $courierListContainer.append(html);
            });
        } else {
            $courierListContainer.html('<p>No couriers configured yet. Add one below.</p>');
        }
    }

    function resetCourierForm() {
        $courierIdField.val('');
        $courierNameField.val('');
        $courierTypeSelect.val('').prop('disabled', false);
        $('#add-courier-card input[type="text"], #add-courier-card input[type="email"], #add-courier-card input[type="password"]').val('');
        $('#add-courier-card input[type="checkbox"]').prop('checked', false);
        $('.courier-type-fields').hide();
        $addCourierHeading.text('Add New Courier');
        $cancelEditBtn.hide();
        $saveCourierBtn.text('Save Courier');
    }

    function populateFormForEdit(courierId) {
        const courier = couriers.find(c => c.id === courierId);
        if (!courier) return;

        resetCourierForm();
        $addCourierHeading.text(`Editing: ${courier.name}`);
        $cancelEditBtn.show();
        $saveCourierBtn.text('Update Courier');
        $courierIdField.val(courier.id);
        $courierNameField.val(courier.name);
        $courierTypeSelect.val(courier.type).prop('disabled', true);
        
        const $fieldsDiv = $(`#${courier.type}-fields`);
        if ($fieldsDiv.length) {
            $fieldsDiv.show();
            for (const [key, value] of Object.entries(courier.credentials)) {
                const $input = $(`#${courier.type}-${key.replace(/_/g, '-')}`);
                if ($input.length) {
                    if ($input.is(':checkbox')) {
                        $input.prop('checked', value === 'yes');
                    } else {
                        $input.val(value);
                    }
                }
            }
        }
        $(`#${courier.type}-webhook-url`).val(`<?php echo esc_url(get_rest_url(null, 'oms/v1/webhook/')); ?>${courier.id}`);
        $('html, body').animate({ scrollTop: $("#add-courier-card").offset().top }, 'smooth');
    }

    $courierTypeSelect.on('change', function() {
        $('.courier-type-fields').hide();
        if (this.value) {
            $(`#${this.value}-fields`).show();
        }
    });

    $saveCourierBtn.on('click', function() {
        const id = $courierIdField.val() || `${$courierTypeSelect.val()}_${Date.now()}`;
        const name = $courierNameField.val().trim();
        const type = $courierTypeSelect.val();
        if (!name || !type) { alert('Please provide a name and select a courier type.'); return; }

        const credentials = {};
        $(`#${type}-fields input, #${type}-fields select`).each(function() {
            const key = this.id.replace(`${type}-`, '').replace(/-/g, '_');
            credentials[key] = $(this).is(':checkbox') ? (this.checked ? 'yes' : 'no') : $(this).val();
        });

        const newCourier = { id, name, type, credentials };
        const existingIndex = couriers.findIndex(c => c.id === id);
        if (existingIndex > -1) {
            couriers[existingIndex] = newCourier;
        } else {
            couriers.push(newCourier);
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'oms_ajax_save_couriers', _wpnonce: settingsNonce, couriers: JSON.stringify(couriers) },
            beforeSend: () => { $saveCourierBtn.prop('disabled', true); $spinner.css('visibility', 'visible'); },
            success: (result) => {
                $responseEl.text(result.data.message).attr('class', `oms-response-message ${result.success ? 'success' : 'error'}`).show();
                if (result.success) setTimeout(() => window.location.reload(), 1000);
            },
            error: (jqXHR, status, err) => {
                console.error("Save Error:", status, err, jqXHR.responseText);
                $responseEl.text('Save failed. See browser console for details.').attr('class', 'oms-response-message error').show();
            },
            complete: () => { $saveCourierBtn.prop('disabled', false); $spinner.css('visibility', 'hidden'); }
        });
    });
    
    $cancelEditBtn.on('click', resetCourierForm);

    $courierListContainer.on('click', '.edit-courier-btn', function() {
        populateFormForEdit($(this).closest('.courier-item').data('id'));
    });

    $courierListContainer.on('click', '.delete-courier-btn', function() {
        if (!confirm('Are you sure you want to delete this courier?')) return;
        const courierId = $(this).closest('.courier-item').data('id');
        couriers = couriers.filter(c => c.id !== courierId);
            
        $responseEl.text('Deleting...').attr('class', 'oms-response-message').show();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'oms_ajax_save_couriers', _wpnonce: settingsNonce, couriers: JSON.stringify(couriers) },
            success: (result) => {
                $responseEl.text(result.data.message || 'Deleted.').attr('class', `oms-response-message ${result.success ? 'success' : 'error'}`).show();
                if (result.success) setTimeout(() => window.location.reload(), 1000);
            },
            error: (jqXHR, status, err) => {
                console.error("Delete Error:", status, err, jqXHR.responseText);
                $responseEl.text('Delete failed. See browser console for details.').attr('class', 'oms-response-message error').show();
            }
        });
    });

    renderCourierList();

    // --- Tools Logic ---
    const syncButton = document.getElementById('oms-sync-pathao-locations');
    const syncStatus = document.getElementById('oms-sync-status');
    const progressBarContainer = document.getElementById('oms-sync-progress-bar-container');
    const progressBar = document.getElementById('oms-sync-progress-bar');
    const syncNonce = "<?php echo wp_create_nonce('oms_sync_nonce'); ?>";
    const pathaoSyncSelect = document.getElementById('oms-pathao-sync-courier');

    if (syncButton) {
        syncButton.addEventListener('click', async function() {
            const courierId = pathaoSyncSelect.value;
            if (!courierId) { syncStatus.textContent = 'Error: Please select a Pathao account to sync with.'; return; }

            this.disabled = true;
            syncStatus.textContent = 'Starting sync...';
            progressBarContainer.style.display = 'block';
            progressBar.style.width = '0%';

            try {
                const cityResponse = await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'oms_ajax_sync_pathao_cities', _wpnonce: syncNonce, courier_id: courierId }) });
                const cityResult = await cityResponse.json();
                if (!cityResult.success) throw new Error(cityResult.data.message || 'Failed to sync cities.');
                
                const cities = cityResult.data.cities;
                if (!cities || cities.length === 0) throw new Error('No cities returned from API.');
                
                for (const [index, city] of cities.entries()) {
                    syncStatus.textContent = `Syncing zones for ${city.city_name}... (${index + 1}/${cities.length})`;
                    const zoneResponse = await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'oms_ajax_sync_pathao_zones', _wpnonce: syncNonce, city_id: city.city_id, courier_id: courierId }) });
                    const zoneResult = await zoneResponse.json();
                    if (!zoneResult.success) { console.warn(`Could not sync zones for city ID ${city.city_id}`); continue; }

                    const zones = zoneResult.data.zones;
                    if (zones && zones.length > 0) {
                         for (const zone of zones) {
                            await fetch(ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'oms_ajax_sync_pathao_areas', _wpnonce: syncNonce, zone_id: zone.zone_id, courier_id: courierId }) });
                        }
                    }
                    progressBar.style.width = `${((index + 1) / cities.length) * 100}%`;
                }
                syncStatus.textContent = 'Sync completed successfully!';
            } catch (error) {
                syncStatus.textContent = `Error: ${error.message}`;
                console.error('Sync Error:', error);
            } finally {
                this.disabled = false;
            }
        });
    }

    const clearCacheBtn = document.getElementById('oms-clear-cache-btn');
    const cacheStatus = document.getElementById('oms-cache-status');
    const cacheNonce = "<?php echo wp_create_nonce('oms_cache_nonce'); ?>";
    
    if (clearCacheBtn) {
        clearCacheBtn.addEventListener('click', function() {
            this.disabled = true;
            cacheStatus.textContent = 'Clearing cache...';
            fetch(ajaxUrl, {
                method: 'POST',
                body: new URLSearchParams({ action: 'oms_ajax_clear_plugin_cache', _wpnonce: cacheNonce })
            }).then(response => response.json()).then(result => {
                cacheStatus.textContent = result.success ? `Success: ${result.data.message}` : `Error: ${result.data.message}`;
            }).catch(error => {
                cacheStatus.textContent = 'An unexpected error occurred.';
            }).finally(() => {
                this.disabled = false;
            });
        });
    }
});
</script>
</div>

