
<?php
require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Testing annotations system...\n";
    
    // Check if table exists
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='paste_annotations'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "❌ paste_annotations table does not exist\n";
        exit(1);
    }
    
    echo "✅ paste_annotations table exists\n";
    
    // Check table structure
    $stmt = $db->query("PRAGMA table_info(paste_annotations)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $expected_columns = ['id', 'paste_id', 'user_id', 'line_number', 'annotation_text', 'created_at', 'updated_at'];
    $actual_columns = array_column($columns, 'name');
    
    foreach ($expected_columns as $col) {
        if (in_array($col, $actual_columns)) {
            echo "✅ Column '$col' exists\n";
        } else {
            echo "❌ Column '$col' missing\n";
        }
    }
    
    // Test basic operations
    echo "\nTesting basic operations...\n";
    
    // Test insert (requires existing paste and user)
    $stmt = $db->query("SELECT id FROM pastes LIMIT 1");
    $test_paste = $stmt->fetch();
    
    $stmt = $db->query("SELECT id FROM users LIMIT 1");
    $test_user = $stmt->fetch();
    
    if ($test_paste && $test_user) {
        $stmt = $db->prepare("INSERT INTO paste_annotations (paste_id, user_id, line_number, annotation_text) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$test_paste['id'], $test_user['id'], 1, 'Test annotation']);
        
        if ($result) {
            echo "✅ Insert test passed\n";
            $annotation_id = $db->lastInsertId();
            
            // Test select
            $stmt = $db->prepare("SELECT * FROM paste_annotations WHERE id = ?");
            $stmt->execute([$annotation_id]);
            $annotation = $stmt->fetch();
            
            if ($annotation) {
                echo "✅ Select test passed\n";
            } else {
                echo "❌ Select test failed\n";
            }
            
            // Clean up test data
            $stmt = $db->prepare("DELETE FROM paste_annotations WHERE id = ?");
            $stmt->execute([$annotation_id]);
            echo "✅ Cleanup completed\n";
        } else {
            echo "❌ Insert test failed\n";
        }
    } else {
        echo "⚠️  No test data available (no pastes or users found)\n";
    }
    
    echo "\nAnnotations system test completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
