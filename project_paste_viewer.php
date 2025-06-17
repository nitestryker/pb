<?php
// Implemented Related Pastes Suggestions feature on the Paste View page.
session_start();
require_once 'database.php';
require_once 'related_pastes_helper.php';

if (!isset($_GET['id'])) {
    header('Location: /');
    exit;
}

$paste_id = $_GET['id'];
$db = Database::getInstance()->getConnection();

function human_time_diff($timestamp) {
    if (!is_numeric($timestamp)) {
        $timestamp = strtotime($timestamp);
    }
    $timestamp = intval($timestamp);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';

    $days = floor($diff / 86400);
    if ($days < 30) return $days . ' days ago';

    $months = floor($days / 30);
    if ($months < 12) return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';

    $years = floor($months / 12);
    return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
}

// Check if this paste belongs to a project
$stmt = $db->prepare("
    SELECT p.*, pr.name as project_name, pr.id as project_id, pr.user_id as project_owner,
           pf.file_path, pf.file_name, pf.is_readme,
           pb.name as branch_name, pb.id as branch_id,
           u.username as author_username, u.profile_image as author_avatar,
           po.username as project_owner_username
    FROM pastes p
    LEFT JOIN project_files pf ON p.id = pf.paste_id
    LEFT JOIN projects pr ON pf.project_id = pr.id
    LEFT JOIN project_branches pb ON pf.branch_id = pb.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN users po ON pr.user_id = po.id
    WHERE p.id = ?
");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    header('Location: /');
    exit;
}

// If this isn't a project file, redirect to normal paste view
if (!$paste['project_id']) {
    header("Location: /?id=$paste_id");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// Get project branches
$stmt = $db->prepare("SELECT * FROM project_branches WHERE project_id = ? ORDER BY name");
$stmt->execute([$paste['project_id']]);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all files in current branch
$stmt = $db->prepare("
    SELECT pf.*, p.title, p.language, p.created_at as file_created,
           u.username as file_author
    FROM project_files pf
    JOIN pastes p ON pf.paste_id = p.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE pf.project_id = ? AND pf.branch_id = ?
    ORDER BY pf.file_path, pf.file_name
");
$stmt->execute([$paste['project_id'], $paste['branch_id']]);
$project_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent commits (file changes) for this project
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
$stmt->execute([$paste['project_id']]);
$recent_commits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get contributors for tooltip
$stmt = $db->prepare("
    SELECT DISTINCT u.username, u.profile_image, COUNT(DISTINCT pf.paste_id) as file_count
    FROM project_files pf
    JOIN pastes p ON pf.paste_id = p.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE pf.project_id = ?
    GROUP BY u.id, u.username
    ORDER BY file_count DESC
");
$stmt->execute([$paste['project_id']]);
$contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle raw and download requests
if (isset($_GET['raw']) && $_GET['raw'] == '1') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $paste['file_name'] . '"');
    echo $paste['content'];
    exit;
}

if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $paste['file_name'] . '"');
    header('Content-Length: ' . strlen($paste['content']));
    echo $paste['content'];
    exit;
}

// Get project statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT pf.paste_id) as total_files,
        COUNT(DISTINCT pb.id) as total_branches,
        COUNT(DISTINCT p.user_id) as contributors,
        SUM(p.views) as total_views
    FROM projects pr
    LEFT JOIN project_files pf ON pr.id = pf.project_id
    LEFT JOIN project_branches pb ON pr.id = pb.project_id
    LEFT JOIN pastes p ON pf.paste_id = p.id
    WHERE pr.id = ?
