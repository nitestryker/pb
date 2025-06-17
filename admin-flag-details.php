<?php
// Handle AJAX requests differently to avoid header issues
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Only start session if headers haven't been sent
if (!headers_sent()) {
    require_once(__DIR__ . '/admin-session.php');
    check_admin_auth();
} else {
    // For AJAX requests where headers are already sent, just start session manually
    if (!session_id()) {
        session_start();
    }
    // Simple auth check without redirect
    if (!isset($_SESSION['admin_id'])) {
        if ($is_ajax) {
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        } else {
            echo '<div class="text-red-500">Not authenticated</div>';
            exit;
        }
    }
}

try {
    $db = new PDO('sqlite:database.sqlite');
    $paste_id = $_GET['paste_id'] ?? null;

    if (!$paste_id || !is_numeric($paste_id)) {
        throw new Exception('Invalid paste ID');
    }
} catch (Exception $e) {
    if ($is_ajax) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        echo '<div class="text-red-500">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    exit;
}

// Get paste info
$stmt = $db->prepare("SELECT * FROM pastes WHERE id = ?");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    die('Paste not found');
}

// Get all flags for this paste
$stmt = $db->prepare("
    SELECT pf.*, u.username as reporter_username, fc.description as category_description, fc.severity
    FROM paste_flags pf
    LEFT JOIN users u ON pf.user_id = u.id
    LEFT JOIN flag_categories fc ON pf.flag_type = fc.name
    WHERE pf.paste_id = ?
    ORDER BY pf.created_at DESC
");
$stmt->execute([$paste_id]);
$flags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get flag summary
$stmt = $db->prepare("
    SELECT flag_type, COUNT(*) as count, MAX(created_at) as latest
    FROM paste_flags
    WHERE paste_id = ? AND status = 'pending'
    GROUP BY flag_type
    ORDER BY count DESC
");
$stmt->execute([$paste_id]);
$flag_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (!$is_ajax): ?>
<!DOCTYPE html>
<html>
<head>
    <title>Flag Details - Paste #<?= $paste_id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config = { darkMode: 'class' }</script>
</head>
<body class="bg-gray-900 text-white p-6">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-flag text-red-500 mr-2"></i>
                Flag Details - Paste #<?= $paste_id ?>
            </h1>
            <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded">
                Close
            </button>
        </div>
<?php else: ?>
    <div class="text-white">
<?php endif; ?>

        <!-- Paste Info -->
        <div class="bg-gray-800 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-semibold mb-2">Paste Information</h3>
            <div class="grid grid-cols-2 gap-4">
                <div><strong>Title:</strong> <?= htmlspecialchars($paste['title'] ?: 'Untitled') ?></div>
                <div><strong>Created:</strong> <?= date('Y-m-d H:i:s', $paste['created_at']) ?></div>
                <div><strong>Language:</strong> <?= htmlspecialchars($paste['language'] ?: 'None') ?></div>
                <div><strong>Visibility:</strong> <?= $paste['is_public'] ? 'Public' : 'Hidden' ?></div>
            </div>
        </div>

        <!-- Flag Summary -->
        <div class="bg-gray-800 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-semibold mb-4">Flag Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($flag_summary as $summary): ?>
                    <div class="bg-gray-700 p-3 rounded">
                        <div class="font-semibold text-lg">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $summary['flag_type']))) ?>
                        </div>
                        <div class="text-sm text-gray-300">
                            <?= $summary['count'] ?> report<?= $summary['count'] > 1 ? 's' : '' ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            Latest: <?= date('M j, Y g:i A', $summary['latest']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Detailed Flags -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">All Reports (<?= count($flags) ?>)</h3>
            <div class="space-y-4">
                <?php foreach ($flags as $flag): ?>
                    <div class="bg-gray-700 p-4 rounded-lg border-l-4 <?= $flag['status'] === 'resolved' ? 'border-green-500' : 'border-red-500' ?>">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-semibold">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $flag['flag_type']))) ?>
                                </span>
                                <?php if ($flag['severity']): ?>
                                    <span class="ml-2 px-2 py-1 text-xs rounded 
                                        <?= $flag['severity'] >= 4 ? 'bg-red-600' : ($flag['severity'] >= 3 ? 'bg-orange-600' : 'bg-yellow-600') ?>">
                                        Severity: <?= $flag['severity'] ?>
                                    </span>
                                <?php endif; ?>
                                <span class="ml-2 px-2 py-1 text-xs rounded <?= $flag['status'] === 'resolved' ? 'bg-green-600' : 'bg-gray-600' ?>">
                                    <?= ucfirst($flag['status']) ?>
                                </span>
                            </div>
                            <div class="text-sm text-gray-400">
                                <?= date('M j, Y g:i A', $flag['created_at']) ?>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-300 mb-2">
                            <strong>Reporter:</strong> 
                            <?= $flag['reporter_username'] ? htmlspecialchars($flag['reporter_username']) : 'Anonymous' ?>
                            (IP: <?= htmlspecialchars($flag['ip_address']) ?>)
                        </div>
                        
                        <?php if ($flag['reason']): ?>
                            <div class="text-sm mb-2">
                                <strong>Reason:</strong> <?= htmlspecialchars($flag['reason']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($flag['description']): ?>
                            <div class="text-sm mb-2">
                                <strong>Description:</strong> <?= htmlspecialchars($flag['description']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($flag['category_description']): ?>
                            <div class="text-xs text-gray-400">
                                <strong>Category:</strong> <?= htmlspecialchars($flag['category_description']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($flag['status'] === 'resolved' && $flag['reviewed_by']): ?>
                            <div class="text-xs text-green-400 mt-2">
                                Resolved by Admin ID <?= $flag['reviewed_by'] ?> on <?= date('M j, Y g:i A', $flag['reviewed_at']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
<?php if (!$is_ajax): ?>
    </div>
</body>
</html>
<?php else: ?>
    </div>
<?php endif; ?>
