
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Setting up project issue comments system...\n";

    // Create project_issue_comments table
    $db->exec("CREATE TABLE IF NOT EXISTS project_issue_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        issue_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        content TEXT NOT NULL,
        parent_comment_id INTEGER NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        updated_at INTEGER NULL,
        is_deleted INTEGER DEFAULT 0,
        FOREIGN KEY(issue_id) REFERENCES project_issues(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(parent_comment_id) REFERENCES project_issue_comments(id) ON DELETE CASCADE
    )");

    // Add updated_at column to project_issues if it doesn't exist
    $columns = $db->query("PRAGMA table_info(project_issues)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('updated_at', $columns)) {
        $db->exec("ALTER TABLE project_issues ADD COLUMN updated_at INTEGER");
        
        // Set updated_at to created_at for existing issues
        $db->exec("UPDATE project_issues SET updated_at = created_at WHERE updated_at IS NULL");
    }

    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_issue_comments_issue ON project_issue_comments(issue_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_issue_comments_user ON project_issue_comments(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_issue_comments_parent ON project_issue_comments(parent_comment_id)");

    echo "Project issue comments system setup complete!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
