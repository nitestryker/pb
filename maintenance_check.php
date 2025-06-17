<?php
function checkMaintenanceMode() {
    // Skip maintenance check for admin pages and admin users
    $current_page = basename($_SERVER['PHP_SELF']);
    $admin_pages = ['admindash.php', 'admin-login.php', 'maintenance.php'];
    
    if (in_array($current_page, $admin_pages)) {
        return false;
    }
    
    // Skip maintenance check for logged-in admin users
    session_start();
    if (isset($_SESSION['admin_id'])) {
        return false;
    }
    
    try {
        $db = new PDO('sqlite:database.sqlite');
        $stmt = $db->query("SELECT maintenance_mode FROM site_settings WHERE id = 1");
        $result = $stmt->fetch();
        
        if ($result && $result['maintenance_mode'] == 1) {
            // Site is in maintenance mode
            header('Location: maintenance.php');
            exit;
        }
    } catch (PDOException $e) {
        // If we can't check the database, assume maintenance mode is off
        // This prevents the site from being locked out if there's a database issue
        return false;
    }
    
    return false;
}

// Call the maintenance check
checkMaintenanceMode();
?>
