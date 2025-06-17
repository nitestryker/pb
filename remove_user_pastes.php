
<?php
require_once 'database.php';
require_once 'audit_logger.php';

// Initialize database and audit logger
$db = Database::getInstance()->getConnection();
$audit_logger = new AuditLogger();

// Target user and date range
$target_username = 'nitestryker';
$start_date = '2025-06-03';
$end_date = '2025-06-05';

// Convert dates to timestamps
$start_timestamp = strtotime($start_date . ' 00:00:00');
$end_timestamp = strtotime($end_date . ' 23:59:59');

echo "Removing pastes for user '{$target_username}' between {$start_date} and {$end_date}\n";
echo "Start timestamp: {$start_timestamp}\n";
echo "End timestamp: {$end_timestamp}\n\n";

try {
    // Begin transaction for data integrity
    $db->beginTransaction();
    
    // First, get the user ID
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$target_username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "Error: User '{$target_username}' not found.\n";
        $db->rollback();
        exit;
    }
    
    $user_id = $user['id'];
    echo "Found user ID: {$user_id}\n\n";
    
    // Get pastes to be deleted (for logging purposes)
    $stmt = $db->prepare("
        SELECT id, title, created_at, views, language 
        FROM pastes 
        WHERE user_id = ? 
        AND created_at >= ? 
        AND created_at <= ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id, $start_timestamp, $end_timestamp]);
    $pastes_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pastes_to_delete)) {
        echo "No pastes found for user '{$target_username}' in the specified date range.\n";
        $db->rollback();
        exit;
    }
    
    echo "Found " . count($pastes_to_delete) . " pastes to delete:\n";
    foreach ($pastes_to_delete as $paste) {
        echo "- ID: {$paste['id']}, Title: '{$paste['title']}', Created: " . date('Y-m-d H:i:s', $paste['created_at']) . ", Views: {$paste['views']}, Language: {$paste['language']}\n";
    }
    echo "\n";
    
    // Delete related data first to maintain referential integrity
    $paste_ids = array_column($pastes_to_delete, 'id');
    $placeholders = str_repeat('?,', count($paste_ids) - 1) . '?';
    
    // Delete comments
    $stmt = $db->prepare("DELETE FROM comments WHERE paste_id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_comments = $stmt->rowCount();
    echo "Deleted {$deleted_comments} comments\n";
    
    // Delete comment replies
    $stmt = $db->prepare("DELETE FROM comment_replies WHERE paste_id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_replies = $stmt->rowCount();
    echo "Deleted {$deleted_replies} comment replies\n";
    
    // Delete comment notifications
    $stmt = $db->prepare("DELETE FROM comment_notifications WHERE paste_id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_notifications = $stmt->rowCount();
    echo "Deleted {$deleted_notifications} comment notifications\n";
    
    // Delete paste views
    $stmt = $db->prepare("DELETE FROM paste_views WHERE paste_id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_views = $stmt->rowCount();
    echo "Deleted {$deleted_views} paste view records\n";
    
    // Delete user favorites
    $stmt = $db->prepare("DELETE FROM user_pastes WHERE paste_id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_favorites = $stmt->rowCount();
    echo "Deleted {$deleted_favorites} favorite records\n";
    
    // Delete collection relationships
    $stmt = $db->prepare("DELETE FROM collection_pastes WHERE paste_id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_collection_refs = $stmt->rowCount();
    echo "Deleted {$deleted_collection_refs} collection references\n";
    
    // Delete paste flags
    $stmt = $db->prepare("DELETE FROM paste_flags WHERE paste_id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_flags = $stmt->rowCount();
    echo "Deleted {$deleted_flags} paste flags\n";
    
    // Delete paste versions if they exist
    try {
        $stmt = $db->prepare("DELETE FROM paste_versions WHERE paste_id IN ($placeholders)");
        $stmt->execute($paste_ids);
        $deleted_versions = $stmt->rowCount();
        echo "Deleted {$deleted_versions} paste versions\n";
    } catch (PDOException $e) {
        // Table might not exist, continue
        echo "Paste versions table not found (skipped)\n";
    }
    
    // Delete admin notes if they exist
    try {
        $stmt = $db->prepare("DELETE FROM admin_notes WHERE paste_id IN ($placeholders)");
        $stmt->execute($paste_ids);
        $deleted_notes = $stmt->rowCount();
        echo "Deleted {$deleted_notes} admin notes\n";
    } catch (PDOException $e) {
        // Table might not exist, continue
        echo "Admin notes table not found (skipped)\n";
    }
    
    // Finally, delete the pastes themselves
    $stmt = $db->prepare("DELETE FROM pastes WHERE id IN ($placeholders)");
    $stmt->execute($paste_ids);
    $deleted_pastes = $stmt->rowCount();
    echo "Deleted {$deleted_pastes} pastes\n\n";
    
    // Log the bulk deletion action
    $audit_logger->log('bulk_paste_deletion', 'admin', null, [
        'target_user' => $target_username,
        'target_user_id' => $user_id,
        'date_range' => "{$start_date} to {$end_date}",
        'deleted_pastes' => $deleted_pastes,
        'deleted_comments' => $deleted_comments,
        'deleted_replies' => $deleted_replies,
        'deleted_notifications' => $deleted_notifications,
        'deleted_views' => $deleted_views,
        'deleted_favorites' => $deleted_favorites,
        'deleted_collection_refs' => $deleted_collection_refs,
        'deleted_flags' => $deleted_flags,
        'paste_details' => array_map(function($paste) {
            return [
                'id' => $paste['id'],
                'title' => $paste['title'],
                'created_at' => date('Y-m-d H:i:s', $paste['created_at'])
            ];
        }, $pastes_to_delete)
    ]);
    
    // Commit the transaction
    $db->commit();
    
    echo "Successfully completed bulk deletion!\n";
    echo "Summary:\n";
    echo "- Deleted {$deleted_pastes} pastes\n";
    echo "- Deleted {$deleted_comments} comments\n";
    echo "- Deleted {$deleted_replies} comment replies\n";
    echo "- Deleted {$deleted_notifications} notifications\n";
    echo "- Deleted {$deleted_views} view records\n";
    echo "- Deleted {$deleted_favorites} favorite records\n";
    echo "- Deleted {$deleted_collection_refs} collection references\n";
    echo "- Deleted {$deleted_flags} flags\n";
    echo "\nAll operations completed successfully.\n";
    
} catch (Exception $e) {
    // Rollback on any error
    $db->rollback();
    echo "Error occurred: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
    
    // Log the error
    error_log("Bulk paste deletion error: " . $e->getMessage());
}
?>
