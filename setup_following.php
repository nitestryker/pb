
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Setting up following system...\n";

    // Create user_follows table
    $db->exec("CREATE TABLE IF NOT EXISTS user_follows (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        follower_id TEXT NOT NULL,
        following_id TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(follower_id) REFERENCES users(id),
        FOREIGN KEY(following_id) REFERENCES users(id),
        UNIQUE(follower_id, following_id)
    )");

    // Add follower/following counts to users table
    try {
        $db->exec("ALTER TABLE users ADD COLUMN followers_count INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE users ADD COLUMN following_count INTEGER DEFAULT 0");
    } catch (PDOException $e) {
        // Columns might already exist
        echo "Follower count columns already exist\n";
    }

    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_follows_follower ON user_follows(follower_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_follows_following ON user_follows(following_id)");

    echo "Following system setup completed successfully!\n";

} catch (PDOException $e) {
    echo "Error setting up following system: " . $e->getMessage() . "\n";
}
?>
