
<?php
require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "Setting up paste annotations system...\n";

    // Create paste_annotations table
    $db->exec("CREATE TABLE IF NOT EXISTS paste_annotations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        paste_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        line_number INTEGER NOT NULL,
        annotation_text TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        updated_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_annotations_paste ON paste_annotations(paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_annotations_user ON paste_annotations(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_annotations_line ON paste_annotations(paste_id, line_number)");

    echo "Paste annotations database setup complete!\n";
    echo "Features enabled:\n";
    echo "- Line-specific annotations\n";
    echo "- User ownership tracking\n";
    echo "- Performance optimized queries\n";

} catch (PDOException $e) {
    echo "Error setting up paste annotations: " . $e->getMessage() . "\n";
}
?>
