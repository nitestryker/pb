<?php
require_once(__DIR__ . '/admin-session.php');
check_admin_auth();
handle_logout();

$db = new PDO('sqlite:database.sqlite');

// Create admin_notes table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS admin_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paste_id INTEGER,
    admin_id INTEGER,
    note TEXT,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY(paste_id) REFERENCES pastes(id),
    FOREIGN KEY(admin_id) REFERENCES admin_users(id)
)");

// Create paste_flags table if not exists (in case it wasn't created yet)
$db->exec("CREATE TABLE IF NOT EXISTS paste_flags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paste_id INTEGER NOT NULL,
    user_id TEXT,
    ip_address TEXT,
    flag_type TEXT NOT NULL,
    reason TEXT,
    description TEXT,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    status TEXT DEFAULT 'pending',
    reviewed_by TEXT,
    reviewed_at INTEGER,
    FOREIGN KEY(paste_id) REFERENCES pastes(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// Create user_warnings table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS user_warnings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT,
    reason TEXT,
    admin_id INTEGER,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(admin_id) REFERENCES admin_users(id)
)");

// Handle actions
if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'approve':
            try {
                $db->beginTransaction();
                
                // Clear flags and mark as resolved
                $db->prepare("UPDATE pastes SET flags = 0 WHERE id = ?")->execute([$_POST['paste_id']]);
                $db->prepare("UPDATE paste_flags SET status = 'resolved', reviewed_by = ?, reviewed_at = ? WHERE paste_id = ? AND status = 'pending'")->execute([$_SESSION['admin_id'], time(), $_POST['paste_id']]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('paste_approved', $_SESSION['admin_id'], [
                    'paste_id' => $_POST['paste_id'],
                    'action' => 'approved'
                ]);
                
                $db->commit();
                $response = ['success' => true, 'message' => 'Paste approved successfully'];
            } catch (Exception $e) {
                $db->rollback();
                $response = ['success' => false, 'message' => 'Error approving paste: ' . $e->getMessage()];
            }
            break;
            
        case 'remove':
            try {
                $db->beginTransaction();
                
                // Get paste info before deletion for logging
                $stmt = $db->prepare("SELECT title, user_id FROM pastes WHERE id = ?");
                $stmt->execute([$_POST['paste_id']]);
                $paste_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete the paste and mark flags as resolved
                $db->prepare("DELETE FROM pastes WHERE id = ?")->execute([$_POST['paste_id']]);
                $db->prepare("UPDATE paste_flags SET status = 'resolved', reviewed_by = ?, reviewed_at = ? WHERE paste_id = ? AND status = 'pending'")->execute([$_SESSION['admin_id'], time(), $_POST['paste_id']]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('paste_removed', $_SESSION['admin_id'], [
                    'paste_id' => $_POST['paste_id'],
                    'paste_title' => $paste_info['title'],
                    'paste_user_id' => $paste_info['user_id'],
                    'action' => 'removed'
                ]);
                
                $db->commit();
                $response = ['success' => true, 'message' => 'Paste removed successfully'];
            } catch (Exception $e) {
                $db->rollback();
                $response = ['success' => false, 'message' => 'Error removing paste: ' . $e->getMessage()];
            }
            break;
            
        case 'warn':
            try {
                $db->prepare("INSERT INTO user_warnings (user_id, reason, admin_id, created_at) VALUES (?, ?, ?, ?)")
                   ->execute([$_POST['user_id'], $_POST['reason'], $_SESSION['admin_id'], time()]);
                
                // Log the action
                require_once 'audit_logger.php';
                $audit_logger = new AuditLogger();
                $audit_logger->log('user_warned', $_SESSION['admin_id'], [
                    'user_id' => $_POST['user_id'],
                    'reason' => $_POST['reason']
                ]);
                
                $response = ['success' => true, 'message' => 'User warning issued successfully'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Error issuing warning: ' . $e->getMessage()];
            }
            break;
            
        case 'add_note':
            try {
                $db->prepare("INSERT INTO admin_notes (paste_id, admin_id, note, created_at) VALUES (?, ?, ?, ?)")
                   ->execute([$_POST['paste_id'], $_SESSION['admin_id'], $_POST['note'], time()]);
                
                $response = ['success' => true, 'message' => 'Note added successfully'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Error adding note: ' . $e->getMessage()];
            }
            break;
    }
    
    // If this is an AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // For regular form submissions, redirect back with message
    if ($response['success']) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode($response['message']));
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($response['message']));
    }
    exit;
}

// Get filters
$flag_type_filter = $_GET['flag_type'] ?? '';
$status_filter = $_GET['status'] ?? 'pending';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query based on filters - updated to use paste_flags table
$where = "WHERE 1=1";

