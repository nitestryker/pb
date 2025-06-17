
<?php
require_once 'database.php';

$db = Database::getInstance()->getConnection();

try {
    // Add columns for import tracking to pastes table
    $db->exec("ALTER TABLE pastes ADD COLUMN source_url TEXT DEFAULT NULL");
    echo "Added source_url column to pastes table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') === false) {
        echo "Error adding source_url column: " . $e->getMessage() . "\n";
    } else {
        echo "source_url column already exists\n";
    }
}

try {
    $db->exec("ALTER TABLE pastes ADD COLUMN imported_from TEXT DEFAULT NULL");
    echo "Added imported_from column to pastes table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') === false) {
        echo "Error adding imported_from column: " . $e->getMessage() . "\n";
    } else {
        echo "imported_from column already exists\n";
    }
}

// Create table for embed analytics (optional)
$db->exec("CREATE TABLE IF NOT EXISTS embed_analytics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paste_id INTEGER NOT NULL,
    referrer TEXT,
    user_agent TEXT,
    embed_time INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY (paste_id) REFERENCES pastes(id)
)");

echo "Embed and import schema setup complete!\n";
?>
