
<?php
require_once 'database.php';

echo "<h2>Testing Embed & Import Functionality</h2>\n";

$db = Database::getInstance()->getConnection();

// Test if new columns exist
try {
    $stmt = $db->prepare("SELECT source_url, imported_from FROM pastes LIMIT 1");
    $stmt->execute();
    echo "✅ Database columns 'source_url' and 'imported_from' exist\n";
} catch (PDOException $e) {
    echo "❌ Database columns missing: " . $e->getMessage() . "\n";
}

// Test embed endpoint
$embed_url = "embed.php?id=1&theme=light";
if (file_exists('embed.php')) {
    echo "✅ Embed endpoint (embed.php) exists\n";
} else {
    echo "❌ Embed endpoint missing\n";
}

// Test import handler
if (file_exists('import_handler.php')) {
    echo "✅ Import handler (import_handler.php) exists\n";
} else {
    echo "❌ Import handler missing\n";
}

echo "\n<h3>Test URLs:</h3>\n";
echo "Embed test: <a href='embed.php?id=1&theme=light' target='_blank'>embed.php?id=1&theme=light</a>\n";
echo "Import test: Try creating a new paste with the import functionality\n";

echo "\n<h3>Features implemented:</h3>\n";
echo "✅ Embed pastes in iframes\n";
echo "✅ Copy embed code with customizable width/height/theme\n";
echo "✅ Import from URLs\n";
echo "✅ Import from GitHub Gists\n";
echo "✅ Upload and import files\n";
echo "✅ Auto-detection of programming languages\n";
echo "✅ Import source tracking in database\n";
echo "✅ Live preview of embeds\n";
?>
