
<?php
// Database setup for paste versioning
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create paste_versions table
    $db->exec("CREATE TABLE IF NOT EXISTS paste_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        paste_id INTEGER NOT NULL,
        version_number INTEGER NOT NULL,
        title TEXT,
        content TEXT,
        language TEXT,
        created_at INTEGER,
        created_by TEXT,
        change_message TEXT,
        FOREIGN KEY(paste_id) REFERENCES pastes(id),
        FOREIGN KEY(created_by) REFERENCES users(id)
    )");

    // Add version tracking columns to pastes table
    $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('current_version', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN current_version INTEGER DEFAULT 1");
    }
    if (!in_array('last_modified', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN last_modified INTEGER");
    }

    echo "Paste versioning database setup complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
