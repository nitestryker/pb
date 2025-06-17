
<?php
require_once 'database.php';
require_once 'audit_logger.php';

// Initialize database and audit logger
$db = Database::getInstance()->getConnection();
$audit_logger = new AuditLogger();

echo "Starting duplicate paste removal process...\n";
echo "This will find pastes with the same title by the same user and keep only the most recent one.\n\n";

try {
    // Begin transaction for data integrity
    $db->beginTransaction();
    
    // Find duplicates - group by user_id and title, having count > 1
    $stmt = $db->prepare("
        SELECT user_id, title, COUNT(*) as duplicate_count
        FROM pastes 
        WHERE user_id IS NOT NULL 
        AND title IS NOT NULL 
        AND title != ''
        GROUP BY user_id, title 
        HAVING COUNT(*) > 1
        ORDER BY user_id, title
    ");
    $stmt->execute();
    $duplicate_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicate_groups)) {
        echo "No duplicate pastes found!\n";
        $db->rollback();
        exit;
    }
    
    echo "Found " . count($duplicate_groups) . " groups of duplicate pastes:\n\n";
    
    $total_removed = 0;
    $total_kept = 0;
    
    foreach ($duplicate_groups as $group) {
        $user_id = $group['user_id'];
        $title = $group['title'];
        $count = $group['duplicate_count'];
        
        echo "Processing: User ID '{$user_id}', Title '{$title}' ({$count} duplicates)\n";
        
        // Get all pastes for this user/title combination, ordered by creation date (newest first)
        $stmt = $db->prepare("
            SELECT id, title, created_at, views, language, content
            FROM pastes 
            WHERE user_id = ? AND title = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id, $title]);
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Keep the first one (newest), mark the rest for deletion
        $keep_paste = array_shift($duplicates);
        $total_kept++;
        
        echo "  → Keeping: ID {$keep_paste['id']} (created " . date('Y-m-d H:i:s', $keep_paste['created_at']) . ", {$keep_paste['views']} views)\n";
        
        foreach ($duplicates as $duplicate) {
            echo "  → Removing: ID {$duplicate['id']} (created " . date('Y-m-d H:i:s', $duplicate['created_at']) . ", {$duplicate['views']} views)\n";
            
            // Delete related data first to maintain referential integrity
            $paste_id = $duplicate['id'];
            
            // Delete comments
            $stmt = $db->prepare("DELETE FROM comments WHERE paste_id = ?");
            $stmt->execute([$paste_id]);
            $deleted_comments = $stmt->rowCount();
            
            // Delete comment replies
            $stmt = $db->prepare("DELETE FROM comment_replies WHERE paste_id = ?");
            $stmt->execute([$paste_id]);
            $deleted_replies = $stmt->rowCount();
            
            // Delete comment notifications
            $stmt = $db->prepare("DELETE FROM comment_notifications WHERE paste_id = ?");
            $stmt->execute([$paste_id]);
            $deleted_notifications = $stmt->rowCount();
            
            // Delete paste views
            $stmt = $db->prepare("DELETE FROM paste_views WHERE paste_id = ?");
            $stmt->execute([$paste_id]);
            $deleted_views = $stmt->rowCount();
            
            // Delete user favorites
            $stmt = $db->prepare("DELETE FROM user_pastes WHERE paste_id = ?");
            $stmt->execute([$paste_id]);
            $deleted_favorites = $stmt->rowCount();
            
            // Delete collection relationships
            $stmt = $db->prepare("DELETE FROM collection_pastes WHERE paste_id = ?");
            $stmt->execute([$paste_id]);
            $deleted_collection_refs = $stmt->rowCount();
            
            // Delete paste flags
            $stmt = $db->prepare("DELETE FROM paste_flags WHERE paste_id = ?");
            $stmt->execute([$paste_id]);
            $deleted_flags = $stmt->rowCount();
            
            // Delete paste versions if they exist
            try {
                $stmt = $db->prepare("DELETE FROM paste_versions WHERE paste_id = ?");
                $stmt->execute([$paste_id]);
                $deleted_versions = $stmt->rowCount();
            } catch (PDOException $e) {
                $deleted_versions = 0; // Table might not exist
            }
            
            // Delete paste forks if they exist
            try {
                $stmt = $db->prepare("DELETE FROM paste_forks WHERE original_paste_id = ? OR forked_paste_id = ?");
                $stmt->execute([$paste_id, $paste_id]);
                $deleted_forks = $stmt->rowCount();
            } catch (PDOException $e) {
                $deleted_forks = 0; // Table might not exist
            }
            
            // Delete discussion threads if they exist
            try {
                $stmt = $db->prepare("DELETE FROM paste_discussion_threads WHERE paste_id = ?");
                $stmt->execute([$paste_id]);
                $deleted_discussions = $stmt->rowCount();
            } catch (PDOException $e) {
                $deleted_discussions = 0; // Table might not exist
            }
            
            // Delete AI summaries if they exist
            try {
                $stmt = $db->prepare("DELETE FROM ai_summaries WHERE paste_id = ?");
                $stmt->execute([$paste_id]);
                $deleted_ai_summaries = $stmt->rowCount();
            } catch (PDOException $e) {
                $deleted_ai_summaries = 0; // Table might not exist
            }
            
            // Clear related pastes cache if it exists
            try {
                $stmt = $db->prepare("DELETE FROM related_pastes_cache WHERE paste_id = ? OR related_paste_id = ?");
                $stmt->execute([$paste_id, $paste_id]);
                $deleted_cache = $stmt->rowCount();
            } catch (PDOException $e) {
                $deleted_cache = 0; // Table might not exist
            }
            
            // Finally, delete the paste itself
            $stmt = $db->prepare("DELETE FROM pastes WHERE id = ?");
            $stmt->execute([$paste_id]);
            
            echo "    ✓ Deleted related data: {$deleted_comments} comments, {$deleted_replies} replies, {$deleted_views} views, {$deleted_favorites} favorites\n";
            
            $total_removed++;
        }
        
        echo "\n";
    }
    
    // Log the duplicate removal action
    $audit_logger->log('duplicate_paste_removal', 'admin', null, [
        'total_groups_processed' => count($duplicate_groups),
        'total_pastes_removed' => $total_removed,
        'total_pastes_kept' => $total_kept,
        'removal_criteria' => 'same_title_same_user_keep_newest',
        'groups_details' => array_map(function($group) {
            return [
                'user_id' => $group['user_id'],
                'title' => $group['title'],
                'duplicate_count' => $group['duplicate_count']
            ];
        }, $duplicate_groups)
    ]);
    
    // Commit the transaction
    $db->commit();
    
    echo "✓ Duplicate removal completed successfully!\n\n";
    echo "Summary:\n";
    echo "- Processed {$total_kept} unique paste titles\n";
    echo "- Removed {$total_removed} duplicate pastes\n";
    echo "- Kept {$total_kept} most recent versions\n";
    echo "- All related data cleaned up properly\n";
    
    // Optional: Run VACUUM to reclaim space
    echo "\nReclaiming database space...\n";
    $db->exec("VACUUM");
    echo "✓ Database optimized\n";
    
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

echo "\nDuplicate paste removal process completed!\n";
?>
