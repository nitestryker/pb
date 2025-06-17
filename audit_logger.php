<?php
class AuditLogger {
    private $db;
    
    public function __construct() {
        require_once 'database.php';
        $this->db = Database::getInstance()->getConnection();
        $this->createAuditTables();
    }
    
    private function createAuditTables() {
        // Audit logs table
        $this->db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT,
            action TEXT NOT NULL,
            resource_type TEXT,
            resource_id TEXT,
            ip_address TEXT,
            user_agent TEXT,
            details TEXT,
            severity TEXT DEFAULT 'info',
            created_at INTEGER DEFAULT (strftime('%s', 'now'))
        )");
        
        // Create indexes separately for SQLite
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_severity ON audit_logs(severity)");
        
        // Security events table for failed logins, suspicious activity
        $this->db->exec("CREATE TABLE IF NOT EXISTS security_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type TEXT NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            details TEXT,
            risk_level TEXT DEFAULT 'low',
            created_at INTEGER DEFAULT (strftime('%s', 'now'))
        )");
        
        // Create indexes separately for SQLite
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_security_events_event_type ON security_events(event_type)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_security_events_ip_address ON security_events(ip_address)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_security_events_created_at ON security_events(created_at)");
        
        // Rate limiting table
        $this->db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id TEXT PRIMARY KEY,
            identifier TEXT NOT NULL,
            action TEXT NOT NULL,
            count INTEGER DEFAULT 1,
            window_start INTEGER,
            expires_at INTEGER
        )");
        
        // Create indexes separately for SQLite
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_rate_limits_identifier ON rate_limits(identifier)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_rate_limits_action ON rate_limits(action)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_rate_limits_expires_at ON rate_limits(expires_at)");
    }
    
    public function log($action, $resource_type = null, $resource_id = null, $details = null, $severity = 'info') {
        // For performance-critical operations like login, defer logging
        if ($action === 'user_login_success' || $action === 'user_registration') {
            $this->deferredLog($action, $resource_type, $resource_id, $details, $severity);
            return;
        }
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (user_id, action, resource_type, resource_id, ip_address, user_agent, details, severity) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$user_id, $action, $resource_type, $resource_id, $ip_address, $user_agent, $details, $severity]);
    }
    
    private function deferredLog($action, $resource_type, $resource_id, $details, $severity) {
        // Log asynchronously to avoid blocking the response
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details);
        }
        
        // Use a background process or queue for better performance
        // For now, just do a quick insert without error handling
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, resource_type, resource_id, ip_address, user_agent, details, severity) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $action, $resource_type, $resource_id, $ip_address, $user_agent, $details, $severity]);
        } catch (Exception $e) {
            // Silently fail for deferred logs to avoid blocking the user
        }
    }
    
    public function logSecurityEvent($event_type, $details = null, $risk_level = 'low') {
        $ip_address = $this->getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO security_events (event_type, ip_address, user_agent, details, risk_level) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$event_type, $ip_address, $user_agent, $details, $risk_level]);
    }
    
    private function getClientIP() {
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
    
    public function getAuditLogs($user_id = null, $action = null, $limit = 100, $offset = 0) {
        $where = [];
        $params = [];
        
        if ($user_id) {
            $where[] = "user_id = ?";
            $params[] = $user_id;
        }
        
        if ($action) {
            $where[] = "action = ?";
            $params[] = $action;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare("
            SELECT * FROM audit_logs 
            {$whereClause}
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function cleanupOldLogs($days = 90) {
        $cutoff = time() - ($days * 86400);
        
        $stmt = $this->db->prepare("DELETE FROM audit_logs WHERE created_at < ?");
        $stmt->execute([$cutoff]);
        
        $stmt = $this->db->prepare("DELETE FROM security_events WHERE created_at < ?");
        $stmt->execute([$cutoff]);
        
        return $stmt->rowCount();
    }
}
?>
