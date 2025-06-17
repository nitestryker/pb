
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 30);

    echo "Starting complete threaded messaging database fix...\n";

    // Check foreign key definitions first
    echo "Checking foreign key definitions...\n";
    $stmt = $db->query("PRAGMA foreign_key_list(messages)");
    $foreign_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($foreign_keys as $fk) {
        echo "FK: {$fk['from']} -> {$fk['table']}({$fk['to']})\n";
    }

    // Temporarily disable foreign keys for migration
    $db->exec("PRAGMA foreign_keys = OFF");
    echo "Disabled foreign keys for migration\n";

    // Check current messages table structure
    $stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='messages'");
    $existing_table = $stmt->fetch();
    
    if ($existing_table) {
        echo "Current messages table:\n";
        echo $existing_table['sql'] . "\n\n";
        
        // Check columns
        $stmt = $db->query("PRAGMA table_info(messages)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $column_names = array_column($columns, 'name');
        
        echo "Current columns: " . implode(', ', $column_names) . "\n\n";
        
        // Check if we have the required columns
        $required_columns = ['thread_id', 'reply_to_message_id', 'sender_keep'];
        $missing_columns = array_diff($required_columns, $column_names);
        
        if (!empty($missing_columns)) {
            echo "Missing columns: " . implode(', ', $missing_columns) . "\n";
            echo "Recreating table with proper structure...\n";
            
            // Backup existing messages
            $stmt = $db->query("SELECT * FROM messages ORDER BY id");
            $existing_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Backing up " . count($existing_messages) . " existing messages\n";
            
            // Drop the old table
            $db->exec("DROP TABLE IF EXISTS messages");
            
            // Create new table with correct structure
            $db->exec("CREATE TABLE messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender_id TEXT NOT NULL,
                subject TEXT NOT NULL,
                content TEXT NOT NULL,
                reply_to_message_id INTEGER NULL,
                thread_id INTEGER NULL,
                created_at INTEGER DEFAULT (strftime('%s', 'now')),
                sender_keep INTEGER DEFAULT 1,
                FOREIGN KEY(sender_id) REFERENCES users(id),
                FOREIGN KEY(reply_to_message_id) REFERENCES messages(id),
                FOREIGN KEY(thread_id) REFERENCES messages(id)
            )");
            
            echo "Created new messages table with threaded structure\n";
            
            // Migrate existing data (validate user IDs first)
            $migrated_count = 0;
            foreach ($existing_messages as $msg) {
                // Check if the sender_id exists in users table
                $stmt = $db->prepare("SELECT 1 FROM users WHERE id = ?");
                $stmt->execute([$msg['sender_id']]);
                
                if ($stmt->fetch()) {
                    // User exists, safe to migrate
                    $stmt = $db->prepare("INSERT INTO messages (id, sender_id, subject, content, created_at, reply_to_message_id, thread_id, sender_keep) VALUES (?, ?, ?, ?, ?, NULL, NULL, 1)");
                    $stmt->execute([
                        $msg['id'],
                        $msg['sender_id'],
                        $msg['subject'],
                        $msg['content'],
                        $msg['created_at'] ?? time()
                    ]);
                    $migrated_count++;
                } else {
                    echo "Skipping message ID {$msg['id']} - sender_id '{$msg['sender_id']}' not found in users table\n";
                }
            }
            
            echo "Migrated $migrated_count of " . count($existing_messages) . " messages to new structure\n";
        } else {
            echo "All required columns already exist\n";
        }
    } else {
        echo "No messages table found, creating new one...\n";
        $db->exec("CREATE TABLE messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id TEXT NOT NULL,
            subject TEXT NOT NULL,
            content TEXT NOT NULL,
            reply_to_message_id INTEGER NULL,
            thread_id INTEGER NULL,
            created_at INTEGER DEFAULT (strftime('%s', 'now')),
            sender_keep INTEGER DEFAULT 1,
            FOREIGN KEY(sender_id) REFERENCES users(id),
            FOREIGN KEY(reply_to_message_id) REFERENCES messages(id),
            FOREIGN KEY(thread_id) REFERENCES messages(id)
        )");
    }

    // Create/update message_recipients table
    echo "Setting up message_recipients table...\n";
    $db->exec("DROP TABLE IF EXISTS message_recipients");
    $db->exec("CREATE TABLE message_recipients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        message_id INTEGER NOT NULL,
        recipient_id TEXT NOT NULL,
        recipient_keep INTEGER DEFAULT 1,
        recipient_read_date INTEGER NULL,
        FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE,
        FOREIGN KEY(recipient_id) REFERENCES users(id)
    )");

    // Create message_notifications table
    echo "Setting up message_notifications table...\n";
    $db->exec("DROP TABLE IF EXISTS message_notifications");
    $db->exec("CREATE TABLE message_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        message_id INTEGER NOT NULL,
        type TEXT DEFAULT 'new_message',
        message TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        is_read INTEGER DEFAULT 0,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE
    )");

    // Create indexes
    echo "Creating indexes...\n";
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_thread ON messages(thread_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_messages_reply_to ON messages(reply_to_message_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_recipients_user ON message_recipients(recipient_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_recipients_message ON message_recipients(message_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON message_notifications(user_id)");

    // Re-enable foreign keys
    $db->exec("PRAGMA foreign_keys = ON");
    echo "Re-enabled foreign key constraints\n";

    // Final verification
    echo "\nFinal verification:\n";
    $stmt = $db->query("PRAGMA table_info(messages)");
    $final_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Messages table columns:\n";
    foreach ($final_columns as $column) {
        echo "- {$column['name']} ({$column['type']})\n";
    }

    // Test the new columns to ensure they're accessible
    try {
        $db->query("SELECT thread_id, reply_to_message_id, sender_keep FROM messages LIMIT 1");
        echo "\n✓ Column verification: SUCCESS - All threaded messaging columns are accessible\n";
    } catch (Exception $e) {
        echo "\n✗ Column verification: FAILED - " . $e->getMessage() . "\n";
        throw $e;
    }

    // Test insert with proper foreign key validation
    echo "\nTesting insert functionality...\n";
    try {
        // Check if there's an existing user we can use for testing
        $stmt = $db->query("SELECT id FROM users LIMIT 1");
        $test_user = $stmt->fetch();
        
        if ($test_user) {
            $test_user_id = $test_user['id'];
            echo "Using existing user ID: $test_user_id for test\n";
            
            $stmt = $db->prepare("INSERT INTO messages (sender_id, subject, content, reply_to_message_id, thread_id, sender_keep) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$test_user_id, 'Test Subject', 'Test Content', null, null, 1]);
            $test_id = $db->lastInsertId();
            $db->exec("DELETE FROM messages WHERE id = $test_id");
            echo "✓ Insert verification: SUCCESS - Table structure is working correctly\n";
        } else {
            echo "No users found in database. Creating test user for validation...\n";
            
            // Create a test user for validation
            $test_user_id = 'test_user_' . time();
            $stmt = $db->prepare("INSERT INTO users (id, username, password, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$test_user_id, 'testuser', 'temppass', time()]);
            
            // Test message insert
            $stmt = $db->prepare("INSERT INTO messages (sender_id, subject, content, reply_to_message_id, thread_id, sender_keep) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$test_user_id, 'Test Subject', 'Test Content', null, null, 1]);
            $test_message_id = $db->lastInsertId();
            
            // Clean up test data
            $db->exec("DELETE FROM messages WHERE id = $test_message_id");
            $db->exec("DELETE FROM users WHERE id = '$test_user_id'");
            
            echo "✓ Insert verification: SUCCESS - Table structure is working correctly (used temporary test user)\n";
        }
    } catch (Exception $e) {
        echo "✗ Insert verification: FAILED - " . $e->getMessage() . "\n";
        
        // Show available users for debugging
        echo "Available users in database:\n";
        $stmt = $db->query("SELECT id, username FROM users LIMIT 5");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}\n";
        }
        
        throw $e;
    }

    // Optimize database
    $db->exec("VACUUM");
    echo "\nDatabase optimized\n";

    echo "\n✓ Threaded messaging database fix completed successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
