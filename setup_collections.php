
<?php
// Database setup for paste collections/folders
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create collections table
    $db->exec("CREATE TABLE IF NOT EXISTS collections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        user_id TEXT NOT NULL,
        is_public BOOLEAN DEFAULT 1,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        updated_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // Create collection_pastes table for many-to-many relationship
    $db->exec("CREATE TABLE IF NOT EXISTS collection_pastes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        collection_id INTEGER NOT NULL,
        paste_id INTEGER NOT NULL,
        added_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(collection_id) REFERENCES collections(id) ON DELETE CASCADE,
        FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        UNIQUE(collection_id, paste_id)
    )");

    // Add collection_id column to pastes table for default collection
    $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('collection_id', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN collection_id INTEGER DEFAULT NULL");
    }

    echo "Collections database setup complete!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