");
$stmt->execute([$paste['project_id']]);
$project_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Build breadcrumb path
$path_parts = array_filter(explode('/', $paste['file_path']));
$breadcrumb = [];
$current_path = '';
foreach ($path_parts as $part) {
    $current_path .= $part . '/';
    $breadcrumb[] = ['name' => $part, 'path' => $current_path];
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>

<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title><?= htmlspecialchars($paste['project_name']) ?> - <?= htmlspecialchars($paste['file_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css" class="light-theme" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-okaidia.min.css" class="dark-theme" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script>tailwind.config = { darkMode: 'class' }</script>
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
    </style>
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
                <div class="hidden md:flex items-center space-x-4">
                    <a href="/" class="text-blue-600 dark:text-blue-400 hover:underline">Home</a>
                    <a href="/?page=archive" class="text-blue-600 dark:text-blue-400 hover:underline">Archive</a>
                    <a href="/?page=collections" class="text-blue-600 dark:text-blue-400 hover:underline">Collections</a>
                </div>
                <div class="text-gray-400">/</div>
                <a href="project_manager.php?action=view&project_id=<?= $paste['project_id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                    <i class="fas fa-folder mr-1"></i><?= htmlspecialchars($paste['project_name']) ?>
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-code-branch mr-1"></i><?= $project_stats['total_branches'] ?> branches
                    <span class="mx-2">•</span>
                    <i class="fas fa-file mr-1"></i><?= $project_stats['total_files'] ?> files
                    <span class="mx-2">•</span>
                    <div class="inline-block relative group">
                        <span class="cursor-help">
                            <i class="fas fa-users mr-1"></i><?= $project_stats['contributors'] ?> contributors
                        </span>
                        <div class="absolute top-full right-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-4 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10">
                            <div class="text-sm font-medium mb-2">Contributors</div>
                            <div class="space-y-2">
                                <?php foreach ($contributors as $contributor): ?>
                                    <div class="flex items-center space-x-2">
                                        <img src="<?= $contributor['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($contributor['username'] ?? 'anonymous')).'?d=mp&s=24' ?>" 
                                             class="w-6 h-6 rounded-full" alt="Contributor">
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($contributor['username'] ?? 'Anonymous') ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?= $contributor['file_count'] ?> files
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-6">
    <!-- Project Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-folder text-3xl text-blue-500"></i>
                    <div>
                        <h1 class="text-2xl font-bold flex items-center">
                            <a href="?page=profile&username=<?= urlencode($paste['project_owner_username']) ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                <?= htmlspecialchars($paste['project_owner_username']) ?>
                            </a>
                            <span class="mx-2 text-gray-400">/</span>
                            <a href="project_manager.php?action=view&project_id=<?= $paste['project_id'] ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                <?= htmlspecialchars($paste['project_name']) ?>
                            </a>
                        </h1>
                        <div class="text-gray-600 dark:text-gray-400 text-sm mt-1">
                            Public repository
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <!-- Add to Chain Button -->
                    <a href="/?parent_id=<?= $paste_id ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm inline-flex items-center">
                        <i class="fas fa-link mr-2"></i>Add to Chain
                    </a>

                    <!-- Branch Selector -->
                    <div class="relative">
                        <select onchange="switchBranch(this.value)" class="appearance-none bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8">
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['name'] ?>" <?= $branch['id'] == $paste['branch_id'] ? 'selected' : '' ?>>
                                    <i class="fas fa-code-branch"></i> <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-gray-400 pointer-events-none"></i>
                    </div>

                    <a href="project_paste_viewer.php?id=<?= $paste_id ?>&raw=1" class="bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-code mr-2"></i>Raw
                    </a>

                    <a href="project_paste_viewer.php?id=<?= $paste_id ?>&download=1" class="bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-download mr-2"></i>Download
                    </a>

                    <a href="project_export.php?project_id=<?= $paste['project_id'] ?>&branch_id=<?= $paste['branch_id'] ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-file-archive mr-2"></i>Export Branch
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-4 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-3 space-y-6">
            <!-- File Navigation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-sm">
                            <i class="fas fa-folder text-blue-500"></i>
                            <a href="project_manager.php?action=view&project_id=<?= $paste['project_id'] ?>&branch=<?= urlencode($paste['branch_name']) ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                <?= htmlspecialchars($paste['project_name']) ?>
                            </a>
                            <?php foreach ($breadcrumb as $crumb): ?>
                                <span class="text-gray-400">/</span>
                                <span class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($crumb['name']) ?></span>
                            <?php endforeach; ?>
                            <span class="text-gray-400">/</span>
                            <span class="font-medium"><?= htmlspecialchars($paste['file_name']) ?></span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?= strlen($paste['content']) ?> bytes
                        </div>
                    </div>
                </div>

                <!-- File Content -->
                <div class="p-6">
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <img src="<?= $paste['author_avatar'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($paste['author_username'] ?? 'anonymous')).'?d=mp&s=32' ?>" 
                                     class="w-8 h-8 rounded-full" alt="Author">
                                <div>
                                    <div class="font-medium">
                                        <?php if ($paste['author_username']): ?>
                                            <a href="?page=profile&username=<?= urlencode($paste['author_username']) ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                                <?= htmlspecialchars($paste['author_username']) ?>
                                            </a>
                                        <?php else: ?>
                                            Anonymous
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php 
                                        $created_timestamp = $paste['created_at'];
                                        if (!is_numeric($created_timestamp)) {
                                            $created_timestamp = strtotime($created_timestamp);
                                        }
                                        $created_timestamp = intval($created_timestamp);

                                        $modified_timestamp = $paste['last_modified'];
                                        if ($modified_timestamp) {
                                            if (!is_numeric($modified_timestamp)) {
                                                $modified_timestamp = strtotime($modified_timestamp);
                                            }
                                            $modified_timestamp = intval($modified_timestamp);
                                        }

                                        // If timestamp is 0 or invalid, use current time as fallback
                                        if ($created_timestamp <= 0) {
                                            $created_timestamp = time();
                                        }

                                        if ($modified_timestamp && $modified_timestamp > 0 && $modified_timestamp > $created_timestamp) {
                                            echo 'Updated ' . date('M j, Y', $modified_timestamp);
                                        } else {
                                            echo 'Created ' . date('M j, Y', $created_timestamp);
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm">
                                    <?= htmlspecialchars($paste['language']) ?>
                                </span>
                                <button onclick="copyToClipboard()" class="px-3 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-sm">
                                    <i class="fas fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Code Display -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-mono"><?= htmlspecialchars($paste['file_name']) ?></span>
                                <span class="text-gray-500"><?= number_format(substr_count($paste['content'], "\n") + 1) ?> lines</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <pre class="line-numbers bg-white dark:bg-gray-900 p-4"><code class="language-<?= htmlspecialchars($paste['language']) ?>"><?= htmlspecialchars($paste['content']) ?></code></pre>
                        </div>
                    </div>
                </div>

            <!-- Related Pastes Section -->
            <?php
            $related_helper = new RelatedPastesHelper($db);
            $related_pastes = $related_helper->getRelatedPastes($paste['id'], 5);
            ?>

            <?php if (!empty($related_pastes)): ?>
            <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <h3 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                Related Pastes
              </h3>

              <div class="space-y-3">
                <?php foreach ($related_pastes as $related): ?>
                  <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <div class="flex items-start justify-between">
                      <div class="flex-1">
                        <h4 class="font-medium mb-1">
                          <a href="project_paste_viewer.php?id=<?= $related['id'] ?>" class="text-blue-500 hover:text-blue-700">
                            <?= htmlspecialchars($related['title']) ?>
                          </a>
                        </h4>
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                          <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs">
                            <?= htmlspecialchars($related['language']) ?>
                          </span>
                          <?php if ($related['username']): ?>
                            <span>by @<?= htmlspecialchars($related['username']) ?></span>
                          <?php else: ?>
                            <span>by Anonymous</span>
                          <?php endif; ?>
                          <span>•</span>
                          <span><?= human_time_diff($related['created_at']) ?></span>
                          <span>•</span>
                          <span><?= number_format($related['views']) ?> views</span>
                        </div>
                      </div>
                      <div class="ml-4">
                        <a href="project_paste_viewer.php?id=<?= $related['id'] ?>" 
                           class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                          View
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <!-- Child Pastes Section (Paste Chain) -->
            <?php
            // Get child pastes in this chain
            $stmt = $db->prepare("
              SELECT p.*, u.username, u.profile_image
              FROM pastes p
              LEFT JOIN users u ON p.user_id = u.id
              WHERE p.parent_paste_id = ? AND p.is_public = 1
              ORDER BY p.created_at ASC
              LIMIT 10
            ");
            $stmt->execute([$paste['id']]);
            $child_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Project Files -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold flex items-center">
                        <i class="fas fa-folder-tree mr-2 text-blue-500"></i>
                        Files (<?= count($project_files) ?>)
                    </h3>
                </div>
                <div class="max-h-64 overflow-y-auto">
                    <?php 
                    $current_dir = '';
                    foreach ($project_files as $file): 
                        $is_current = $file['paste_id'] == $paste_id;
                    ?>
                        <div class="file-tree-item px-4 py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0 <?= $is_current ? 'bg-blue-50 dark:bg-blue-900/20' : '' ?>">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-file-code text-gray-400 text-sm"></i>
                                <?php if ($is_current): ?>
                                    <span class="font-medium text-blue-600 dark:text-blue-400">
                                        <?= htmlspecialchars($file['file_path'] . $file['file_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <a href="project_paste_viewer.php?id=<?= $file['paste_id'] ?>" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                                        <?= htmlspecialchars($file['file_path'] . $file['file_name']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($file['is_readme']): ?>
                                    <span class="text-xs bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-2 py-1 rounded">README</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 mt-1 ml-6">
                                <?= htmlspecialchars($file['language']) ?>
                                <?php if ($file['file_author']): ?>
                                    • by <?= htmlspecialchars($file['file_author']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Commits -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold flex items-center">
                        <i class="fas fa-history mr-2 text-green-500"></i>
                        Recent Activity
                    </h3>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    <?php foreach ($recent_commits as $commit): ?>
                        <div class="commit-item px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                            <div class="flex items-start space-x-3">
                                <img src="<?= $commit['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($commit['username'] ?? 'anonymous')).'?d=mp&s=24' ?>" 
                                     class="w-6 h-6 rounded-full mt-1" alt="Committer">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm">
                                        <a href="project_paste_viewer.php?id=<?= $commit['id'] ?>" class="font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">
                                            <?= htmlspecialchars($commit['file_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        by <?= htmlspecialchars($commit['username'] ?? 'Anonymous') ?>
                                        • <?php 
                                        $commit_created = $commit['created_at'];
                                        if (!is_numeric($commit_created)) {
                                            $commit_created = strtotime($commit_created);
                                        }
                                        $commit_created = intval($commit_created);

                                        $commit_modified = $commit['last_modified'];
                                        if ($commit_modified) {
                                            if (!is_numeric($commit_modified)) {
                                                $commit_modified = strtotime($commit_modified);
                                            }
                                            $commit_modified = intval($commit_modified);
                                        }

                                        // If timestamp is 0 or invalid, use current time as fallback
                                        if ($commit_created <= 0) {
                                            $commit_created = time();
                                        }

                                        $display_timestamp = ($commit_modified && $commit_modified > 0 && $commit_modified > $commit_created) ? $commit_modified : $commit_created;
                                        echo date('M j, g:i A', $display_timestamp);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Branch Info -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold flex items-center">
                        <i class="fas fa-code-branch mr-2 text-purple-500"></i>
                        Branch: <?= htmlspecialchars($paste['branch_name']) ?>
                    </h3>
                </div>
                <div class="p-4 space-y-3">
                    <?php foreach ($branches as $branch): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-code-branch text-gray-400"></i>
                                <?php if ($branch['id'] == $paste['branch_id']): ?>
                                    <span class="font-medium text-purple-600 dark:text-purple-400">
                                        <?= htmlspecialchars($branch['name']) ?>
                                    </span>
                                    <span class="text-xs bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 px-2 py-1 rounded">current</span>
                                <?php else: ?>
                                    <a href="javascript:switchBranch('<?= $branch['name'] ?>')" class="text-gray-700 dark:text-gray-300 hover:text-purple-600 dark:hover:text-purple-400">
                                        <?= htmlspecialchars($branch['name']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchBranch(branchName) {
    // Find a file in the new branch and redirect there
    const currentProjectId = <?= $paste['project_id'] ?>;
    const currentFileName = '<?= htmlspecialchars($paste['file_name']) ?>';

    // For now, redirect to project manager with the new branch
    window.location.href = `project_manager.php?action=view&project_id=${currentProjectId}&branch=${encodeURIComponent(branchName)}`;
}

function copyToClipboard() {
    const code = document.querySelector('pre code').textContent;
    navigator.clipboard.writeText(code).then(() => {
        // Show success feedback
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Copied';
        btn.classList.add('text-green-600');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('text-green-600');
        }, 2000);
    });
}

// Initialize Prism syntax highlighting
document.addEventListener('DOMContentLoaded', () => {
    Prism.highlightAll();
});
</script>

</body>
</html>

