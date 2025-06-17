
<?php
// Expiration reminder system for PasteForge
// This script should be run daily via cron job

require_once 'database.php';
require_once 'audit_logger.php';

try {
    $db = Database::getInstance()->getConnection();
    $audit_logger = new AuditLogger($db);
    
    // Create expiration_reminders table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS expiration_reminders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        paste_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        reminder_type TEXT NOT NULL, -- '3_days', '1_day', '1_hour'
        sent_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(paste_id, reminder_type)
    )");
    
    // Create paste_expiration_notifications table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS paste_expiration_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        paste_id INTEGER NOT NULL,
        paste_title TEXT NOT NULL,
        expires_at INTEGER NOT NULL,
        reminder_type TEXT NOT NULL,
        message TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        is_read INTEGER DEFAULT 0,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE
    )");
    
    $current_time = time();
    
    // Find pastes expiring in 3 days (259200 seconds = 3 days)
    $three_days_from_now = $current_time + (3 * 24 * 60 * 60);
    $stmt = $db->prepare("
        SELECT p.id, p.title, p.user_id, p.expire_time, u.username 
        FROM pastes p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.expire_time IS NOT NULL 
        AND p.expire_time > ? 
        AND p.expire_time <= ? 
        AND p.user_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM expiration_reminders er 
            WHERE er.paste_id = p.id AND er.reminder_type = '3_days'
        )
    ");
    $stmt->execute([$current_time, $three_days_from_now]);
    $expiring_in_3_days = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find pastes expiring in 1 day (86400 seconds = 1 day)
    $one_day_from_now = $current_time + (24 * 60 * 60);
    $stmt = $db->prepare("
        SELECT p.id, p.title, p.user_id, p.expire_time, u.username 
        FROM pastes p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.expire_time IS NOT NULL 
        AND p.expire_time > ? 
        AND p.expire_time <= ? 
        AND p.user_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM expiration_reminders er 
            WHERE er.paste_id = p.id AND er.reminder_type = '1_day'
        )
    ");
    $stmt->execute([$current_time, $one_day_from_now]);
    $expiring_in_1_day = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find pastes expiring in 1 hour (3600 seconds = 1 hour)
    $one_hour_from_now = $current_time + (60 * 60);
    $stmt = $db->prepare("
        SELECT p.id, p.title, p.user_id, p.expire_time, u.username 
        FROM pastes p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.expire_time IS NOT NULL 
        AND p.expire_time > ? 
        AND p.expire_time <= ? 
        AND p.user_id IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM expiration_reminders er 
            WHERE er.paste_id = p.id AND er.reminder_type = '1_hour'
        )
    ");
    $stmt->execute([$current_time, $one_hour_from_now]);
    $expiring_in_1_hour = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_sent = 0;
    
    // Send 3-day reminders
    foreach ($expiring_in_3_days as $paste) {
        $days_left = ceil(($paste['expire_time'] - $current_time) / (24 * 60 * 60));
        $message = "Your paste '{$paste['title']}' will expire in {$days_left} days — renew it?";
        
        // Create notification
        $stmt = $db->prepare("
            INSERT INTO paste_expiration_notifications 
            (user_id, paste_id, paste_title, expires_at, reminder_type, message) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $paste['user_id'], 
            $paste['id'], 
            $paste['title'], 
            $paste['expire_time'], 
            '3_days', 
            $message
        ]);
        
        // Mark as reminded
        $stmt = $db->prepare("
            INSERT INTO expiration_reminders (paste_id, user_id, reminder_type) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$paste['id'], $paste['user_id'], '3_days']);
        
        echo "Sent 3-day reminder for paste: {$paste['title']} (ID: {$paste['id']}) to user: {$paste['username']}\n";
        $total_sent++;
    }
    
    // Send 1-day reminders
    foreach ($expiring_in_1_day as $paste) {
        $hours_left = ceil(($paste['expire_time'] - $current_time) / (60 * 60));
        $message = "Your paste '{$paste['title']}' will expire in {$hours_left} hours — renew it?";
        
        // Create notification
        $stmt = $db->prepare("
            INSERT INTO paste_expiration_notifications 
            (user_id, paste_id, paste_title, expires_at, reminder_type, message) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $paste['user_id'], 
            $paste['id'], 
            $paste['title'], 
            $paste['expire_time'], 
            '1_day', 
            $message
        ]);
        
        // Mark as reminded
        $stmt = $db->prepare("
            INSERT INTO expiration_reminders (paste_id, user_id, reminder_type) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$paste['id'], $paste['user_id'], '1_day']);
        
        echo "Sent 1-day reminder for paste: {$paste['title']} (ID: {$paste['id']}) to user: {$paste['username']}\n";
        $total_sent++;
    }
    
    // Send 1-hour reminders
    foreach ($expiring_in_1_hour as $paste) {
        $minutes_left = ceil(($paste['expire_time'] - $current_time) / 60);
        $message = "Your paste '{$paste['title']}' will expire in {$minutes_left} minutes — renew it now!";
        
        // Create notification
        $stmt = $db->prepare("
            INSERT INTO paste_expiration_notifications 
            (user_id, paste_id, paste_title, expires_at, reminder_type, message) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $paste['user_id'], 
            $paste['id'], 
            $paste['title'], 
            $paste['expire_time'], 
            '1_hour', 
            $message
        ]);
        
        // Mark as reminded
        $stmt = $db->prepare("
            INSERT INTO expiration_reminders (paste_id, user_id, reminder_type) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$paste['id'], $paste['user_id'], '1_hour']);
        
        echo "Sent 1-hour reminder for paste: {$paste['title']} (ID: {$paste['id']}) to user: {$paste['username']}\n";
        $total_sent++;
    }
    
    // Clean up old reminder records for expired pastes
    $stmt = $db->prepare("DELETE FROM expiration_reminders WHERE paste_id IN (SELECT id FROM pastes WHERE expire_time < ?)");
    $stmt->execute([$current_time]);
    $cleaned = $stmt->rowCount();
    
    // Clean up old expiration notifications (older than 7 days)
    $week_ago = $current_time - (7 * 24 * 60 * 60);
    $stmt = $db->prepare("DELETE FROM paste_expiration_notifications WHERE created_at < ?");
    $stmt->execute([$week_ago]);
    $cleaned_notifications = $stmt->rowCount();
    
    echo "\nExpiration reminder summary:\n";
    echo "- Total reminders sent: {$total_sent}\n";
    echo "- 3-day reminders: " . count($expiring_in_3_days) . "\n";
    echo "- 1-day reminders: " . count($expiring_in_1_day) . "\n";
    echo "- 1-hour reminders: " . count($expiring_in_1_hour) . "\n";
    echo "- Cleaned up {$cleaned} old reminder records\n";
    echo "- Cleaned up {$cleaned_notifications} old notifications\n";
    
    // Log the reminder activity
    if ($total_sent > 0) {
        $audit_logger->log('expiration_reminders_sent', 'system', [
            'total_sent' => $total_sent,
            '3_day_reminders' => count($expiring_in_3_days),
            '1_day_reminders' => count($expiring_in_1_day),
            '1_hour_reminders' => count($expiring_in_1_hour)
        ]);
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
