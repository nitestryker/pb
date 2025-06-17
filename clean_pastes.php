
<?php
// Script to clean all paste data from database while maintaining structure
// This will remove all pastes and related data, then reset auto-increment IDs

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 30);

    echo "Starting database cleanup...\n";

    // Disable foreign key checks temporarily
    $db->exec("PRAGMA foreign_keys = OFF");
    echo "Disabled foreign key constraints\n";

    // Start transaction
    $db->beginTransaction();

    // Count records before deletion
    $stmt = $db->query("SELECT COUNT(*) FROM pastes");
    $paste_count = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM comments");
    $comment_count = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM paste_views");
    $view_count = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM user_pastes");
    $user_paste_count = $stmt->fetchColumn();
    
    echo "Found {$paste_count} pastes, {$comment_count} comments, {$view_count} views, {$user_paste_count} user-paste relations\n";

    // Delete all paste-related data in correct order (respecting foreign keys)
    echo "Deleting paste-related data...\n";

    // Delete comment replies first
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='comment_replies'")->fetch()) {
        $stmt = $db->query("DELETE FROM comment_replies");
        echo "Deleted " . $stmt->rowCount() . " comment replies\n";
    }

    // Delete comment notifications
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='comment_notifications'")->fetch()) {
        $stmt = $db->query("DELETE FROM comment_notifications");
        echo "Deleted " . $stmt->rowCount() . " comment notifications\n";
    }

    // Delete comment reports
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='comment_reports'")->fetch()) {
        $stmt = $db->query("DELETE FROM comment_reports");
        echo "Deleted " . $stmt->rowCount() . " comment reports\n";
    }

    // Delete comments
    $stmt = $db->query("DELETE FROM comments");
    echo "Deleted " . $stmt->rowCount() . " comments\n";

    // Delete collection-paste relationships
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='collection_pastes'")->fetch()) {
        $stmt = $db->query("DELETE FROM collection_pastes");
        echo "Deleted " . $stmt->rowCount() . " collection-paste relationships\n";
    }

    // Delete paste forks
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='paste_forks'")->fetch()) {
        $stmt = $db->query("DELETE FROM paste_forks");
        echo "Deleted " . $stmt->rowCount() . " paste forks\n";
    }

    // Delete paste versions
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='paste_versions'")->fetch()) {
        $stmt = $db->query("DELETE FROM paste_versions");
        echo "Deleted " . $stmt->rowCount() . " paste versions\n";
    }

    // Delete paste flags
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='paste_flags'")->fetch()) {
        $stmt = $db->query("DELETE FROM paste_flags");
        echo "Deleted " . $stmt->rowCount() . " paste flags\n";
    }

    // Delete paste shares
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='paste_shares'")->fetch()) {
        $stmt = $db->query("DELETE FROM paste_shares");
        echo "Deleted " . $stmt->rowCount() . " paste shares\n";
    }

    // Delete paste views
    $stmt = $db->query("DELETE FROM paste_views");
    echo "Deleted " . $stmt->rowCount() . " paste views\n";

    // Delete user-paste relationships (favorites)
    $stmt = $db->query("DELETE FROM user_pastes");
    echo "Deleted " . $stmt->rowCount() . " user-paste relationships\n";

    // Finally delete pastes themselves
    $stmt = $db->query("DELETE FROM pastes");
    echo "Deleted " . $stmt->rowCount() . " pastes\n";

    // Reset auto-increment values for all paste-related tables
    echo "Resetting auto-increment values...\n";

    $tables_to_reset = [
        'pastes',
        'comments',
        'paste_views',
        'user_pastes',
        'comment_replies',
        'comment_notifications',
        'comment_reports',
        'collection_pastes',
        'paste_forks',
        'paste_versions',
        'paste_flags',
        'paste_shares'
    ];

    foreach ($tables_to_reset as $table) {
        // Check if table exists
        $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        
        if ($stmt->fetch()) {
            // Reset auto-increment by updating sqlite_sequence
            $db->exec("DELETE FROM sqlite_sequence WHERE name='$table'");
            echo "Reset auto-increment for table: $table\n";
        }
    }

    // Re-enable foreign key checks
    $db->exec("PRAGMA foreign_keys = ON");
    echo "Re-enabled foreign key constraints\n";

    // Commit transaction
    $db->commit();
    echo "Transaction committed successfully\n";

    // Vacuum database to reclaim space
    echo "Vacuuming database to reclaim space...\n";
    $db->exec("VACUUM");
    echo "Database vacuumed\n";

    // Final verification
    echo "\nVerification:\n";
    $stmt = $db->query("SELECT COUNT(*) FROM pastes");
    echo "Remaining pastes: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) FROM comments");
    echo "Remaining comments: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) FROM paste_views");
    echo "Remaining views: " . $stmt->fetchColumn() . "\n";

    // Check auto-increment values
    echo "\nAuto-increment status:\n";
    $stmt = $db->query("SELECT name, seq FROM sqlite_sequence WHERE name IN ('pastes', 'comments', 'paste_views', 'user_pastes')");
    $sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sequences)) {
        echo "All auto-increment values have been reset (no entries in sqlite_sequence)\n";
    } else {
        foreach ($sequences as $seq) {
            echo "Table {$seq['name']}: next ID will be " . ($seq['seq'] + 1) . "\n";
        }
    }

    echo "\n✓ Database cleanup completed successfully!\n";
    echo "Summary:\n";
    echo "- Deleted {$paste_count} pastes\n";
    echo "- Deleted {$comment_count} comments\n";
    echo "- Deleted {$view_count} views\n";
    echo "- Deleted {$user_paste_count} user-paste relations\n";
    echo "- Reset all auto-increment values to start from 1\n";
    echo "- Database structure maintained intact\n";

} catch (PDOException $e) {
    // Rollback on error
    if ($db->inTransaction()) {
        $db->rollback();
        echo "Transaction rolled back due to error\n";
    }
    
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    // Rollback on any other error
    if ($db->inTransaction()) {
        $db->rollback();
        echo "Transaction rolled back due to error\n";
    }
    
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
