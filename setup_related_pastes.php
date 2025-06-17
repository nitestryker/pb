
<?php
require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Create paste_related_cache table for optional caching
    $db->exec("CREATE TABLE IF NOT EXISTS paste_related_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        paste_id INTEGER NOT NULL,
        related_paste_id INTEGER NOT NULL,
        relevance_score INTEGER DEFAULT 1,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY(related_paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        UNIQUE(paste_id, related_paste_id)
    )");

    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_related_cache_paste ON paste_related_cache(paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_related_cache_related ON paste_related_cache(related_paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pastes_language ON pastes(language)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pastes_user_id ON pastes(user_id)");

    echo "Related pastes feature database setup complete!\n";
    echo "Tables created:\n";
    echo "- paste_related_cache: caches related paste relationships\n";
    echo "- Added performance indexes\n";

} catch (PDOException $e) {
    echo "Error setting up related pastes feature: " . $e->getMessage() . "\n";
}
?>
