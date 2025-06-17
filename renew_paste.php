
<?php
session_start();

require_once 'database.php';
require_once 'audit_logger.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
$audit_logger = new AuditLogger($db);

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$paste_id = $_POST['paste_id'] ?? null;
$expire_time = $_POST['expire_time'] ?? null;

if (!$paste_id) {
    echo json_encode(['success' => false, 'message' => 'Paste ID is required']);
    exit;
}

try {
    // Verify the user owns this paste
    $stmt = $db->prepare("SELECT id, title, expire_time FROM pastes WHERE id = ? AND user_id = ?");
    $stmt->execute([$paste_id, $user_id]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paste) {
        echo json_encode(['success' => false, 'message' => 'Paste not found or access denied']);
        exit;
    }
    
    // Calculate new expiration time
    $new_expire_time = null;
    if ($expire_time > 0) {
        $new_expire_time = time() + intval($expire_time);
    }
    
    // Update the paste expiration
    $stmt = $db->prepare("UPDATE pastes SET expire_time = ? WHERE id = ?");
    $stmt->execute([$new_expire_time, $paste_id]);
    
    // Log the renewal
    $audit_logger->log('paste_renewed', $user_id, [
        'paste_id' => $paste_id,
        'paste_title' => $paste['title'],
        'old_expire_time' => $paste['expire_time'],
        'new_expire_time' => $new_expire_time,
        'extension_seconds' => $expire_time
    ]);
    
    // Mark related expiration notifications as read
    $stmt = $db->prepare("UPDATE paste_expiration_notifications SET is_read = 1 WHERE paste_id = ? AND user_id = ?");
    $stmt->execute([$paste_id, $user_id]);
    
    $renewal_message = $new_expire_time ? 
        'Paste renewed until ' . date('M j, Y g:i A', $new_expire_time) : 
        'Paste set to never expire';
    
    echo json_encode([
        'success' => true, 
        'message' => $renewal_message,
        'new_expire_time' => $new_expire_time
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in renew_paste.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in renew_paste.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