// Default to pending if no status specified
if ($status_filter && $status_filter !== '') {
    $where .= " AND pf.status = " . $db->quote($status_filter);
} else {
    $where .= " AND pf.status = 'pending'";
}

if ($flag_type_filter && $flag_type_filter !== '') {
    $where .= " AND pf.flag_type = " . $db->quote($flag_type_filter);
}

if ($date_from && $date_from !== '') {
    $where .= " AND pf.created_at >= " . strtotime($date_from);
}

if ($date_to && $date_to !== '') {
    $where .= " AND pf.created_at <= " . strtotime($date_to . ' 23:59:59');
}

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'flag_count';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build the complete query - improved to show all flagged pastes
$query = "
    SELECT p.*, u.username, 
           (SELECT COUNT(*) FROM admin_notes WHERE paste_id = p.id) as note_count,
           (SELECT GROUP_CONCAT(DISTINCT flag_type) FROM paste_flags WHERE paste_id = p.id AND status = 'pending') as flag_types,
           (SELECT COUNT(*) FROM paste_flags WHERE paste_id = p.id AND status = 'pending') as flag_count,
           (SELECT MAX(created_at) FROM paste_flags WHERE paste_id = p.id AND status = 'pending') as latest_flag
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    INNER JOIN paste_flags pf ON p.id = pf.paste_id
    {$where}
    GROUP BY p.id
    ORDER BY {$sort} {$order}
";

try {
    $stmt = $db->query($query);
    if ($stmt === false) {
        error_log("SQL Error in admin-flagged.php: " . implode(", ", $db->errorInfo()));
        $flagged_pastes = [];
    } else {
        $flagged_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database error in admin-flagged.php: " . $e->getMessage());
    $flagged_pastes = [];
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
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Flagged Pastes Management</h2>
        
        <!-- Filters -->
        <form id="filterForm" class="flex gap-4" onsubmit="return false;">
            <select name="flag_type" id="flagTypeFilter" class="bg-gray-700 rounded px-3 py-1">
                <option value="">All Flag Types</option>
                <option value="spam">Spam</option>
                <option value="offensive">Offensive</option>
                <option value="malware">Malware</option>
                <option value="phishing">Phishing</option>
                <option value="copyright">Copyright</option>
                <option value="personal_info">Personal Info</option>
                <option value="illegal">Illegal</option>
                <option value="other">Other</option>
            </select>
            
            <select name="status" id="statusFilter" class="bg-gray-700 rounded px-3 py-1">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
            </select>
            
            <input type="date" name="date_from" id="dateFromFilter" class="bg-gray-700 rounded px-3 py-1">
            <input type="date" name="date_to" id="dateToFilter" class="bg-gray-700 rounded px-3 py-1">
            
            <button type="button" onclick="applyFilters()" class="bg-blue-500 px-4 py-1 rounded">Filter</button>
            <button type="button" onclick="clearFilters()" class="bg-gray-600 px-4 py-1 rounded">Clear</button>
        </form>
    </div>
    
    <!-- Pastes Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="text-left border-b border-gray-700">
                    <th class="p-3">ID</th>
                    <th class="p-3">Title</th>
                    <th class="p-3">User</th>
                    <th class="p-3">Flag Types</th>
                    <th class="p-3">Flag Count</th>
                    <th class="p-3">Date</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($flagged_pastes)): ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-gray-400">
                        <i class="fas fa-flag text-4xl mb-4"></i>
                        <div>No flagged pastes found</div>
                        <div class="text-sm mt-2">Pastes with user reports will appear here</div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($flagged_pastes as $paste): ?>
                <tr class="border-b border-gray-700 hover:bg-gray-700/50">
                    <td class="p-3"><?= $paste['id'] ?></td>
                    <td class="p-3">
                        <button onclick="togglePreview(<?= $paste['id'] ?>)" class="text-blue-400 hover:underline">
                            <?= htmlspecialchars($paste['title'] ?: 'Untitled') ?>
                        </button>
                    </td>
                    <td class="p-3"><?= htmlspecialchars($paste['username'] ?: 'Anonymous') ?></td>
                    <td class="p-3">
                        <?php if ($paste['flag_types']): ?>
                            <?php foreach (explode(',', $paste['flag_types']) as $type): ?>
                                <span class="px-2 py-1 rounded text-xs mr-1 mb-1 inline-block
                                    <?= $type === 'spam' ? 'bg-yellow-500/20 text-yellow-200' : 
                                       ($type === 'offensive' || $type === 'illegal' ? 'bg-red-500/20 text-red-200' : 
                                        'bg-orange-500/20 text-orange-200') ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type))) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <span class="font-semibold <?= $paste['flag_count'] >= 5 ? 'text-red-400' : ($paste['flag_count'] >= 3 ? 'text-yellow-400' : 'text-gray-400') ?>">
                            <?= $paste['flag_count'] ?>
                        </span>
                    </td>
                    <td class="p-3"><?= date('Y-m-d H:i', $paste['created_at']) ?></td>
                    <td class="p-3 space-x-2">
                        <div class="flex space-x-1">
                            <button onclick="approvePaste(<?= $paste['id'] ?>)" class="text-green-400 hover:text-green-300 p-1 rounded hover:bg-green-500/20" title="Approve Paste">✅</button>
                            <button onclick="removePaste(<?= $paste['id'] ?>)" class="text-red-400 hover:text-red-300 p-1 rounded hover:bg-red-500/20" title="Remove Paste">❌</button>
                            <?php if ($paste['user_id']): ?>
                                <button onclick="warnUser('<?= $paste['user_id'] ?>')" class="text-yellow-400 hover:text-yellow-300 p-1 rounded hover:bg-yellow-500/20" title="Warn User">⚠️</button>
                            <?php endif; ?>
                            <a href="admin-paste-viewer.php?id=<?= $paste['id'] ?>" target="_blank" class="text-blue-400 hover:text-blue-300 p-1 rounded hover:bg-blue-500/20" title="View Full Paste (Admin)">👁️</a>
                            <button onclick="viewFlagDetails(<?= $paste['id'] ?>)" class="text-purple-400 hover:text-purple-300 p-1 rounded hover:bg-purple-500/20" title="View Flag Details">🔍</button>
                            <button onclick="addNote(<?= $paste['id'] ?>)" class="text-gray-400 hover:text-gray-300 p-1 rounded hover:bg-gray-500/20" title="Add Note">📝</button>
                            <button onclick="viewNotes(<?= $paste['id'] ?>)" class="text-green-400 hover:text-green-300 p-1 rounded hover:bg-green-500/20" title="View Notes (<?= $paste['note_count'] ?>)">👁️‍🗨️</button>
                        </div>
                    </td>
                </tr>
                <!-- Preview Panel -->
                <tr id="preview_<?= $paste['id'] ?>" class="hidden">
                    <td colspan="7" class="p-4 bg-gray-700/50">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-semibold">Preview</h4>
                            <label class="flex items-center">
                                <input type="checkbox" onchange="toggleBlur(this, <?= $paste['id'] ?>)" class="mr-2">
                                Blur Content
                            </label>
                        </div>
                        <pre class="whitespace-pre-wrap font-mono text-sm" id="content_<?= $paste['id'] ?>"><?= htmlspecialchars($paste['content']) ?></pre>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
