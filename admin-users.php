<?php
require_once(__DIR__ . '/admin-session.php');
check_admin_auth();
handle_logout();

$db = new PDO('sqlite:database.sqlite');

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    $user_id = $_POST['user_id'];
    
    try {
        $db->beginTransaction();
        
        // Get user info for logging
        $stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_info) {
            throw new Exception('User not found');
        }
        
        switch($_POST['action']) {
            case 'ban':
                $db->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$user_id]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('user_banned', $_SESSION['admin_id'], [
                    'target_user_id' => $user_id,
                    'target_username' => $user_info['username']
                ]);
                
                $response = ['success' => true, 'message' => 'User banned successfully'];
                break;
                
            case 'warn':
                $reason = $_POST['warn_reason'] ?? 'No reason provided';
                
                // Add warning to user_warnings table
                $db->prepare("INSERT INTO user_warnings (user_id, reason, admin_id, created_at) VALUES (?, ?, ?, ?)")
                   ->execute([$user_id, $reason, $_SESSION['admin_id'], time()]);
                
                // Also log in user_logs
                $db->prepare("INSERT INTO user_logs (user_id, type, message, created_at) VALUES (?, 'warning', ?, ?)")
                   ->execute([$user_id, "Admin warning: " . $reason, time()]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('user_warned', $_SESSION['admin_id'], [
                    'target_user_id' => $user_id,
                    'target_username' => $user_info['username'],
                    'reason' => $reason
                ]);
                
                $response = ['success' => true, 'message' => 'User warning issued successfully'];
                break;
                
            case 'promote':
                $db->prepare("UPDATE users SET role = 'premium' WHERE id = ?")->execute([$user_id]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('user_promoted', $_SESSION['admin_id'], [
                    'target_user_id' => $user_id,
                    'target_username' => $user_info['username'],
                    'new_role' => 'premium'
                ]);
                
                $response = ['success' => true, 'message' => 'User promoted to premium successfully'];
                break;
                
            case 'demote':
                $db->prepare("UPDATE users SET role = 'free' WHERE id = ?")->execute([$user_id]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('user_demoted', $_SESSION['admin_id'], [
                    'target_user_id' => $user_id,
                    'target_username' => $user_info['username'],
                    'new_role' => 'free'
                ]);
                
                $response = ['success' => true, 'message' => 'User demoted to free tier successfully'];
                break;
                
            case 'send_message':
                // Create messages table if not exists
                $db->exec("CREATE TABLE IF NOT EXISTS messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sender_id TEXT NOT NULL,
                    recipient_id TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    content TEXT NOT NULL,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    is_read INTEGER DEFAULT 0,
                    FOREIGN KEY(sender_id) REFERENCES users(id),
                    FOREIGN KEY(recipient_id) REFERENCES users(id)
                )");
                
                $subject = $_POST['subject'] ?? '';
                $message = $_POST['message'] ?? '';
                
                if (empty($subject) || empty($message)) {
                    throw new Exception('Subject and message are required');
                }
                
                // Send message from admin
                $db->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content, created_at) VALUES (?, ?, ?, ?, ?)")
                   ->execute(['admin_' . $_SESSION['admin_id'], $user_id, $subject, $message, time()]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('message_sent', $_SESSION['admin_id'], [
                    'target_user_id' => $user_id,
                    'target_username' => $user_info['username'],
                    'subject' => $subject
                ]);
                
                $response = ['success' => true, 'message' => 'Message sent successfully'];
                break;
                
            case 'impersonate':
                // Create admin_impersonations table if not exists
                $db->exec("CREATE TABLE IF NOT EXISTS admin_impersonations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    admin_id INTEGER NOT NULL,
                    target_user_id TEXT NOT NULL,
                    started_at INTEGER DEFAULT (strftime('%s', 'now')),
                    ended_at INTEGER,
                    ip_address TEXT,
                    FOREIGN KEY(admin_id) REFERENCES admin_users(id),
                    FOREIGN KEY(target_user_id) REFERENCES users(id)
                )");
                
                // Log impersonation start
                $db->prepare("INSERT INTO admin_impersonations (admin_id, target_user_id, ip_address) VALUES (?, ?, ?)")
                   ->execute([$_SESSION['admin_id'], $user_id, $_SERVER['REMOTE_ADDR'] ?? '']);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('user_impersonated', $_SESSION['admin_id'], [
                    'target_user_id' => $user_id,
                    'target_username' => $user_info['username']
                ]);
                
                // Set session to impersonate user
                $_SESSION['impersonating_user'] = $user_id;
                $_SESSION['impersonating_username'] = $user_info['username'];
                $_SESSION['original_admin_id'] = $_SESSION['admin_id'];
                
                // Switch to user session
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user_info['username'];
                
                // Redirect to main site
                header('Location: /');
                exit;
                
            default:
                throw new Exception('Invalid action');
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollback();
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    
    // Redirect with message
    if ($response['success']) {
        header('Location: admin-users.php?message=' . urlencode($response['message']));
    } else {
        header('Location: admin-users.php?error=' . urlencode($response['message']));
    }
    exit;
}

// Create user_logs table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS user_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT,
    type TEXT,
    message TEXT,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// Add role column if not exists
try {
    $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'free'");
    $db->exec("ALTER TABLE users ADD COLUMN status TEXT DEFAULT 'active'");
} catch (PDOException $e) {
    // Column might already exist
}

// Get filters
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT 
    u.*, 
    COUNT(DISTINCT p.id) as paste_count,
    SUM(COALESCE(p.views, 0)) as total_views,
    COUNT(DISTINCT CASE WHEN p.flags > 0 THEN p.id END) as flagged_pastes
FROM users u 
LEFT JOIN pastes p ON u.id = p.user_id
WHERE 1=1 ";

if ($role_filter !== 'all') {
    $query .= "AND u.role = :role ";
}
if ($status_filter !== 'all') {
    $query .= "AND u.status = :status ";
}
if ($search) {
    $query .= "AND (u.username LIKE :search OR u.email LIKE :search) ";
}

$query .= "GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
if ($role_filter !== 'all') $stmt->bindValue(':role', $role_filter);
if ($status_filter !== 'all') $stmt->bindValue(':status', $status_filter);
if ($search) $stmt->bindValue(':search', "%$search%");

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'user_export_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Add BOM for Excel compatibility
    echo "\xEF\xBB\xBF";
    
    // CSV header with additional info
    echo "Username,Email,Role,Status,Pastes Count,Total Views,Flagged Pastes,Created Date,User ID\n";
    
    foreach ($users as $user) {
        echo sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
            str_replace('"', '""', $user['username']),
            str_replace('"', '""', $user['email'] ?? 'N/A'),
            str_replace('"', '""', $user['role']),
            str_replace('"', '""', $user['status']),
            $user['paste_count'],
            $user['total_views'],
            $user['flagged_pastes'],
            date('Y-m-d H:i:s', $user['created_at']),
            str_replace('"', '""', $user['id'])
        );
    }
    exit;
}

