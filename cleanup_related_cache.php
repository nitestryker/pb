
<?php
require_once 'related_pastes_helper.php';
require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();
    $related_helper = new RelatedPastesHelper($db);
    
    // Clean cache entries older than 7 days
    $related_helper->cleanOldCache(7);
    
    echo "Related pastes cache cleanup completed successfully.\n";
    
} catch (Exception $e) {
    echo "Error during cache cleanup: " . $e->getMessage() . "\n";
}
?>
