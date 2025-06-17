<?php
class RelatedPastesHelper {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get related pastes for a given paste ID
     */
    public function getRelatedPastes($paste_id, $limit = 5) {
        // First, try to get from cache
        $cached_results = $this->getCachedRelatedPastes($paste_id, $limit);
        if (!empty($cached_results)) {
            return $cached_results;
        }
        
        // If no cached results, calculate related pastes
        $related_pastes = $this->calculateRelatedPastes($paste_id, $limit);
        
        // Cache the results for future use
        $this->cacheRelatedPastes($paste_id, $related_pastes);
        
        return $related_pastes;
    }
    
    /**
     * Calculate related pastes based on user, language, and tags
     */
    private function calculateRelatedPastes($paste_id, $limit = 5) {
        // Get current paste metadata
        $stmt = $this->db->prepare("SELECT user_id, language, tags FROM pastes WHERE id = ?");
        $stmt->execute([$paste_id]);
        $current_paste = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_paste) {
            return [];
        }
        
        // Parse tags from comma-separated string
        $tags = [];
        if (!empty($current_paste['tags'])) {
            $tags = array_map('trim', explode(',', $current_paste['tags']));
            $tags = array_filter($tags); // Remove empty tags
        }
        
        // Build the query for related pastes
        $conditions = [];
        $params = [$paste_id]; // First param for id != ?
        
        // Same user condition
        if ($current_paste['user_id']) {
            $conditions[] = "p.user_id = ?";
            $params[] = $current_paste['user_id'];
        }
        
        // Same language condition
        if ($current_paste['language']) {
            $conditions[] = "p.language = ?";
            $params[] = $current_paste['language'];
        }
        
        // Tags condition - check if any tag matches
        if (!empty($tags)) {
            $tag_conditions = [];
            foreach ($tags as $tag) {
                $tag_conditions[] = "p.tags LIKE ?";
                $params[] = '%' . $tag . '%';
            }
            if (!empty($tag_conditions)) {
                $conditions[] = '(' . implode(' OR ', $tag_conditions) . ')';
            }
        }
        
        if (empty($conditions)) {
            // Fallback: just get recent public pastes
            $conditions[] = "p.is_public = 1 AND p.zero_knowledge = 0";
            $params[] = 1;
        }
        
        $where_clause = implode(' OR ', $conditions);
        
        $sql = "
            SELECT DISTINCT p.id, p.title, p.created_at, p.language, p.views, u.username,
                   CASE 
                       WHEN p.user_id = ? THEN 3
                       WHEN p.language = ? THEN 2
                       ELSE 1
                   END as relevance_score
            FROM pastes p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id != ?
            AND p.is_public = 1 AND p.zero_knowledge = 0
            AND (p.expire_time IS NULL OR p.expire_time > ?)
            AND ($where_clause)
            ORDER BY relevance_score DESC, p.created_at DESC
            LIMIT ?
        ";
        
        // Add relevance scoring parameters
        $final_params = [
            $current_paste['user_id'],
            $current_paste['language'],
            ...$params,
            time(),
            $limit
        ];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($final_params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get cached related pastes
     */
    private function getCachedRelatedPastes($paste_id, $limit) {
        $stmt = $this->db->prepare("
            SELECT p.id, p.title, p.created_at, p.language, p.views, u.username
            FROM paste_related_cache prc
            JOIN pastes p ON prc.related_paste_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE prc.paste_id = ?
            AND p.is_public = 1 AND p.zero_knowledge = 0
            AND (p.expire_time IS NULL OR p.expire_time > ?)
            ORDER BY prc.relevance_score DESC, p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$paste_id, time(), $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cache related pastes
     */
    private function cacheRelatedPastes($paste_id, $related_pastes) {
        if (empty($related_pastes)) {
            return;
        }
        
        // Clear existing cache for this paste
        $stmt = $this->db->prepare("DELETE FROM paste_related_cache WHERE paste_id = ?");
        $stmt->execute([$paste_id]);
        
        // Insert new cache entries
        $stmt = $this->db->prepare("
            INSERT INTO paste_related_cache (paste_id, related_paste_id, relevance_score) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($related_pastes as $index => $related_paste) {
            $relevance_score = isset($related_paste['relevance_score']) ? $related_paste['relevance_score'] : (5 - $index);
            $stmt->execute([$paste_id, $related_paste['id'], $relevance_score]);
        }
    }
    
    /**
     * Clear cache for a specific paste (call when paste is updated)
     */
    public function clearCache($paste_id) {
        $stmt = $this->db->prepare("DELETE FROM paste_related_cache WHERE paste_id = ? OR related_paste_id = ?");
        $stmt->execute([$paste_id, $paste_id]);
    }
    
    /**
     * Clean old cache entries (call daily via cron)
     */
    public function cleanOldCache($days_old = 7) {
        $cutoff = time() - ($days_old * 24 * 60 * 60);
        $stmt = $this->db->prepare("DELETE FROM paste_related_cache WHERE created_at < ?");
        $stmt->execute([$cutoff]);
    }
}
?>