// Handle AJAX requests - return only table body content
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if (empty($users)) {
        echo '<tr><td colspan="8" class="p-8 text-center text-gray-400">No users found matching your search criteria</td></tr>';
    } else {
        foreach ($users as $user): ?>
        <tr class="border-b border-gray-700">
            <td class="p-3">
                <a href="#" onclick="viewProfile('<?= $user['id'] ?>')" class="text-blue-400 hover:text-blue-300">
                    <?= htmlspecialchars($user['username']) ?>
                </a>
            </td>
            <td class="p-3"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
            <td class="p-3">
                <span class="px-2 py-1 rounded text-xs <?= $user['role'] === 'premium' ? 'bg-yellow-600' : 'bg-gray-600' ?>">
                    <?= ucfirst($user['role']) ?>
                </span>
            </td>
            <td class="p-3">
                <span class="px-2 py-1 rounded text-xs <?= $user['status'] === 'banned' ? 'bg-red-600' : 'bg-green-600' ?>">
                    <?= ucfirst($user['status']) ?>
                </span>
            </td>
            <td class="p-3"><?= $user['paste_count'] ?></td>
            <td class="p-3"><?= $user['total_views'] ?></td>
            <td class="p-3"><?= $user['flagged_pastes'] ?></td>
            <td class="p-3">
                <div class="flex gap-2">
                    <button onclick="userAction('ban', '<?= $user['id'] ?>')" class="text-red-500 hover:text-red-400" title="Ban User">
                        🚫
                    </button>
                    <button onclick="userAction('warn', '<?= $user['id'] ?>')" class="text-yellow-500 hover:text-yellow-400" title="Warn User">
                        ⚠️
                    </button>
                    <?php if ($user['role'] === 'free'): ?>
                    <button onclick="userAction('promote', '<?= $user['id'] ?>')" class="text-green-500 hover:text-green-400" title="Promote to Premium">
                        ⭐
                    </button>
                    <?php else: ?>
                    <button onclick="userAction('demote', '<?= $user['id'] ?>')" class="text-gray-500 hover:text-gray-400" title="Demote to Free">
                        🔽
                    </button>
                    <?php endif; ?>
                    <button onclick="sendMessage('<?= $user['id'] ?>')" class="text-blue-500 hover:text-blue-400" title="Send Message">
                        ✉️
                    </button>
                    <button onclick="impersonateUser('<?= $user['id'] ?>')" class="text-purple-500 hover:text-purple-400" title="Impersonate User">
                        👤
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach;
    }
    exit;
}
?>

