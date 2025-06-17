<?php
require_once(__DIR__ . '/admin-session.php');
check_admin_auth();
handle_logout();

// Handle AJAX requests for error log management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_error':
            $source = $_POST['source'] ?? '';
            $error_id = $_POST['error_id'] ?? '';
            
            try {
                $db = new PDO('sqlite:database.sqlite');
                
                if ($source === 'audit') {
                    $stmt = $db->prepare("DELETE FROM audit_logs WHERE id = ? AND severity IN ('error', 'critical')");
                    $stmt->execute([$error_id]);
                } elseif ($source === 'system') {
                    $stmt = $db->prepare("DELETE FROM system_logs WHERE id = ? AND type = 'error'");
                    $stmt->execute([$error_id]);
                }
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'clear_error_file':
            try {
                if (file_exists('error.log')) {
                    file_put_contents('error.log', '');
                }
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Handle special GET requests
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'check_error_log') {
        header('Content-Type: application/json');
        $file_exists = file_exists('error.log') && filesize('error.log') > 0;
        
        // Check if there are database errors
        $has_db_errors = false;
        try {
            $db = new PDO('sqlite:database.sqlite');
            $audit_count = $db->query("SELECT COUNT(*) FROM audit_logs WHERE severity IN ('error', 'critical')")->fetchColumn();
            $system_count = $db->query("SELECT COUNT(*) FROM system_logs WHERE type = 'error'")->fetchColumn();
            $has_db_errors = ($audit_count > 0 || $system_count > 0);
        } catch (Exception $e) {
            // Database might not be accessible
        }
        
        echo json_encode([
            'exists' => $file_exists,
            'has_db_errors' => $has_db_errors,
            'file_size' => $file_exists ? filesize('error.log') : 0
        ]);
        exit;
    }
    
    if ($_GET['action'] === 'view_full_log') {
        header('Content-Type: text/html');
        echo '<!DOCTYPE html><html><head><title>Full Error Log</title>';
        echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<style>body{font-family:monospace;background:#1a1a1a;color:#fff;padding:20px;margin:0;line-height:1.4;}';
        echo '.header{background:#333;padding:10px;margin:-20px -20px 20px -20px;border-bottom:2px solid #555;}';
        echo '.no-logs{text-align:center;color:#999;padding:50px;}';
        echo 'pre{white-space:pre-wrap;word-wrap:break-word;background:#2a2a2a;padding:15px;border-radius:5px;border-left:4px solid #ff6b6b;}';
        echo '</style></head><body>';
        echo '<div class="header"><h2>Full Error Log Viewer</h2>';
        echo '<p>Real-time view of all error logs • <a href="javascript:window.close()" style="color:#4dabf7;">Close Window</a></p></div>';
        
        if (file_exists('error.log') && filesize('error.log') > 0) {
            $content = file_get_contents('error.log');
            if (trim($content)) {
                echo '<pre>' . htmlspecialchars($content) . '</pre>';
            } else {
                echo '<div class="no-logs">Error log file is empty</div>';
            }
        } else {
            echo '<div class="no-logs">No error log file found or file is empty</div>';
        }
        
        echo '<script>document.title = "Error Log (' . (file_exists('error.log') ? number_format(filesize('error.log')) . ' bytes' : '0 bytes') . ')";</script>';
        echo '</body></html>';
        exit;
    }
    
    if ($_GET['action'] === 'export_errors') {
        $filename = 'error_logs_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // CSV header
        echo "Timestamp,Source,Type,Severity,Message,IP Address,File,Line,User\n";
        
        $exported_count = 0;
        
        // Export from error.log file
        if (file_exists('error.log') && filesize('error.log') > 0) {
            $lines = file('error.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $error = json_decode($line, true);
                if ($error && !empty($error['message'])) {
                    echo sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                        $error['timestamp'] ?? date('Y-m-d H:i:s'),
                        'file',
                        $error['type'] ?? 'Error',
                        $error['severity'] ?? 'error',
                        str_replace('"', '""', $error['message'] ?? ''),
                        $error['ip'] ?? '',
                        $error['file'] ?? '',
                        $error['line'] ?? '',
                        ''
                    );
                    $exported_count++;
                }
            }
        }
        
        // Export from audit_logs
        try {
            $db = new PDO('sqlite:database.sqlite');
            $audit_errors = $db->query("
                SELECT a.*, u.username 
                FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.id 
                WHERE a.severity IN ('error', 'critical') 
                ORDER BY a.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($audit_errors as $error) {
                echo sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    date('Y-m-d H:i:s', $error['created_at']),
                    'audit',
                    'system',
                    $error['severity'],
                    str_replace('"', '""', $error['details'] ?? ''),
                    $error['ip_address'] ?? '',
                    '',
                    '',
                    $error['username'] ?? ''
                );
                $exported_count++;
            }
        } catch (Exception $e) {
            // Database errors - add to export
            echo sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                date('Y-m-d H:i:s'),
                'system',
                'Database Error',
                'error',
                str_replace('"', '""', 'Failed to access audit logs: ' . $e->getMessage()),
                '',
                '',
                '',
                ''
            );
            $exported_count++;
        }
        
        // Add export summary as last line
        echo sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
            date('Y-m-d H:i:s'),
            'export',
            'Info',
            'info',
            "Export completed - {$exported_count} entries exported",
            '',
            '',
            '',
            'Admin'
        );
        
        exit;
    }
}

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $now = time();
    $day_ago = $now - 86400;
    $week_ago = $now - 604800;
    $month_ago = $now - 2592000;

    // Create system_logs table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT,
        message TEXT,
        created_at INTEGER
    )");

    // Create site_settings table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
        id INTEGER PRIMARY KEY,
        site_name TEXT,
        max_paste_size INTEGER,
        default_expiry INTEGER,
        maintenance_mode INTEGER
    )");

    // Paste Statistics
    $daily_pastes = $db->query("SELECT COUNT(*) FROM pastes WHERE created_at > $day_ago")->fetchColumn();
    $weekly_pastes = $db->query("SELECT COUNT(*) FROM pastes WHERE created_at > $week_ago")->fetchColumn();
    $monthly_pastes = $db->query("SELECT COUNT(*) FROM pastes WHERE created_at > $month_ago")->fetchColumn();

    // User Statistics
    $daily_new_users = $db->query("SELECT COUNT(*) FROM users WHERE created_at > $day_ago")->fetchColumn();
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Active Users - users who have been active (created pastes, logged in, etc.)
    $daily_active = $db->query("SELECT COUNT(DISTINCT user_id) FROM pastes WHERE created_at > $day_ago AND user_id IS NOT NULL")->fetchColumn();
    $weekly_active = $db->query("SELECT COUNT(DISTINCT user_id) FROM pastes WHERE created_at > $week_ago AND user_id IS NOT NULL")->fetchColumn();
    $monthly_active = $db->query("SELECT COUNT(DISTINCT user_id) FROM pastes WHERE created_at > $month_ago AND user_id IS NOT NULL")->fetchColumn();
    
    // Also count users who viewed pastes (if we have view tracking)
    try {
        // This will help identify users who are consuming content even if not creating
        $daily_viewers = $db->query("SELECT COUNT(DISTINCT ip_address) FROM (
            SELECT ip_address FROM pastes WHERE created_at > $day_ago AND user_id IS NULL
            UNION
            SELECT user_id as ip_address FROM pastes WHERE created_at > $day_ago AND user_id IS NOT NULL
        )")->fetchColumn();
    } catch (Exception $e) {
        $daily_viewers = $daily_active;
    }

    // Top 5 Most Viewed Pastes
    $top_pastes = $db->query("
        SELECT p.*, u.username 
        FROM pastes p 
        LEFT JOIN users u ON p.user_id = u.id 
        ORDER BY p.views DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Most Used Languages
    $top_languages = $db->query("
        SELECT language, COUNT(*) as count 
        FROM pastes 
        WHERE language IS NOT NULL 
        GROUP BY language 
        ORDER BY count DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // System Status
    $disk_total = disk_total_space("/");
    $disk_free = disk_free_space("/");
    $disk_used_percent = round(($disk_total - $disk_free) / $disk_total * 100, 2);

    // Error Logs from multiple sources
    $error_logs = [];
    
    // Get errors from audit_logs
    try {
        $audit_errors = $db->query("
            SELECT 'audit' as source, id, 'system' as type, details as message, created_at, severity, ip_address
            FROM audit_logs 
            WHERE severity IN ('error', 'critical') 
            ORDER BY created_at DESC 
            LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);
        $error_logs = array_merge($error_logs, $audit_errors);
    } catch (Exception $e) {
        // Audit table might not exist yet
    }
    
    // Get errors from system_logs if table exists
    try {
        $system_errors = $db->query("
            SELECT 'system' as source, id, type, message, created_at, 'error' as severity, NULL as ip_address
            FROM system_logs 
            WHERE type = 'error' 
            ORDER BY created_at DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        $error_logs = array_merge($error_logs, $system_errors);
    } catch (Exception $e) {
        // System logs table might not exist
    }
    
    // Get errors from error.log file (last 20 lines)
    if (file_exists('error.log')) {
        $error_file_content = file_get_contents('error.log');
        $error_lines = array_filter(explode("\n", $error_file_content));
        $recent_file_errors = array_slice(array_reverse($error_lines), 0, 20);
        
        foreach ($recent_file_errors as $line) {
            if (!empty(trim($line))) {
                $error_data = json_decode($line, true);
                if ($error_data && isset($error_data['message'])) {
                    $error_logs[] = [
                        'source' => 'file',
                        'id' => 'file_' . md5($line),
                        'type' => $error_data['type'] ?? 'Error',
                        'message' => $error_data['message'],
                        'created_at' => isset($error_data['timestamp']) ? strtotime($error_data['timestamp']) : time(),
                        'severity' => strtolower($error_data['severity'] ?? 'error'),
                        'ip_address' => $error_data['ip'] ?? null,
                        'file' => $error_data['file'] ?? null,
                        'line' => $error_data['line'] ?? null
                    ];
                }
            }
        }
    }
    
    // Sort all errors by timestamp and limit to 15 most recent
    usort($error_logs, function($a, $b) {
        return $b['created_at'] - $a['created_at'];
    });
    $error_logs = array_slice($error_logs, 0, 15);

    // Daily Paste Creation Trend (last 7 days)
    $trend_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $start = $now - (($i + 1) * 86400);
        $end = $now - ($i * 86400);
        $count = $db->query("SELECT COUNT(*) FROM pastes WHERE created_at BETWEEN $start AND $end")->fetchColumn();
        $trend_data[] = [
            'date' => date('M j', $start), // Use 'j' for day without leading zeros
            'count' => intval($count) // Ensure it's an integer
        ];
    }
    
    // Debug: Log trend data for troubleshooting
    error_log("Trend data generated: " . json_encode($trend_data));
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please check the error logs.";
}
?>

<div class="p-6 space-y-6">
    <!-- Overview Cards -->
    <div class="grid grid-cols-4 gap-6">
        <!-- Total Pastes -->
        <div class="bg-gray-800 p-6 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Paste Activity</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Today:</span>
                    <span class="font-bold"><?= number_format($daily_pastes) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>This Week:</span>
                    <span class="font-bold"><?= number_format($weekly_pastes) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>This Month:</span>
                    <span class="font-bold"><?= number_format($monthly_pastes) ?></span>
                </div>
            </div>
        </div>

        <!-- User Stats -->
        <div class="bg-gray-800 p-6 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">User Statistics</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span>Total Users:</span>
                    <span class="font-bold"><?= number_format($total_users) ?></span>
                </div>
                <div class="flex justify-between">
                    <span>New Today:</span>
                    <span class="font-bold"><?= number_format($daily_new_users) ?></span>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="bg-gray-800 p-6 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">User Activity</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-sm text-gray-400">Daily Active</span>
                        <div class="text-xs text-gray-500">Users who created pastes today</div>
                    </div>
                    <span class="font-bold text-green-400"><?= number_format($daily_active) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-sm text-gray-400">Weekly Active</span>
                        <div class="text-xs text-gray-500">Users who created pastes this week</div>
                    </div>
                    <span class="font-bold text-blue-400"><?= number_format($weekly_active) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-sm text-gray-400">Monthly Active</span>
                        <div class="text-xs text-gray-500">Users who created pastes this month</div>
                    </div>
                    <span class="font-bold text-purple-400"><?= number_format($monthly_active) ?></span>
                </div>
                <?php if ($daily_active > 0 || $weekly_active > 0): ?>
                <div class="pt-2 border-t border-gray-700">
                    <div class="text-xs text-gray-500">
                        Activity Rate: <?= $total_users > 0 ? round(($monthly_active / $total_users) * 100, 1) : 0 ?>% of users active this month
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Status -->
        <div class="bg-gray-800 p-6 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">System Status</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between mb-1">
                        <span>Disk Usage:</span>
                        <span><?= $disk_used_percent ?>%</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-2">
                        <div class="bg-blue-500 rounded-full h-2" style="width: <?= $disk_used_percent ?>%"></div>
                    </div>
                </div>
                <div class="flex justify-between">
                    <span>API Status:</span>
                    <span class="text-green-500">●&nbsp;Healthy</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Paste Creation Trend Chart -->
    <div class="bg-gray-800 p-6 rounded-lg">
        <h3 class="text-lg font-semibold mb-4">Paste Creation Trend (Last 7 Days)</h3>
        <div class="h-64 relative">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Top Pastes & Languages -->
    <div class="grid grid-cols-2 gap-6">
        <!-- Most Viewed Pastes -->
        <div class="bg-gray-800 p-6 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Most Viewed Pastes</h3>
            <div class="space-y-3">
                <?php foreach ($top_pastes as $paste): ?>
                <div class="flex justify-between items-center">
                    <div class="truncate flex-1">
                        <a href="?id=<?= $paste['id'] ?>" class="hover:text-blue-500">
                            <?= htmlspecialchars($paste['title'] ?: 'Untitled') ?>
                        </a>
                        <span class="text-gray-400 text-sm">
                            by <?= $paste['username'] ? htmlspecialchars($paste['username']) : 'Anonymous' ?>
                        </span>
                    </div>
                    <span class="text-gray-400"><?= number_format($paste['views']) ?> views</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Most Used Languages -->
        <div class="bg-gray-800 p-6 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Popular Languages</h3>
            <div class="space-y-3">
                <?php foreach ($top_languages as $lang): ?>
                <div class="flex justify-between items-center">
                    <span><?= htmlspecialchars($lang['language']) ?></span>
                    <span class="text-gray-400"><?= number_format($lang['count']) ?> pastes</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Error Logs -->
    <div class="bg-gray-800 p-6 rounded-lg">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Recent Error Logs</h3>
            <div class="flex gap-2">
                <button onclick="clearErrorFile()" class="bg-yellow-600 hover:bg-yellow-700 px-3 py-1 rounded text-sm">
                    <i class="fas fa-broom mr-1"></i>Clear Error File
                </button>
                <button onclick="viewFullErrorLog()" class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm">
                    <i class="fas fa-file-alt mr-1"></i>View Full Log
                </button>
                <button onclick="exportErrorLogs()" class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-sm">
                    <i class="fas fa-download mr-1"></i>Export
                </button>
            </div>
        </div>
        
        <div class="space-y-3 max-h-96 overflow-y-auto">
            <?php if (empty($error_logs)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-check-circle text-green-500 text-3xl mb-2"></i>
                    <p class="text-gray-400">No recent errors - System running smoothly!</p>
                </div>
            <?php else: ?>
                <?php foreach ($error_logs as $log): ?>
                <div class="flex items-start gap-4 p-3 bg-gray-700 rounded hover:bg-gray-600 transition-colors" data-error-id="<?= $log['id'] ?>">
                    <div class="flex-shrink-0 mt-1">
                        <?php
                        $severity = $log['severity'] ?? 'error';
                        $color = $severity === 'critical' ? 'text-red-400' : 
                                ($severity === 'error' ? 'text-red-500' : 'text-yellow-500');
                        $icon = $severity === 'critical' ? 'fa-exclamation-triangle' : 'fa-bug';
                        ?>
                        <i class="fas <?= $icon ?> <?= $color ?>"></i>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm mb-1 break-words"><?= htmlspecialchars($log['message']) ?></div>
                                
                                <div class="flex flex-wrap gap-3 text-xs text-gray-400">
                                    <span>
                                        <i class="fas fa-clock mr-1"></i>
                                        <?= date('M d, H:i:s', $log['created_at']) ?>
                                    </span>
                                    
                                    <span class="px-2 py-1 rounded <?= 
                                        $log['source'] === 'audit' ? 'bg-blue-600' : 
                                        ($log['source'] === 'file' ? 'bg-purple-600' : 'bg-gray-600') 
                                    ?>">
                                        <?= ucfirst($log['source']) ?>
                                    </span>
                                    
                                    <?php if (!empty($log['ip_address'])): ?>
                                    <span>
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?= htmlspecialchars($log['ip_address']) ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($log['file'])): ?>
                                    <span title="<?= htmlspecialchars($log['file']) ?>:<?= $log['line'] ?>">
                                        <i class="fas fa-file-code mr-1"></i>
                                        <?= htmlspecialchars(basename($log['file'])) ?><?= isset($log['line']) ? ':' . $log['line'] : '' ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex gap-1 ml-2">
                                <?php if ($log['source'] !== 'file'): ?>
                                <button onclick="deleteError('<?= $log['source'] ?>', '<?= $log['id'] ?>')" 
                                        class="text-red-400 hover:text-red-300 p-1" 
                                        title="Delete Error">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button onclick="showErrorDetails('<?= htmlspecialchars(json_encode($log), ENT_QUOTES) ?>')" 
                                        class="text-blue-400 hover:text-blue-300 p-1" 
                                        title="View Details">
                                    <i class="fas fa-info-circle text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error_logs)): ?>
        <div class="mt-4 pt-4 border-t border-gray-700 flex justify-between items-center text-sm text-gray-400">
            <span>Showing last <?= count($error_logs) ?> errors from multiple sources</span>
            <button onclick="refreshErrorLogs()" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-sync-alt mr-1"></i>Refresh
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js for trend visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Global function to initialize the chart - can be called from admindash.php
window.initTrendChart = function() {
    // Multiple attempts to find and initialize the chart
    let attempts = 0;
    const maxAttempts = 10;
    
    function tryInitChart() {
        attempts++;
        const canvas = document.getElementById('trendChart');
        
        if (!canvas) {
            console.log(`Chart canvas not found, attempt ${attempts}/${maxAttempts}`);
            if (attempts < maxAttempts) {
                setTimeout(tryInitChart, 200);
            } else {
                console.error('Chart canvas not found after maximum attempts');
            }
            return;
        }
        
        // Check if chart already exists and destroy it
        if (window.trendChartInstance) {
            window.trendChartInstance.destroy();
        }
        
        const ctx = canvas.getContext('2d');
        const trendData = <?= json_encode($trend_data) ?>;
        
        console.log('Initializing trend chart with data:', trendData);
        
        // Validate data
        if (!trendData || !Array.isArray(trendData) || trendData.length === 0) {
            console.warn('No trend data available');
            // Show "No data" message in chart area
            const chartContainer = canvas.parentElement;
            chartContainer.innerHTML = '<div class="flex items-center justify-center h-64 text-gray-400"><div class="text-center"><i class="fas fa-chart-line text-4xl mb-2"></i><br>No data available</div></div>';
            return;
        }
        
        window.trendChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.date),
                datasets: [{
                    label: 'Daily Pastes Created',
                    data: trendData.map(item => parseInt(item.count) || 0),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `Pastes Created: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            precision: 0,
                            padding: 10
                        },
                        title: {
                            display: true,
                            text: 'Number of Pastes',
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            padding: 10
                        },
                        title: {
                            display: true,
                            text: 'Date',
                            color: 'rgba(255, 255, 255, 0.8)'
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutCubic'
                }
            }
        });
        
        console.log('Trend chart initialized successfully');
    }
    
    tryInitChart();
};

// Initialize on DOM content loaded if we're on the dashboard directly
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('trendChart')) {
        window.initTrendChart();
    }
});

// Error Log Management Functions
function deleteError(source, errorId) {
    if (!confirm('Are you sure you want to delete this error log entry?')) return;
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_error&source=${source}&error_id=${errorId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-error-id="${errorId}"]`).remove();
            showNotification('Error deleted successfully', 'success');
        } else {
            showNotification('Failed to delete error: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error occurred', 'error');
    });
}

