
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting complete messaging database fix...\n";

    // Check current messages table structure
    $stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='messages'");
    $existing_table = $stmt->fetch();
    
    if ($existing_table) {
        echo "Current messages table:\n";
        echo $existing_table['sql'] . "\n\n";
        
        // Check columns
        $stmt = $db->query("PRAGMA table_info(messages)");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        echo "Current columns: " . implode(', ', $columns) . "\n\n";
        
        // If we don't have the threaded columns, we need to recreate the table
        if (!in_array('thread_id', $columns) || !in_array('reply_to_message_id', $columns)) {
            echo "Missing threaded messaging columns. Backing up and recreating...\n";
            
            // Backup existing messages
            $db->exec("CREATE TABLE IF NOT EXISTS messages_backup AS SELECT * FROM messages");
            
            // Drop and recreate messages table
            $db->exec("DROP TABLE IF EXISTS messages");
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
            
            // Try to migrate old data if it exists
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM messages_backup");
                $count = $stmt->fetchColumn();
                if ($count > 0) {
                    echo "Migrating $count old messages...\n";
                    $db->exec("INSERT INTO messages (sender_id, subject, content, created_at) 
                              SELECT sender_id, subject, content, created_at FROM messages_backup");
                }
                $db->exec("DROP TABLE messages_backup");
            } catch (Exception $e) {
                echo "Note: Could not migrate old messages (this is normal for new installs)\n";
            }
        }
    } else {
        echo "Creating new messages table...\n";
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

    // Ensure message_recipients table exists
    echo "Creating message_recipients table...\n";
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
    echo "Creating message_notifications table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS message_notifications (
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
    $db->exec("CREATE INDEX IF NOT EXISTS idx_recipients_user ON message_recipients(recipient_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_recipients_message ON message_recipients(message_id)");

    // Verify final structure
    echo "\nFinal verification:\n";
    $stmt = $db->query("PRAGMA table_info(messages)");
    $final_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Messages table columns:\n";
    foreach ($final_columns as $column) {
        echo "- {$column['name']} ({$column['type']})\n";
    }
    
    $stmt = $db->query("PRAGMA table_info(message_recipients)");
    $recipient_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nMessage_recipients table columns:\n";
    foreach ($recipient_columns as $column) {
        echo "- {$column['name']} ({$column['type']})\n";
    }

    echo "\nComplete messaging database fix completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
