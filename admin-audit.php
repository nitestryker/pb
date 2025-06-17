<?php
// Handle export request FIRST before any output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Clean any output buffers completely
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();
    
    $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Output CSV headers
    echo "Timestamp,User,Action,Resource,Severity,IP Address,Details\n";
    
    // Get filters for export
    $user_filter = $_GET['user'] ?? '';
    $action_filter = $_GET['action'] ?? '';
    $severity_filter = $_GET['severity'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Get all audit logs with current filters
    $where = [];
    $params = [];
    
    if ($user_filter) {
        $where[] = "a.user_id = ?";
        $params[] = $user_filter;
    }
    
    if ($action_filter) {
        $where[] = "a.action LIKE ?";
        $params[] = "%{$action_filter}%";
    }
    
    if ($severity_filter) {
        $where[] = "a.severity = ?";
        $params[] = $severity_filter;
    }
    
    if ($date_from) {
        $where[] = "a.created_at >= ?";
        $params[] = strtotime($date_from);
    }
    
    if ($date_to) {
        $where[] = "a.created_at <= ?";
        $params[] = strtotime($date_to . ' 23:59:59');
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $db = new PDO('sqlite:database.sqlite');
    $stmt = $db->prepare("
        SELECT a.*, u.username 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        {$whereClause}
        ORDER BY a.created_at DESC
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($logs as $log) {
        $resource = $log['resource_type'] ? $log['resource_type'] . ':' . $log['resource_id'] : '';
        echo sprintf('"%s","%s","%s","%s","%s","%s","%s"' . "\n",
            date('Y-m-d H:i:s', $log['created_at']),
            $log['username'] ?: 'System',
            str_replace('"', '""', $log['action']),
            str_replace('"', '""', $resource),
            $log['severity'],
            $log['ip_address'],
            str_replace('"', '""', $log['details'] ?: '')
        );
    }
    
    // End output buffer and send
    ob_end_flush();
    exit;
}

require_once(__DIR__ . '/admin-session.php');
check_admin_auth();
handle_logout();

require_once 'audit_logger.php';
$audit_logger = new AuditLogger();

// Handle cleanup request
if ($_POST['action'] ?? '' === 'cleanup_logs') {
    $days = (int)($_POST['cleanup_days'] ?? 90);
    $deleted = $audit_logger->cleanupOldLogs($days);
    $success_message = "Cleaned up {$deleted} old log entries.";
}

// Get filters
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where = [];
$params = [];

if ($user_filter) {
    $where[] = "user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where[] = "action LIKE ?";
    $params[] = "%{$action_filter}%";
}

if ($severity_filter) {
    $where[] = "severity = ?";
    $params[] = $severity_filter;
}

if ($date_from) {
    $where[] = "created_at >= ?";
    $params[] = strtotime($date_from);
}

if ($date_to) {
    $where[] = "created_at <= ?";
    $params[] = strtotime($date_to . ' 23:59:59');
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$db = new PDO('sqlite:database.sqlite');
$stmt = $db->prepare("
    SELECT a.*, u.username 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    {$whereClause}
    ORDER BY a.created_at DESC 
    LIMIT 100
");
$stmt->execute($params);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get security events
$stmt = $db->prepare("
    SELECT * FROM security_events 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute();
$security_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h2 class="text-xl font-bold">Audit Logs</h2>
        <div class="flex gap-2">
            <button onclick="exportLogs()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-white">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="showCleanupModal()" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-white">
                <i class="fas fa-trash mr-2"></i>Cleanup
            </button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="bg-gray-800 p-4 rounded-lg" id="filterForm">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <input type="text" name="user" placeholder="User ID" value="<?= htmlspecialchars($user_filter) ?>" 
                   class="px-3 py-2 bg-gray-700 rounded text-white">
            <input type="text" name="action" placeholder="Action" value="<?= htmlspecialchars($action_filter) ?>" 
                   class="px-3 py-2 bg-gray-700 rounded text-white">
            <select name="severity" class="px-3 py-2 bg-gray-700 rounded text-white">
                <option value="">All Severities</option>
                <option value="info" <?= $severity_filter === 'info' ? 'selected' : '' ?>>Info</option>
                <option value="warning" <?= $severity_filter === 'warning' ? 'selected' : '' ?>>Warning</option>
                <option value="error" <?= $severity_filter === 'error' ? 'selected' : '' ?>>Error</option>
                <option value="critical" <?= $severity_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                   class="px-3 py-2 bg-gray-700 rounded text-white">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                   class="px-3 py-2 bg-gray-700 rounded text-white">
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white">
                    Filter
                </button>
                <button type="button" onclick="clearFilters()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded text-white">
                    Clear
                </button>
            </div>
        </div>
    </form>

    <!-- Audit Logs Table -->
    <div class="bg-gray-800 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left">Timestamp</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Resource</th>
                        <th class="px-4 py-3 text-left">Severity</th>
                        <th class="px-4 py-3 text-left">IP Address</th>
                        <th class="px-4 py-3 text-left">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php foreach ($audit_logs as $log): ?>
                    <tr class="hover:bg-gray-700">
                        <td class="px-4 py-3 text-sm"><?= date('Y-m-d H:i:s', $log['created_at']) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?= $log['username'] ? htmlspecialchars($log['username']) : 'System' ?>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium"><?= htmlspecialchars($log['action']) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?= $log['resource_type'] ? htmlspecialchars($log['resource_type']) . ':' . htmlspecialchars($log['resource_id']) : '-' ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 rounded text-xs <?= 
                                $log['severity'] === 'critical' ? 'bg-red-500 text-white' : 
                                ($log['severity'] === 'error' ? 'bg-red-400 text-white' : 
                                ($log['severity'] === 'warning' ? 'bg-yellow-500 text-black' : 'bg-gray-500 text-white'))
                            ?>">
                                <?= ucfirst($log['severity']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($log['details']): ?>
                                <button onclick="showDetails('<?= htmlspecialchars(json_encode($log['details'])) ?>')" 
                                        class="text-blue-400 hover:text-blue-300">
                                    View Details
                                </button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Security Events -->
    <div class="bg-gray-800 rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Security Events</h3>
        <div class="space-y-3">
            <?php foreach ($security_events as $event): ?>
            <div class="flex items-center justify-between p-3 bg-gray-700 rounded">
                <div class="flex-1">
                    <div class="font-medium"><?= htmlspecialchars($event['event_type']) ?></div>
                    <div class="text-sm text-gray-400">
                        <?= htmlspecialchars($event['ip_address']) ?> • <?= date('Y-m-d H:i:s', $event['created_at']) ?>
                    </div>
                </div>
                <span class="px-2 py-1 rounded text-xs <?= 
                    $event['risk_level'] === 'high' ? 'bg-red-500 text-white' : 
                    ($event['risk_level'] === 'medium' ? 'bg-yellow-500 text-black' : 'bg-green-500 text-white')
                ?>">
                    <?= ucfirst($event['risk_level']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Cleanup Modal -->
<div id="cleanupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Cleanup Old Logs</h3>
        <form method="POST">
            <input type="hidden" name="action" value="cleanup_logs">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Delete logs older than:</label>
                <select name="cleanup_days" class="w-full px-3 py-2 bg-gray-700 rounded">
                    <option value="30">30 days</option>
                    <option value="60">60 days</option>
                    <option value="90" selected>90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">1 year</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-white">
                    Delete Logs
                </button>
                <button type="button" onclick="hideCleanupModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded text-white">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showCleanupModal() {
    document.getElementById('cleanupModal').classList.remove('hidden');
}

function hideCleanupModal() {
    document.getElementById('cleanupModal').classList.add('hidden');
}

function showDetails(details) {
    // Create a modal to show details instead of alert
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Log Details</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="p-4">
                <pre class="whitespace-pre-wrap text-sm bg-gray-700 p-4 rounded">${details}</pre>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

function exportLogs() {
    // Get current filter values
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    // Add current filters to export URL
    for (let [key, value] of formData.entries()) {
        if (value.trim()) {
            params.set(key, value);
        }
    }
    
    params.set('export', 'csv');
    window.location.href = 'admin-audit.php?' + params.toString();
}

function clearFilters() {
    const form = document.getElementById('filterForm');
    const inputs = form.querySelectorAll('input[type="text"], input[type="date"], select');
    inputs.forEach(input => {
        input.value = '';
    });
    form.submit();
}

// Auto-submit form when Enter is pressed in input fields
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const inputs = form.querySelectorAll('input[type="text"]');
    
    inputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                form.submit();
            }
        });
    });
    
    // Auto-submit when date fields change
    const dateInputs = form.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            form.submit();
        });
    });
    
    // Auto-submit when severity selection changes
    const severitySelect = form.querySelector('select[name="severity"]');
    if (severitySelect) {
        severitySelect.addEventListener('change', function() {
            form.submit();
        });
    }
});

// Handle cleanup form submission via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const cleanupForm = document.querySelector('#cleanupModal form');
    if (cleanupForm) {
        cleanupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('admin-audit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                hideCleanupModal();
                // Reload the page to show the success message
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while cleaning up logs');
            });
        });
    }
});
</script>
