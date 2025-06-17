
<?php
require_once 'database.php';

class AchievementsHelper {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check and award achievements for a user action
     */
    public function checkAchievements($user_id, $action, $data = []) {
        if (!$user_id) return [];
        
        $new_achievements = [];
        
        switch ($action) {
            case 'paste_created':
                $new_achievements = array_merge(
                    $new_achievements,
                    $this->checkPasteCreatedAchievements($user_id, $data)
                );
                break;
                
            case 'paste_viewed':
                $new_achievements = array_merge(
                    $new_achievements,
                    $this->checkPasteViewAchievements($user_id, $data)
                );
                break;
                
            case 'chain_created':
                $new_achievements = array_merge(
                    $new_achievements,
                    $this->checkChainAchievements($user_id, $data)
                );
                break;
                
            case 'fork_created':
                $new_achievements = array_merge(
                    $new_achievements,
                    $this->checkForkAchievements($user_id, $data)
                );
                break;
                
            case 'user_followed':
                $new_achievements = array_merge(
                    $new_achievements,
                    $this->checkSocialAchievements($user_id, $data)
                );
                break;
                
            case 'comment_created':
                $new_achievements = array_merge(
                    $new_achievements,
                    $this->checkCommentAchievements($user_id, $data)
                );
                break;
                
            case 'collection_created':
                $new_achievements = array_merge(
                    $new_achievements,
                    $this->checkCollectionAchievements($user_id, $data)
                );
                break;
        }
        
        return $new_achievements;
    }
    
    private function checkPasteCreatedAchievements($user_id, $data) {
        $achievements = [];
        
        // Get total paste count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $paste_count = $stmt->fetchColumn();
        
        // First paste
        if ($paste_count == 1) {
            $achievements[] = $this->awardAchievement($user_id, 'first_paste');
        }
        
        // Paste milestones
        $milestones = [10 => 'paste_creator_10', 50 => 'paste_creator_50', 100 => 'paste_creator_100'];
        foreach ($milestones as $count => $achievement_name) {
            if ($paste_count == $count) {
                $achievements[] = $this->awardAchievement($user_id, $achievement_name);
            }
        }
        
        // Check time-based achievements
        $hour = (int)date('H');
        if ($hour >= 0 && $hour < 6) {
            $this->updateProgress($user_id, 'night_owl', 1, 10);
            if ($this->getProgress($user_id, 'night_owl') >= 10) {
                $achievements[] = $this->awardAchievement($user_id, 'night_owl');
            }
        }
        
        // Language diversity
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT language) FROM pastes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $language_count = $stmt->fetchColumn();
        
        if ($language_count >= 10) {
            $achievements[] = $this->awardAchievement($user_id, 'language_explorer');
        }
        
