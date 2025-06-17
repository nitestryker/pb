
<?php
// Script to fix database locks and optimize SQLite database

echo "Fixing database locks...\n";

try {
    // Close any existing connections
    $pdo = new PDO('sqlite:database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable WAL mode for better concurrency
    $pdo->exec('PRAGMA journal_mode=WAL;');
    echo "Enabled WAL mode\n";
    
    // Optimize database
    $pdo->exec('PRAGMA optimize;');
    echo "Optimized database\n";
    
    // Vacuum the database to clean up
    $pdo->exec('VACUUM;');
    echo "Vacuumed database\n";
    
    // Check integrity
    $result = $pdo->query('PRAGMA integrity_check;')->fetchColumn();
    echo "Database integrity: $result\n";
    
    $pdo = null;
    echo "Database lock fix completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // If database is still locked, try to forcefully close connections
    if (strpos($e->getMessage(), 'database is locked') !== false) {
        echo "Attempting to force close connections...\n";
        
        // Remove any journal files that might be causing locks
        if (file_exists('database.sqlite-journal')) {
            unlink('database.sqlite-journal');
            echo "Removed journal file\n";
        }
        
        if (file_exists('database.sqlite-wal')) {
            unlink('database.sqlite-wal');
            echo "Removed WAL file\n";
        }
        
        if (file_exists('database.sqlite-shm')) {
            unlink('database.sqlite-shm');
            echo "Removed shared memory file\n";
        }
        
        echo "Please restart your application\n";
    }
}
?>
