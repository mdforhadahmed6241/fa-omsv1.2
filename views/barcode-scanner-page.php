<div class="wrap oms-barcode-scanner-wrap">
    <h1>Barcode Scanner</h1>
    <p>Scan an order's barcode from the invoice or sticker to update its status automatically.</p>

    <h2 class="nav-tab-wrapper">
        <a href="#scan-to-ready-to-ship" class="nav-tab nav-tab-active" data-status="ready-to-ship">Scan to Ready to Ship</a>
        <a href="#scan-to-shipped" class="nav-tab" data-status="shipped">Scan to Shipped</a>
    </h2>

    <!-- Tab Content: Ready to Ship -->
    <div id="scan-to-ready-to-ship" class="oms-tab-content active">
        <div class="oms-card">
            <input type="text" id="barcode-input-ready-to-ship" class="oms-barcode-input" placeholder="Click here and start scanning..." data-target-status="ready-to-ship" autocomplete="off">
            <div id="feedback-ready-to-ship" class="oms-scan-feedback"></div>
        </div>
        <div class="oms-card">
            <h3>Scan Log</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;">Time</th>
                        <th style="width: 20%;">Order #</th>
                        <th style="width: 40%;">Result</th>
                        <th style="width: 20%;">Previous Status</th>
                    </tr>
                </thead>
                <tbody id="log-ready-to-ship">
                    <!-- Log entries will be added here by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab Content: Shipped -->
    <div id="scan-to-shipped" class="oms-tab-content">
        <div class="oms-card">
            <input type="text" id="barcode-input-shipped" class="oms-barcode-input" placeholder="Click here and start scanning..." data-target-status="shipped" autocomplete="off">
            <div id="feedback-shipped" class="oms-scan-feedback"></div>
        </div>
        <div class="oms-card">
            <h3>Scan Log</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;">Time</th>
                        <th style="width: 20%;">Order #</th>
                        <th style="width: 40%;">Result</th>
                        <th style="width: 20%;">Previous Status</th>
                    </tr>
                </thead>
                <tbody id="log-shipped">
                    <!-- Log entries will be added here by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audio cues for feedback -->
    <audio id="oms-audio-success" src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" preload="auto"></audio>
    <audio id="oms-audio-error" src="https://actions.google.com/sounds/v1/alarms/alarm_clock.ogg" preload="auto"></audio>
</div>