function clearErrorFile() {
    if (!confirm('Are you sure you want to clear the entire error.log file? This cannot be undone.')) return;
    
    fetch('admin-dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=clear_error_file'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('Error log file cleared successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Failed to clear error file: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error occurred while clearing error file', 'error');
    });
}

function viewFullErrorLog() {
    // Check if error log exists first
    fetch('admin-dashboard.php?action=check_error_log')
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            window.open('admin-dashboard.php?action=view_full_log', '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
        } else {
            showNotification('No error log file found', 'error');
        }
    })
    .catch(error => {
        console.error('Error checking log file:', error);
        // Still try to open the log viewer even if check failed
        window.open('admin-dashboard.php?action=view_full_log', '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
    });
}

function exportErrorLogs() {
    // Show loading notification
    showNotification('Preparing export...', 'info');
    
    // Check if there are errors to export
    fetch('admin-dashboard.php?action=check_error_log')
    .then(response => response.json())
    .then(data => {
        if (data.exists || data.has_db_errors) {
            // Create a temporary link for download
            const link = document.createElement('a');
            link.href = 'admin-dashboard.php?action=export_errors';
            link.download = 'error_logs_' + new Date().toISOString().slice(0, 19).replace(/[:-]/g, '') + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotification('Export started - check your downloads', 'success');
        } else {
            showNotification('No error logs found to export', 'error');
        }
    })
    .catch(error => {
        console.error('Error checking for export data:', error);
        // Still try to export even if check failed
        window.location.href = 'admin-dashboard.php?action=export_errors';
    });
}

