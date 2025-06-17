<?php
session_start();
require_once 'database.php';
require_once 'maintenance_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /?page=login');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Create necessary tables if they don't exist
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

$db->exec("CREATE TABLE IF NOT EXISTS project_branches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    created_from_branch_id INTEGER,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    commit_count INTEGER DEFAULT 0,
    last_commit_at INTEGER,
    base_commit_count INTEGER DEFAULT 0,
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY(created_from_branch_id) REFERENCES project_branches(id),
    UNIQUE(project_id, name)
)");

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
    FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE
)");

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

$db->exec("CREATE TABLE IF NOT EXISTS issue_milestones (
    issue_id INTEGER NOT NULL,
    milestone_id INTEGER NOT NULL,
    PRIMARY KEY(issue_id, milestone_id),
    FOREIGN KEY(issue_id) REFERENCES project_issues(id) ON DELETE CASCADE,
    FOREIGN KEY(milestone_id) REFERENCES project_milestones(id) ON DELETE CASCADE
)");

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

// Create indexes for performance
$db->exec("CREATE INDEX IF NOT EXISTS idx_projects_user ON projects(user_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_project_files_project ON project_files(project_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_project_files_branch ON project_files(branch_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_project_issues_project ON project_issues(project_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_project_collaborators ON project_collaborators(project_id, user_id)");

// Handle actions
$action = $_GET['action'] ?? 'list';
$project_id = $_GET['project_id'] ?? null;
$tab = $_GET['tab'] ?? 'files';
$branch = $_GET['branch'] ?? 'main';

