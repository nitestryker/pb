<?php
class SiteSettings {
    private static $settings = null;
    
    public static function get($key = null, $default = null) {
        if (self::$settings === null) {
            self::loadSettings();
        }
        
        if ($key === null) {
            return self::$settings;
        }
        
        return isset(self::$settings[$key]) ? self::$settings[$key] : $default;
    }
    
    private static function loadSettings() {
        try {
            $db = new PDO('sqlite:database.sqlite');
            $stmt = $db->query("SELECT * FROM site_settings WHERE id = 1");
            self::$settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!self::$settings) {
                // Create default settings if none exist
                $db->exec("INSERT OR IGNORE INTO site_settings (id) VALUES (1)");
                $stmt = $db->query("SELECT * FROM site_settings WHERE id = 1");
                self::$settings = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            // Fallback to default settings
            self::$settings = [
                'site_name' => 'PasteForge',
                'theme_default' => 'dark',
                'site_logo' => null,
                'max_paste_size' => 500000,
                'default_expiry' => 604800,
                'maintenance_mode' => 0,
                'registration_enabled' => 1,
                'email_verification_required' => 0,
                'allowed_email_domains' => '*'
            ];
        }
    }
    
    public static function refresh() {
        self::$settings = null;
        self::loadSettings();
    }
    
    public static function getSiteName() {
        return self::get('site_name', 'PasteForge');
    }
    
    public static function getLogo() {
        $logo = self::get('site_logo');
        return ($logo && file_exists($logo)) ? $logo : null;
    }
    
    public static function getDefaultTheme() {
        return self::get('theme_default', 'dark');
    }
    
    public static function isMaintenanceMode() {
        return (bool) self::get('maintenance_mode', 0);
    }
}
?>
