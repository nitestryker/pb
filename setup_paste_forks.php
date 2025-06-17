
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Setting up paste forking system...\n";

    // Create paste_forks table to track fork relationships
    $db->exec("CREATE TABLE IF NOT EXISTS paste_forks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        original_paste_id INTEGER NOT NULL,
        forked_paste_id INTEGER NOT NULL,
        forked_by_user_id TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(original_paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY(forked_paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY(forked_by_user_id) REFERENCES users(id),
        UNIQUE(original_paste_id, forked_by_user_id)
    )");

    // Add columns to pastes table if they don't exist
    $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('original_paste_id', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN original_paste_id INTEGER DEFAULT NULL");
    }
    
    if (!in_array('fork_count', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN fork_count INTEGER DEFAULT 0");
    }

    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_paste_forks_original ON paste_forks(original_paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_paste_forks_forked ON paste_forks(forked_paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_paste_forks_user ON paste_forks(forked_by_user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pastes_original ON pastes(original_paste_id)");

    echo "Paste forking database setup complete!\n";
    echo "Tables created:\n";
    echo "- paste_forks: tracks fork relationships\n";
    echo "- Added columns to pastes: original_paste_id, fork_count\n";

} catch (PDOException $e) {
    echo "Error setting up paste forking: " . $e->getMessage() . "\n";
}
?>
