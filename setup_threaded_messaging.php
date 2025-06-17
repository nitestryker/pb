
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop existing messages table and recreate with new structure
    $db->exec("DROP TABLE IF EXISTS messages");
    $db->exec("DROP TABLE IF EXISTS message_recipients");

    // Create messages table for threaded conversations
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

    // Create message_recipients table for many-to-many relationship
    $db->exec("CREATE TABLE message_recipients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        message_id INTEGER NOT NULL,
        recipient_id TEXT NOT NULL,
        recipient_keep INTEGER DEFAULT 1,
        recipient_read_date INTEGER NULL,
        FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE,
        FOREIGN KEY(recipient_id) REFERENCES users(id)
    )");

    // Create indexes for better performance
    $db->exec("CREATE INDEX idx_messages_sender ON messages(sender_id)");
    $db->exec("CREATE INDEX idx_messages_thread ON messages(thread_id)");
    $db->exec("CREATE INDEX idx_recipients_user ON message_recipients(recipient_id)");
    $db->exec("CREATE INDEX idx_recipients_message ON message_recipients(message_id)");

    // Create message_notifications table
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

    echo "Threaded messaging database setup complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
