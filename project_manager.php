<?php
// Adding delete branch functionality with UI elements and backend logic.
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /?page=login');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Handle different actions
$action = $_GET['action'] ?? 'list';
$project_id = $_GET['project_id'] ?? null;

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $license = $_POST['license'] ?? 'MIT';
            $is_public = isset($_POST['is_public']) ? 1 : 0;

            if (empty($name)) {
                $error = "Project name is required";
                break;
            }

            try {
                $db->beginTransaction();

                // Create project
                $stmt = $db->prepare("INSERT INTO projects (name, description, license_type, user_id, is_public) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $license, $user_id, $is_public]);
                $project_id = $db->lastInsertId();

                // Create main branch
                $stmt = $db->prepare("INSERT INTO project_branches (project_id, name, description) VALUES (?, 'main', 'Main branch')");
                $stmt->execute([$project_id]);

                $db->commit();
                header("Location: ?action=view&project_id=$project_id");
                exit;

            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to create project: " . $e->getMessage();
            }
        }
        break;

    case 'add_file':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $branch_id = $_POST['branch_id'];
            $file_path = trim($_POST['file_path']);
            $file_name = trim($_POST['file_name']);
            $content = $_POST['content'];
            $language = $_POST['language'] ?? 'plaintext';
            $is_readme = isset($_POST['is_readme']) ? 1 : 0;

            try {
                $db->beginTransaction();

                // Create paste for the file
                $stmt = $db->prepare("INSERT INTO pastes (title, content, language, user_id, project_id, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$file_name, $content, $language, $user_id, $project_id, $branch_id]);
                $paste_id = $db->lastInsertId();

                // Add to project files
                $stmt = $db->prepare("INSERT INTO project_files (project_id, branch_id, paste_id, file_path, file_name, is_readme) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $branch_id, $paste_id, $file_path, $file_name, $is_readme]);

                // Update branch commit tracking
                $commit_hash = md5($project_id . $branch_id . time() . $file_name);
                $stmt = $db->prepare("INSERT INTO branch_commits (branch_id, commit_hash, commit_message, author_id, created_at, file_changes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$branch_id, $commit_hash, "Added file: $file_name", $user_id, time(), 1]);

                // Update branch commit count and last commit time
                $stmt = $db->prepare("UPDATE project_branches SET commit_count = commit_count + 1, last_commit_at = ? WHERE id = ?");
                $stmt->execute([time(), $branch_id]);

                $db->commit();
                header("Location: ?action=view&project_id=$project_id");
                exit;

            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to add file: " . $e->getMessage();
            }
        }
        break;

    case 'create_branch':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $branch_name = trim($_POST['branch_name']);
            $description = trim($_POST['description']);
            $from_branch = $_POST['from_branch'];

            try {
                $db->beginTransaction();

                // Get the source branch commit count to set as base
                $stmt = $db->prepare("SELECT commit_count FROM project_branches WHERE id = ?");
                $stmt->execute([$from_branch]);
                $source_commit_count = $stmt->fetchColumn() ?: 0;

                // Create the new branch
                $stmt = $db->prepare("INSERT INTO project_branches (project_id, name, description, created_from_branch_id, base_commit_count, commit_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $branch_name, $description, $from_branch, $source_commit_count, $source_commit_count, time()]);
                $new_branch_id = $db->lastInsertId();

                // Copy all files from the source branch to the new branch
                $stmt = $db->prepare("
                    SELECT pf.*, p.title, p.content, p.language 
                    FROM project_files pf 
                    JOIN pastes p ON pf.paste_id = p.id 
                    WHERE pf.project_id = ? AND pf.branch_id = ?
                ");
                $stmt->execute([$project_id, $from_branch]);
                $source_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Create new paste records and project_files entries for each file
                foreach ($source_files as $file) {
                    // Create a new paste record for the file
                    $stmt = $db->prepare("INSERT INTO pastes (title, content, language, user_id, project_id, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $file['title'], 
                        $file['content'], 
                        $file['language'], 
                        $user_id, 
                        $project_id, 
                        $new_branch_id
                    ]);
                    $new_paste_id = $db->lastInsertId();

                    // Create project_files entry for the new branch
                    $stmt = $db->prepare("INSERT INTO project_files (project_id, branch_id, paste_id, file_path, file_name, is_readme) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $project_id,
                        $new_branch_id,
                        $new_paste_id,
                        $file['file_path'],
                        $file['file_name'],
                        $file['is_readme']
                    ]);
                }

                $db->commit();
                header("Location: ?action=view&project_id=$project_id&branch=" . urlencode($branch_name));
                exit;

            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to create branch: " . $e->getMessage();
            }
        }
        break;

    case 'delete_branch':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $branch_id = $_POST['branch_id'];

            try {
                $db->beginTransaction();

                // Delete project files associated with the branch
                $stmt = $db->prepare("DELETE FROM project_files WHERE project_id = ? AND branch_id = ?");
                $stmt->execute([$project_id, $branch_id]);

                // Delete pastes associated with the branch
                $stmt = $db->prepare("DELETE FROM pastes WHERE project_id = ? AND branch_id = ?");
                $stmt->execute([$project_id, $branch_id]);

                // Delete the branch
                $stmt = $db->prepare("DELETE FROM project_branches WHERE project_id = ? AND id = ?");
                $stmt->execute([$project_id, $branch_id]);

                $db->commit();
                header("Location: ?action=view&project_id=$project_id");
                exit;

            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to delete branch: " . $e->getMessage();
            }
        }
        break;

    case 'add_issue':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $priority = $_POST['priority'];
            $label = $_POST['label'];
            $milestone_id = !empty($_POST['milestone_id']) ? $_POST['milestone_id'] : null;

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("INSERT INTO project_issues (project_id, title, description, priority, label, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $title, $description, $priority, $label, $user_id]);
                $issue_id = $db->lastInsertId();

                // Link to milestone if selected
                if ($milestone_id) {
                    $stmt = $db->prepare("INSERT INTO issue_milestones (issue_id, milestone_id) VALUES (?, ?)");
                    $stmt->execute([$issue_id, $milestone_id]);
                }

                $db->commit();
                header("Location: ?action=view&project_id=$project_id&tab=issues");
                exit;

            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to add issue: " . $e->getMessage();
            }
        }
        break;

    case 'add_milestone':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $due_date = !empty($_POST['due_date']) ? strtotime($_POST['due_date']) : null;

            try {
                $stmt = $db->prepare("INSERT INTO project_milestones (project_id, title, description, due_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$project_id, $title, $description, $due_date]);

                header("Location: ?action=view&project_id=$project_id&tab=milestones");
                exit;

            } catch (PDOException $e) {
                $error = "Failed to add milestone: " . $e->getMessage();
            }
        }
        break;

    case 'complete_milestone':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $milestone_id = $_POST['milestone_id'];

            try {
                $stmt = $db->prepare("UPDATE project_milestones SET completed_at = strftime('%s', 'now') WHERE id = ? AND project_id = ?");
                $stmt->execute([$milestone_id, $project_id]);

                header("Location: ?action=view&project_id=$project_id&tab=milestones");
                exit;

            } catch (PDOException $e) {
                $error = "Failed to complete milestone: " . $e->getMessage();
            }
        }
        break;

    case 'delete_milestone':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $milestone_id = $_POST['milestone_id'];

            try {
                $db->beginTransaction();

                // Remove issue-milestone relationships
                $stmt = $db->prepare("DELETE FROM issue_milestones WHERE milestone_id = ?");
                $stmt->execute([$milestone_id]);

                // Delete the milestone
                $stmt = $db->prepare("DELETE FROM project_milestones WHERE id = ? AND project_id = ?");
                $stmt->execute([$milestone_id, $project_id]);

                $db->commit();
                header("Location: ?action=view&project_id=$project_id&tab=milestones");
                exit;

            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to delete milestone: " . $e->getMessage();
            }
        }
        break;

    case 'edit_project':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $license = $_POST['license'] ?? 'MIT';
            $is_public = isset($_POST['is_public']) ? 1 : 0;

            if (empty($name)) {
                $error = "Project name is required";
                break;
            }

            try {
                $stmt = $db->prepare("UPDATE projects SET name = ?, description = ?, license_type = ?, is_public = ?, updated_at = strftime('%s', 'now') WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $description, $license, $is_public, $project_id, $user_id]);

                header("Location: ?action=view&project_id=$project_id");
                exit;

            } catch (PDOException $e) {
                $error = "Failed to update project: " . $e->getMessage();
            }
        }
        break;

    case 'delete_project':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $project_id) {
            try {
                $db->beginTransaction();

                // Delete all project issue comments
                $stmt = $db->prepare("DELETE FROM project_issue_comments WHERE issue_id IN (SELECT id FROM project_issues WHERE project_id = ?)");
                $stmt->execute([$project_id]);

                // Delete all project issues
                $stmt = $db->prepare("DELETE FROM project_issues WHERE project_id = ?");
                $stmt->execute([$project_id]);

                // Delete all project files
                $stmt = $db->prepare("DELETE FROM project_files WHERE project_id = ?");
                $stmt->execute([$project_id]);

                // Delete all project branches
                $stmt = $db->prepare("DELETE FROM project_branches WHERE project_id = ?");
                $stmt->execute([$project_id]);

                // Delete the project
                $stmt = $db->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
                $stmt->execute([$project_id, $user_id]);

                $db->commit();
                header("Location: ?action=list");
                exit;

            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to delete project: " . $e->getMessage();
            }
        }
        break;
}

