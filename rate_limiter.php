<?php
class RateLimiter {
    private $db;
    private $limits = [
        'login_attempts' => ['limit' => 10, 'window' => 900], // 10 attempts per 15 minutes
        'paste_creation' => ['limit' => 50, 'window' => 3600], // 50 pastes per hour
        'comment_creation' => ['limit' => 100, 'window' => 3600], // 100 comments per hour
        'api_requests' => ['limit' => 250, 'window' => 3600], // 250 API requests per hour
        'password_reset' => ['limit' => 5, 'window' => 1800], // 5 password resets per 30 minutes
        'registration' => ['limit' => 5, 'window' => 3600], // 5 registrations per hour per IP
    ];
    
    public function __construct() {
        require_once 'database.php';
        $this->db = Database::getInstance()->getConnection();
        
        // Only cleanup occasionally to avoid slowing down every request
        if (rand(1, 100) === 1) {
            $this->cleanupExpired();
        }
    }
    
    public function checkLimit($action, $identifier = null) {
        if (!isset($this->limits[$action])) {
            return ['allowed' => true, 'remaining' => 0, 'reset_time' => 0];
        }
        
        $limit = $this->limits[$action]['limit'];
        $window = $this->limits[$action]['window'];
        
        if (!$identifier) {
            $identifier = $this->getClientIP();
        }
        
        $id = md5($identifier . ':' . $action);
        $now = time();
        $window_start = $now - $window;
        
        // Get current count with index hint
        $stmt = $this->db->prepare("
            SELECT count, window_start, expires_at 
            FROM rate_limits 
            WHERE id = ? AND expires_at > ?
            LIMIT 1
        ");
        $stmt->execute([$id, $now]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            // First request in window
            $this->createRecord($id, $identifier, $action, $now, $window);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset_time' => $now + $window
            ];
        }
        
        // Check if we're still in the same window
        if ($record['window_start'] >= $window_start) {
            if ($record['count'] >= $limit) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $record['expires_at']
                ];
            }
            
            // Increment count
            $stmt = $this->db->prepare("UPDATE rate_limits SET count = count + 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            return [
                'allowed' => true,
                'remaining' => $limit - ($record['count'] + 1),
                'reset_time' => $record['expires_at']
            ];
        } else {
            // New window
            $this->updateRecord($id, $identifier, $action, $now, $window);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset_time' => $now + $window
            ];
        }
    }
    
    public function hit($action, $identifier = null) {
        return $this->checkLimit($action, $identifier);
    }
    
    private function createRecord($id, $identifier, $action, $now, $window) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO rate_limits (id, identifier, action, count, window_start, expires_at) 
            VALUES (?, ?, ?, 1, ?, ?)
        ");
        $stmt->execute([$id, $identifier, $action, $now, $now + $window]);
    }
    
    private function updateRecord($id, $identifier, $action, $now, $window) {
        $stmt = $this->db->prepare("
            UPDATE rate_limits 
            SET count = 1, window_start = ?, expires_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([$now, $now + $window, $id]);
    }
    
    public function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function cleanupExpired() {
        $now = time();
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE expires_at < ?");
        $stmt->execute([$now]);
    }
    
    public function getStatus($action, $identifier = null) {
        if (!$identifier) {
            $identifier = $this->getClientIP();
        }
        
        $id = md5($identifier . ':' . $action);
        $now = time();
        
        $stmt = $this->db->prepare("
            SELECT count, expires_at 
            FROM rate_limits 
            WHERE id = ? AND expires_at > ?
        ");
        $stmt->execute([$id, $now]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            return [
                'count' => 0,
                'limit' => $this->limits[$action]['limit'] ?? 0,
                'remaining' => $this->limits[$action]['limit'] ?? 0,
                'reset_time' => 0
            ];
        }
        
        $limit = $this->limits[$action]['limit'] ?? 0;
        
        return [
            'count' => $record['count'],
            'limit' => $limit,
            'remaining' => max(0, $limit - $record['count']),
            'reset_time' => $record['expires_at']
        ];
    }
    
    public function resetLimit($action, $identifier = null) {
        if (!$identifier) {
            $identifier = $this->getClientIP();
        }
        
        $id = md5($identifier . ':' . $action);
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE id = ?");
        $stmt->execute([$id]);
    }
}
?>
