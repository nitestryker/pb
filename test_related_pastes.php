
<?php
require_once 'database.php';
require_once 'related_pastes_helper.php';

$db = Database::getInstance()->getConnection();

// Create test pastes if they don't exist
$test_pastes = [
    ['title' => 'JavaScript Array Methods', 'content' => 'const arr = [1,2,3]; arr.map(x => x * 2);', 'language' => 'javascript', 'tags' => 'javascript, array, es6'],
    ['title' => 'Python List Comprehension', 'content' => 'numbers = [1,2,3]\nsquared = [x**2 for x in numbers]', 'language' => 'python', 'tags' => 'python, list, comprehension'],
    ['title' => 'JavaScript Functions', 'content' => 'function greet(name) { return `Hello ${name}`; }', 'language' => 'javascript', 'tags' => 'javascript, function, es6'],
    ['title' => 'PHP Array Functions', 'content' => '$arr = [1,2,3]; $result = array_map(function($x) { return $x * 2; }, $arr);', 'language' => 'php', 'tags' => 'php, array, function'],
];

echo "Creating test pastes...\n";
foreach ($test_pastes as $paste) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE title = ?");
    $stmt->execute([$paste['title']]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO pastes (title, content, language, tags, is_public, created_at) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute([$paste['title'], $paste['content'], $paste['language'], $paste['tags'], time()]);
        echo "Created: {$paste['title']}\n";
    } else {
        echo "Already exists: {$paste['title']}\n";
    }
}

// Test related pastes functionality
$stmt = $db->prepare("SELECT id, title FROM pastes WHERE title = ?");
$stmt->execute(['JavaScript Array Methods']);
$test_paste = $stmt->fetch();

if ($test_paste) {
    echo "\nTesting related pastes for: {$test_paste['title']} (ID: {$test_paste['id']})\n";
    
    $helper = new RelatedPastesHelper($db);
    $related = $helper->getRelatedPastes($test_paste['id'], 5);
    
    echo "Found " . count($related) . " related pastes:\n";
    foreach ($related as $rel) {
        echo "  - {$rel['title']} ({$rel['language']})\n";
    }
    
    if (empty($related)) {
        echo "No related pastes found. This might be why the feature isn't showing up.\n";
        echo "Make sure you have multiple pastes with similar languages or tags.\n";
    }
} else {
    echo "Test paste not found.\n";
}

echo "\nDone!\n";
?>
