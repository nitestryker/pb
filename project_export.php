
<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /?page=login');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

if (!isset($_GET['project_id']) || !isset($_GET['branch_id'])) {
    header('Location: project_manager.php');
    exit;
}

$project_id = $_GET['project_id'];
$branch_id = $_GET['branch_id'];

// Verify user has access to this project
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND (user_id = ? OR is_public = 1)");
$stmt->execute([$project_id, $user_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: project_manager.php');
    exit;
}

// Get branch info
$stmt = $db->prepare("SELECT * FROM project_branches WHERE id = ? AND project_id = ?");
$stmt->execute([$branch_id, $project_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: project_manager.php');
    exit;
}

// Get all files in this branch
$stmt = $db->prepare("
    SELECT pf.*, p.content, p.language 
    FROM project_files pf 
    JOIN pastes p ON pf.paste_id = p.id 
    WHERE pf.project_id = ? AND pf.branch_id = ? 
    ORDER BY pf.file_path, pf.file_name
");
$stmt->execute([$project_id, $branch_id]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($files)) {
    header("Location: project_manager.php?action=view&project_id=$project_id&error=no_files");
    exit;
}

// Create temporary directory for the export
$temp_dir = sys_get_temp_dir() . '/pasteforge_export_' . uniqid();
mkdir($temp_dir, 0755, true);

$project_dir = $temp_dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']);
mkdir($project_dir, 0755, true);

// Create files with proper directory structure
foreach ($files as $file) {
    $file_path = $file['file_path'] . $file['file_name'];
    $full_path = $project_dir . '/' . $file_path;
    
    // Create directory structure if needed
    $dir = dirname($full_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Write file content
    file_put_contents($full_path, $file['content']);
}

// Create a README with project info
$readme_content = "# " . $project['name'] . "\n\n";
if ($project['description']) {
    $readme_content .= $project['description'] . "\n\n";
}
$readme_content .= "**Branch:** " . $branch['name'] . "\n";
$readme_content .= "**Exported:** " . date('Y-m-d H:i:s') . "\n";
$readme_content .= "**Total Files:** " . count($files) . "\n";
$readme_content .= "**License:** " . $project['license_type'] . "\n\n";
$readme_content .= "This project was exported from PasteForge.\n";

file_put_contents($project_dir . '/README_EXPORT.md', $readme_content);

// Create ZIP file
$zip_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']) . '_' . 
                preg_replace('/[^a-zA-Z0-9_-]/', '_', $branch['name']) . '_' . 
                date('Y-m-d_H-i-s') . '.zip';

$zip = new ZipArchive();
$zip_path = $temp_dir . '/' . $zip_filename;

if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
    die('Cannot create ZIP file');
}

// Add all files to ZIP
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($project_dir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($project_dir) + 1);
        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

// Send ZIP file to browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);

// Clean up temporary files
function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    return rmdir($dir);
}

deleteDirectory($temp_dir);
exit;
?>