function showErrorDetails(errorData) {
    const error = JSON.parse(errorData);
    
    let details = `
        <div class="space-y-3">
            <div><strong>Message:</strong> ${error.message}</div>
            <div><strong>Type:</strong> ${error.type}</div>
            <div><strong>Severity:</strong> ${error.severity}</div>
            <div><strong>Source:</strong> ${error.source}</div>
            <div><strong>Time:</strong> ${new Date(error.created_at * 1000).toLocaleString()}</div>
    `;
    
    if (error.ip_address) details += `<div><strong>IP Address:</strong> ${error.ip_address}</div>`;
    if (error.file) details += `<div><strong>File:</strong> ${error.file}${error.line ? ':' + error.line : ''}</div>`;
    
    details += `</div>`;
    
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-lg p-6 max-w-2xl w-full max-h-96 overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Error Details</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            ${details}
        </div>
    `;
    
    document.body.appendChild(modal);
}

function refreshErrorLogs() {
    location.reload();
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    const colors = {
        'success': 'bg-green-600',
        'error': 'bg-red-600', 
        'info': 'bg-blue-600',
        'warning': 'bg-yellow-600'
    };
    
    notification.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded text-white shadow-lg transform transition-all duration-300 ${colors[type] || 'bg-gray-600'}`;
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove based on type
    const timeout = type === 'info' ? 2000 : (type === 'success' ? 3000 : 5000);
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, timeout);
}
</script>