        return array_filter($achievements);
    }
    
    private function checkPasteViewAchievements($user_id, $data) {
        $achievements = [];
        $paste_id = $data['paste_id'] ?? null;
        
        if (!$paste_id) return $achievements;
        
        // Get paste owner and current views
        $stmt = $this->db->prepare("SELECT user_id, views FROM pastes WHERE id = ?");
        $stmt->execute([$paste_id]);
        $paste_data = $stmt->fetch();
        
        if ($paste_data && $paste_data['user_id'] === $user_id) {
            $views = $paste_data['views'];
            
            $milestones = [
                100 => 'popular_paste_100',
                500 => 'popular_paste_500',
                1000 => 'popular_paste_1000'
            ];
            
            foreach ($milestones as $view_count => $achievement_name) {
                if ($views == $view_count) {
                    $achievements[] = $this->awardAchievement($user_id, $achievement_name);
                }
            }
        }
        
        return array_filter($achievements);
    }
    
    private function checkChainAchievements($user_id, $data) {
        $achievements = [];
        
        if ($data['is_first_chain'] ?? false) {
            $achievements[] = $this->awardAchievement($user_id, 'first_chain');
        }
        
        if ($data['is_contribution'] ?? false) {
            $achievements[] = $this->awardAchievement($user_id, 'chain_contributor');
        }
        
        return array_filter($achievements);
    }
    
    private function checkForkAchievements($user_id, $data) {
        $achievements = [];
        
        // Check if this is user's first fork
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND original_paste_id IS NOT NULL");
        $stmt->execute([$user_id]);
        $fork_count = $stmt->fetchColumn();
        
        if ($fork_count == 1) {
            $achievements[] = $this->awardAchievement($user_id, 'first_fork');
        }
        
        return array_filter($achievements);
    }
    
    private function checkSocialAchievements($user_id, $data) {
        $achievements = [];
        
        // Check following count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
        $stmt->execute([$user_id]);
        $following_count = $stmt->fetchColumn();
        
        if ($following_count >= 5) {
            $achievements[] = $this->awardAchievement($user_id, 'social_butterfly');
        }
        
        return array_filter($achievements);
    }
    
    private function checkCommentAchievements($user_id, $data) {
        $achievements = [];
        
        // Count total comments by user
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM paste_comments WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $comment_count = $stmt->fetchColumn();
        
        if ($comment_count >= 10) {
            $achievements[] = $this->awardAchievement($user_id, 'commenter');
        }
        
        return array_filter($achievements);
    }
    
    private function checkCollectionAchievements($user_id, $data) {
        $achievements = [];
        
        // Check if this is user's first collection
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM collections WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $collection_count = $stmt->fetchColumn();
        
        if ($collection_count == 1) {
            $achievements[] = $this->awardAchievement($user_id, 'collection_creator');
        }
        
        return array_filter($achievements);
    }
    
    /**
     * Award an achievement to a user
     */
    public function awardAchievement($user_id, $achievement_name) {
        try {
            // Check if user already has this achievement
            $stmt = $this->db->prepare("
                SELECT ua.id FROM user_achievements ua 
                JOIN achievements a ON ua.achievement_id = a.id 
                WHERE ua.user_id = ? AND a.name = ?
            ");
            $stmt->execute([$user_id, $achievement_name]);
            
            if ($stmt->fetch()) {
                return null; // Already has achievement
            }
            
            // Get achievement details
            $stmt = $this->db->prepare("SELECT * FROM achievements WHERE name = ?");
            $stmt->execute([$achievement_name]);
            $achievement = $stmt->fetch();
            
            if (!$achievement) {
                return null; // Achievement doesn't exist
            }
            
            // Award the achievement
            $stmt = $this->db->prepare("
                INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user_id, $achievement['id'], time()]);
            
            return $achievement;
            
        } catch (PDOException $e) {
            error_log("Error awarding achievement: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update progress towards an achievement
     */
    public function updateProgress($user_id, $achievement_name, $increment, $target) {
        try {
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO user_achievement_progress 
                (user_id, achievement_name, current_progress, target_progress, last_updated) 
                VALUES (?, ?, COALESCE((SELECT current_progress FROM user_achievement_progress WHERE user_id = ? AND achievement_name = ?), 0) + ?, ?, ?)
            ");
            $stmt->execute([$user_id, $achievement_name, $user_id, $achievement_name, $increment, $target, time()]);
        } catch (PDOException $e) {
            error_log("Error updating progress: " . $e->getMessage());
        }
    }
    
    /**
     * Get current progress for an achievement
     */
    public function getProgress($user_id, $achievement_name) {
        $stmt = $this->db->prepare("
            SELECT current_progress FROM user_achievement_progress 
            WHERE user_id = ? AND achievement_name = ?
        ");
        $stmt->execute([$user_id, $achievement_name]);
        $result = $stmt->fetch();
        return $result ? $result['current_progress'] : 0;
    }
    
    /**
     * Get user's achievements
     */
    public function getUserAchievements($user_id, $limit = null) {
        $sql = "
            SELECT a.*, ua.unlocked_at 
            FROM user_achievements ua 
            JOIN achievements a ON ua.achievement_id = a.id 
            WHERE ua.user_id = ? 
            ORDER BY ua.unlocked_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user's achievement stats
     */
    public function getUserAchievementStats($user_id) {
        // Total achievements unlocked
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_achievements WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $unlocked_count = $stmt->fetchColumn();
        
        // Total achievements available
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM achievements WHERE is_active = 1");
        $stmt->execute();
        $total_count = $stmt->fetchColumn();
        
        // Total points earned
        $stmt = $this->db->prepare("
            SELECT SUM(a.points) 
            FROM user_achievements ua 
            JOIN achievements a ON ua.achievement_id = a.id 
            WHERE ua.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $total_points = $stmt->fetchColumn() ?: 0;
        
        return [
            'unlocked_count' => $unlocked_count,
            'total_count' => $total_count,
            'completion_percentage' => $total_count > 0 ? round(($unlocked_count / $total_count) * 100, 1) : 0,
            'total_points' => $total_points
        ];
    }
    
    /**
     * Get recent achievements for activity feed
     */
    public function getRecentAchievements($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT a.*, ua.unlocked_at, u.username 
            FROM user_achievements ua 
            JOIN achievements a ON ua.achievement_id = a.id 
            JOIN users u ON ua.user_id = u.id 
            ORDER BY ua.unlocked_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
