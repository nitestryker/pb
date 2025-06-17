
<?php
require_once 'database.php';

$db = Database::getInstance()->getConnection();

// First, let's check if paste ID 1 exists
$stmt = $db->prepare("SELECT id, title FROM pastes WHERE id = 1");
$stmt->execute();
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    echo "Paste ID 1 not found. Let's check what pastes exist:\n";
    $stmt = $db->query("SELECT id, title FROM pastes ORDER BY id LIMIT 5");
    $pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pastes as $p) {
        echo "Paste ID: {$p['id']}, Title: {$p['title']}\n";
    }
    exit;
}

echo "Found paste ID 1: {$paste['title']}\n";

// Clear any existing flags for this paste
$stmt = $db->prepare("DELETE FROM paste_flags WHERE paste_id = 1");
$stmt->execute();
echo "Cleared existing flags\n";

// Add 3 test flags
$flag_types = ['spam', 'offensive', 'inappropriate'];
$test_ips = ['192.168.1.100', '192.168.1.101', '192.168.1.102'];

for ($i = 0; $i < 3; $i++) {
    $stmt = $db->prepare("INSERT INTO paste_flags (paste_id, user_id, ip_address, flag_type, reason, description, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        1, // paste_id
        null, // user_id (anonymous)
        $test_ips[$i], // ip_address
        $flag_types[$i], // flag_type
        'Test flag reason', // reason
        'This is a test flag for debugging purposes', // description
        time() - (3600 * $i), // created_at (staggered times)
        'pending' // status
    ]);
    echo "Added flag " . ($i + 1) . ": {$flag_types[$i]}\n";
}

// Update the paste flags count
$stmt = $db->prepare("UPDATE pastes SET flags = 3 WHERE id = 1");
$stmt->execute();
echo "Updated paste flags count to 3\n";

// Verify the flags were added
$stmt = $db->prepare("SELECT COUNT(*) as count FROM paste_flags WHERE paste_id = 1 AND status = 'pending'");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "Verification: Paste ID 1 now has {$count} pending flags\n";

// Check the auto-blur threshold setting
require_once 'settings_helper.php';
$auto_blur_threshold = SiteSettings::get('auto_blur_threshold', 3);
echo "Auto-blur threshold is set to: {$auto_blur_threshold}\n";

if ($count >= $auto_blur_threshold) {
    echo "✅ Paste should now be auto-blurred when viewed!\n";
} else {
    echo "❌ Paste will not be auto-blurred (count: {$count}, threshold: {$auto_blur_threshold})\n";
}

echo "\nYou can now visit the paste at: http://0.0.0.0:8000/?id=1\n";
?>
