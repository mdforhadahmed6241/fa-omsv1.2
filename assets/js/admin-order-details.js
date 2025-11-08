document.addEventListener('DOMContentLoaded', function () {
    const ajaxUrl = oms_order_details.ajax_url;
    const nonce = oms_order_details.nonce;
    const allowedStatuses = oms_order_details.allowed_statuses || {};

    // Page detection
    const pageMarker = document.getElementById('oms-order-details-page-marker') || document.getElementById('oms-add-order-page-marker');
    const isEditPage = !!document.getElementById('oms-order-details-page-marker');
    const isAddPage = !!document.getElementById('oms-add-order-page-marker');
    const isIncompleteEditPage = !!document.getElementById('oms-incomplete-order-id');
    const orderId = document.getElementById('oms-order-id')?.value;
    const allCouriers = pageMarker ? JSON.parse(pageMarker.dataset.couriers) : [];


    // --- SHARED FUNCTIONS ---
    const calculateTotals = () => {
        let subtotal = 0;
        document.querySelectorAll('.oms-ordered-product-item').forEach(item => {
            const quantity = parseFloat(item.querySelector('.oms-item-quantity')?.value || 0);
            const price = parseFloat(item.querySelector('.oms-item-price')?.value || 0);
            const lineTotal = quantity * price;
            const totalEl = item.querySelector('.oms-item-total');
            if (totalEl) totalEl.textContent = lineTotal.toFixed(2);
            subtotal += lineTotal;
        });
        const discount = parseFloat(document.getElementById('oms-order-discount')?.value || 0);
        const shipping = parseFloat(document.getElementById('oms-order-shipping')?.value || 0);
        const grandTotal = subtotal - discount + shipping;
        const subtotalEl = document.getElementById('oms-order-subtotal');
        const grandTotalEl = document.getElementById('oms-order-grandtotal');
        if (subtotalEl) subtotalEl.value = subtotal.toFixed(2);
        if (grandTotalEl) grandTotalEl.value = grandTotal.toFixed(2);
    };

    function fetchHistoryData() {
        const phoneInput = document.getElementById('oms-customer-phone');
        const courierHistoryContainer = document.getElementById('oms-courier-history-container');
        const customerHistoryContainer = document.querySelector('.oms-customer-history-stats');
        
        if (!phoneInput) return;
        const phone = phoneInput.value.trim();
        if (phone.length < 5) {
            if (courierHistoryContainer) courierHistoryContainer.innerHTML = '<p>Enter a mobile number to see courier history.</p>';
            if (customerHistoryContainer) customerHistoryContainer.innerHTML = '<p>Enter a phone number to view customer history.</p>';
            return;
        }

        if(courierHistoryContainer) {
            courierHistoryContainer.innerHTML = '<span class="spinner is-active" style="display:block; margin: 20px auto;"></span>';
            fetch(ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'oms_ajax_get_courier_history', nonce: nonce, phone: phone })
            }).then(response => response.json()).then(result => {
                if (result.success) {
                    const data = result.data;
                    let html = '<div class="oms-courier-history-grid">';
                    const totals = data.totals;
                    const totalParcels = parseInt(totals['Total Parcels']) || 0;
                    if(totalParcels > 0) {
                        const deliveredParcels = parseInt(totals['Total Delivered']) || 0;
                        const canceledParcels = parseInt(totals['Total Canceled']) || 0;
                        const successRate = totalParcels > 0 ? ((deliveredParcels / totalParcels) * 100).toFixed(0) : 0;
                        html += `<div class="oms-courier-card"><h3>Overall</h3><p>Success Rate: <strong>${successRate}%</strong></p><p>Total: ${totalParcels}</p><div class="oms-courier-breakdown"><span class="oms-breakdown-delivered">✓ ${deliveredParcels}</span><span class="oms-breakdown-canceled">✗ ${canceledParcels}</span></div><div class="oms-progress-bar"><div class="oms-progress-fill" style="width: ${successRate}%;"></div></div></div>`;

                        for (const courierName in data.breakdown) {
                            const courierData = data.breakdown[courierName];
                            const cTotal = parseInt(courierData.total) || 0;
                            if(cTotal > 0){
                                const cDelivered = parseInt(courierData.delivered) || 0;
                                const cCanceled = parseInt(courierData.canceled) || 0;
                                const cSuccessRate = cTotal > 0 ? ((cDelivered / cTotal) * 100).toFixed(0) : 0;
                                html += `<div class="oms-courier-card"><h3>${courierName}</h3><p>Success Rate: <strong>${cSuccessRate}%</strong></p><p>Total: ${cTotal}</p><div class="oms-courier-breakdown"><span class="oms-breakdown-delivered">✓ ${cDelivered}</span><span class="oms-breakdown-canceled">✗ ${cCanceled}</span></div><div class="oms-progress-bar"><div class="oms-progress-fill" style="width: ${cSuccessRate}%;"></div></div></div>`;
                            }
                        }
                    } else {
                         html += '<p>No courier history found for this number.</p>';
                    }
                    html += '</div>';
                    courierHistoryContainer.innerHTML = html;
                } else {
                    courierHistoryContainer.innerHTML = `<p>${result.data?.message || 'Could not fetch courier history.'}</p>`;
                }
            });
        }

        if(customerHistoryContainer){
            customerHistoryContainer.innerHTML = '<span class="spinner is-active" style="float:left;"></span>';
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'oms_ajax_get_customer_history', nonce: nonce, phone: phone })
            }).then(res => res.json()).then(result => {
                if (result.success && result.data.total_orders > 0) {
                    const h = result.data;
                    customerHistoryContainer.innerHTML = `
                        <div class="oms-stat-item"><span>Total Orders:</span><strong>${h.total_orders}</strong></div>
                        <div class="oms-stat-item"><span>Completed:</span><strong>${h.completed}</strong></div>
                        <div class="oms-stat-item"><span>Shipped:</span><strong>${h.shipped}</strong></div>
                        <div class="oms-stat-item"><span>Ready to Ship:</span><strong>${h['ready-to-ship']}</strong></div>
                        <div class="oms-stat-item"><span>Delivered:</span><strong>${h.delivered}</strong></div>
                        <div class="oms-stat-item"><span>Returned:</span><strong>${h.returned}</strong></div>
                        <div class="oms-stat-item"><span>Cancelled:</span><strong>${h.cancelled}</strong></div>
                        <div class="oms-stat-item oms-total-value"><span>Total Spend (Conversion):</span><strong>${h.total_value_formatted}</strong></div>
                    `;
                } else {
                    customerHistoryContainer.innerHTML = '<p>No past orders found for this customer.</p>';
                }
            });
        }
    }
    
    // --- UNIVERSAL EVENT LISTENERS ---
    const orderedProductsContainer = document.getElementById('oms-ordered-products');
    if (orderedProductsContainer) {
        orderedProductsContainer.addEventListener('click', (e) => {
            const productItem = e.target.closest('.oms-ordered-product-item');
            if (!productItem) return;
            if (e.target.classList.contains('qty-btn')) {
                const input = productItem.querySelector('.oms-item-quantity');
                let qty = parseInt(input.value);
                if (e.target.classList.contains('plus')) qty++;
                else if (e.target.classList.contains('minus') && qty > 1) qty--;
                input.value = qty;
            } else if (e.target.classList.contains('oms-remove-item-btn')) {
                productItem.remove();
            }
            calculateTotals();
        });
        orderedProductsContainer.addEventListener('input', (e) => {
            if (e.target.classList.contains('oms-item-quantity') || e.target.classList.contains('oms-item-price')) {
                calculateTotals();
            }
        });
    }

    const searchInput = document.getElementById('oms-product-search');
    const searchResultsContainer = document.getElementById('oms-search-results');
    if (searchInput && searchResultsContainer && orderedProductsContainer) {
        let searchTimeout;
        searchInput.addEventListener('keyup', () => {
            clearTimeout(searchTimeout);
            const searchTerm = searchInput.value.trim();
            if (searchTerm.length < 2) { searchResultsContainer.innerHTML = ''; return; }
            searchTimeout = setTimeout(() => {
                searchResultsContainer.innerHTML = '<span class="spinner is-active" style="float:left; margin: 10px;"></span>';
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'oms_ajax_search_products', nonce: nonce, search_term: searchTerm })
                }).then(res => res.json()).then(result => {
                    searchResultsContainer.innerHTML = '';
                    if (result.success && result.data.length > 0) {
                        result.data.forEach(p => {
                            searchResultsContainer.innerHTML += `<div class="oms-search-result-item" data-product-id="${p.id}"><img src="${p.image_url}" alt="${p.name}"><div class="oms-search-result-item-details"><span class="oms-product-name">${p.name}</span><span class="oms-product-sku">SKU: ${p.sku}</span><span class="oms-product-price">Price: ${p.price_html} | Stock: ${p.stock_quantity}</span></div></div>`;
                        });
                    } else {
                        searchResultsContainer.innerHTML = '<p style="padding: 10px;">No products found.</p>';
                    }
                });
            }, 500);
        });
        searchResultsContainer.addEventListener('click', (e) => {
            const item = e.target.closest('.oms-search-result-item');
            if (!item) return;
            const productId = item.dataset.productId;
            if (orderedProductsContainer.querySelector(`[data-product-id="${productId}"]`)) return;
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'oms_ajax_get_product_details_for_order', nonce: nonce, product_id: productId })
            }).then(res => res.json()).then(result => {
                if (result.success) {
                    const p = result.data;
                    orderedProductsContainer.innerHTML += `<div class="oms-ordered-product-item" data-product-id="${p.id}" data-variation-id="0"><img src="${p.image_url}" alt="${p.name}"><div class="oms-ordered-item-details"><span class="oms-product-name">${p.name}</span><span class="oms-product-sku">SKU: ${p.sku}</span></div><div class="oms-item-controls"><div class="oms-quantity-control"><button class="button qty-btn minus">-</button><input type="number" class="oms-item-quantity" value="1" min="1"><button class="button qty-btn plus">+</button></div><div class="oms-price-control"><span>Price:</span><input type="number" class="oms-item-price" value="${p.price}" step="any"></div><div class="oms-total-control"><span>Total:</span><span class="oms-item-total">${p.price}</span></div><button class="oms-remove-item-btn">&times;</button></div></div>`;
                    calculateTotals();
                    searchInput.value = '';
                    searchResultsContainer.innerHTML = '';
                }
            });
        });
    }

    const phoneInput = document.getElementById('oms-customer-phone');
    if(phoneInput) phoneInput.addEventListener('blur', fetchHistoryData);

    ['oms-order-discount', 'oms-order-shipping'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', calculateTotals);
    });
    
    // --- Courier & Pathao Logic ---
    const courierSelect = document.getElementById('oms-courier-select');
    const pathaoLocationCard = document.getElementById('oms-pathao-location-card');
    
    function togglePathaoFields() {
        if (!courierSelect || !pathaoLocationCard) return;
        const selectedCourierId = courierSelect.value;
        const courier = allCouriers.find(c => c.id === selectedCourierId);
        pathaoLocationCard.style.display = (courier && courier.type === 'pathao') ? 'block' : 'none';
    }

    if (courierSelect) {
        courierSelect.addEventListener('change', togglePathaoFields);
        togglePathaoFields(); // Initial check
    }

    if (pathaoLocationCard) {
        const citySelect = document.getElementById('oms-pathao-city');
        const zoneSelect = document.getElementById('oms-pathao-zone');
        const areaSelect = document.getElementById('oms-pathao-area');
        
        const savedCityId = document.getElementById('oms-pathao-saved-city').value;
        const savedZoneId = document.getElementById('oms-pathao-saved-zone').value;
        const savedAreaId = document.getElementById('oms-pathao-saved-area').value;

        const fetchAndPopulate = async (selectElement, action, params, valueToSelect) => {
            selectElement.innerHTML = `<option value="">Loading...</option>`;
            try {
                const body = new URLSearchParams({ action, nonce, ...params });
                const response = await fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
                const result = await response.json();

                if (result.success && result.data.length > 0) {
                    const firstOptionText = action.includes('zones') ? 'Select Zone' : 'Select Area (Optional)';
                    selectElement.innerHTML = `<option value="">${firstOptionText}</option>`;
                    result.data.forEach(item => {
                        const id = item.zone_id || item.area_id;
                        const name = item.zone_name || item.area_name;
                        selectElement.innerHTML += `<option value="${id}">${name}</option>`;
                    });
                } else {
                    const errorText = action.includes('zones') ? 'No Zones Found' : 'No Areas Found';
                    selectElement.innerHTML = `<option value="">${errorText}</option>`;
                }
                 if (valueToSelect) {
                    selectElement.value = valueToSelect;
                }
            } catch (error) {
                selectElement.innerHTML = `<option value="">Error Loading Data</option>`;
            }
        };

        citySelect.addEventListener('change', function() {
            areaSelect.innerHTML = '<option value="">Select Zone First</option>';
            fetchAndPopulate(zoneSelect, 'oms_ajax_get_pathao_zones_for_order_page', { city_id: this.value });
        });

        zoneSelect.addEventListener('change', function() {
            fetchAndPopulate(areaSelect, 'oms_ajax_get_pathao_areas_for_order_page', { zone_id: this.value });
        });
        
        const initialize = async () => {
            if (savedCityId) {
                citySelect.value = savedCityId;
                await fetchAndPopulate(zoneSelect, 'oms_ajax_get_pathao_zones_for_order_page', { city_id: savedCityId }, savedZoneId);
                if (savedZoneId) {
                   await fetchAndPopulate(areaSelect, 'oms_ajax_get_pathao_areas_for_order_page', { zone_id: savedZoneId }, savedAreaId);
                }
            }
        };
        initialize();
    }

    // --- PAGE-SPECIFIC LOGIC ---
    if (isEditPage) {
        if(phoneInput && phoneInput.value) fetchHistoryData();
        calculateTotals();
        
        const allowedStatusListEl = document.getElementById('oms-allowed-status-list');
        if (allowedStatusListEl) {
            allowedStatusListEl.innerHTML = '';
            const statusSlugs = Object.keys(allowedStatuses);
            statusSlugs.length > 0 ? statusSlugs.forEach(slug => {
                const name = allowedStatuses[slug];
                allowedStatusListEl.innerHTML += `<span class="oms-status-button status-${slug}">${name}</span>`;
            }) : allowedStatusListEl.textContent = 'None';
        }
        
        const addNoteBtn = document.getElementById('oms-add-note-button');
        if (addNoteBtn) {
            addNoteBtn.addEventListener('click', function() {
                const note = document.getElementById('oms-add-note-textarea').value.trim();
                if (!note) return;
                this.disabled = true;
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'oms_ajax_add_order_note', nonce: nonce, order_id: orderId, note: note })
                }).then(res => res.json()).then(result => { if(result.success) window.location.reload(); });
            });
        }

        const updateStatusBtn = document.getElementById('oms-update-status-btn');
        if(updateStatusBtn){
             updateStatusBtn.addEventListener('click', function() {
                this.disabled = true;
                const newStatus = document.getElementById('oms-order-status').value;
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ action: 'oms_ajax_update_order_status', nonce: nonce, order_id: orderId, new_status: newStatus })
                }).then(res => res.json()).then(result => { if (result.success) window.location.reload(); });
            });
        }
        
        // This event listener is for the button inside the container
        document.getElementById('oms-courier-action-container').addEventListener('click', function(e) {
            if (e.target && e.target.id === 'oms-send-to-courier-btn') {
                const button = e.target;
                const courierId = document.getElementById('oms-courier-select').value;
                if (!courierId) {
                    alert('Please select a delivery method first.');
                    return;
                }
                const spinner = document.getElementById('oms-courier-spinner'), responseEl = document.getElementById('oms-courier-response');
                button.disabled = true; spinner.style.visibility = 'visible'; responseEl.style.display = 'none';

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'oms_ajax_send_to_courier', nonce: nonce, order_id: orderId, courier_id: courierId })
                }).then(res => res.json()).then(result => {
                    if (result.success) {
                        window.location.reload();
                    } else {
                        responseEl.textContent = result.data.message;
                        responseEl.className = 'oms-response-message error';
                        responseEl.style.display = 'block';
                        button.disabled = false; spinner.style.visibility = 'hidden';
                    }
                });
            }
        });
        
        const saveOrderBtn = document.getElementById('oms-save-order-btn');
        if(saveOrderBtn){
             saveOrderBtn.addEventListener('click', function() {
                const spinner = this.nextElementSibling;
                const responseEl = document.getElementById('oms-save-response');
                const orderData = {
                    order_id: orderId,
                    customer: { 
                        phone: document.getElementById('oms-customer-phone')?.value, 
                        name: document.getElementById('oms-customer-name')?.value, 
                        address_1: document.getElementById('oms-customer-address')?.value,
                        note: document.getElementById('oms-add-note-textarea')?.value
                    },
                    items: [],
                    totals: { 
                        discount: document.getElementById('oms-order-discount')?.value, 
                        shipping: document.getElementById('oms-order-shipping')?.value 
                    },
                    courier_id: document.getElementById('oms-courier-select')?.value,
                    pathao_location: {
                        city_id: document.getElementById('oms-pathao-city')?.value,
                        zone_id: document.getElementById('oms-pathao-zone')?.value,
                        area_id: document.getElementById('oms-pathao-area')?.value
                    }
                };
                document.querySelectorAll('.oms-ordered-product-item').forEach(item => {
                    orderData.items.push({ 
                        product_id: item.dataset.productId, 
                        variation_id: item.dataset.variationId, 
                        quantity: item.querySelector('.oms-item-quantity').value, 
                        price: item.querySelector('.oms-item-price').value 
                    });
                });
                this.disabled = true; spinner.style.visibility = 'visible'; responseEl.style.display = 'none';
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'oms_ajax_save_order_details', nonce: nonce, order_data: JSON.stringify(orderData) })
                }).then(res => res.json()).then(result => {
                    responseEl.textContent = result.data.message;
                    responseEl.className = `oms-response-message ${result.success ? 'success' : 'error'}`;
                    responseEl.style.display = 'block';
                }).finally(() => {
                    this.disabled = false; spinner.style.visibility = 'hidden';
                });
            });
        }
    }

    if (isAddPage || isIncompleteEditPage) {
        const createBtn = document.getElementById('oms-save-order-btn') || document.getElementById('oms-create-order-from-incomplete-btn');
        if (createBtn) {
            createBtn.addEventListener('click', function () {
                const spinner = this.nextElementSibling;
                const responseEl = document.getElementById('oms-save-response');
                const action = isIncompleteEditPage ? 'oms_ajax_create_incomplete_order' : 'oms_ajax_create_order';
                const orderData = {
                    customer: { 
                        phone: document.getElementById('oms-customer-phone')?.value, 
                        name: document.getElementById('oms-customer-name')?.value, 
                        address_1: document.getElementById('oms-customer-address')?.value, 
                        note: isIncompleteEditPage ? document.getElementById('oms-add-incomplete-note-textarea')?.value : document.getElementById('oms-shipping-note')?.value 
                    },
                    items: [],
                    totals: { 
                        discount: document.getElementById('oms-order-discount')?.value, 
                        shipping: document.getElementById('oms-order-shipping')?.value 
                    },
                    courier_id: document.getElementById('oms-courier-select')?.value,
                    order_source: document.getElementById('oms-order-source')?.value, // Get selected source
                    pathao_location: {
                        city_id: document.getElementById('oms-pathao-city')?.value,
                        zone_id: document.getElementById('oms-pathao-zone')?.value,
                        area_id: document.getElementById('oms-pathao-area')?.value
                    }
                };
                document.querySelectorAll('.oms-ordered-product-item').forEach(item => {
                    orderData.items.push({ 
                        product_id: item.dataset.productId, 
                        variation_id: item.dataset.variationId, 
                        quantity: item.querySelector('.oms-item-quantity').value, 
                        price: item.querySelector('.oms-item-price').value 
                    });
                });

                const bodyParams = { action: action, nonce: nonce, order_data: JSON.stringify(orderData) };
                if (isIncompleteEditPage) {
                    bodyParams.incomplete_order_id = document.getElementById('oms-incomplete-order-id').value;
                }

                this.disabled = true; spinner.style.visibility = 'visible';
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(bodyParams)
                }).then(res => res.json()).then(result => {
                    if (result.success && result.data.redirect_url) {
                        window.location.href = result.data.redirect_url;
                    } else {
                        responseEl.textContent = result.data.message;
                        responseEl.className = 'oms-response-message error';
                        responseEl.style.display = 'block';
                        this.disabled = false; spinner.style.visibility = 'hidden';
                    }
                });
            });
        }
    }

    if (isIncompleteEditPage) {
        if(phoneInput && phoneInput.value) fetchHistoryData();
        calculateTotals();
        
        const addIncompleteNoteBtn = document.getElementById('oms-add-incomplete-note-button');
        if (addIncompleteNoteBtn) {
            addIncompleteNoteBtn.addEventListener('click', function() {
                const button = this, spinner = button.nextElementSibling, noteTextarea = document.getElementById('oms-add-incomplete-note-textarea'), responseEl = document.getElementById('oms-incomplete-note-response');
                const incompleteOrderId = document.getElementById('oms-incomplete-order-id').value, note = noteTextarea.value.trim();
                button.disabled = true; spinner.style.visibility = 'visible'; responseEl.style.display = 'none';
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'oms_ajax_add_incomplete_order_note', nonce: nonce, incomplete_order_id: incompleteOrderId, note: note })
                }).then(res => res.json()).then(result => {
                    responseEl.textContent = result.data.message;
                    responseEl.className = `oms-response-message ${result.success ? 'success' : 'error'}`;
                    responseEl.style.display = 'block';
                }).finally(() => {
                    button.disabled = false; spinner.style.visibility = 'hidden';
                });
            });
        }
        
        const createBtn = document.getElementById('oms-create-order-from-incomplete-btn'), timerSpan = document.getElementById('oms-wait-timer'), deleteBtn = document.getElementById('oms-delete-incomplete-order-btn');
        const confirmPrompt = document.getElementById('oms-delete-confirm-prompt'), confirmBtn = document.getElementById('oms-delete-confirm-btn'), cancelBtn = document.getElementById('oms-delete-cancel-btn');

        if (timerSpan) {
            let timeLeft = parseInt(timerSpan.textContent);
            if (!isNaN(timeLeft)) {
                const timerInterval = setInterval(() => {
                    timeLeft--;
                    if (timeLeft > 0) {
                        timerSpan.textContent = timeLeft;
                    } else {
                        clearInterval(timerInterval);
                        createBtn.disabled = false;
                        createBtn.innerHTML = 'Create Order';
                    }
                }, 1000);
            } else {
                 createBtn.disabled = false;
                 createBtn.innerHTML = 'Create Order';
            }
        }
        
        if(deleteBtn && confirmPrompt && confirmBtn && cancelBtn){
            deleteBtn.addEventListener('click', () => { confirmPrompt.style.display = 'block'; });
            cancelBtn.addEventListener('click', () => { confirmPrompt.style.display = 'none'; });
            confirmBtn.addEventListener('click', () => {
                const responseEl = document.getElementById('oms-save-response'), incompleteOrderId = document.getElementById('oms-incomplete-order-id').value;
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ action: 'oms_ajax_delete_incomplete_order', nonce: nonce, incomplete_order_id: incompleteOrderId })
                }).then(res => res.json()).then(result => {
                     responseEl.textContent = result.data.message;
                     responseEl.className = `oms-response-message ${result.success ? 'success' : 'error'}`;
                     responseEl.style.display = 'block';
                     if(result.success && result.data.redirect_url){
                         setTimeout(() => { window.location.href = result.data.redirect_url; }, 1500);
                     }
                });
            });
        }
    }
});

