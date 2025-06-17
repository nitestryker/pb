<?php
session_start();

// Check for maintenance mode
require_once 'maintenance_check.php';
require_once 'database.php';
require_once 'audit_logger.php';

$db = Database::getInstance()->getConnection();
$audit_logger = new AuditLogger();

// Create flagging tables if they don't exist
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

// Drop and recreate flag_categories table to ensure clean state
$db->exec("DROP TABLE IF EXISTS flag_categories");
$db->exec("CREATE TABLE flag_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    severity INTEGER DEFAULT 1,
    auto_hide BOOLEAN DEFAULT 0,
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
)");

// Insert default flag categories with INSERT OR IGNORE
$categories = [
    ['spam', 'Spam or unwanted promotional content', 2, 0],
    ['offensive', 'Offensive, hateful, or inappropriate content', 3, 1],
    ['malware', 'Contains malicious code or viruses', 4, 1],
    ['phishing', 'Phishing or scam attempt', 4, 1],
    ['copyright', 'Copyright infringement', 3, 0],
    ['personal_info', 'Contains personal or private information', 3, 1],
    ['illegal', 'Illegal content or activities', 4, 1],
    ['other', 'Other reason (please specify)', 1, 0]
];

foreach ($categories as $category) {
    $stmt = $db->prepare("INSERT OR IGNORE INTO flag_categories (name, description, severity, auto_hide) VALUES (?, ?, ?, ?)");
    $stmt->execute($category);
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paste_id = $_POST['paste_id'] ?? null;
    $flag_type = $_POST['flag_type'] ?? null;
    $reason = $_POST['reason'] ?? '';
    $description = $_POST['description'] ?? '';
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Validate input
    if (!$paste_id || !$flag_type) {
        $response['message'] = 'Missing required fields';
        echo json_encode($response);
        exit;
    }

    // Check if paste exists
    $stmt = $db->prepare("SELECT id, user_id, title FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paste) {
        $response['message'] = 'Paste not found';
        echo json_encode($response);
        exit;
    }

    // Prevent self-flagging
    if ($user_id && $paste['user_id'] === $user_id) {
        $response['message'] = 'You cannot flag your own paste';
        echo json_encode($response);
        exit;
    }

    // Check for duplicate flags from same user/IP (more comprehensive check)
    $check_params = [$paste_id];
    $check_query = "SELECT id FROM paste_flags WHERE paste_id = ? AND status = 'pending' AND (";
    
    if ($user_id) {
        $check_query .= "user_id = ?";
        $check_params[] = $user_id;
    } else {
        $check_query .= "user_id IS NULL";
    }
    
    $check_query .= " OR ip_address = ?)";
    $check_params[] = $ip_address;
    
    $stmt = $db->prepare($check_query);
    $stmt->execute($check_params);
    if ($stmt->fetch()) {
        $response['message'] = 'You have already flagged this paste';
        echo json_encode($response);
        exit;
    }

    // Get flag category details
    $stmt = $db->prepare("SELECT * FROM flag_categories WHERE name = ?");
    $stmt->execute([$flag_type]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    // Insert flag
    $stmt = $db->prepare("INSERT INTO paste_flags (paste_id, user_id, ip_address, flag_type, reason, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$paste_id, $user_id, $ip_address, $flag_type, $reason, $description]);

    // Update paste flags count
    $stmt = $db->prepare("SELECT COUNT(*) FROM paste_flags WHERE paste_id = ? AND status = 'pending'");
    $stmt->execute([$paste_id]);
    $flag_count = $stmt->fetchColumn();

    $stmt = $db->prepare("UPDATE pastes SET flags = ? WHERE id = ?");
    $stmt->execute([$flag_count, $paste_id]);

    // Auto-hide if category requires it and threshold is met
    if ($category && $category['auto_hide'] && $flag_count >= 3) {
        $stmt = $db->prepare("UPDATE pastes SET is_public = 0 WHERE id = ?");
        $stmt->execute([$paste_id]);
        
        // Log auto-hide action
        $audit_logger->log('paste_auto_hidden', $user_id ?: $ip_address, [
            'paste_id' => $paste_id,
            'flag_count' => $flag_count,
            'flag_type' => $flag_type
        ]);
    }
    
    // Get configurable thresholds from site settings
    require_once 'settings_helper.php';
    $auto_delete_threshold = SiteSettings::get('auto_delete_threshold', 10);
    $auto_blur_threshold = SiteSettings::get('auto_blur_threshold', 3);
    
    // Check auto-delete threshold
    if ($flag_count >= $auto_delete_threshold) {
        // Delete the paste automatically
        $stmt = $db->prepare("DELETE FROM pastes WHERE id = ?");
        $stmt->execute([$paste_id]);
        
        // Log auto-delete action
        $audit_logger->log('paste_auto_deleted', $user_id ?: $ip_address, [
            'paste_id' => $paste_id,
            'flag_count' => $flag_count,
            'flag_type' => $flag_type,
            'reason' => 'Exceeded auto-delete threshold',
            'threshold' => $auto_delete_threshold
        ]);
    }
    
    // Check auto-blur threshold but only if not already deleted
    if ($flag_count >= $auto_blur_threshold && $flag_count < $auto_delete_threshold) {
        // Log auto-blur trigger
        $audit_logger->log('paste_auto_blurred', $user_id ?: $ip_address, [
            'paste_id' => $paste_id,
            'flag_count' => $flag_count,
            'flag_type' => $flag_type,
            'reason' => 'Exceeded auto-blur threshold',
            'threshold' => $auto_blur_threshold
        ]);
    }

    // Log the flag
    $audit_logger->log('paste_flagged', $user_id ?: $ip_address, [
        'paste_id' => $paste_id,
        'flag_type' => $flag_type,
        'reason' => $reason
    ]);

    $response['success'] = true;
    $response['message'] = 'Thank you for your report. We will review it shortly.';
    echo json_encode($response);
    exit;
}

// Handle GET request for modal content
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $paste_id = $_GET['paste_id'] ?? null;
    
    if (!$paste_id) {
        http_response_code(400);
        echo 'Invalid request';
        exit;
    }

    // Get flag categories
    $stmt = $db->prepare("SELECT * FROM flag_categories ORDER BY severity DESC, name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get paste info
    $stmt = $db->prepare("SELECT id, title, user_id FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paste) {
        http_response_code(404);
        echo 'Paste not found';
        exit;
    }
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 max-w-sm w-full max-h-[80vh] overflow-hidden flex flex-col">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-flag text-red-500 mr-2"></i>Report Paste
            </h3>
            <button onclick="closeFlagModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="flagForm" onsubmit="submitFlag(event)" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="paste_id" value="<?= htmlspecialchars($paste_id) ?>">
            
            <div class="mb-3 flex-1 overflow-hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Why are you reporting this paste?
                </label>
                <div class="space-y-1 max-h-48 overflow-y-auto pr-2">
                    <?php foreach ($categories as $category): ?>
                        <label class="flex items-start p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                            <input type="radio" name="flag_type" value="<?= htmlspecialchars($category['name']) ?>" 
                                   class="mt-1 mr-2 flex-shrink-0" required
                                   onchange="toggleOtherField('<?= $category['name'] ?>')">
                            <div class="min-w-0">
                                <div class="font-medium text-sm text-gray-900 dark:text-white">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $category['name']))) ?>
                                    <?php if ($category['severity'] >= 3): ?>
                                        <span class="text-red-500 text-xs ml-1">HIGH</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    <?= htmlspecialchars($category['description']) ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div id="otherReasonField" class="mb-3 hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Please specify the reason:
                </label>
                <input type="text" name="reason" maxlength="200" 
                       class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Additional details (optional):
                </label>
                <textarea name="description" rows="2" maxlength="500" 
                          class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-white"
                          placeholder="Additional context..."></textarea>
            </div>
            
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded p-2 mb-3">
                <div class="text-xs text-yellow-800 dark:text-yellow-200">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Note:</strong> False reports may result in account restrictions.
                </div>
            </div>
            
            <div class="flex justify-end space-x-2 flex-shrink-0">
                <button type="button" onclick="closeFlagModal()" 
                        class="px-3 py-1 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
                    <i class="fas fa-flag mr-1"></i>Submit
                </button>
            </div>
        </form>
    </div>
    <?php
    exit;
}
?>
