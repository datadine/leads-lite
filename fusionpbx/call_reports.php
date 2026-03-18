<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

// All logged-in users can access
if (!is_logged_in()) {
    header("Location: /leads/login.php");
    exit;
}

$page_title = "Call Reports - FusionPBX Analytics";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header {
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            color: #333;
        }
        
        .filters {
            padding: 20px 30px;
            background: #f9f9f9;
            border-bottom: 1px solid #e0e0e0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 8px;
            color: white;
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .work-hours {
            padding: 0 30px 30px;
        }
        
        .work-hours h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .time-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }
        
        .time-item label {
            font-size: 12px;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }
        
        .time-item .time {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .compliance {
            padding: 15px;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
        }
        
        .compliance.success {
            background: #d4edda;
            color: #155724;
        }
        
        .compliance.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .compliance.danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .hourly-breakdown {
            padding: 0 30px 30px;
        }
        
        .hourly-breakdown h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .hour-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .hour-label {
            width: 120px;
            font-size: 14px;
            color: #666;
        }
        
        .bar-container {
            flex: 1;
            height: 30px;
            background: #f0f0f0;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
        }
        
        .bar-label {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            font-weight: 600;
            color: #333;
        }
        
        #loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .back-link {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Call Reports & Analytics</h1>
            <a href="<?= is_admin() ? '/leads/admin.php' : '/leads/' ?>" class="back-link">← Back to <?= is_admin() ? 'Admin' : 'Home' ?></a>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <label>Extension</label>
                <?php
                    $stmt_ext = $pdo->prepare("SELECT extension FROM users WHERE id = ?");
                    $stmt_ext->execute([current_user_id()]);
                    $user_extension = $stmt_ext->fetchColumn();
                ?>
                <?php if (is_admin()): ?>
                <select id="extension">
                    <option value="">All Extensions</option>
                </select>
                <?php else: ?>
                <select id="extension" disabled style="background:#f5f5f5;cursor:not-allowed;">
                    <option value="<?= htmlspecialchars($user_extension) ?>"><?= htmlspecialchars($user_extension) ?></option>
                </select>
                <?php endif; ?>
            </div>
            
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" id="date_from" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" id="date_to" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="filter-group">
                <label>Min Duration</label>
                <select id="min_duration">
                    <option value="0">All Calls</option>
                    <option value="30">30+ seconds</option>
                    <option value="60">60+ seconds (1 min)</option>
                    <option value="90">90+ seconds (1.5 min)</option>
                    <option value="120">120+ seconds (2 min)</option>
                    <option value="180">180+ seconds (3 min)</option>
                    <option value="300">300+ seconds (5 min)</option>
                </select>
            </div>
            
            <div class="filter-group" style="align-self: flex-end; display: flex; gap: 8px;">
                <button class="btn" onclick="loadReport()">Generate Report</button>
                <button class="btn" onclick="downloadCSV()" style="background:#28a745;border-color:#28a745;">⬇ Download CSV</button>
            </div>
        </div>
        
        <div id="loading" style="display: none;">
            Loading data...
        </div>
        
        <div id="report" style="display: none;">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Calls</h3>
                    <div class="value" id="total_calls">0</div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Talk Time</h3>
                    <div class="value" id="total_talk_time">0h 0m</div>
                </div>
                
                <div class="stat-card">
                    <h3>Unique Numbers</h3>
                    <div class="value" id="unique_numbers">0</div>
                </div>
                
                <div class="stat-card">
                    <h3>Avg Call Duration</h3>
                    <div class="value" id="avg_duration">0m 0s</div>
                </div>
            </div>
            
            <div class="work-hours">
                <h2>⏰ Work Hours</h2>
                <div class="time-grid">
                    <div class="time-item">
                        <label>First Call (EST)</label>
                        <div class="time" id="first_call">--:--</div>
                    </div>
                    
                    <div class="time-item">
                        <label>Last Call (EST)</label>
                        <div class="time" id="last_call">--:--</div>
                    </div>
                    
                    <div class="time-item">
                        <label>Time Span</label>
                        <div class="time" id="time_span">0h 0m</div>
                    </div>
                    
                    <div class="time-item">
                        <label>Target Hours</label>
                        <div class="time">9h 0m</div>
                    </div>
                </div>
                
                <div id="compliance_status" class="compliance">
                    --
                </div>
            </div>
            
            <div class="hourly-breakdown">
                <h2>📈 Hourly Breakdown</h2>
                <div id="hourly_chart">
                    <!-- Generated by JavaScript -->
                </div>
            </div>
        </div>
        
        <div id="no_data" class="no-data" style="display: none;">
            No call data found for the selected filters.
        </div>
    </div>
    
    <script>
        // Load available extensions on page load
        const IS_ADMIN = <?= is_admin() ? 'true' : 'false' ?>;

        window.onload = function() {
            if (IS_ADMIN) {
                loadExtensions();
            } else {
                loadReport();
            }
        };
        
        function loadExtensions() {
            fetch('call_reports_api.php?action=get_extensions')
                .then(r => r.json())
                .then(data => {
                    const select = document.getElementById('extension');
                    select.innerHTML = '<option value="">All Extensions</option>';
                    data.extensions.forEach(ext => {
                        select.innerHTML += `<option value="${ext}">${ext}</option>`;
                    });
                });
        }
        
        function loadReport() {
            const extension = document.getElementById('extension').value;
            const date_from = document.getElementById('date_from').value;
            const date_to = document.getElementById('date_to').value;
            const min_duration = document.getElementById('min_duration').value;
            
            document.getElementById('loading').style.display = 'block';
            document.getElementById('report').style.display = 'none';
            document.getElementById('no_data').style.display = 'none';
            
            const params = new URLSearchParams({
                action: 'get_report',
                extension,
                date_from,
                date_to,
                min_duration
            });
            
            fetch('call_reports_api.php?' + params)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    
                    if (data.total_calls === 0) {
                        document.getElementById('no_data').style.display = 'block';
                        return;
                    }
                    
                    document.getElementById('report').style.display = 'block';
                    
                    // Update stats
                    document.getElementById('total_calls').textContent = data.total_calls;
                    document.getElementById('total_talk_time').textContent = data.total_talk_time;
                    document.getElementById('unique_numbers').textContent = data.unique_numbers;
                    document.getElementById('avg_duration').textContent = data.avg_duration;
                    
                    // Update work hours
                    document.getElementById('first_call').textContent = data.first_call;
                    document.getElementById('last_call').textContent = data.last_call;
                    document.getElementById('time_span').textContent = data.time_span;
                    
                    // Update compliance
                    const compliance = document.getElementById('compliance_status');
                    compliance.textContent = data.compliance_message;
                    compliance.className = 'compliance ' + data.compliance_class;
                    
                    // Generate hourly breakdown
                    generateHourlyChart(data.hourly_breakdown);
                });
        }
        
        function downloadCSV() {
            const extension = document.getElementById('extension').value;
            const date_from = document.getElementById('date_from').value;
            const date_to = document.getElementById('date_to').value;
            const min_duration = document.getElementById('min_duration').value;

            const params = new URLSearchParams({
                action: 'download_csv',
                extension,
                date_from,
                date_to,
                min_duration
            });

            window.location.href = 'call_reports_api.php?' + params;
        }

        function generateHourlyChart(hourly) {
            const chart = document.getElementById('hourly_chart');
            chart.innerHTML = '';
            
            if (!hourly || hourly.length === 0) {
                chart.innerHTML = '<p style="text-align: center; color: #999;">No hourly data available</p>';
                return;
            }
            
            const maxCalls = Math.max(...hourly.map(h => h.calls));
            
            hourly.forEach(hour => {
                const percentage = maxCalls > 0 ? (hour.calls / maxCalls) * 100 : 0;
                
                chart.innerHTML += `
                    <div class="hour-bar">
                        <div class="hour-label">${hour.hour}</div>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: ${percentage}%"></div>
                            <div class="bar-label">${hour.calls} calls (${hour.talk_time})</div>
                        </div>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
