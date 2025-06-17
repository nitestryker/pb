
<?php
require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "Setting up AI summary storage schema...\n";

    // Create ai_summaries table
    $db->exec("CREATE TABLE IF NOT EXISTS ai_summaries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        paste_id INTEGER NOT NULL,
        summary_text TEXT NOT NULL,
        model_used TEXT DEFAULT 'gpt-3.5-turbo',
        confidence_score FLOAT DEFAULT 0.0,
        generated_at INTEGER DEFAULT (strftime('%s', 'now')),
        is_approved BOOLEAN DEFAULT 0,
        approved_by TEXT NULL,
        approved_at INTEGER NULL,
        content_hash TEXT NOT NULL,
        token_count INTEGER DEFAULT 0,
        processing_time_ms INTEGER DEFAULT 0,
        FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id),
        UNIQUE(paste_id, content_hash)
    )");

    // Create ai_summary_requests table for tracking generation requests
    $db->exec("CREATE TABLE IF NOT EXISTS ai_summary_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        paste_id INTEGER NOT NULL,
        requested_by TEXT NULL,
        request_status TEXT DEFAULT 'pending',
        error_message TEXT NULL,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        completed_at INTEGER NULL,
        FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
        FOREIGN KEY (requested_by) REFERENCES users(id)
    )");

    // Create ai_summary_settings table for configuration
    $db->exec("CREATE TABLE IF NOT EXISTS ai_summary_settings (
        id INTEGER PRIMARY KEY,
        feature_enabled BOOLEAN DEFAULT 0,
        auto_generate BOOLEAN DEFAULT 0,
        min_paste_length INTEGER DEFAULT 100,
        max_paste_length INTEGER DEFAULT 10000,
        api_key_set BOOLEAN DEFAULT 0,
        daily_limit INTEGER DEFAULT 100,
        requires_approval BOOLEAN DEFAULT 1,
        allowed_languages TEXT DEFAULT 'javascript,python,php,java,cpp,csharp,html,css,sql'
    )");

    // Insert default settings
    $db->exec("INSERT OR IGNORE INTO ai_summary_settings (id) VALUES (1)");

    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_summaries_paste ON ai_summaries(paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_summaries_approved ON ai_summaries(is_approved)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_summary_requests_paste ON ai_summary_requests(paste_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ai_summary_requests_status ON ai_summary_requests(request_status)");

    // Add ai_summary_id column to pastes table if it doesn't exist
    $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('ai_summary_id', $columns)) {
        $db->exec("ALTER TABLE pastes ADD COLUMN ai_summary_id INTEGER DEFAULT NULL");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_pastes_ai_summary ON pastes(ai_summary_id)");
        echo "Added ai_summary_id column to pastes table\n";
    }

    echo "AI summary database schema setup complete!\n";
    echo "Features enabled:\n";
    echo "- AI summary storage with version tracking\n";
    echo "- Request tracking and status management\n";
    echo "- Admin approval workflow\n";
    echo "- Performance metrics and analytics\n";
    echo "- Configurable settings and limits\n";

} catch (PDOException $e) {
    echo "Error setting up AI summary schema: " . $e->getMessage() . "\n";
}
?>