<div class="bg-gray-800 p-6 rounded-lg">
    <!-- Message Display -->
    <?php if (isset($_GET['message'])): ?>
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
            <?= htmlspecialchars($_GET['message']) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">User Management</h2>
        
        <!-- Search and Filters -->
        <div class="flex gap-4">
            <input type="text" 
                   id="searchUser" 
                   placeholder="Search users..." 
                   class="bg-gray-700 rounded px-3 py-1"
                   oninput="performSearch()">
            
            <select id="roleFilter" class="bg-gray-700 rounded px-3 py-1" onchange="performSearch()">
                <option value="all">All Roles</option>
                <option value="free">Free</option>
                <option value="premium">Premium</option>
                <option value="admin">Admin</option>
            </select>
            
            <select id="statusFilter" class="bg-gray-700 rounded px-3 py-1" onchange="performSearch()">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="banned">Banned</option>
            </select>
            
            <button onclick="exportUserData()" class="bg-green-600 hover:bg-green-700 px-4 py-1 rounded">
                <i class="fas fa-download mr-2"></i>Export CSV
            </button>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-700">
                <tr>
                    <th class="p-3">Username</th>
                    <th class="p-3">Email</th>
                    <th class="p-3">Role</th>
                    <th class="p-3">Status</th>
                    <th class="p-3"># Pastes</th>
                    <th class="p-3">Views</th>
                    <th class="p-3">Flags</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <?php foreach ($users as $user): ?>
                <tr class="border-b border-gray-700">
                    <td class="p-3">
                        <a href="#" onclick="viewProfile('<?= $user['id'] ?>')" class="text-blue-400 hover:text-blue-300">
                            <?= htmlspecialchars($user['username']) ?>
                        </a>
                    </td>
                    <td class="p-3"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?= $user['role'] === 'premium' ? 'bg-yellow-600' : 'bg-gray-600' ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?= $user['status'] === 'banned' ? 'bg-red-600' : 'bg-green-600' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </td>
                    <td class="p-3"><?= $user['paste_count'] ?></td>
                    <td class="p-3"><?= $user['total_views'] ?></td>
                    <td class="p-3"><?= $user['flagged_pastes'] ?></td>
                    <td class="p-3">
                        <div class="flex gap-2">
                            <button onclick="userAction('ban', '<?= $user['id'] ?>')" class="text-red-500 hover:text-red-400" title="Ban User">
                                🚫
                            </button>
                            <button onclick="userAction('warn', '<?= $user['id'] ?>')" class="text-yellow-500 hover:text-yellow-400" title="Warn User">
                                ⚠️
                            </button>
                            <?php if ($user['role'] === 'free'): ?>
                            <button onclick="userAction('promote', '<?= $user['id'] ?>')" class="text-green-500 hover:text-green-400" title="Promote to Premium">
                                ⭐
                            </button>
                            <?php else: ?>
                            <button onclick="userAction('demote', '<?= $user['id'] ?>')" class="text-gray-500 hover:text-gray-400" title="Demote to Free">
                                🔽
                            </button>
                            <?php endif; ?>
                            <button onclick="sendMessage('<?= $user['id'] ?>')" class="text-blue-500 hover:text-blue-400" title="Send Message">
                                ✉️
                            </button>
                            <button onclick="impersonateUser('<?= $user['id'] ?>')" class="text-purple-500 hover:text-purple-400" title="Impersonate User">
                                👤
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Loading indicator -->
        <div id="loadingIndicator" class="hidden text-center py-4">
            <i class="fas fa-spinner fa-spin mr-2"></i>Searching...
        </div>
        
        <!-- No results message -->
        <div id="noResults" class="hidden text-center py-8 text-gray-400">
            <i class="fas fa-search text-3xl mb-2"></i>
            <p>No users found matching your search criteria</p>
        </div>
    </div>