window.togglePreview = function(id) {
    const preview = document.getElementById(`preview_${id}`);
    if (preview) {
        preview.classList.toggle('hidden');
    }
}

window.toggleBlur = function(checkbox, id) {
    const content = document.getElementById(`content_${id}`);
    if (content) {
        content.style.filter = checkbox.checked ? 'blur(5px)' : 'none';
    }
}

window.approvePaste = function(pasteId) {
    if (!confirm('Are you sure you want to approve this paste? This will clear all flags and mark them as resolved.')) {
        return;
    }
    
    submitAction('approve', { paste_id: pasteId });
}

window.removePaste = function(pasteId) {
    if (!confirm('Are you sure you want to remove this paste? This action cannot be undone.')) {
        return;
    }
    
    submitAction('remove', { paste_id: pasteId });
}

window.warnUser = function(userId) {
    if (!userId) {
        alert('Cannot warn anonymous user');
        return;
    }
    
    const reason = prompt('Enter warning reason:');
    if (reason && reason.trim()) {
        submitAction('warn', { user_id: userId, reason: reason.trim() });
    }
}

window.addNote = function(pasteId) {
    const note = prompt('Enter admin note for this paste:');
    if (note && note.trim()) {
        submitAction('add_note', { paste_id: pasteId, note: note.trim() });
    }
}

