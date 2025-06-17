
<?php
require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Create paste discussion threads table
    $db->exec("CREATE TABLE IF NOT EXISTS paste_discussion_threads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        paste_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        category TEXT NOT NULL CHECK (category IN ('Q&A', 'Tip', 'Idea', 'Bug', 'General')),
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Create paste discussion posts table
    $db->exec("CREATE TABLE IF NOT EXISTS paste_discussion_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        thread_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        is_deleted INTEGER DEFAULT 0,
        FOREIGN KEY (thread_id) REFERENCES paste_discussion_threads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_discussion_threads_paste_id ON paste_discussion_threads(paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_discussion_posts_thread_id ON paste_discussion_posts(thread_id)");
    
    echo "Paste discussions tables created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