</div>

<script>
function viewProfile(userId) {
    // Open user profile in new tab
    window.open(`/?page=profile&user_id=${userId}`, '_blank');
}

function userAction(action, userId) {
    let confirmMessage = '';
    let extraData = '';
    
    switch(action) {
        case 'ban':
            confirmMessage = 'Are you sure you want to ban this user? They will lose access to their account.';
            break;
        case 'warn':
            const reason = prompt('Enter warning reason:');
            if (!reason || !reason.trim()) return;
            confirmMessage = `Are you sure you want to warn this user for: ${reason}?`;
            extraData = `<input type="hidden" name="warn_reason" value="${reason.trim()}">`;
            break;
        case 'promote':
            confirmMessage = 'Are you sure you want to promote this user to premium?';
            break;
        case 'demote':
            confirmMessage = 'Are you sure you want to demote this user to free tier?';
            break;
        default:
            confirmMessage = `Are you sure you want to ${action} this user?`;
    }
    
    if (!confirm(confirmMessage)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="${action}">
        <input type="hidden" name="user_id" value="${userId}">
        ${extraData}
    `;
    document.body.appendChild(form);
    form.submit();
}

function sendMessage(userId) {
    // Create modal for sending message
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Send Message to User</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="admin-users.php">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="recipient_id" value="${userId}">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Subject</label>
                    <input type="text" name="subject" required class="w-full bg-gray-700 rounded px-3 py-2" 
                           placeholder="Message subject">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Message</label>
                    <textarea name="message" required rows="4" class="w-full bg-gray-700 rounded px-3 py-2" 
                              placeholder="Type your message here..."></textarea>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                        <i class="fas fa-paper-plane mr-2"></i>Send Message
                    </button>
                    <button type="button" onclick="this.closest('.fixed').remove()" 
                            class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded">Cancel</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
}

function impersonateUser(userId) {
    if (!confirm('Are you sure you want to impersonate this user? This is for debugging purposes only and will log you out of the admin panel.')) return;
    
    // Create form to impersonate user
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin-users.php';
    form.innerHTML = `
        <input type="hidden" name="action" value="impersonate">
        <input type="hidden" name="user_id" value="${userId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function exportUserData() {
    // Show loading notification
    showNotification('Preparing user data export...', 'info');
    
    // Get current filter values to include in export
    const search = document.getElementById('searchUser').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    // Build export URL with current filters
    const params = new URLSearchParams();
    params.set('export', 'csv');
    
    if (search) params.set('search', search);
    if (role !== 'all') params.set('role', role);
    if (status !== 'all') params.set('status', status);
    
    // Trigger export with filters
    window.location.href = 'admin-users.php?' + params.toString();
    
    // Show success notification after a delay
    setTimeout(() => {
        showNotification('Export completed successfully!', 'success');
    }, 1000);
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
    
    // Auto-remove
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, type === 'info' ? 2000 : 5000);
}

// Initialize filters
document.getElementById('roleFilter').value = '<?= $role_filter ?>';
document.getElementById('statusFilter').value = '<?= $status_filter ?>';
document.getElementById('searchUser').value = '<?= $search ?>';

// Search debouncing
let searchTimeout;

function performSearch() {
    clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(() => {
        const search = document.getElementById('searchUser').value;
        const role = document.getElementById('roleFilter').value;
        const status = document.getElementById('statusFilter').value;
        
        // Show loading indicator
        document.getElementById('loadingIndicator').classList.remove('hidden');
        document.getElementById('usersTableBody').style.opacity = '0.5';
        document.getElementById('noResults').classList.add('hidden');
        
        // Build query parameters
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (role !== 'all') params.set('role', role);
        if (status !== 'all') params.set('status', status);
        params.set('ajax', '1'); // Flag for AJAX request
        
        // Perform search
        fetch(`admin-users.php?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                // Hide loading indicator
                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('usersTableBody').style.opacity = '1';
                
                // Update table body with new content
                if (html.trim()) {
                    document.getElementById('usersTableBody').innerHTML = html;
                    document.getElementById('noResults').classList.add('hidden');
                } else {
                    // No results found
                    document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-400">No users found matching your search criteria</td></tr>';
                    document.getElementById('noResults').classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('usersTableBody').style.opacity = '1';
                showNotification('Search failed. Please try again.', 'error');
            });
    }, 300); // 300ms debounce delay
}
</script>
