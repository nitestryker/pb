<?php
if(!session_id()) session_start();
function check_admin_auth(){if(!isset($_SESSION['admin_id'])){header('Location: admin-login.php');exit();}}
function handle_logout(){if(isset($_GET['logout'])){session_destroy();header('Location: admin-login.php');exit();}}

function end_impersonation() {
    if (isset($_SESSION['impersonating_user']) && isset($_SESSION['original_admin_id'])) {
        // Log impersonation end
        try {
            $db = new PDO('sqlite:database.sqlite');
            $db->prepare("UPDATE admin_impersonations SET ended_at = ? WHERE admin_id = ? AND target_user_id = ? AND ended_at IS NULL")
               ->execute([time(), $_SESSION['original_admin_id'], $_SESSION['impersonating_user']]);

            // Log the action
            require_once 'audit_logger.php';
            $audit_logger = new AuditLogger();
            $audit_logger->log('impersonation_ended', $_SESSION['original_admin_id'], [
                'target_user_id' => $_SESSION['impersonating_user']
            ]);
        } catch (Exception $e) {
            error_log("Error ending impersonation: " . $e->getMessage());
        }

        // Restore admin session
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['impersonating_user']);
        unset($_SESSION['impersonating_username']);
        $_SESSION['admin_id'] = $_SESSION['original_admin_id'];
        unset($_SESSION['original_admin_id']);

        header('Location: admindash.php');
        exit;
    }
}

// Handle end impersonation
if (isset($_GET['end_impersonation'])) {
    end_impersonation();
}

// Add flags column if it doesn't exist
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->exec("ALTER TABLE pastes ADD COLUMN flags INTEGER DEFAULT 0");
} catch (PDOException $e) {
    // Column might already exist
}
?>