
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Setting up paste chains system...\n";

    // Check if parent_paste_id column exists
    $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('parent_paste_id', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN parent_paste_id INTEGER DEFAULT NULL");
        echo "Added parent_paste_id column to pastes table\n";
    } else {
        echo "parent_paste_id column already exists\n";
    }

    // Create index for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pastes_parent ON pastes(parent_paste_id)");
    echo "Created index for parent_paste_id\n";

    // Add foreign key constraint (recreate table with constraint)
    $db->exec("PRAGMA foreign_keys=off");
    
    // Create new table with foreign key
    $db->exec("CREATE TABLE IF NOT EXISTS pastes_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        content TEXT,
        language TEXT DEFAULT 'text',
        password TEXT,
        expire_time INTEGER,
        created_at INTEGER,
        is_public BOOLEAN DEFAULT 1,
        tags TEXT,
        user_id TEXT,
        views INTEGER DEFAULT 0,
        burn_after_read BOOLEAN DEFAULT 0,
        zero_knowledge INTEGER DEFAULT 0,
        current_version INTEGER DEFAULT 1,
        last_modified INTEGER,
        flags INTEGER DEFAULT 0,
        collection_id INTEGER,
        original_paste_id INTEGER,
        fork_count INTEGER DEFAULT 0,
        parent_paste_id INTEGER DEFAULT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(collection_id) REFERENCES collections(id),
        FOREIGN KEY(original_paste_id) REFERENCES pastes(id),
        FOREIGN KEY(parent_paste_id) REFERENCES pastes(id)
    )");

    // Copy data if we're recreating the table
    $existing_data = $db->query("SELECT COUNT(*) FROM pastes")->fetchColumn();
    if ($existing_data > 0) {
        echo "Copying existing data...\n";
        $db->exec("INSERT INTO pastes_new SELECT * FROM pastes");
        $db->exec("DROP TABLE pastes");
        $db->exec("ALTER TABLE pastes_new RENAME TO pastes");
        echo "Table recreated with foreign key constraints\n";
    }

    $db->exec("PRAGMA foreign_keys=on");

    echo "Paste chains database setup complete!\n";
    echo "Features enabled:\n";
    echo "- Parent-child paste relationships\n";
    echo "- Chain navigation support\n";

} catch (PDOException $e) {
    echo "Error setting up paste chains: " . $e->getMessage() . "\n";
}
?>
