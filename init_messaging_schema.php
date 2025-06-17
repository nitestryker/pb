
<?php
// Dedicated messaging schema initialization - DO NOT MODIFY
// This ensures the correct threaded messaging table structure is always maintained

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if messages table exists with correct schema
    $stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='messages'");
    $existing_table = $stmt->fetch();
    
    $correct_schema = "CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id TEXT NOT NULL,
    subject TEXT NOT NULL,
    content TEXT NOT NULL,
    reply_to_message_id INTEGER,
    thread_id INTEGER,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    sender_keep INTEGER DEFAULT 1,
    FOREIGN KEY(sender_id) REFERENCES users(id),
    FOREIGN KEY(reply_to_message_id) REFERENCES messages(id),
    FOREIGN KEY(thread_id) REFERENCES messages(id)
)";
    
    if (!$existing_table || strpos($existing_table['sql'], 'reply_to_message_id') === false) {
        echo "Initializing correct messages table schema...\n";
        
        // Backup existing messages if any
        $backup_data = [];
        if ($existing_table) {
            try {
                $stmt = $db->query("SELECT * FROM messages ORDER BY id");
                $backup_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "Backed up " . count($backup_data) . " existing messages\n";
            } catch (Exception $e) {
                echo "No existing messages to backup\n";
            }
        }
        
        // Drop and recreate with correct schema
        $db->exec("DROP TABLE IF EXISTS messages");
        $db->exec($correct_schema);
        
        // Restore backed up data if possible
        if (!empty($backup_data)) {
            $restored = 0;
            foreach ($backup_data as $msg) {
                try {
                    // Only restore if sender_id exists in users table
                    $stmt = $db->prepare("SELECT 1 FROM users WHERE id = ?");
                    $stmt->execute([$msg['sender_id']]);
                    if ($stmt->fetch()) {
                        $stmt = $db->prepare("INSERT INTO messages (sender_id, subject, content, created_at) VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $msg['sender_id'],
                            $msg['subject'] ?? 'Migrated Message',
                            $msg['content'],
                            $msg['created_at'] ?? time()
                        ]);
                        $restored++;
                    }
                } catch (Exception $e) {
                    // Skip invalid messages
                    continue;
                }
            }
            echo "Restored $restored messages\n";
        }
        
        echo "Messages table schema initialized successfully\n";
    } else {
        echo "Messages table already has correct schema\n";
    }
    
    // Ensure message_recipients table exists
    $db->exec("CREATE TABLE IF NOT EXISTS message_recipients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        message_id INTEGER NOT NULL,
        recipient_id TEXT NOT NULL,
        recipient_keep INTEGER DEFAULT 1,
        recipient_read_date INTEGER NULL,
        FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE,
        FOREIGN KEY(recipient_id) REFERENCES users(id)
    )");
    
    // Ensure message_notifications table exists
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
    
    echo "All messaging tables verified/created successfully\n";
    
} catch (PDOException $e) {
    echo "Error initializing messaging schema: " . $e->getMessage() . "\n";
    exit(1);
}
?>
