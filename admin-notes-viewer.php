<?php
require_once(__DIR__ . '/admin-session.php');
check_admin_auth();

$db = new PDO('sqlite:database.sqlite');
$paste_id = $_GET['paste_id'] ?? null;

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

// Get all admin notes for this paste
$stmt = $db->prepare("
    SELECT an.*, au.username as admin_username 
    FROM admin_notes an
    LEFT JOIN admin_users au ON an.admin_id = au.id
    WHERE an.paste_id = ?
    ORDER BY an.created_at DESC
");
$stmt->execute([$paste_id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle delete note action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_note') {
    $note_id = $_POST['note_id'] ?? null;
    if ($note_id && is_numeric($note_id)) {
        try {
            $stmt = $db->prepare("DELETE FROM admin_notes WHERE id = ? AND paste_id = ?");
            $stmt->execute([$note_id, $paste_id]);
            
            // Log the action
            require_once 'audit_logger.php';
            $audit_logger = new AuditLogger();
            $audit_logger->log('admin_note_deleted', $_SESSION['admin_id'], [
                'paste_id' => $paste_id,
                'note_id' => $note_id
            ]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Note deleted successfully']);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting note: ' . $e->getMessage()]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html class="dark">
<head>
    <title>Admin Notes - <?= htmlspecialchars($paste['title'] ?: 'Untitled') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-6">
        <!-- Paste Info -->
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold mb-2">Paste Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><strong>Title:</strong> <?= htmlspecialchars($paste['title'] ?: 'Untitled') ?></div>
                <div><strong>Created:</strong> <?= date('Y-m-d H:i:s', $paste['created_at']) ?></div>
                <div><strong>Author:</strong> <?= htmlspecialchars($paste['username'] ?: 'Anonymous') ?></div>
                <div><strong>Language:</strong> <?= htmlspecialchars($paste['language'] ?: 'None') ?></div>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="bg-gray-800 rounded-lg p-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-sticky-note mr-2"></i>
                    Admin Notes (<?= count($notes) ?>)
                </h3>
                <button onclick="addNewNote(<?= $paste_id ?>)" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm">
                    <i class="fas fa-plus mr-1"></i>Add New Note
                </button>
            </div>

            <?php if (empty($notes)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-clipboard text-gray-500 text-4xl mb-4"></i>
                    <p class="text-gray-400">No admin notes found for this paste</p>
                    <p class="text-sm text-gray-500 mt-2">Notes help track administrative actions and decisions</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($notes as $note): ?>
                        <div class="bg-gray-700 rounded-lg p-4 border-l-4 border-blue-500" data-note-id="<?= $note['id'] ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-user-shield text-blue-400"></i>
                                    <span class="font-semibold text-blue-300">
                                        <?= htmlspecialchars($note['admin_username'] ?: 'Admin ID ' . $note['admin_id']) ?>
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        <?= date('M j, Y g:i A', $note['created_at']) ?>
                                    </span>
                                </div>
                                <div class="flex space-x-1">
                                    <?php if ($note['admin_id'] == $_SESSION['admin_id']): ?>
                                        <button onclick="deleteNote(<?= $note['id'] ?>, <?= $paste_id ?>)" 
                                                class="text-red-400 hover:text-red-300 p-1 rounded hover:bg-red-500/20" 
                                                title="Delete Note">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="text-gray-200 whitespace-pre-wrap bg-gray-800 p-3 rounded border-l-2 border-gray-600">
                                <?= htmlspecialchars($note['note']) ?>
                            </div>
                            
                            <div class="mt-2 text-xs text-gray-500">
                                Note ID: <?= $note['id'] ?>
                                <?php if ($note['admin_id'] == $_SESSION['admin_id']): ?>
                                    • <span class="text-green-400">Your note</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="bg-gray-800 rounded-lg p-4 mt-6">
            <h3 class="text-lg font-semibold mb-3">Quick Actions</h3>
            <div class="flex space-x-3">
                <button onclick="viewPasteInNewTab(<?= $paste_id ?>)" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                    <i class="fas fa-eye mr-1"></i>View Paste
                </button>
                <button onclick="addNewNote(<?= $paste_id ?>)" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded">
                    <i class="fas fa-plus mr-1"></i>Add Note
                </button>
                <button onclick="closeModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded">
                    <i class="fas fa-times mr-1"></i>Close
                </button>
            </div>
        </div>
    </div>

    <script>
    function viewPasteInNewTab(pasteId) {
        window.open(`admin-paste-viewer.php?id=${pasteId}`, '_blank');
    }

    function closeModal() {
        // If this is in a modal, close it
        const modal = document.querySelector('.fixed.inset-0');
        if (modal) {
            modal.remove();
        } else {
            // If this is a popup window, close it
            window.close();
        }
    }

    function addNewNote(pasteId) {
        const note = prompt('Enter admin note for this paste:');
        if (note && note.trim()) {
            // Submit the note via the parent window's function
            if (window.parent && window.parent.submitAction) {
                window.parent.submitAction('add_note', { paste_id: pasteId, note: note.trim() });
                // Reload this window to show the new note
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                // Fallback: submit directly
                const formData = new FormData();
                formData.append('action', 'add_note');
                formData.append('paste_id', pasteId);
                formData.append('note', note.trim());
                
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
        }
    }

    function deleteNote(noteId, pasteId) {
        if (!confirm('Are you sure you want to delete this note? This action cannot be undone.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_note');
        formData.append('note_id', noteId);

        fetch('admin-notes-viewer.php?paste_id=' + pasteId, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Remove the note from the display with animation
                const noteElement = document.querySelector(`[data-note-id="${noteId}"]`);
                if (noteElement) {
                    noteElement.style.transition = 'all 0.3s ease';
                    noteElement.style.opacity = '0';
                    noteElement.style.transform = 'scale(0.95)';
                    
                    setTimeout(() => {
                        noteElement.remove();
                        
                        // Update the count in the header
                        const currentCount = document.querySelectorAll('[data-note-id]').length;
                        const headerElement = document.querySelector('h3 i.fa-sticky-note').parentElement;
                        if (headerElement) {
                            headerElement.innerHTML = `<i class="fas fa-sticky-note mr-2"></i>Admin Notes (${currentCount})`;
                        }
                        
                        // If no notes left, show the empty state
                        if (currentCount === 0) {
                            const notesContainer = document.querySelector('.space-y-4');
                            if (notesContainer) {
                                notesContainer.innerHTML = `
                                    <div class="text-center py-8">
                                        <i class="fas fa-clipboard text-gray-500 text-4xl mb-4"></i>
                                        <p class="text-gray-400">No admin notes found for this paste</p>
                                        <p class="text-sm text-gray-500 mt-2">Notes help track administrative actions and decisions</p>
                                    </div>
                                `;
                            }
                        }
                    }, 300);
                }
                showNotification(result.message, 'success');
            } else {
                showNotification(result.message || 'Failed to delete note', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Network error occurred', 'error');
        });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        const colors = {
            'success': 'bg-green-600',
            'error': 'bg-red-600', 
            'info': 'bg-blue-600'
        };
        
        notification.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded text-white shadow-lg transform transition-all duration-300 ${colors[type] || 'bg-gray-600'}`;
        notification.innerHTML = `
            <div class="flex items-center gap-2">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }
        }, 3000);
    }
    </script>
</body>
</html>