// Handle project creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_project') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $license_type = $_POST['license_type'] ?? 'MIT';
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        
        if (empty($name)) {
            $error = "Project name is required";
        } else {
            try {
                $db->beginTransaction();
                
                // Create project
                $stmt = $db->prepare("INSERT INTO projects (name, description, license_type, user_id, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $now = time();
                $stmt->execute([$name, $description, $license_type, $user_id, $is_public, $now, $now]);
                $project_id = $db->lastInsertId();
                
                // Create default main branch
                $stmt = $db->prepare("INSERT INTO project_branches (project_id, name, created_at) VALUES (?, ?, ?)");
                $stmt->execute([$project_id, 'main', $now]);
                
                $db->commit();
                
                // Redirect to the new project
                header("Location: project_manager.php?action=view&project_id=$project_id");
                exit;
            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to create project: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'add_file' && $project_id) {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $language = $_POST['language'] ?? 'text';
        $file_path = trim($_POST['file_path'] ?? '');
        $file_name = trim($_POST['file_name'] ?? '');
        $branch_id = $_POST['branch_id'] ?? null;
        $is_readme = isset($_POST['is_readme']) ? 1 : 0;
        
        if (empty($title) || empty($content) || empty($file_name)) {
            $error = "Title, content, and file name are required";
        } else {
            try {
                $db->beginTransaction();
                
                // Create paste
                $stmt = $db->prepare("INSERT INTO pastes (title, content, language, user_id, created_at, is_public) VALUES (?, ?, ?, ?, ?, ?)");
                $now = time();
                $stmt->execute([$title, $content, $language, $user_id, $now, 1]);
                $paste_id = $db->lastInsertId();
                
                // Add file to project
                $stmt = $db->prepare("INSERT INTO project_files (project_id, branch_id, paste_id, file_path, file_name, is_readme, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $branch_id, $paste_id, $file_path, $file_name, $is_readme, $now]);
                
                // Update project updated_at
                $stmt = $db->prepare("UPDATE projects SET updated_at = ? WHERE id = ?");
                $stmt->execute([$now, $project_id]);
                
                // Update branch commit count and last commit time
                $stmt = $db->prepare("UPDATE project_branches SET commit_count = commit_count + 1, last_commit_at = ? WHERE id = ?");
                $stmt->execute([$now, $branch_id]);
                
                $db->commit();
                
                // Redirect back to project
                header("Location: project_manager.php?action=view&project_id=$project_id&tab=files&branch=$branch");
                exit;
            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to add file: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'create_branch' && $project_id) {
        $branch_name = trim($_POST['branch_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $from_branch_id = $_POST['from_branch_id'] ?? null;
        
        if (empty($branch_name)) {
            $error = "Branch name is required";
        } else {
            try {
                // Check if branch already exists
                $stmt = $db->prepare("SELECT 1 FROM project_branches WHERE project_id = ? AND name = ?");
                $stmt->execute([$project_id, $branch_name]);
                if ($stmt->fetch()) {
                    $error = "A branch with this name already exists";
                } else {
                    $db->beginTransaction();
                    
                    // Create branch
                    $stmt = $db->prepare("INSERT INTO project_branches (project_id, name, description, created_from_branch_id, created_at) VALUES (?, ?, ?, ?, ?)");
                    $now = time();
                    $stmt->execute([$project_id, $branch_name, $description, $from_branch_id, $now]);
                    $new_branch_id = $db->lastInsertId();
                    
                    // Copy files from source branch if specified
                    if ($from_branch_id) {
                        $stmt = $db->prepare("
                            INSERT INTO project_files (project_id, branch_id, paste_id, file_path, file_name, is_readme, created_at)
                            SELECT project_id, ?, paste_id, file_path, file_name, is_readme, ?
                            FROM project_files
                            WHERE project_id = ? AND branch_id = ?
                        ");
                        $stmt->execute([$new_branch_id, $now, $project_id, $from_branch_id]);
                        
                        // Get base commit count
                        $stmt = $db->prepare("SELECT commit_count FROM project_branches WHERE id = ?");
                        $stmt->execute([$from_branch_id]);
                        $base_commit_count = $stmt->fetchColumn() ?: 0;
                        
                        // Update new branch base commit count
                        $stmt = $db->prepare("UPDATE project_branches SET base_commit_count = ? WHERE id = ?");
                        $stmt->execute([$base_commit_count, $new_branch_id]);
                    }
                    
                    // Update project updated_at
                    $stmt = $db->prepare("UPDATE projects SET updated_at = ? WHERE id = ?");
                    $stmt->execute([$now, $project_id]);
                    
                    $db->commit();
                    
                    // Redirect to the new branch
                    header("Location: project_manager.php?action=view&project_id=$project_id&tab=files&branch=$branch_name");
                    exit;
                }
            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to create branch: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'create_issue' && $project_id) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $label = $_POST['label'] ?? 'general';
        $milestone_id = !empty($_POST['milestone_id']) ? $_POST['milestone_id'] : null;
        
        if (empty($title)) {
            $error = "Issue title is required";
        } else {
            try {
                $db->beginTransaction();
                
                // Create issue
                $stmt = $db->prepare("INSERT INTO project_issues (project_id, title, description, priority, label, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $now = time();
                $stmt->execute([$project_id, $title, $description, $priority, $label, $user_id, $now, $now]);
                $issue_id = $db->lastInsertId();
                
                // Add to milestone if specified
                if ($milestone_id) {
                    $stmt = $db->prepare("INSERT INTO issue_milestones (issue_id, milestone_id) VALUES (?, ?)");
                    $stmt->execute([$issue_id, $milestone_id]);
                }
                
                // Update project updated_at
                $stmt = $db->prepare("UPDATE projects SET updated_at = ? WHERE id = ?");
                $stmt->execute([$now, $project_id]);
                
                $db->commit();
                
                // Redirect to issues tab
                header("Location: project_manager.php?action=view&project_id=$project_id&tab=issues");
                exit;
            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to create issue: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'create_milestone' && $project_id) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = !empty($_POST['due_date']) ? strtotime($_POST['due_date']) : null;
        
        if (empty($title)) {
            $error = "Milestone title is required";
        } else {
            try {
                // Create milestone
                $stmt = $db->prepare("INSERT INTO project_milestones (project_id, title, description, due_date, created_at) VALUES (?, ?, ?, ?, ?)");
                $now = time();
                $stmt->execute([$project_id, $title, $description, $due_date, $now]);
                
                // Update project updated_at
                $stmt = $db->prepare("UPDATE projects SET updated_at = ? WHERE id = ?");
                $stmt->execute([$now, $project_id]);
                
                // Redirect to milestones tab
                header("Location: project_manager.php?action=view&project_id=$project_id&tab=milestones");
                exit;
            } catch (PDOException $e) {
                $error = "Failed to create milestone: " . $e->getMessage();
            }
        }
    }
}

// Get theme from cookie
$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Project Manager - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <style>
        .file-tree-item {
            transition: background-color 0.2s;
        }
        .file-tree-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-6">
                    <a href="/" class="flex items-center space-x-3">
                        <i class="fas fa-paste text-2xl"></i>
                        <span class="text-xl font-bold">PasteForge</span>
                    </a>
                    <div class="flex space-x-4">
                        <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">Home</a>
                        <a href="/?page=archive" class="hover:bg-blue-700 px-3 py-2 rounded">Archive</a>
                        <a href="/?page=collections" class="hover:bg-blue-700 px-3 py-2 rounded">Collections</a>
                        <a href="project_manager.php" class="bg-blue-700 px-3 py-2 rounded">Projects</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleTheme()" class="p-2 rounded hover:bg-blue-700">
                        <i class="fas fa-moon"></i>
                    </button>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="relative group">
                            <button class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                                <?php
                                $stmt = $db->prepare("SELECT username, profile_image FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $user = $stmt->fetch();
                                $username = $user['username'];
                                $profile_image = $user['profile_image'] ?? null;
                                ?>
                                <img src="<?= $profile_image ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($username)).'?d=mp&s=32' ?>" 
                                     class="w-8 h-8 rounded-full" alt="Profile">
                                <span><?= htmlspecialchars($username) ?></span>
                                <i class="fas fa-chevron-down ml-1"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 hidden group-hover:block z-10">
                                <a href="/?page=account" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user mr-2"></i>Account
                                </a>
                                <a href="/?page=settings" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                                <a href="/?page=collections" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-folder mr-2"></i>Collections
                                </a>
                                <a href="project_manager.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700">
                                    <i class="fas fa-folder-tree mr-2"></i>Projects
                                </a>
                                <a href="following.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users mr-2"></i>Following
                                </a>
                                <a href="/?page=import-export" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-exchange-alt mr-2"></i>Import/Export
                                </a>
                                <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                                <a href="/?logout=1" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/?page=login" class="hover:bg-blue-700 px-3 py-2 rounded">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Project List View -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-folder-tree mr-3 text-blue-500"></i>
                        My Projects
                    </h1>
                    <button onclick="showCreateProjectModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>New Project
                    </button>
                </div>

                <?php
                // Get user's projects
                $stmt = $db->prepare("
                    SELECT p.*, 
                           (SELECT COUNT(*) FROM project_files WHERE project_id = p.id) as file_count,
                           (SELECT COUNT(*) FROM project_branches WHERE project_id = p.id) as branch_count,
                           (SELECT COUNT(DISTINCT user_id) FROM project_collaborators WHERE project_id = p.id) + 1 as contributor_count
                    FROM projects p
                    WHERE p.user_id = ?
                    ORDER BY p.updated_at DESC
                ");
                $stmt->execute([$user_id]);
                $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if (empty($projects)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-folder-open text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                        <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No projects yet</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6">
                            Projects help you organize your code into structured repositories
                        </p>
                        <button onclick="showCreateProjectModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors">
                            <i class="fas fa-folder-plus mr-2"></i>
                            Create Your First Project
                        </button>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($projects as $project): ?>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden hover:shadow-md transition-shadow">
                                <div class="p-6">
                                    <div class="flex items-start justify-between mb-2">
                                        <h3 class="text-lg font-semibold">
                                            <a href="project_manager.php?action=view&project_id=<?= $project['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                <?= htmlspecialchars($project['name']) ?>
                                            </a>
                                        </h3>
                                        <span class="px-2 py-1 text-xs rounded-full <?= $project['is_public'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' ?>">
                                            <?= $project['is_public'] ? 'Public' : 'Private' ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                                        <?= htmlspecialchars($project['description'] ?: 'No description') ?>
                                    </p>
                                    
                                    <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                                        <div>
                                            <i class="fas fa-file mr-1"></i>
                                            <?= $project['file_count'] ?> files
                                        </div>
                                        <div>
                                            <i class="fas fa-code-branch mr-1"></i>
                                            <?= $project['branch_count'] ?> branches
                                        </div>
                                        <div>
                                            <i class="fas fa-users mr-1"></i>
                                            <?= $project['contributor_count'] ?> contributors
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-100 dark:bg-gray-600 px-6 py-3 flex justify-between items-center">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-calendar mr-1"></i>
                                        Updated <?= date('M j, Y', $project['updated_at']) ?>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="project_manager.php?action=view&project_id=<?= $project['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                            View Project
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create Project Modal -->
            <div id="createProjectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
                    <h2 class="text-xl font-semibold mb-4">Create New Project</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_project">
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Project Name *</label>
                            <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Description</label>
                            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">License</label>
                            <select name="license_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                                <option value="MIT">MIT License</option>
                                <option value="Apache-2.0">Apache License 2.0</option>
                                <option value="GPL-3.0">GNU GPL v3</option>
                                <option value="BSD-3-Clause">BSD 3-Clause</option>
                                <option value="Unlicense">The Unlicense</option>
                            </select>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_public" checked class="mr-2">
                                <span>Make project public</span>
                            </label>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Create Project
                            </button>
                            <button type="button" onclick="hideCreateProjectModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'view' && $project_id): ?>
            <?php
            // Get project details
            $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                echo '<div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">Project not found</div>';
                echo '<a href="project_manager.php" class="text-blue-500 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Projects</a>';
                exit;
            }
            
            // Check if user has access to this project
            if ($project['user_id'] !== $user_id && !$project['is_public']) {
                echo '<div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">You do not have access to this project</div>';
                echo '<a href="project_manager.php" class="text-blue-500 hover:underline"><i class="fas fa-arrow-left mr-2"></i>Back to Projects</a>';
                exit;
            }
            
            // Get branches
            $stmt = $db->prepare("SELECT * FROM project_branches WHERE project_id = ? ORDER BY name = ? DESC, name ASC");
            $stmt->execute([$project_id, $project['default_branch']]);
            $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get current branch
            $current_branch = null;
            foreach ($branches as $b) {
                if ($b['name'] === $branch) {
                    $current_branch = $b;
                    break;
                }
            }
            
            // If branch not found, use default branch
            if (!$current_branch && !empty($branches)) {
                $current_branch = $branches[0];
                $branch = $current_branch['name'];
            }
            
            // Get files in current branch
            $files = [];
            if ($current_branch) {
                $stmt = $db->prepare("
                    SELECT pf.*, p.title, p.language, p.created_at as file_created,
                           u.username as file_author
                    FROM project_files pf
                    JOIN pastes p ON pf.paste_id = p.id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE pf.project_id = ? AND pf.branch_id = ?
                    ORDER BY pf.file_path, pf.file_name
                ");
                $stmt->execute([$project_id, $current_branch['id']]);
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Get issues
            $stmt = $db->prepare("
                SELECT pi.*, u.username as created_by_username
                FROM project_issues pi
                LEFT JOIN users u ON pi.created_by = u.id
                WHERE pi.project_id = ?
                ORDER BY pi.created_at DESC
            ");
            $stmt->execute([$project_id]);
            $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get milestones
            $stmt = $db->prepare("
                SELECT pm.*, 
                       (SELECT COUNT(*) FROM issue_milestones im JOIN project_issues pi ON im.issue_id = pi.id WHERE im.milestone_id = pm.id) as issue_count,
                       (SELECT COUNT(*) FROM issue_milestones im JOIN project_issues pi ON im.issue_id = pi.id WHERE im.milestone_id = pm.id AND pi.status = 'closed') as completed_issues
                FROM project_milestones pm
                WHERE pm.project_id = ?
                ORDER BY pm.due_date ASC
            ");
            $stmt->execute([$project_id]);
            $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent commits (file changes)
            $stmt = $db->prepare("
                SELECT p.id, p.title, p.created_at, p.last_modified,
                       u.username, u.profile_image,
                       pf.file_name, pf.file_path
                FROM pastes p
                JOIN project_files pf ON p.id = pf.paste_id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE pf.project_id = ?
                ORDER BY COALESCE(p.last_modified, p.created_at) DESC
                LIMIT 10
            ");
            $stmt->execute([$project_id]);
            $recent_commits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <!-- Project Header -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div>
                        <div class="flex items-center">
                            <a href="project_manager.php" class="text-blue-500 hover:underline mr-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-2xl font-bold"><?= htmlspecialchars($project['name']) ?></h1>
                            <span class="ml-3 px-2 py-1 text-xs rounded-full <?= $project['is_public'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' ?>">
                                <?= $project['is_public'] ? 'Public' : 'Private' ?>
                            </span>
                        </div>
                        <?php if ($project['description']): ?>
                            <p class="text-gray-600 dark:text-gray-400 mt-2"><?= htmlspecialchars($project['description']) ?></p>
                        <?php endif; ?>
                        <div class="flex items-center mt-2 text-sm text-gray-500 dark:text-gray-400">
                            <span class="mr-4">
                                <i class="fas fa-code-branch mr-1"></i>
                                <?= count($branches) ?> branches
                            </span>
                            <span class="mr-4">
                                <i class="fas fa-file mr-1"></i>
                                <?= count($files) ?> files
                            </span>
                            <span>
                                <i class="fas fa-calendar mr-1"></i>
                                Created <?= date('M j, Y', $project['created_at']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-2">
                        <?php if ($current_branch): ?>
                            <div class="relative group">
                                <button class="bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-lg text-sm flex items-center">
                                    <i class="fas fa-code-branch mr-2"></i>
                                    <?= htmlspecialchars($current_branch['name']) ?>
                                    <i class="fas fa-chevron-down ml-2"></i>
                                </button>
                                <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 hidden group-hover:block z-10">
                                    <?php foreach ($branches as $b): ?>
                                        <a href="project_manager.php?action=view&project_id=<?= $project_id ?>&tab=<?= $tab ?>&branch=<?= urlencode($b['name']) ?>" 
                                           class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $b['name'] === $branch ? 'bg-gray-100 dark:bg-gray-700' : '' ?>">
                                            <?= htmlspecialchars($b['name']) ?>
                                            <?php if ($b['name'] === $project['default_branch']): ?>
                                                <span class="text-xs text-gray-500">(default)</span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                    <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                                    <a href="#" onclick="showCreateBranchModal(); return false;" class="block px-4 py-2 text-sm text-blue-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-plus mr-2"></i>New Branch
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($project['user_id'] === $user_id): ?>
                            <a href="#" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($current_branch): ?>
                            <a href="project_export.php?project_id=<?= $project_id ?>&branch_id=<?= $current_branch['id'] ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-download mr-2"></i>Export
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Project Tabs -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-4 px-6" aria-label="Tabs">
                        <a href="project_manager.php?action=view&project_id=<?= $project_id ?>&tab=files&branch=<?= urlencode($branch) ?>" 
                           class="py-4 px-2 text-sm font-medium <?= $tab === 'files' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                            <i class="fas fa-file-code mr-2"></i>Files
                        </a>
                        <a href="project_manager.php?action=view&project_id=<?= $project_id ?>&tab=issues" 
                           class="py-4 px-2 text-sm font-medium <?= $tab === 'issues' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                            <i class="fas fa-exclamation-circle mr-2"></i>Issues
                            <?php if (count($issues) > 0): ?>
                                <span class="ml-1 px-2 py-1 text-xs rounded-full bg-gray-200 dark:bg-gray-700"><?= count($issues) ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="project_manager.php?action=view&project_id=<?= $project_id ?>&tab=milestones" 
                           class="py-4 px-2 text-sm font-medium <?= $tab === 'milestones' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                            <i class="fas fa-flag mr-2"></i>Milestones
                        </a>
                        <a href="project_manager.php?action=view&project_id=<?= $project_id ?>&tab=activity" 
                           class="py-4 px-2 text-sm font-medium <?= $tab === 'activity' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                            <i class="fas fa-history mr-2"></i>Activity
                        </a>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    <?php if ($tab === 'files'): ?>
                        <!-- Files Tab -->
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold">Files</h2>
                            <button onclick="showAddFileModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-plus mr-2"></i>Add File
                            </button>
                        </div>

                        <?php if (empty($files)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-file-code text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No files yet</h3>
                                <p class="text-gray-500 dark:text-gray-400 mb-6">
                                    This branch doesn't have any files yet
                                </p>
                                <button onclick="showAddFileModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Your First File
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                                <?php foreach ($files as $file): ?>
                                    <div class="file-tree-item px-4 py-3 border-b border-gray-200 dark:border-gray-600 last:border-b-0">
                                        <a href="project_paste_viewer.php?id=<?= $file['paste_id'] ?>" class="flex items-center hover:text-blue-500">
                                            <i class="fas fa-file-code text-gray-400 mr-3"></i>
                                            <div>
                                                <div class="font-medium">
                                                    <?= htmlspecialchars($file['file_path'] . $file['file_name']) ?>
                                                    <?php if ($file['is_readme']): ?>
                                                        <span class="ml-2 px-2 py-1 text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">README</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?= htmlspecialchars($file['language']) ?> • 
                                                    Updated <?= date('M j, Y', $file['file_created']) ?> • 
                                                    by <?= htmlspecialchars($file['file_author'] ?: 'Unknown') ?>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($tab === 'issues'): ?>
                        <!-- Issues Tab -->
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold">Issues</h2>
                            <button onclick="showCreateIssueModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-plus mr-2"></i>New Issue
                            </button>
                        </div>

                        <?php if (empty($issues)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-exclamation-circle text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No issues yet</h3>
                                <p class="text-gray-500 dark:text-gray-400 mb-6">
                                    Track bugs, feature requests, and tasks with issues
                                </p>
                                <button onclick="showCreateIssueModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Create Your First Issue
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                        <thead class="bg-gray-100 dark:bg-gray-600">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Priority</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Author</th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                            <?php foreach ($issues as $issue): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <a href="project_issue_viewer.php?issue_id=<?= $issue['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                                            <?= htmlspecialchars($issue['title']) ?>
                                                        </a>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            <span class="px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                                <?= ucfirst($issue['label']) ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 text-xs rounded-full <?= $issue['status'] === 'open' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' ?>">
                                                            <?= ucfirst($issue['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-2 py-1 text-xs rounded-full 
                                                            <?php
                                                            switch ($issue['priority']) {
                                                                case 'low':
                                                                    echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                                                    break;
                                                                case 'medium':
                                                                    echo 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                                                                    break;
                                                                case 'high':
                                                                    echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                                                    break;
                                                                case 'critical':
                                                                    echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                                                    break;
                                                                default:
                                                                    echo 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                                            }
                                                            ?>">
                                                            <?= ucfirst($issue['priority']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <?= date('M j, Y', $issue['created_at']) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <?= htmlspecialchars($issue['created_by_username'] ?: 'Unknown') ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <a href="project_issue_viewer.php?issue_id=<?= $issue['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                            View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($tab === 'milestones'): ?>
                        <!-- Milestones Tab -->
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold">Milestones</h2>
                            <button onclick="showCreateMilestoneModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-plus mr-2"></i>New Milestone
                            </button>
                        </div>

                        <?php if (empty($milestones)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-flag text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No milestones yet</h3>
                                <p class="text-gray-500 dark:text-gray-400 mb-6">
                                    Track project progress with milestones
                                </p>
                                <button onclick="showCreateMilestoneModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors">
                                    <i class="fas fa-plus mr-2"></i>
                                    Create Your First Milestone
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($milestones as $milestone): ?>
                                    <?php
                                    $progress = $milestone['issue_count'] > 0 ? round(($milestone['completed_issues'] / $milestone['issue_count']) * 100) : 0;
                                    $is_completed = $milestone['completed_at'] !== null;
                                    $is_overdue = !$is_completed && $milestone['due_date'] && $milestone['due_date'] < time();
                                    ?>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-6">
                                        <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                                            <div>
                                                <h3 class="text-lg font-semibold flex items-center">
                                                    <?= htmlspecialchars($milestone['title']) ?>
                                                    <?php if ($is_completed): ?>
                                                        <span class="ml-2 px-2 py-1 text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full">
                                                            Completed
                                                        </span>
                                                    <?php elseif ($is_overdue): ?>
                                                        <span class="ml-2 px-2 py-1 text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-full">
                                                            Overdue
                                                        </span>
                                                    <?php endif; ?>
                                                </h3>
                                                <?php if ($milestone['description']): ?>
                                                    <p class="text-gray-600 dark:text-gray-400 mt-2"><?= htmlspecialchars($milestone['description']) ?></p>
                                                <?php endif; ?>
                                                <div class="flex items-center mt-2 text-sm text-gray-500 dark:text-gray-400">
                                                    <span class="mr-4">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        <?= $milestone['due_date'] ? date('M j, Y', $milestone['due_date']) : 'No due date' ?>
                                                    </span>
                                                    <span>
                                                        <i class="fas fa-tasks mr-1"></i>
                                                        <?= $milestone['completed_issues'] ?>/<?= $milestone['issue_count'] ?> issues completed
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <a href="#" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                                    View Issues
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                                                <span>Progress</span>
                                                <span><?= $progress ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                                <div class="bg-blue-500 h-2 rounded-full" style="width: <?= $progress ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($tab === 'activity'): ?>
                        <!-- Activity Tab -->
                        <h2 class="text-xl font-semibold mb-6">Recent Activity</h2>

                        <?php if (empty($recent_commits)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-history text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                                <h3 class="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No activity yet</h3>
                                <p class="text-gray-500 dark:text-gray-400">
                                    Activity will appear here as you make changes to the project
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_commits as $commit): ?>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border-l-4 border-blue-500">
                                        <div class="flex items-start space-x-3">
                                            <img src="<?= $commit['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($commit['username'] ?? 'anonymous')).'?d=mp&s=40' ?>" 
                                                 class="w-10 h-10 rounded-full" alt="User">
                                            <div class="flex-1">
                                                <div class="flex justify-between">
                                                    <div>
                                                        <a href="project_paste_viewer.php?id=<?= $commit['id'] ?>" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                                            <?= htmlspecialchars($commit['title']) ?>
                                                        </a>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            <?= htmlspecialchars($commit['file_path'] . $commit['file_name']) ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?= date('M j, Y g:i A', $commit['last_modified'] ?: $commit['created_at']) ?>
                                                    </div>
                                                </div>
                                                <div class="mt-2 text-sm">
                                                    <span class="text-gray-600 dark:text-gray-300">
                                                        by <span class="font-medium"><?= htmlspecialchars($commit['username'] ?: 'Anonymous') ?></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add File Modal -->
            <div id="addFileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full p-6">
                    <h2 class="text-xl font-semibold mb-4">Add New File</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_file">
                        <input type="hidden" name="branch_id" value="<?= $current_branch ? $current_branch['id'] : '' ?>">
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">File Name *</label>
                            <input type="text" name="file_name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700" placeholder="example.js">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">File Path (optional)</label>
                            <input type="text" name="file_path" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700" placeholder="src/components/">
                            <p class="text-xs text-gray-500 mt-1">Leave empty for root directory. Include trailing slash.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Title *</label>
                            <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Content *</label>
                            <textarea name="content" required rows="10" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 font-mono"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Language</label>
                            <select name="language" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                                <option value="plaintext">Plain Text</option>
                                <option value="javascript">JavaScript</option>
                                <option value="python">Python</option>
                                <option value="php">PHP</option>
                                <option value="java">Java</option>
                                <option value="cpp">C++</option>
                                <option value="c">C</option>
                                <option value="csharp">C#</option>
                                <option value="html">HTML</option>
                                <option value="css">CSS</option>
                                <option value="sql">SQL</option>
                                <option value="json">JSON</option>
                                <option value="xml">XML</option>
                                <option value="markdown">Markdown</option>
                                <option value="bash">Bash</option>
                            </select>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_readme" class="mr-2">
                                <span>This is a README file</span>
                            </label>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Add File
                            </button>
                            <button type="button" onclick="hideAddFileModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Create Branch Modal -->
            <div id="createBranchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
                    <h2 class="text-xl font-semibold mb-4">Create New Branch</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_branch">
                        <input type="hidden" name="from_branch_id" value="<?= $current_branch ? $current_branch['id'] : '' ?>">
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Branch Name *</label>
                            <input type="text" name="branch_name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700" placeholder="feature/new-feature">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Description (optional)</label>
                            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Create from</label>
                            <select name="from_branch_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $b['name'] === $branch ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['name']) ?>
                                        <?php if ($b['name'] === $project['default_branch']): ?>
                                            (default)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">The new branch will include all files from the selected branch</p>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Create Branch
                            </button>
                            <button type="button" onclick="hideCreateBranchModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Create Issue Modal -->
            <div id="createIssueModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full p-6">
                    <h2 class="text-xl font-semibold mb-4">Create New Issue</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_issue">
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Issue Title *</label>
                            <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Description</label>
                            <textarea name="description" rows="5" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Priority</label>
                                <select name="priority" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Label</label>
                                <select name="label" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                                    <option value="bug">Bug</option>
                                    <option value="feature">Feature Request</option>
                                    <option value="enhancement">Enhancement</option>
                                    <option value="documentation">Documentation</option>
                                    <option value="help-wanted">Help Wanted</option>
                                    <option value="question">Question</option>
                                    <option value="general" selected>General</option>
                                </select>
                            </div>
                        </div>

                        <?php if (!empty($milestones)): ?>
                            <div>
                                <label class="block text-sm font-medium mb-2">Milestone (optional)</label>
                                <select name="milestone_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                                    <option value="">No milestone</option>
                                    <?php foreach ($milestones as $m): ?>
                                        <?php if (!$m['completed_at']): ?>
                                            <option value="<?= $m['id'] ?>">
                                                <?= htmlspecialchars($m['title']) ?>
                                                <?php if ($m['due_date']): ?>
                                                    (Due: <?= date('M j, Y', $m['due_date']) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Create Issue
                            </button>
                            <button type="button" onclick="hideCreateIssueModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Create Milestone Modal -->
            <div id="createMilestoneModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
                    <h2 class="text-xl font-semibold mb-4">Create New Milestone</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_milestone">
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Milestone Title *</label>
                            <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Description</label>
                            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Due Date (optional)</label>
                            <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Create Milestone
                            </button>
                            <button type="button" onclick="hideCreateMilestoneModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.classList.contains('dark') ? 'light' : 'dark';
            html.classList.remove('dark', 'light');
            html.classList.add(newTheme);
            document.cookie = `theme=${newTheme};path=/`;
        }

        function showCreateProjectModal() {
            document.getElementById('createProjectModal').classList.remove('hidden');
        }

        function hideCreateProjectModal() {
            document.getElementById('createProjectModal').classList.add('hidden');
        }

        function showAddFileModal() {
            document.getElementById('addFileModal').classList.remove('hidden');
        }

        function hideAddFileModal() {
            document.getElementById('addFileModal').classList.add('hidden');
        }

        function showCreateBranchModal() {
            document.getElementById('createBranchModal').classList.remove('hidden');
        }

        function hideCreateBranchModal() {
            document.getElementById('createBranchModal').classList.add('hidden');
        }

        function showCreateIssueModal() {
            document.getElementById('createIssueModal').classList.remove('hidden');
        }

        function hideCreateIssueModal() {
            document.getElementById('createIssueModal').classList.add('hidden');
        }

        function showCreateMilestoneModal() {
            document.getElementById('createMilestoneModal').classList.remove('hidden');
        }

        function hideCreateMilestoneModal() {
            document.getElementById('createMilestoneModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = [
                { element: document.getElementById('createProjectModal'), hide: hideCreateProjectModal },
                { element: document.getElementById('addFileModal'), hide: hideAddFileModal },
                { element: document.getElementById('createBranchModal'), hide: hideCreateBranchModal },
                { element: document.getElementById('createIssueModal'), hide: hideCreateIssueModal },
                { element: document.getElementById('createMilestoneModal'), hide: hideCreateMilestoneModal }
            ];
            
            modals.forEach(modal => {
                if (modal.element && event.target === modal.element) {
                    modal.hide();
                }
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = [
                    { element: document.getElementById('createProjectModal'), hide: hideCreateProjectModal },
                    { element: document.getElementById('addFileModal'), hide: hideAddFileModal },
                    { element: document.getElementById('createBranchModal'), hide: hideCreateBranchModal },
                    { element: document.getElementById('createIssueModal'), hide: hideCreateIssueModal },
                    { element: document.getElementById('createMilestoneModal'), hide: hideCreateMilestoneModal }
                ];
                
                modals.forEach(modal => {
                    if (modal.element && !modal.element.classList.contains('hidden')) {
                        modal.hide();
                    }
                });
            }
        });
    </script>
</body>
</html>