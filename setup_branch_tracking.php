
<?php
require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Add commit tracking to project_branches table
    $columns = $db->query("PRAGMA table_info(project_branches)")->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('commit_count', $columns)) {
        $db->exec("ALTER TABLE project_branches ADD COLUMN commit_count INTEGER DEFAULT 0");
    }
    
    if (!in_array('last_commit_at', $columns)) {
        $db->exec("ALTER TABLE project_branches ADD COLUMN last_commit_at INTEGER");
    }
    
    if (!in_array('base_commit_count', $columns)) {
        $db->exec("ALTER TABLE project_branches ADD COLUMN base_commit_count INTEGER DEFAULT 0");
    }
    
    if (!in_array('created_at', $columns)) {
        $db->exec("ALTER TABLE project_branches ADD COLUMN created_at INTEGER");
    }
    
    // Create branch commits table for detailed tracking
    $db->exec("CREATE TABLE IF NOT EXISTS branch_commits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        branch_id INTEGER NOT NULL,
        commit_hash TEXT NOT NULL,
        commit_message TEXT,
        author_id INTEGER,
        created_at INTEGER NOT NULL,
        file_changes INTEGER DEFAULT 1,
        FOREIGN KEY(branch_id) REFERENCES project_branches(id),
        FOREIGN KEY(author_id) REFERENCES users(id)
    )");
    
    // Initialize commit counts for existing branches
    $stmt = $db->prepare("
        UPDATE project_branches 
        SET commit_count = (
            SELECT COUNT(*) 
            FROM project_files pf 
            WHERE pf.branch_id = project_branches.id
        )
        WHERE commit_count = 0
    ");
    $stmt->execute();
    
    echo "Branch tracking database setup complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
