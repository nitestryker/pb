
<?php
// Setup script for advanced project management system
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Setting up project management system...\n";
    
    // Projects table - main container for paste collections
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        readme_content TEXT,
        license_type TEXT DEFAULT 'MIT',
        user_id TEXT NOT NULL,
        is_public BOOLEAN DEFAULT 1,
        default_branch TEXT DEFAULT 'main',
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        updated_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");
    
    // Project branches
    $db->exec("CREATE TABLE IF NOT EXISTS project_branches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        created_from_branch_id INTEGER,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY(created_from_branch_id) REFERENCES project_branches(id),
        UNIQUE(project_id, name)
    )");
    
    // Project files (pastes within projects)
    $db->exec("CREATE TABLE IF NOT EXISTS project_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        branch_id INTEGER NOT NULL,
        paste_id INTEGER NOT NULL,
        file_path TEXT NOT NULL,
        file_name TEXT NOT NULL,
        is_readme BOOLEAN DEFAULT 0,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY(branch_id) REFERENCES project_branches(id) ON DELETE CASCADE,
        FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        UNIQUE(branch_id, file_path)
    )");
    
    // Project issues/notes
    $db->exec("CREATE TABLE IF NOT EXISTS project_issues (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'open',
        priority TEXT DEFAULT 'medium',
        label TEXT DEFAULT 'general',
        assigned_to TEXT,
        created_by TEXT NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        updated_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY(assigned_to) REFERENCES users(id),
        FOREIGN KEY(created_by) REFERENCES users(id)
    )");
    
    // Project milestones
    $db->exec("CREATE TABLE IF NOT EXISTS project_milestones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        due_date INTEGER,
        completed_at INTEGER,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
    )");
    
    // Issue-milestone relationships
    $db->exec("CREATE TABLE IF NOT EXISTS issue_milestones (
        issue_id INTEGER NOT NULL,
        milestone_id INTEGER NOT NULL,
        PRIMARY KEY(issue_id, milestone_id),
        FOREIGN KEY(issue_id) REFERENCES project_issues(id) ON DELETE CASCADE,
        FOREIGN KEY(milestone_id) REFERENCES project_milestones(id) ON DELETE CASCADE
    )");
    
    // Project collaborators
    $db->exec("CREATE TABLE IF NOT EXISTS project_collaborators (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        role TEXT DEFAULT 'contributor',
        added_at INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id),
        UNIQUE(project_id, user_id)
    )");
    
    // Add project_id column to pastes table
    $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('project_id', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN project_id INTEGER DEFAULT NULL");
        $db->exec("ALTER TABLE pastes ADD COLUMN branch_id INTEGER DEFAULT NULL");
    }
    
    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_projects_user ON projects(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_files_project ON project_files(project_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_files_branch ON project_files(branch_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_issues_project ON project_issues(project_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_project_collaborators ON project_collaborators(project_id, user_id)");
    
    echo "✓ Project management database setup complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
