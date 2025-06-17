<?php
require_once(__DIR__ . '/admin-session.php');
check_admin_auth();

$db = new PDO('sqlite:database.sqlite');
$paste_id = $_GET['id'] ?? null;

if (!$paste_id || !is_numeric($paste_id)) {
    die('Invalid paste ID');
}

// Get paste info
$stmt = $db->prepare("SELECT p.*, u.username FROM pastes p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    die('Paste not found');
}

// Get flag information
$stmt = $db->prepare("SELECT COUNT(*) as flag_count FROM paste_flags WHERE paste_id = ? AND status = 'pending'");
$stmt->execute([$paste_id]);
$flag_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get flag types
$stmt = $db->prepare("SELECT flag_type, COUNT(*) as count FROM paste_flags WHERE paste_id = ? AND status = 'pending' GROUP BY flag_type ORDER BY count DESC");
$stmt->execute([$paste_id]);
$flag_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html class="dark">
<head>
    <title>Admin Review - <?= htmlspecialchars($paste['title'] ?: 'Untitled') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-dark.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="bg-red-900/20 border border-red-500 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-red-500 text-xl mr-3"></i>
                    <div>
                        <h1 class="text-xl font-bold text-red-400">ADMIN REVIEW MODE</h1>
                        <p class="text-sm text-red-300">This content is flagged and under administrative review</p>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded text-sm">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                    <a href="admindash.php#flagged" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Admin
                    </a>
                </div>
            </div>
        </div>

        <!-- Flag Information -->
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h2 class="text-lg font-semibold mb-3 text-red-400">Flag Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-700 p-3 rounded">
                    <div class="text-sm text-gray-300">Total Flags</div>
                    <div class="text-xl font-bold text-red-400"><?= $flag_info['flag_count'] ?></div>
                </div>
                <div class="bg-gray-700 p-3 rounded">
                    <div class="text-sm text-gray-300">Flag Types</div>
                    <div class="text-sm">
                        <?php foreach ($flag_types as $type): ?>
                            <span class="inline-block bg-red-500/20 text-red-300 px-2 py-1 rounded text-xs mr-1 mb-1">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type['flag_type']))) ?> (<?= $type['count'] ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-gray-700 p-3 rounded">
                    <div class="text-sm text-gray-300">Actions</div>
                    <div class="space-x-2 mt-1">
                        <button onclick="viewFlagDetails(<?= $paste_id ?>)" class="bg-purple-600 hover:bg-purple-700 px-3 py-1 rounded text-xs">
                            <i class="fas fa-info-circle mr-1"></i>Flag Details
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paste Information -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold"><?= htmlspecialchars($paste['title'] ?: 'Untitled') ?></h2>
                <div class="flex items-center space-x-4 text-sm text-gray-400">
                    <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($paste['username'] ?: 'Anonymous') ?></span>
                    <span><i class="fas fa-calendar mr-1"></i><?= date('M j, Y g:i A', $paste['created_at']) ?></span>
                    <span><i class="fas fa-eye mr-1"></i><?= number_format($paste['views']) ?> views</span>
                    <?php if ($paste['language']): ?>
                        <span><i class="fas fa-code mr-1"></i><?= htmlspecialchars($paste['language']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin Warning -->
            <div class="bg-yellow-900/20 border border-yellow-500 rounded p-3 mb-4">
                <div class="flex items-center text-yellow-400">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span class="font-semibold">WARNING:</span>
                    <span class="ml-2">This content has been flagged <?= $flag_info['flag_count'] ?> times and is currently hidden from public view.</span>
                </div>
            </div>

            <!-- Content -->
            <div class="bg-gray-900 rounded-lg border">
                <div class="flex items-center justify-between px-4 py-2 border-b border-gray-700">
                    <span class="text-sm text-gray-400">Content Preview</span>
                    <div class="flex items-center space-x-2">
                        <label class="flex items-center text-sm">
                            <input type="checkbox" id="blurToggle" class="mr-2" onchange="toggleBlur()">
                            Blur sensitive content
                        </label>
                        <button onclick="copyContent()" class="text-gray-400 hover:text-white">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="p-4">
                    <pre id="pasteContent" class="whitespace-pre-wrap font-mono text-sm"><code class="language-<?= htmlspecialchars($paste['language'] ?: 'text') ?>"><?= htmlspecialchars($paste['content']) ?></code></pre>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3">Quick Actions</h3>
            <div class="flex space-x-3">
                <button onclick="approvePaste(<?= $paste_id ?>)" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded">
                    <i class="fas fa-check mr-1"></i>Approve Paste
                </button>
                <button onclick="removePaste(<?= $paste_id ?>)" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded">
                    <i class="fas fa-trash mr-1"></i>Remove Paste
                </button>
                <?php if ($paste['user_id']): ?>
                    <button onclick="warnUser('<?= $paste['user_id'] ?>')" class="bg-yellow-600 hover:bg-yellow-700 px-4 py-2 rounded">
                        <i class="fas fa-exclamation-triangle mr-1"></i>Warn User
                    </button>
                <?php endif; ?>
                <button onclick="addNote(<?= $paste_id ?>)" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                    <i class="fas fa-sticky-note mr-1"></i>Add Note
                </button>
            </div>
        </div>
    </div>

    <script>
    function toggleBlur() {
        const content = document.getElementById('pasteContent');
        const checkbox = document.getElementById('blurToggle');
        content.style.filter = checkbox.checked ? 'blur(5px)' : 'none';
    }

    function copyContent() {
        const content = document.getElementById('pasteContent').textContent;
        navigator.clipboard.writeText(content).then(() => {
            const btn = event.target;
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => {
                btn.innerHTML = originalIcon;
            }, 2000);
        });
    }

    function approvePaste(pasteId) {
        if (!confirm('Are you sure you want to approve this paste? This will clear all flags.')) return;
        submitAction('approve', { paste_id: pasteId });
    }

    function removePaste(pasteId) {
        if (!confirm('Are you sure you want to remove this paste? This action cannot be undone.')) return;
        submitAction('remove', { paste_id: pasteId });
    }

    function warnUser(userId) {
        const reason = prompt('Enter warning reason:');
        if (reason && reason.trim()) {
            submitAction('warn', { user_id: userId, reason: reason.trim() });
        }
    }

    function addNote(pasteId) {
        const note = prompt('Enter admin note:');
        if (note && note.trim()) {
            submitAction('add_note', { paste_id: pasteId, note: note.trim() });
        }
    }

    function viewFlagDetails(pasteId) {
        window.open(`admin-flag-details.php?paste_id=${pasteId}`, 'flagDetails', 'width=800,height=600');
    }

    function submitAction(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        Object.keys(data).forEach(key => formData.append(key, data[key]));

        fetch('admin-flagged.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
    </script>
</body>
</html>
