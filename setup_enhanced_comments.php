
<?php
// Database setup for enhanced comments system
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create comment_replies table for nested comments
    $db->exec("CREATE TABLE IF NOT EXISTS comment_replies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parent_comment_id INTEGER NOT NULL,
        paste_id INTEGER NOT NULL,
        user_id TEXT,
        content TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        is_deleted BOOLEAN DEFAULT 0,
        FOREIGN KEY(parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE,
        FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // Create comment_notifications table
    $db->exec("CREATE TABLE IF NOT EXISTS comment_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        comment_id INTEGER,
        reply_id INTEGER,
        paste_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- 'comment', 'reply', 'mention'
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT 0,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE,
        FOREIGN KEY(reply_id) REFERENCES comment_replies(id) ON DELETE CASCADE,
        FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE
    )");

    // Create comment_reports table for moderation
    $db->exec("CREATE TABLE IF NOT EXISTS comment_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        comment_id INTEGER,
        reply_id INTEGER,
        reporter_user_id TEXT,
        reason TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'pending', -- 'pending', 'reviewed', 'dismissed'
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        reviewed_by TEXT,
        reviewed_at INTEGER,
        FOREIGN KEY(comment_id) REFERENCES comments(id) ON DELETE CASCADE,
        FOREIGN KEY(reply_id) REFERENCES comment_replies(id) ON DELETE CASCADE,
        FOREIGN KEY(reporter_user_id) REFERENCES users(id),
        FOREIGN KEY(reviewed_by) REFERENCES users(id)
    )");

    // Add moderation columns to existing comments table
    $columns = $db->query("PRAGMA table_info(comments)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('is_deleted', $columns)) {
        $db->exec("ALTER TABLE comments ADD COLUMN is_deleted BOOLEAN DEFAULT 0");
    }
    if (!in_array('is_flagged', $columns)) {
        $db->exec("ALTER TABLE comments ADD COLUMN is_flagged BOOLEAN DEFAULT 0");
    }
    if (!in_array('reply_count', $columns)) {
        $db->exec("ALTER TABLE comments ADD COLUMN reply_count INTEGER DEFAULT 0");
    }

    echo "Enhanced comments database setup complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
