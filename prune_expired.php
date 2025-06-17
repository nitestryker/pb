
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current timestamp
    $current_time = time();

    // Find and delete expired pastes
    $stmt = $db->prepare("SELECT id, title FROM pastes WHERE expire_time IS NOT NULL AND expire_time < ?");
    $stmt->execute([$current_time]);
    $expired_pastes = $stmt->fetchAll();

    // Delete the expired pastes and related data
    $delete_stmt = $db->prepare("DELETE FROM pastes WHERE id = ?");
    $delete_comments = $db->prepare("DELETE FROM comments WHERE paste_id = ?");
    $delete_views = $db->prepare("DELETE FROM paste_views WHERE paste_id = ?");
    $delete_favorites = $db->prepare("DELETE FROM user_pastes WHERE paste_id = ?");

    foreach ($expired_pastes as $paste) {
        // Delete related data first
        $delete_comments->execute([$paste['id']]);
        $delete_views->execute([$paste['id']]);
        $delete_favorites->execute([$paste['id']]);
        
        // Delete the paste itself
        $delete_stmt->execute([$paste['id']]);
        
        echo "Deleted expired paste: " . $paste['title'] . " (ID: " . $paste['id'] . ")\n";
    }

    echo "Pruning completed. Removed " . count($expired_pastes) . " expired pastes.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
