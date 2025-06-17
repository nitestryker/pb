
<?php
require_once 'database.php';
require_once 'settings_helper.php';

class AISummaryHelper {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check if AI summary feature is enabled
     */
    public function isFeatureEnabled() {
        $stmt = $this->db->query("SELECT feature_enabled FROM ai_summary_settings WHERE id = 1");
        $result = $stmt->fetch();
        return $result ? (bool)$result['feature_enabled'] : false;
    }
    
    /**
     * Get AI summary settings
     */
    public function getSettings() {
        $stmt = $this->db->query("SELECT * FROM ai_summary_settings WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update AI summary settings
     */
    public function updateSettings($settings) {
        $stmt = $this->db->prepare("UPDATE ai_summary_settings SET 
            feature_enabled = ?, auto_generate = ?, min_paste_length = ?, 
            max_paste_length = ?, daily_limit = ?, requires_approval = ?, 
            allowed_languages = ? WHERE id = 1");
        
        return $stmt->execute([
            $settings['feature_enabled'] ?? 0,
            $settings['auto_generate'] ?? 0,
            $settings['min_paste_length'] ?? 100,
            $settings['max_paste_length'] ?? 10000,
            $settings['daily_limit'] ?? 100,
            $settings['requires_approval'] ?? 1,
            $settings['allowed_languages'] ?? 'javascript,python,php,java,cpp,csharp,html,css,sql'
        ]);
    }
    
    /**
     * Check if paste is eligible for AI summary
     */
    public function isPasteEligible($paste) {
        $settings = $this->getSettings();
        
        if (!$settings || !$settings['feature_enabled']) {
            return false;
        }
        
        $content_length = strlen($paste['content']);
        if ($content_length < $settings['min_paste_length'] || 
            $content_length > $settings['max_paste_length']) {
            return false;
        }
        
        $allowed_languages = explode(',', $settings['allowed_languages']);
        $allowed_languages = array_map('trim', $allowed_languages);
        
        return in_array(strtolower($paste['language']), $allowed_languages);
    }
    
    /**
     * Get existing AI summary for paste
     */
    public function getSummaryForPaste($paste_id) {
        $stmt = $this->db->prepare("SELECT * FROM ai_summaries WHERE paste_id = ? AND is_approved = 1 ORDER BY generated_at DESC LIMIT 1");
        $stmt->execute([$paste_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create AI summary request
     */
    public function createSummaryRequest($paste_id, $user_id = null) {
        $stmt = $this->db->prepare("INSERT INTO ai_summary_requests (paste_id, requested_by) VALUES (?, ?)");
        return $stmt->execute([$paste_id, $user_id]);
    }
    
    /**
     * Store AI summary
     */
    public function storeSummary($paste_id, $summary_text, $options = []) {
        $content_hash = md5($summary_text);
        
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO ai_summaries 
            (paste_id, summary_text, model_used, confidence_score, content_hash, token_count, processing_time_ms, is_approved) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $paste_id,
            $summary_text,
            $options['model_used'] ?? 'gpt-3.5-turbo',
            $options['confidence_score'] ?? 0.0,
            $content_hash,
            $options['token_count'] ?? 0,
            $options['processing_time_ms'] ?? 0,
            $options['is_approved'] ?? 0
        ]);
        
        if ($result) {
            $summary_id = $this->db->lastInsertId();
            
            // Update paste with summary ID if auto-approved
            if ($options['is_approved'] ?? 0) {
                $this->db->prepare("UPDATE pastes SET ai_summary_id = ? WHERE id = ?")
                         ->execute([$summary_id, $paste_id]);
            }
            
            return $summary_id;
        }
        
        return false;
    }
    
    /**
     * Approve AI summary
     */
    public function approveSummary($summary_id, $admin_user_id) {
        $stmt = $this->db->prepare("UPDATE ai_summaries SET is_approved = 1, approved_by = ?, approved_at = ? WHERE id = ?");
        $result = $stmt->execute([
            $admin_user_id,
            time(),
            $summary_id
        ]);
        
        if ($result) {
            // Update paste with approved summary ID
            $stmt = $this->db->prepare("SELECT paste_id FROM ai_summaries WHERE id = ?");
            $stmt->execute([$summary_id]);
            $paste_id = $stmt->fetchColumn();
            
            if ($paste_id) {
                $this->db->prepare("UPDATE pastes SET ai_summary_id = ? WHERE id = ?")
                         ->execute([$summary_id, $paste_id]);
            }
        }
        
        return $result;
    }
    
    /**
     * Get pending summaries for admin review
     */
    public function getPendingSummaries($limit = 20) {
        $stmt = $this->db->prepare("
            SELECT s.*, p.title, p.language, u.username as requested_by_username
            FROM ai_summaries s
            JOIN pastes p ON s.paste_id = p.id
            LEFT JOIN ai_summary_requests r ON s.paste_id = r.paste_id
            LEFT JOIN users u ON r.requested_by = u.id
            WHERE s.is_approved = 0
            ORDER BY s.generated_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get daily usage stats
     */
    public function getDailyUsage($date = null) {
        $date = $date ?: date('Y-m-d');
        $start_time = strtotime($date . ' 00:00:00');
        $end_time = strtotime($date . ' 23:59:59');
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ai_summaries WHERE generated_at BETWEEN ? AND ?");
        $stmt->execute([$start_time, $end_time]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Check if daily limit is reached
     */
    public function isDailyLimitReached() {
        $settings = $this->getSettings();
        $daily_usage = $this->getDailyUsage();
        
        return $daily_usage >= ($settings['daily_limit'] ?? 100);
    }
    
    /**
     * Generate content hash for paste content
     */
    public function generateContentHash($content) {
        return md5(trim($content));
    }
    
    /**
     * Check if summary already exists for content
     */
    public function summaryExistsForContent($paste_id, $content) {
        $content_hash = $this->generateContentHash($content);
        
        $stmt = $this->db->prepare("SELECT id FROM ai_summaries WHERE paste_id = ? AND content_hash = ? AND is_approved = 1");
        $stmt->execute([$paste_id, $content_hash]);
        
        return $stmt->fetch() !== false;
    }
}
?>
