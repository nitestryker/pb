<?php
session_start();
require_once 'database.php';

if (!isset($_GET['issue_id']) || !isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$issue_id = $_GET['issue_id'];
$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Get issue details
$stmt = $db->prepare("
    SELECT pi.*, pr.name as project_name, pr.id as project_id, pr.user_id as project_owner,
           u.username as created_by_username, u.profile_image as created_by_avatar,
           po.username as project_owner_username
    FROM project_issues pi
    JOIN projects pr ON pi.project_id = pr.id
    JOIN users u ON pi.created_by = u.id
    LEFT JOIN users po ON pr.user_id = po.id
    WHERE pi.id = ?
");
$stmt->execute([$issue_id]);
$issue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$issue) {
    header('Location: /');
    exit;
}

// Get milestone information for this issue
$milestone_stmt = $db->prepare("
    SELECT m.* 
    FROM project_milestones m
    JOIN issue_milestones im ON m.id = im.milestone_id
    WHERE im.issue_id = ?
");
$milestone_stmt->execute([$issue_id]);
$milestone = $milestone_stmt->fetch(PDO::FETCH_ASSOC);

// Check permissions
$is_project_owner = $issue['project_owner'] === $user_id;
$is_issue_creator = $issue['created_by'] === $user_id;

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_comment') {
        $content = trim($_POST['content']);
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
        
        if (!empty($content)) {
            try {
                $stmt = $db->prepare("INSERT INTO project_issue_comments (issue_id, user_id, content, parent_comment_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$issue_id, $user_id, $content, $parent_id]);
                
                // Update issue's updated_at timestamp
                $stmt = $db->prepare("UPDATE project_issues SET updated_at = strftime('%s', 'now') WHERE id = ?");
                $stmt->execute([$issue_id]);
                
                header("Location: ?issue_id=$issue_id");
                exit;
            } catch (PDOException $e) {
                $error = "Failed to add comment: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_status' && ($is_project_owner || $is_issue_creator)) {
        $new_status = $_POST['status'];
        if (in_array($new_status, ['open', 'closed'])) {
            $stmt = $db->prepare("UPDATE project_issues SET status = ?, updated_at = strftime('%s', 'now') WHERE id = ?");
            $stmt->execute([$new_status, $issue_id]);
            header("Location: ?issue_id=$issue_id");
            exit;
        }
    } elseif ($action === 'edit_issue' && ($is_project_owner || $is_issue_creator)) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $priority = $_POST['priority'];
        $label = $_POST['label'];
        $milestone_id = !empty($_POST['milestone_id']) ? $_POST['milestone_id'] : null;
        
        if (!empty($title)) {
            try {
                $db->beginTransaction();
                
                // Update the issue
                $stmt = $db->prepare("UPDATE project_issues SET title = ?, description = ?, priority = ?, label = ?, updated_at = strftime('%s', 'now') WHERE id = ?");
                $stmt->execute([$title, $description, $priority, $label, $issue_id]);
                
                // Update milestone assignment
                // First remove existing milestone assignment
                $stmt = $db->prepare("DELETE FROM issue_milestones WHERE issue_id = ?");
                $stmt->execute([$issue_id]);
                
                // Add new milestone assignment if provided
                if ($milestone_id) {
                    $stmt = $db->prepare("INSERT INTO issue_milestones (issue_id, milestone_id) VALUES (?, ?)");
                    $stmt->execute([$issue_id, $milestone_id]);
                }
                
                $db->commit();
                header("Location: ?issue_id=$issue_id");
                exit;
            } catch (PDOException $e) {
                $db->rollback();
                $error = "Failed to update issue: " . $e->getMessage();
            }
        } else {
            $error = "Issue title is required";
        }
    } elseif ($action === 'delete_issue' && ($is_project_owner || $is_issue_creator)) {
        try {
            $db->beginTransaction();
            
            // Delete all comments for this issue
            $stmt = $db->prepare("DELETE FROM project_issue_comments WHERE issue_id = ?");
            $stmt->execute([$issue_id]);
            
            // Delete the issue
            $stmt = $db->prepare("DELETE FROM project_issues WHERE id = ?");
            $stmt->execute([$issue_id]);
            
            $db->commit();
            header("Location: project_manager.php?action=view&project_id=".$issue['project_id']."&tab=issues");
            exit;
        } catch (PDOException $e) {
            $db->rollback();
            $error = "Failed to delete issue: " . $e->getMessage();
        }
    }
}

// Get comments with replies
$stmt = $db->prepare("
    SELECT pic.*, u.username, u.profile_image,
           (SELECT COUNT(*) FROM project_issue_comments pic2 WHERE pic2.parent_comment_id = pic.id) as reply_count
    FROM project_issue_comments pic
    JOIN users u ON pic.user_id = u.id
    WHERE pic.issue_id = ? AND pic.parent_comment_id IS NULL AND pic.is_deleted = 0
    ORDER BY pic.created_at ASC
");
$stmt->execute([$issue_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get replies for a comment
function getReplies($db, $comment_id) {
    $stmt = $db->prepare("
        SELECT pic.*, u.username, u.profile_image
        FROM project_issue_comments pic
        JOIN users u ON pic.user_id = u.id
        WHERE pic.parent_comment_id = ? AND pic.is_deleted = 0
        ORDER BY pic.created_at ASC
    ");
    $stmt->execute([$comment_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>

<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title><?= htmlspecialchars($issue['title']) ?> - <?= htmlspecialchars($issue['project_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class' }</script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white">

<!-- Navigation -->
<nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex items-center space-x-4">
                <a href="/" class="flex items-center space-x-2 text-blue-600 dark:text-blue-400">
                    <i class="fas fa-paste text-xl"></i>
                    <span class="font-semibold">PasteForge</span>
                </a>
                <div class="text-gray-400">/</div>
                <a href="project_manager.php?action=view&project_id=<?= $issue['project_id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                    <i class="fas fa-folder mr-1"></i><?= htmlspecialchars($issue['project_name']) ?>
                </a>
                <div class="text-gray-400">/</div>
                <a href="project_manager.php?action=view&project_id=<?= $issue['project_id'] ?>&tab=issues" class="text-blue-600 dark:text-blue-400 hover:underline">
                    <i class="fas fa-exclamation-circle mr-1"></i>Issues
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-6">
    <?php if (isset($error)): ?>
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Issue Header -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
                <div class="p-6">
                    <!-- Title and Actions -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1 min-w-0">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                <?= htmlspecialchars($issue['title']) ?>
                                <span class="text-lg text-gray-500 font-normal ml-2">#<?= $issue['id'] ?></span>
                            </h1>
                            
                            <!-- Status and Meta Information -->
                            <div class="flex items-center space-x-3 text-sm text-gray-600 dark:text-gray-400">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $issue['status'] === 'open' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' ?>">
                                    <i class="fas fa-<?= $issue['status'] === 'open' ? 'exclamation-circle' : 'check-circle' ?> mr-1"></i>
                                    <?= ucfirst($issue['status']) ?>
                                </span>
                                <span>opened <?= date('M j, Y', $issue['created_at']) ?> by 
                                    <a href="?page=profile&username=<?= urlencode($issue['created_by_username']) ?>" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                        <?= htmlspecialchars($issue['created_by_username']) ?>
                                    </a>
                                </span>
                                <?php if ($issue['updated_at'] && $issue['updated_at'] != $issue['created_at']): ?>
                                    <span>• updated <?= date('M j, Y', $issue['updated_at']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($is_project_owner || $is_issue_creator): ?>
                            <div class="flex space-x-2 ml-4">
                                <button onclick="showEditIssueModal()" class="inline-flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-medium transition-colors">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="status" value="<?= $issue['status'] === 'open' ? 'closed' : 'open' ?>">
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded text-sm font-medium transition-colors">
                                        <i class="fas fa-<?= $issue['status'] === 'open' ? 'times' : 'check' ?> mr-1"></i>
                                        <?= $issue['status'] === 'open' ? 'Close' : 'Reopen' ?>
                                    </button>
                                </form>
                                <button onclick="confirmDeleteIssue()" class="inline-flex items-center px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded text-sm font-medium transition-colors">
                                    <i class="fas fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Labels, Priority, and Milestone -->
                    <div class="flex items-center flex-wrap gap-2 mb-4">
                        <span class="inline-flex items-center px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full font-medium">
                            <i class="fas fa-tag mr-1"></i><?= ucfirst($issue['label']) ?>
                        </span>
                        <span class="inline-flex items-center px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 text-xs rounded-full font-medium">
                            <i class="fas fa-exclamation-triangle mr-1"></i><?= ucfirst($issue['priority']) ?>
                        </span>
                        <?php if ($milestone): ?>
                            <span class="inline-flex items-center px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-xs rounded-full font-medium">
                                <i class="fas fa-flag mr-1"></i><?= htmlspecialchars($milestone['title']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <?php if ($issue['description']): ?>
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="text-gray-900 dark:text-gray-100">
                                <?= nl2br(htmlspecialchars($issue['description'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="space-y-4">
                <?php foreach ($comments as $comment): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <!-- Main Comment -->
                        <div class="p-4">
                            <div class="flex items-start space-x-3">
                                <img src="<?= $comment['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($comment['username'])).'?d=mp&s=32' ?>" 
                                     class="w-8 h-8 rounded-full ring-2 ring-gray-200 dark:ring-gray-700" alt="<?= htmlspecialchars($comment['username']) ?>">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <a href="?page=profile&username=<?= urlencode($comment['username']) ?>" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                            <?= htmlspecialchars($comment['username']) ?>
                                        </a>
                                        <span class="text-xs text-gray-500">
                                            commented <?= date('M j, Y \a\t g:i A', $comment['created_at']) ?>
                                        </span>
                                        <?php if ($comment['updated_at'] && $comment['updated_at'] != $comment['created_at']): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">edited</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-900 dark:text-gray-100 mb-3">
                                        <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <button onclick="toggleReplyForm(<?= $comment['id'] ?>)" class="inline-flex items-center text-xs text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            <i class="fas fa-reply mr-1"></i>Reply
                                        </button>
                                        <?php if ($comment['reply_count'] > 0): ?>
                                            <button onclick="toggleReplies(<?= $comment['id'] ?>)" class="inline-flex items-center text-xs text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                <i class="fas fa-comments mr-1"></i>
                                                <span id="replies-toggle-<?= $comment['id'] ?>">Show <?= $comment['reply_count'] ?> replies</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Reply Form -->
                            <div id="reply-form-<?= $comment['id'] ?>" class="hidden mt-4 ml-11">
                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                                    <div class="flex space-x-2">
                                        <img src="<?= $_SESSION['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($_SESSION['username'])).'?d=mp&s=24' ?>" 
                                             class="w-6 h-6 rounded-full ring-2 ring-gray-200 dark:ring-gray-700" alt="You">
                                        <textarea name="content" rows="2" required placeholder="Write a reply..." 
                                                  class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm"></textarea>
                                    </div>
                                    <div class="ml-8 flex space-x-2">
                                        <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-xs font-medium transition-colors">
                                            Reply
                                        </button>
                                        <button type="button" onclick="toggleReplyForm(<?= $comment['id'] ?>)" class="bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600 text-xs font-medium transition-colors">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Replies -->
                        <?php 
                        $replies = getReplies($db, $comment['id']);
                        if (!empty($replies)): 
                        ?>
                            <div id="replies-<?= $comment['id'] ?>" class="hidden border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
                                <?php foreach ($replies as $reply): ?>
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                                        <div class="flex items-start space-x-2">
                                            <img src="<?= $reply['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($reply['username'])).'?d=mp&s=24' ?>" 
                                                 class="w-6 h-6 rounded-full ring-2 ring-gray-200 dark:ring-gray-700" alt="<?= htmlspecialchars($reply['username']) ?>">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center space-x-2 mb-1">
                                                    <a href="?page=profile&username=<?= urlencode($reply['username']) ?>" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline text-xs">
                                                        <?= htmlspecialchars($reply['username']) ?>
                                                    </a>
                                                    <span class="text-xs text-gray-500">
                                                        <?= date('M j, Y \a\t g:i A', $reply['created_at']) ?>
                                                    </span>
                                                    <?php if ($reply['updated_at'] && $reply['updated_at'] != $reply['created_at']): ?>
                                                        <span class="text-xs text-gray-500 bg-gray-200 dark:bg-gray-600 px-1 py-0.5 rounded">edited</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-xs text-gray-900 dark:text-gray-100">
                                                    <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Add New Comment -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_comment">
                            <div class="flex items-start space-x-3">
                                <img src="<?= $_SESSION['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($_SESSION['username'])).'?d=mp&s=32' ?>" 
                                     class="w-8 h-8 rounded-full ring-2 ring-gray-200 dark:ring-gray-700" alt="You">
                                <div class="flex-1">
                                    <textarea name="content" rows="3" required placeholder="Leave a comment..." 
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    <div class="mt-3">
                                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 font-medium transition-colors">
                                            <i class="fas fa-comment mr-2"></i>Comment
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar - Issue Details -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 sticky top-24">
                <div class="p-4">
                    <h3 class="font-semibold text-sm mb-4 flex items-center text-gray-900 dark:text-white">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                        Issue Details
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Status</label>
                            <div class="flex items-center space-x-2">
                                <span class="w-2 h-2 rounded-full <?= $issue['status'] === 'open' ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                                <span class="text-xs font-medium text-gray-900 dark:text-white"><?= ucfirst($issue['status']) ?></span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Priority</label>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-exclamation-triangle text-yellow-500 text-xs"></i>
                                <span class="text-xs text-gray-900 dark:text-white"><?= ucfirst($issue['priority']) ?></span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Label</label>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-tag text-blue-500 text-xs"></i>
                                <span class="text-xs text-gray-900 dark:text-white"><?= ucfirst($issue['label']) ?></span>
                            </div>
                        </div>
                        
                        <?php if ($milestone): ?>
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Milestone</label>
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-flag text-purple-500 text-xs"></i>
                                        <span class="text-xs text-gray-900 dark:text-white"><?= htmlspecialchars($milestone['title']) ?></span>
                                    </div>
                                    <?php if ($milestone['due_date']): ?>
                                        <div class="text-xs text-gray-500 ml-4">
                                            Due <?= date('M j, Y', $milestone['due_date']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Created by</label>
                            <div class="flex items-center space-x-2">
                                <img src="<?= $issue['created_by_avatar'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($issue['created_by_username'])).'?d=mp&s=16' ?>" 
                                     class="w-4 h-4 rounded-full ring-1 ring-gray-200 dark:ring-gray-700" alt="<?= htmlspecialchars($issue['created_by_username']) ?>">
                                <a href="?page=profile&username=<?= urlencode($issue['created_by_username']) ?>" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                    <?= htmlspecialchars($issue['created_by_username']) ?>
                                </a>
                            </div>
                        </div>
                        
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Created</label>
                            <div class="text-xs text-gray-900 dark:text-white">
                                <?= date('M j, Y', $issue['created_at']) ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?= date('g:i A', $issue['created_at']) ?>
                            </div>
                        </div>
                        
                        <?php if ($issue['updated_at'] && $issue['updated_at'] != $issue['created_at']): ?>
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Last Updated</label>
                                <div class="text-xs text-gray-900 dark:text-white">
                                    <?= date('M j, Y', $issue['updated_at']) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= date('g:i A', $issue['updated_at']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Issue Modal -->
<div id="editIssueModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
            <h3 class="text-xl font-semibold mb-4">Edit Issue</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_issue">
                
                <div>
                    <label class="block text-sm font-medium mb-2">Issue Title *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($issue['title']) ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Description</label>
                    <textarea name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"><?= htmlspecialchars($issue['description']) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Priority</label>
                    <select name="priority" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <option value="low" <?= $issue['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $issue['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $issue['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="critical" <?= $issue['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Label</label>
                    <select name="label" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <option value="bug" <?= $issue['label'] === 'bug' ? 'selected' : '' ?>>Bug</option>
                        <option value="feature" <?= $issue['label'] === 'feature' ? 'selected' : '' ?>>Feature Request</option>
                        <option value="enhancement" <?= $issue['label'] === 'enhancement' ? 'selected' : '' ?>>Enhancement</option>
                        <option value="documentation" <?= $issue['label'] === 'documentation' ? 'selected' : '' ?>>Documentation</option>
                        <option value="help-wanted" <?= $issue['label'] === 'help-wanted' ? 'selected' : '' ?>>Help Wanted</option>
                        <option value="question" <?= $issue['label'] === 'question' ? 'selected' : '' ?>>Question</option>
                    </select>
                </div>

                <?php
                // Get available milestones for this project
                $milestones_stmt = $db->prepare("SELECT * FROM project_milestones WHERE project_id = ? AND completed_at IS NULL ORDER BY title");
                $milestones_stmt->execute([$issue['project_id']]);
                $available_milestones = $milestones_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div>
                    <label class="block text-sm font-medium mb-2">Milestone (optional)</label>
                    <select name="milestone_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <option value="">No milestone</option>
                        <?php foreach ($available_milestones as $ms): ?>
                            <option value="<?= $ms['id'] ?>" <?= ($milestone && $milestone['id'] == $ms['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ms['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                        Update Issue
                    </button>
                    <button type="button" onclick="hideEditIssueModal()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    if (form) {
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
            form.querySelector('textarea').focus();
        }
    }
}

function toggleReplies(commentId) {
    const replies = document.getElementById(`replies-${commentId}`);
    const toggle = document.getElementById(`replies-toggle-${commentId}`);
    
    if (replies && toggle) {
        replies.classList.toggle('hidden');
        const isHidden = replies.classList.contains('hidden');
        const replyCount = toggle.textContent.match(/\d+/)[0];
        toggle.textContent = isHidden ? `Show ${replyCount} replies` : `Hide ${replyCount} replies`;
    }
}

function showEditIssueModal() {
    document.getElementById('editIssueModal').classList.remove('hidden');
}

function hideEditIssueModal() {
    document.getElementById('editIssueModal').classList.add('hidden');
}

function confirmDeleteIssue() {
    if (confirm('Are you sure you want to delete this issue? This action cannot be undone and will also delete all comments.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_issue">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

</body>
</html>