// Get project data if viewing
$project = null;
$branches = [];
$files = [];
$issues = [];
$milestones = [];

if ($project_id && $action === 'view') {
    // Get project
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND (user_id = ? OR is_public = 1)");
    $stmt->execute([$project_id, $user_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header('Location: ?action=list');
        exit;
    }

    // Get branches with commit tracking
    $stmt = $db->prepare("SELECT * FROM project_branches WHERE project_id = ? ORDER BY name");
    $stmt->execute([$project_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get main branch for comparison
    $main_branch = null;
    foreach ($branches as $branch) {
        if ($branch['name'] === 'main') {
            $main_branch = $branch;
            break;
        }
    }

    // Calculate branch relationships
    $branch_status = [];
    foreach ($branches as $branch) {
        if ($branch['name'] === 'main') {
            $branch_status[$branch['id']] = ['status' => 'main', 'ahead' => 0, 'behind' => 0];
        } else if ($main_branch) {
            // Get actual user commits made on this branch (excluding the initial copied files)
            $stmt = $db->prepare("SELECT COUNT(*) FROM branch_commits WHERE branch_id = ? AND commit_message NOT LIKE 'Copied from%'");
            $stmt->execute([$branch['id']]);
            $actual_commits = $stmt->fetchColumn() ?: 0;
            
            // Get actual commits made on main branch since this branch was created
            $branch_created_at = $branch['created_at'] ?? 0;
            $stmt = $db->prepare("SELECT COUNT(*) FROM branch_commits WHERE branch_id = ? AND created_at > ? AND commit_message NOT LIKE 'Copied from%'");
            $stmt->execute([$main_branch['id'], $branch_created_at]);
            $main_commits_since = $stmt->fetchColumn() ?: 0;
            
            // If no created_at timestamp, fall back to comparing file counts
            if ($branch_created_at == 0) {
                // For branches created before we tracked timestamps
                $base_count = $branch['base_commit_count'] ?? 0;
                $current_main_commits = $main_branch['commit_count'] ?? 0;
                $current_branch_commits = $branch['commit_count'] ?? 0;
                
                $actual_commits = max(0, $current_branch_commits - $base_count);
                $main_commits_since = max(0, $current_main_commits - $base_count);
            }
            
            if ($actual_commits == 0 && $main_commits_since == 0) {
                $branch_status[$branch['id']] = ['status' => 'up_to_date', 'ahead' => 0, 'behind' => 0];
            } else if ($actual_commits > 0 && $main_commits_since == 0) {
                $branch_status[$branch['id']] = ['status' => 'ahead', 'ahead' => $actual_commits, 'behind' => 0];
            } else if ($actual_commits == 0 && $main_commits_since > 0) {
                $branch_status[$branch['id']] = ['status' => 'behind', 'ahead' => 0, 'behind' => $main_commits_since];
            } else {
                $branch_status[$branch['id']] = ['status' => 'diverged', 'ahead' => $actual_commits, 'behind' => $main_commits_since];
            }
        } else {
            $branch_status[$branch['id']] = ['status' => 'unknown', 'ahead' => 0, 'behind' => 0];
        }
    }

    // Get files for current branch
    $current_branch = $_GET['branch'] ?? 'main';
    $stmt = $db->prepare("
        SELECT pf.*, p.content, p.language, p.views 
        FROM project_files pf 
        JOIN pastes p ON pf.paste_id = p.id 
        JOIN project_branches pb ON pf.branch_id = pb.id 
        WHERE pf.project_id = ? AND pb.name = ? 
        ORDER BY pf.file_path, pf.file_name
    ");
    $stmt->execute([$project_id, $current_branch]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get issues
    $stmt = $db->prepare("
        SELECT pi.*, u.username as created_by_username 
        FROM project_issues pi 
        JOIN users u ON pi.created_by = u.id 
        WHERE pi.project_id = ? 
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute([$project_id]);
    $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get milestones
    $stmt = $db->prepare("SELECT * FROM project_milestones WHERE project_id = ? ORDER BY due_date");
    $stmt->execute([$project_id]);
    $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's projects for listing
$projects = [];
if ($action === 'list') {
    $stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>

<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Project Manager - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.classList.contains('dark') ? 'light' : 'dark';
            html.classList.remove('dark', 'light');
            html.classList.add(newTheme);
            document.cookie = `theme=${newTheme};path=/`;
        }
    </script>
    <style>
        .file-tree-item {
            transition: background-color 0.2s;
        }
        .file-tree-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
        .commit-item {
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .commit-item:hover {
            border-left-color: #3b82f6;
            background-color: rgba(59, 130, 246, 0.05);
        }
        .comment-thread {
            position: relative;
        }
        .comment-thread::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 60px;
            bottom: -10px;
            width: 2px;
            background: #e5e7eb;
        }
        .dark .comment-thread::before {
            background: #374151;
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .prose {
            color: inherit;
            max-width: none;
        }
        .prose p {
            margin: 0;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white">

<!-- Navigation -->
<nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg fixed w-full z-10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-6">
                <a href="/" class="flex items-center space-x-3">
                    <i class="fas fa-paste text-2xl"></i>
                    <span class="text-xl font-bold">PasteForge</span>
                </a>
                <div class="flex space-x-4">
                    <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">Home</a>
                    <a href="?page=archive" class="hover:bg-blue-700 px-3 py-2 rounded">Archive</a>
                    <?php if ($user_id): ?>
                        <a href="?page=collections" class="hover:bg-blue-700 px-3 py-2 rounded">Collections</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <?php if ($user_id): ?>
                    <!-- Notification Bell -->
                    <a href="notifications.php" class="relative p-2 rounded hover:bg-blue-700 transition-colors">
                        <i class="fas fa-bell text-lg"></i>
                        <?php
                        // Get unread notification count for navigation
                        $stmt = $db->prepare("SELECT COUNT(*) FROM comment_notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->execute([$user_id]);
                        $nav_unread_notifications = $stmt->fetchColumn();
                        if ($nav_unread_notifications > 0):
                        ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] animate-pulse">
                                <?= $nav_unread_notifications > 99 ? '99+' : $nav_unread_notifications ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <button onclick="toggleTheme()" class="p-2 rounded hover:bg-blue-700">
                    <i class="fas fa-moon"></i>
                </button>
                <?php if (!$user_id): ?>
                    <div class="flex items-center space-x-2">
                        <a href="?page=login" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login</span>
                        </a>
                        <a href="?page=signup" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                            <i class="fas fa-user-plus"></i>
                            <span>Sign Up</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                            <?php
                            $stmt = $db->prepare("SELECT username, profile_image FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                            $username = $user_data['username'] ?? 'Unknown';
                            $user_avatar = $user_data['profile_image'];
                            ?>
                            <img src="<?= $user_avatar ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($username)).'?d=mp&s=32' ?>" 
                                 class="w-8 h-8 rounded-full" alt="Profile">
                            <span><?= htmlspecialchars($username) ?></span>
                            <i class="fas fa-chevron-down ml-1"></i>
                        </button>
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                            <div class="py-1">
                                <!-- Account Group -->
                                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Account</div>
                                <a href="?page=edit-profile" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-edit mr-2"></i> Edit Profile
                                </a>
                                <a href="?page=profile&username=<?= urlencode($username) ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user mr-2"></i> View Profile
                                </a>
                                <a href="?page=account" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-crown mr-2"></i> Account
                                </a>
                                <a href="?page=settings" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-cog mr-2"></i> Edit Settings
                                </a>

                                <hr class="my-1 border-gray-200 dark:border-gray-700">

                                <!-- Messages Group -->
                                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Messages</div>
                                <a href="threaded_messages.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-envelope mr-2"></i> My Messages
                                </a>

                                <hr class="my-1 border-gray-200 dark:border-gray-700">

                                <!-- Tools Group -->
                                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tools</div>
                                <a href="project_manager.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 bg-blue-50 dark:bg-blue-900/20">
                                    <i class="fas fa-folder-tree mr-2"></i> Projects
                                </a>
                                <a href="following.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users mr-2"></i> Following
                                </a>
                                <a href="?page=import-export" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-exchange-alt mr-2"></i> Import/Export
                                </a>

                                <hr class="my-1 border-gray-200 dark:border-gray-700">

                                <a href="?logout=1" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto py-8 px-4 pt-24">
    <?php if (isset($error)): ?>
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <!-- Project List -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">My Projects</h1>
            <a href="?action=create" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                <i class="fas fa-plus mr-2"></i>New Project
            </a>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($projects as $proj): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold mb-2">
                        <a href="?action=view&project_id=<?= $proj['id'] ?>" class="text-blue-500 hover:text-blue-700">
                            <?= htmlspecialchars($proj['name']) ?>
                        </a>
                    </h3>
                    <?php if ($proj['description']): ?>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            <?= htmlspecialchars(substr($proj['description'], 0, 100)) ?><?= strlen($proj['description']) > 100 ? '...' : '' ?>
                        </p>
                    <?php endif; ?>
                    <div class="flex justify-between items-center text-sm text-gray-500">
                        <span><i class="fas fa-code-branch mr-1"></i>License: <?= $proj['license_type'] ?></span>
                        <span><?= $proj['is_public'] ? 'Public' : 'Private' ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif ($action === 'create'): ?>
        <!-- Create Project Form -->
        <h1 class="text-3xl font-bold mb-6">Create New Project</h1>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium mb-2">Project Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea name="description" rows="4" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">License</label>
                    <select name="license" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="MIT">MIT License</option>
                        <option value="GPL-3.0">GPL-3.0 License</option>
                        <option value="Apache-2.0">Apache 2.0 License</option>
                        <option value="BSD-3-Clause">BSD 3-Clause License</option>
                        <option value="ISC">ISC License</option>
                        <option value="Unlicense">Unlicense</option>
                        <option value="Custom">Custom License</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="is_public" checked class="rounded">
                        <span>Make project public</span>
                    </label>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-save mr-2"></i>Create Project
                    </button>
                    <a href="?action=list" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

    <?php elseif ($action === 'view' && $project): ?>
        <!-- Project View -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-3xl font-bold"><?= htmlspecialchars($project['name']) ?></h1>
                <?php if ($project['description']): ?>
                    <p class="text-gray-600 dark:text-gray-400 mt-2"><?= htmlspecialchars($project['description']) ?></p>
                <?php endif; ?>
                <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                    <span><i class="fas fa-certificate mr-1"></i><?= $project['license_type'] ?></span>
                    <span><i class="fas fa-globe mr-1"></i><?= $project['is_public'] ? 'Public' : 'Private' ?></span>
                </div>
            </div>

            <?php if ($project['user_id'] === $user_id): ?>
                <div class="flex space-x-2">
                    <button onclick="showAddFileModal()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        <i class="fas fa-plus mr-2"></i>Add File
                    </button>
                    <button onclick="showCreateBranchModal()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                        <i class="fas fa-code-branch mr-2"></i>New Branch
                    </button>
                    <button onclick="showEditProjectModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        <i class="fas fa-edit mr-2"></i>Edit Project
                    </button>
                    <button onclick="exportCurrentBranch()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                        <i class="fas fa-file-archive mr-2"></i>Export Branch
                    </button>
                    <button onclick="confirmDeleteProject()" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        <i class="fas fa-trash mr-2"></i>Delete Project
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <nav class="flex space-x-8">
                <button onclick="showTab('files')" id="tab-files" class="tab-button border-b-2 border-blue-500 text-blue-600 dark:text-blue-400 py-4 px-1 font-medium">
                    <i class="fas fa-folder mr-2"></i>Files
                </button>
                <button onclick="showTab('branches')" id="tab-branches" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-4 px-1 font-medium">
                    <i class="fas fa-code-branch mr-2"></i>Branches (<?= count($branches) ?>)
                </button>
                <button onclick="showTab('issues')" id="tab-issues" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-4 px-1 font-medium">
                    <i class="fas fa-exclamation-circle mr-2"></i>Issues (<?= count($issues) ?>)
                </button>
                <button onclick="showTab('milestones')" id="tab-milestones" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 py-4 px-1 font-medium">
                    <i class="fas fa-flag mr-2"></i>Milestones
                </button>
            </nav>
        </div>

        <!-- Files Tab -->
        <div id="files-tab" class="tab-content">
            <!-- Current Branch Info -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <h3 class="font-semibold flex items-center">
                                <i class="fas fa-folder mr-2 text-blue-500"></i>
                                Project Files
                            </h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">Branch:</span>
                                <select onchange="switchBranch(this.value)" class="px-3 py-1 rounded border dark:bg-gray-700 text-sm">
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['name'] ?>" <?= ($branch['name'] === ($_GET['branch'] ?? 'main')) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($branch['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php if ($project['user_id'] === $user_id): ?>
                            <button onclick="showAddFileModal()" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                                <i class="fas fa-plus mr-1"></i>Add File
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- File Tree -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                <div class="border-b dark:border-gray-700 p-4">
                    <h3 class="font-semibold">Project Files</h3>
                </div>

                <?php if (empty($files)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-folder-open text-4xl mb-4"></i>
                        <p>No files in this branch yet.</p>
                        <?php if ($project['user_id'] === $user_id): ?>
                            <button onclick="showAddFileModal()" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Add First File
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="divide-y dark:divide-gray-700">
                        <?php foreach ($files as $file): ?>
                            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-file-code text-blue-500"></i>
                                        <div>
                                            <a href="project_paste_viewer.php?id=<?= $file['paste_id'] ?>" class="font-medium text-blue-500 hover:text-blue-700">
                                                <?= htmlspecialchars($file['file_path'] . $file['file_name']) ?>
                                            </a>
                                            <?php if ($file['is_readme']): ?>
                                                <span class="ml-2 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded">README</span>
                                            <?php endif; ?>
                                            <div class="text-sm text-gray-500"><?= $file['language'] ?></div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= $file['views'] ?> views
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Branches Tab -->
        <div id="branches-tab" class="tab-content hidden">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold flex items-center">
                            <i class="fas fa-code-branch mr-2 text-purple-500"></i>
                            Branch Management
                        </h3>
                        <?php if ($project['user_id'] === $user_id): ?>
                            <button onclick="showCreateBranchModal()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                                <i class="fas fa-plus mr-2"></i>New Branch
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-6">
                    <!-- Current Branch Info -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-code-branch text-blue-500 text-lg"></i>
                            <div>
                                <h4 class="font-medium text-blue-800 dark:text-blue-200">
                                    Current Branch: <?= htmlspecialchars($_GET['branch'] ?? 'main') ?>
                                </h4>
                                <p class="text-sm text-blue-600 dark:text-blue-300">
                                    You are currently viewing files from this branch
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Branch List -->
                    <div class="space-y-3">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">All Branches</h4>
                        <?php foreach ($branches as $branch): ?>
                            <?php $status = $branch_status[$branch['id']] ?? ['status' => 'unknown', 'ahead' => 0, 'behind' => 0]; ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-code-branch text-purple-500"></i>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium <?= $branch['name'] === ($_GET['branch'] ?? 'main') ? 'text-purple-600 dark:text-purple-400' : 'text-gray-900 dark:text-white' ?>">
                                                <?= htmlspecialchars($branch['name']) ?>
                                            </span>
                                            <?php if ($branch['name'] === ($_GET['branch'] ?? 'main')): ?>
                                                <span class="text-xs bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 px-2 py-1 rounded-full">current</span>
                                            <?php endif; ?>
                                            <?php if ($branch['name'] === 'main'): ?>
                                                <span class="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-1 rounded-full">default</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($branch['description']): ?>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                                <?= htmlspecialchars($branch['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Branch Status -->
                                        <div class="flex items-center space-x-4 mt-2">
                                            <div class="text-xs text-gray-400">
                                                <?php
                                                // Get file count for this branch
                                                $file_count_stmt = $db->prepare("SELECT COUNT(*) FROM project_files WHERE project_id = ? AND branch_id = ?");
                                                $file_count_stmt->execute([$project_id, $branch['id']]);
                                                $file_count = $file_count_stmt->fetchColumn();
                                                ?>
                                                <?= $file_count ?> files • <?= $branch['commit_count'] ?> commits
                                            </div>
                                            
                                            <?php if ($status['status'] !== 'main'): ?>
                                                <div class="text-xs">
                                                    <?php if ($status['status'] === 'up_to_date'): ?>
                                                        <span class="text-green-600 dark:text-green-400">
                                                            <i class="fas fa-check mr-1"></i>Up to date with main
                                                        </span>
                                                    <?php elseif ($status['status'] === 'ahead'): ?>
                                                        <span class="text-blue-600 dark:text-blue-400">
                                                            <i class="fas fa-arrow-up mr-1"></i><?= $status['ahead'] ?> commit<?= $status['ahead'] > 1 ? 's' : '' ?> ahead of main
                                                        </span>
                                                    <?php elseif ($status['status'] === 'behind'): ?>
                                                        <span class="text-orange-600 dark:text-orange-400">
                                                            <i class="fas fa-arrow-down mr-1"></i><?= $status['behind'] ?> commit<?= $status['behind'] > 1 ? 's' : '' ?> behind main
                                                        </span>
                                                    <?php elseif ($status['status'] === 'diverged'): ?>
                                                        <span class="text-yellow-600 dark:text-yellow-400">
                                                            <i class="fas fa-code-branch mr-1"></i><?= $status['ahead'] ?> ahead, <?= $status['behind'] ?> behind main
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if ($branch['name'] !== ($_GET['branch'] ?? 'main')): ?>
                                        <button onclick="switchBranch('<?= htmlspecialchars($branch['name']) ?>')" 
                                                class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600" title="Switch to this branch">
                                            <i class="fas fa-exchange-alt mr-1"></i>Switch
                                        </button>
                                    <?php endif; ?>
                                    <a href="project_export.php?project_id=<?= $project_id ?>&branch_id=<?= $branch['id'] ?>" 
                                       class="bg-purple-500 text-white px-3 py-1 rounded text-sm hover:bg-purple-600" title="Export this branch">
                                        <i class="fas fa-file-archive mr-1"></i>Export
                                    </a>
                                    <?php if ($branch['name'] !== 'main' && $project['user_id'] === $user_id): ?>
                                        <button onclick="confirmDeleteBranch(<?= $branch['id'] ?>, '<?= htmlspecialchars($branch['name']) ?>')" 
                                                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600" title="Delete branch">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($branches) === 1): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-code-branch text-4xl mb-4 text-gray-300"></i>
                            <p class="mb-4">Only the main branch exists.</p>
                            <?php if ($project['user_id'] === $user_id): ?>
                                <button onclick="showCreateBranchModal()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                                    <i class="fas fa-plus mr-2"></i>Create Your First Branch
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Issues Tab -->
        <div id="issues-tab" class="tab-content hidden">
            <?php if ($project['user_id'] === $user_id): ?>
                <div class="mb-6">
                    <button onclick="showAddIssueModal()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        <i class="fas fa-plus mr-2"></i>New Issue
                    </button>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <?php if (empty($issues)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center text-gray-500">
                        <i class="fas fa-check-circle text-4xl mb-4"></i>
                        <p>No issues yet. Great job!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($issues as $issue): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 mt-1">
                                            <div class="w-4 h-4 rounded-full <?= $issue['status'] === 'open' ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-lg">
                                                <a href="project_issue_viewer.php?issue_id=<?= $issue['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                    <?= htmlspecialchars($issue['title']) ?>
                                                </a>
                                            </h4>
                                            <?php if ($issue['description']): ?>
                                                <p class="text-gray-600 dark:text-gray-400 mt-2 line-clamp-2">
                                                    <?= htmlspecialchars(substr($issue['description'], 0, 150)) ?><?= strlen($issue['description']) > 150 ? '...' : '' ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="flex items-center space-x-4 mt-4 text-sm">
                                                <span class="text-gray-500">#<?= $issue['id'] ?></span>
                                                <span class="text-gray-500">opened by 
                                                    <a href="?page=profile&username=<?= urlencode($issue['created_by_username']) ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                        <?= htmlspecialchars($issue['created_by_username']) ?>
                                                    </a>
                                                </span>
                                                <span class="text-gray-500"><?= date('M j, Y', $issue['created_at']) ?></span>
                                                <?php if ($issue['updated_at'] && $issue['updated_at'] != $issue['created_at']): ?>
                                                    <span class="text-gray-500">• updated <?= date('M j, Y', $issue['updated_at']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2 items-end">
                                    <div class="flex space-x-2">
                                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded">
                                            <?= ucfirst($issue['label']) ?>
                                        </span>
                                        <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 text-xs rounded">
                                            <?= ucfirst($issue['priority']) ?>
                                        </span>
                                    </div>
                                    <?php
                                    // Get comment count for this issue
                                    $comment_stmt = $db->prepare("SELECT COUNT(*) FROM project_issue_comments WHERE issue_id = ? AND is_deleted = 0");
                                    $comment_stmt->execute([$issue['id']]);
                                    $comment_count = $comment_stmt->fetchColumn();
                                    ?>
                                    <?php if ($comment_count > 0): ?>
                                        <div class="text-xs text-gray-500 flex items-center">
                                            <i class="fas fa-comments mr-1"></i>
                                            <?= $comment_count ?> comments
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Milestones Tab -->
        <div id="milestones-tab" class="tab-content hidden">
            <?php if ($project['user_id'] === $user_id): ?>
                <div class="mb-6">
                    <button onclick="showAddMilestoneModal()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        <i class="fas fa-plus mr-2"></i>New Milestone
                    </button>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <?php if (empty($milestones)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center text-gray-500">
                        <i class="fas fa-flag text-4xl mb-4"></i>
                        <p>No milestones created yet.</p>
                        <?php if ($project['user_id'] === $user_id): ?>
                            <button onclick="showAddMilestoneModal()" class="mt-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                Create First Milestone
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($milestones as $milestone): ?>
                        <?php
                        // Get issues linked to this milestone
                        $milestone_issues_stmt = $db->prepare("
                            SELECT pi.*, im.milestone_id
                            FROM project_issues pi
                            JOIN issue_milestones im ON pi.id = im.issue_id
                            WHERE im.milestone_id = ?
                        ");
                        $milestone_issues_stmt->execute([$milestone['id']]);
                        $milestone_issues = $milestone_issues_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $total_issues = count($milestone_issues);
                        $completed_issues = array_filter($milestone_issues, function($issue) {
                            return $issue['status'] === 'closed';
                        });
                        $completed_count = count($completed_issues);
                        $progress = $total_issues > 0 ? ($completed_count / $total_issues) * 100 : 0;
                        ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <?php if ($milestone['completed_at']): ?>
                                            <i class="fas fa-check-circle text-green-500 text-lg"></i>
                                        <?php else: ?>
                                            <i class="fas fa-flag text-blue-500 text-lg"></i>
                                        <?php endif; ?>
                                        <h4 class="font-semibold text-lg <?= $milestone['completed_at'] ? 'line-through text-gray-500' : '' ?>">
                                            <?= htmlspecialchars($milestone['title']) ?>
                                        </h4>
                                        <?php if ($milestone['completed_at']): ?>
                                            <span class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded-full">
                                                Completed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($milestone['description']): ?>
                                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                                            <?= htmlspecialchars($milestone['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Progress Bar -->
                                    <div class="mb-4">
                                        <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                                            <span><?= $completed_count ?> of <?= $total_issues ?> issues completed</span>
                                            <span><?= round($progress) ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: <?= $progress ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                                        <span>Created <?= date('M j, Y', $milestone['created_at']) ?></span>
                                        <?php if ($milestone['due_date']): ?>
                                            <?php 
                                            $is_overdue = !$milestone['completed_at'] && $milestone['due_date'] < time();
                                            ?>
                                            <span class="<?= $is_overdue ? 'text-red-600 dark:text-red-400' : '' ?>">
                                                • Due <?= date('M j, Y', $milestone['due_date']) ?>
                                                <?= $is_overdue ? ' (Overdue)' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($milestone['completed_at']): ?>
                                            <span>• Completed <?= date('M j, Y', $milestone['completed_at']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Linked Issues Preview -->
                                    <?php if (!empty($milestone_issues)): ?>
                                        <div class="mt-4">
                                            <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Linked Issues (<?= count($milestone_issues) ?>)
                                            </h5>
                                            <div class="space-y-1">
                                                <?php foreach (array_slice($milestone_issues, 0, 3) as $issue): ?>
                                                    <div class="flex items-center space-x-2 text-sm">
                                                        <div class="w-2 h-2 rounded-full <?= $issue['status'] === 'open' ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                                        <a href="project_issue_viewer.php?issue_id=<?= $issue['id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                            <?= htmlspecialchars($issue['title']) ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php if (count($milestone_issues) > 3): ?>
                                                    <div class="text-sm text-gray-500">
                                                        + <?= count($milestone_issues) - 3 ?> more issues
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($project['user_id'] === $user_id): ?>
                                    <div class="flex flex-col space-y-2">
                                        <?php if (!$milestone['completed_at']): ?>
                                            <form method="POST" action="?action=complete_milestone&project_id=<?= $project_id ?>" class="inline">
                                                <input type="hidden" name="milestone_id" value="<?= $milestone['id'] ?>">
                                                <button type="submit" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600" title="Mark as completed">
                                                    <i class="fas fa-check mr-1"></i>Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button onclick="confirmDeleteMilestone(<?= $milestone['id'] ?>, '<?= htmlspecialchars($milestone['title']) ?>')" 
                                                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600" title="Delete milestone">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Modals -->
<!-- Add File Modal -->
<div id="addFileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full max-h-[90vh] flex flex-col">
<form method="POST" action="?action=add_file&project_id=<?= $project_id ?>" class="space-y-4">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-semibold">Add File to Project</h3>
                </div>
                <div class="flex-1 overflow-y-auto p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Branch</label>
                        <select name="branch_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">File Path</label>
                        <input type="text" name="file_path" placeholder="src/" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <div class="text-sm text-gray-500 mt-1">Optional: folder path ending with /</div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">File Name *</label>
                        <input type="text" name="file_name" required placeholder="main.py" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Language</label>
                        <select name="language" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                            <option value="plaintext">Plain Text</option>
                            <option value="python">Python</option>
                            <option value="javascript">JavaScript</option>
                            <option value="html">HTML</option>
                            <option value="css">CSS</option>
                            <option value="php">PHP</option>
                            <option value="java">Java</option>
                            <option value="cpp">C++</option>
                            <option value="c">C</option>
                            <option value="sql">SQL</option>
                            <option value="json">JSON</option>
                            <option value="xml">XML</option>
                            <option value="markdown">Markdown</option>
                        </select>
                    </div>

                    <div>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="is_readme" class="rounded">
                            <span>This is a README file</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">File Content *</label>
                        <textarea name="content" required rows="15" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 font-mono text-sm"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 p-6 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="hideAddFileModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        <i class="fas fa-save mr-2"></i>Add File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Branch Modal -->
<div id="createBranchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-4">Create New Branch</h3>
            <form method="POST" action="?action=create_branch&project_id=<?= $project_id ?>" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Branch Name *</label>
                    <input type="text" name="branch_name" required placeholder="feature-branch" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <input type="text" name="description" placeholder="Brief description of this branch" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Create from Branch</label>
                    <select name="from_branch" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" class="bg-purple-500 text-white px-6 py-2 rounded hover:bg-purple-600">
                        Create Branch
                    </button>
                    <button type="button" onclick="hideCreateBranchModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div id="editProjectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-4">Edit Project</h3>
            <form method="POST" action="?action=edit_project&project_id=<?= $project_id ?>" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Project Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($project['name'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">License</label>
                    <select name="license" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <option value="MIT" <?= ($project['license_type'] ?? '') === 'MIT' ? 'selected' : '' ?>>MIT License</option>
                        <option value="GPL-3.0" <?= ($project['license_type'] ?? '') === 'GPL-3.0' ? 'selected' : '' ?>>GPL-3.0 License</option>
                        <option value="Apache-2.0" <?= ($project['license_type'] ?? '') === 'Apache-2.0' ? 'selected' : '' ?>>Apache 2.0 License</option>
                        <option value="BSD-3-Clause" <?= ($project['license_type'] ?? '') === 'BSD-3-Clause' ? 'selected' : '' ?>>BSD 3-Clause License</option>
                        <option value="ISC" <?= ($project['license_type'] ?? '') === 'ISC' ? 'selected' : '' ?>>ISC License</option>
                        <option value="Unlicense" <?= ($project['license_type'] ?? '') === 'Unlicense' ? 'selected' : '' ?>>Unlicense</option>
                        <option value="Custom" <?= ($project['license_type'] ?? '') === 'Custom' ? 'selected' : '' ?>>Custom License</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="is_public" <?= ($project['is_public'] ?? 0) ? 'checked' : '' ?> class="rounded">
                        <span>Make project public</span>
                    </label>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                        Update Project
                    </button>
                    <button type="button" onclick="hideEditProjectModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Issue Modal -->
<div id="addIssueModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-4">Create New Issue</h3>
            <form method="POST" action="?action=add_issue&project_id=<?= $project_id ?>" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Issue Title *</label>
                    <input type="text" name="title" required placeholder="Brief description of the issue" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea name="description" rows="4" placeholder="Detailed description of the issue..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"></textarea>
                </div>

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
                    </select>
                </div>

                <?php if (!empty($milestones)): ?>
                    <div>
                        <label class="block text-sm font-medium mb-2">Milestone (optional)</label>
                        <select name="milestone_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                            <option value="">No milestone</option>
                            <?php foreach ($milestones as $milestone): ?>
                                <?php if (!$milestone['completed_at']): ?>
                                    <option value="<?= $milestone['id'] ?>"><?= htmlspecialchars($milestone['title']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="flex space-x-4">
                    <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                        Create Issue
                    </button>
                    <button type="button" onclick="hideAddIssueModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Milestone Modal -->
<div id="addMilestoneModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-4">Create New Milestone</h3>
            <form method="POST" action="?action=add_milestone&project_id=<?= $project_id ?>" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Milestone Title *</label>
                    <input type="text" name="title" required placeholder="Version 1.0 Release" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea name="description" rows="3" placeholder="Describe what this milestone represents..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Due Date (optional)</label>
                    <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>

                <div class="flex space-x-4">
                    <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
                        Create Milestone
                    </button>
                    <button type="button" onclick="hideAddMilestoneModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Milestone Modal -->
<div id="deleteMilestoneModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-4">Confirm Delete Milestone</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Are you sure you want to delete the milestone "<span id="deleteMilestoneName"></span>"?
                This will also remove all issue associations with this milestone.
            </p>
            <form method="POST" action="?action=delete_milestone&project_id=<?= $project_id ?>" class="space-y-4">
                <input type="hidden" name="milestone_id" id="deleteMilestoneId">
                <div class="flex space-x-4">
                    <button type="submit" class="bg-red-500 text-white px-6 py-2 rounded hover:bg-red-600">
                        Delete Milestone
                    </button>
                    <button type="button" onclick="hideDeleteMilestoneModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Branch Modal -->
<div id="deleteBranchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-4">Confirm Delete Branch</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Are you sure you want to delete the branch "<span id="deleteBranchName"></span>"?
                This action cannot be undone.
            </p>
            <form method="POST" action="?action=delete_branch&project_id=<?= $project_id ?>" class="space-y-4">
                <input type="hidden" name="branch_id" id="deleteBranchId">
                <div class="flex space-x-4">
                    <button type="submit" class="bg-red-500 text-white px-6 py-2 rounded hover:bg-red-600">
                        Delete Branch
                    </button>
                    <button type="button" onclick="hideDeleteBranchModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        btn.classList.add('border-transparent', 'text-gray-500');
    });

    // Show selected tab
    document.getElementById(tabName + '-tab').classList.remove('hidden');
    document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + tabName).classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
}

function switchBranch(branchName) {
    window.location.href = `?action=view&project_id=<?= $project_id ?>&branch=${encodeURIComponent(branchName)}`;
}

function confirmDeleteBranch(branchId, branchName) {
    document.getElementById('deleteBranchId').value = branchId;
    document.getElementById('deleteBranchName').innerText = branchName;
    document.getElementById('deleteBranchModal').classList.remove('hidden');
}

function hideDeleteBranchModal() {
    document.getElementById('deleteBranchModal').classList.add('hidden');
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

function showEditProjectModal() {
    document.getElementById('editProjectModal').classList.remove('hidden');
}

function hideEditProjectModal() {
    document.getElementById('editProjectModal').classList.add('hidden');
}

function showAddIssueModal() {
    document.getElementById('addIssueModal').classList.remove('hidden');
}

function hideAddIssueModal() {
    document.getElementById('addIssueModal').classList.add('hidden');
}

function showAddMilestoneModal() {
    document.getElementById('addMilestoneModal').classList.remove('hidden');
}

function hideAddMilestoneModal() {
    document.getElementById('addMilestoneModal').classList.add('hidden');
}

function confirmDeleteMilestone(milestoneId, milestoneName) {
    document.getElementById('deleteMilestoneId').value = milestoneId;
    document.getElementById('deleteMilestoneName').innerText = milestoneName;
    document.getElementById('deleteMilestoneModal').classList.remove('hidden');
}

function hideDeleteMilestoneModal() {
    document.getElementById('deleteMilestoneModal').classList.add('hidden');
}

function exportCurrentBranch() {
    const currentBranch = '<?= htmlspecialchars($_GET['branch'] ?? 'main') ?>';
    const branchSelect = document.querySelector('select[onchange*="switchBranch"]');
    let branchId = null;
    
    // Find the branch ID for the current branch
    <?php foreach ($branches as $branch): ?>
        if (currentBranch === '<?= htmlspecialchars($branch['name']) ?>') {
            branchId = <?= $branch['id'] ?>;
        }
    <?php endforeach; ?>
    
    if (branchId) {
        window.location.href = `project_export.php?project_id=<?= $project_id ?>&branch_id=${branchId}`;
    } else {
        alert('Could not determine current branch for export.');
    }
}

function confirmDeleteProject() {
    if (confirm('Are you sure you want to delete this project? This action cannot be undone and will delete all files, branches, and issues.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?action=delete_project&project_id=<?= $project_id ?>';
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const currentTab = new URLSearchParams(window.location.search).get('tab') || 'files';
    showTab(currentTab);
});
</script>

</body>
</html>