window.viewNotes = function(pasteId) {
    console.log('viewNotes called with pasteId:', pasteId);
    
    // Fetch notes for this paste
    fetch(`admin-notes-viewer.php?paste_id=${pasteId}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            console.log('Notes loaded successfully');
            showNotesModal(pasteId, html);
        })
        .catch(error => {
            console.error('Error loading notes:', error);
            alert('Failed to load notes: ' + error.message);
        });
}

window.showNotesModal = function(pasteId, html) {
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    
    // Extract just the body content if it's a full HTML page
    let content = html;
    if (html.includes('<html')) {
        const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        if (bodyMatch) {
            content = bodyMatch[1];
        }
    }
    
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <h2 class="text-xl font-bold">Admin Notes - Paste #${pasteId}</h2>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="p-4">
                ${content}
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
    
    // Close on Escape key
    const escapeHandler = function(e) {
        if (e.key === 'Escape' && modal.parentElement) {
            modal.remove();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

window.viewFlagDetails = function(pasteId) {
    console.log('viewFlagDetails called with pasteId:', pasteId);
    
    // First check if the file exists by making a fetch request
    fetch(`admin-flag-details.php?paste_id=${pasteId}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            console.log('Flag details loaded successfully');
            // Show the details in a modal since popup might be blocked
            showFlagDetailsModal(pasteId, html);
        })
        .catch(error => {
            console.error('Error loading flag details:', error);
            alert('Failed to load flag details: ' + error.message);
        });
}

window.showFlagDetailsModal = function(pasteId, html) {
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    
    // Extract just the body content if it's a full HTML page
    let content = html;
    if (html.includes('<html')) {
        const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        if (bodyMatch) {
            content = bodyMatch[1];
        }
    }
    
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <h2 class="text-xl font-bold">Flag Details - Paste #${pasteId}</h2>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="p-4">
                ${content}
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
    
    // Close on Escape key
    const escapeHandler = function(e) {
        if (e.key === 'Escape' && modal.parentElement) {
            modal.remove();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

window.submitAction = function(action, data) {
    // Show loading state
    const submitButton = event ? event.target : null;
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', action);
    
    Object.keys(data).forEach(key => {
        formData.append(key, data[key]);
    });
    
    // Submit via AJAX
    fetch('admin-flagged.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Show success message
            showMessage(result.message, 'success');
            
            // Reload the flagged content after a short delay to reflect changes
            setTimeout(() => {
                // Reload just the flagged tab content instead of the whole page
                const event = new Event('click');
                const flaggedLink = document.querySelector('[data-tab="flagged"]');
                if (flaggedLink) {
                    flaggedLink.dispatchEvent(event);
                }
            }, 1000);
        } else {
            showMessage(result.message || 'An error occurred', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error occurred', 'error');
    })
    .finally(() => {
        // Reset button state
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = submitButton.dataset.originalContent || submitButton.innerHTML;
        }
    });
}

window.showMessage = function(message, type) {
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `fixed top-4 right-4 p-4 rounded-lg z-50 max-w-sm ${
        type === 'success' 
            ? 'bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200' 
            : 'bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200'
    }`;
    messageDiv.innerHTML = `
        <div class="flex justify-between items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-lg">&times;</button>
        </div>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentElement) {
            messageDiv.remove();
        }
    }, 5000);
}

// Apply filters function
window.applyFilters = function() {
    const flagType = document.getElementById('flagTypeFilter').value;
    const status = document.getElementById('statusFilter').value;
    const dateFrom = document.getElementById('dateFromFilter').value;
    const dateTo = document.getElementById('dateToFilter').value;
    
    const params = new URLSearchParams();
    if (flagType) params.set('flag_type', flagType);
    if (status) params.set('status', status);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    
    // Reload the flagged tab with filters
    fetch(`admin-flagged.php?${params.toString()}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('mainContent').innerHTML = html;
            
            // Re-execute scripts in the loaded content
            const scripts = document.querySelectorAll('#mainContent script');
            scripts.forEach(script => {
                if (script.textContent) {
                    try {
                        (1, eval)(script.textContent);
                    } catch (e) {
                        console.error('Error executing script:', e);
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error applying filters:', error);
        });
}

// Clear filters function
window.clearFilters = function() {
    document.getElementById('flagTypeFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFromFilter').value = '';
    document.getElementById('dateToFilter').value = '';
    applyFilters();
}

// Initialize filters and add auto-submit functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        const selects = filterForm.querySelectorAll('select');
        const inputs = filterForm.querySelectorAll('input[type="date"]');
        
        // Set filter values from PHP
        document.getElementById('flagTypeFilter').value = '<?= htmlspecialchars($flag_type_filter) ?>';
        document.getElementById('statusFilter').value = '<?= htmlspecialchars($status_filter) ?>';
        document.getElementById('dateFromFilter').value = '<?= htmlspecialchars($date_from) ?>';
        document.getElementById('dateToFilter').value = '<?= htmlspecialchars($date_to) ?>';
        
        // Auto-submit form when filters change
        [...selects, ...inputs].forEach(element => {
            element.addEventListener('change', function() {
                applyFilters();
            });
        });
    }
});
</script>
