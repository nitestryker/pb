
<?php
class SocialMediaIntegration {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->initializeTables();
    }
    
    private function initializeTables() {
        // Social media accounts linking
        $this->db->exec("CREATE TABLE IF NOT EXISTS user_social_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            platform TEXT NOT NULL,
            platform_user_id TEXT NOT NULL,
            platform_username TEXT,
            profile_url TEXT,
            access_token TEXT,
            refresh_token TEXT,
            token_expires_at INTEGER,
            is_active INTEGER DEFAULT 1,
            created_at INTEGER DEFAULT (strftime('%s', 'now')),
            updated_at INTEGER DEFAULT (strftime('%s', 'now')),
            FOREIGN KEY(user_id) REFERENCES users(id),
            UNIQUE(user_id, platform)
        )");
        
        // Social sharing analytics
        $this->db->exec("CREATE TABLE IF NOT EXISTS social_shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            paste_id INTEGER NOT NULL,
            platform TEXT NOT NULL,
            shared_by_user_id TEXT,
            share_url TEXT,
            clicks INTEGER DEFAULT 0,
            created_at INTEGER DEFAULT (strftime('%s', 'now')),
            FOREIGN KEY(paste_id) REFERENCES pastes(id),
            FOREIGN KEY(shared_by_user_id) REFERENCES users(id)
        )");
        
        // Social login providers
        $this->db->exec("CREATE TABLE IF NOT EXISTS social_login_providers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            client_id TEXT,
            client_secret TEXT,
            is_enabled INTEGER DEFAULT 0,
            auth_url TEXT,
            token_url TEXT,
            user_info_url TEXT,
            scopes TEXT DEFAULT '',
            created_at INTEGER DEFAULT (strftime('%s', 'now'))
        )");
        
        // Insert default providers
        $this->setupDefaultProviders();
    }
    
    private function setupDefaultProviders() {
        $providers = [
            [
                'name' => 'google',
                'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
                'scopes' => 'openid email profile'
            ],
            [
                'name' => 'github',
                'auth_url' => 'https://github.com/login/oauth/authorize',
                'token_url' => 'https://github.com/login/oauth/access_token',
                'user_info_url' => 'https://api.github.com/user',
                'scopes' => 'user:email'
            ],
            [
                'name' => 'twitter',
                'auth_url' => 'https://twitter.com/i/oauth2/authorize',
                'token_url' => 'https://api.twitter.com/2/oauth2/token',
                'user_info_url' => 'https://api.twitter.com/2/users/me',
                'scopes' => 'tweet.read users.read'
            ],
            [
                'name' => 'discord',
                'auth_url' => 'https://discord.com/api/oauth2/authorize',
                'token_url' => 'https://discord.com/api/oauth2/token',
                'user_info_url' => 'https://discord.com/api/users/@me',
                'scopes' => 'identify email'
            ]
        ];
        
        foreach ($providers as $provider) {
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO social_login_providers 
                (name, auth_url, token_url, user_info_url, scopes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $provider['name'],
                $provider['auth_url'],
                $provider['token_url'],
                $provider['user_info_url'],
                $provider['scopes']
            ]);
        }
    }
    
    public function generateSocialLoginUrl($provider, $redirect_uri) {
        $stmt = $this->db->prepare("SELECT * FROM social_login_providers WHERE name = ? AND is_enabled = 1");
        $stmt->execute([$provider]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config || !$config['client_id']) {
            return false;
        }
        
        $state = bin2hex(random_bytes(32));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_provider'] = $provider;
        
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirect_uri,
            'scope' => $config['scopes'],
            'response_type' => 'code',
            'state' => $state
        ];
        
        return $config['auth_url'] . '?' . http_build_query($params);
    }
    
    public function handleSocialCallback($provider, $code, $state) {
        // Verify state parameter
        if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
            throw new Exception('Invalid state parameter');
        }
        
        $stmt = $this->db->prepare("SELECT * FROM social_login_providers WHERE name = ?");
        $stmt->execute([$provider]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception('Provider not found');
        }
        
        // Exchange code for access token
        $token_data = $this->exchangeCodeForToken($config, $code);
        
        // Get user info from provider
        $user_info = $this->getUserInfoFromProvider($config, $token_data['access_token']);
        
        // Create or link user account
        return $this->createOrLinkUser($provider, $user_info, $token_data);
    }
    
    private function exchangeCodeForToken($config, $code) {
        $data = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['token_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Failed to exchange code for token');
        }
        
        return json_decode($response, true);
    }
    
    private function getUserInfoFromProvider($config, $access_token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['user_info_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Failed to get user info');
        }
        
        return json_decode($response, true);
    }
    
    private function createOrLinkUser($provider, $user_info, $token_data) {
        // Normalize user info based on provider
        $normalized = $this->normalizeUserInfo($provider, $user_info);
        
        // Check if social account already exists
        $stmt = $this->db->prepare("SELECT user_id FROM user_social_accounts WHERE platform = ? AND platform_user_id = ?");
        $stmt->execute([$provider, $normalized['id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing social account
            $this->updateSocialAccount($provider, $normalized, $token_data, $existing['user_id']);
            return $existing['user_id'];
        }
        
        // Check if user exists by email
        if ($normalized['email']) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$normalized['email']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Link to existing user
                $this->createSocialAccount($provider, $normalized, $token_data, $user['id']);
                return $user['id'];
            }
        }
        
        // Create new user
        $user_id = $this->createUserFromSocial($normalized);
        $this->createSocialAccount($provider, $normalized, $token_data, $user_id);
        
        return $user_id;
    }
    
    private function normalizeUserInfo($provider, $user_info) {
        switch ($provider) {
            case 'google':
                return [
                    'id' => $user_info['id'],
                    'email' => $user_info['email'] ?? null,
                    'name' => $user_info['name'] ?? null,
                    'username' => $user_info['email'] ? explode('@', $user_info['email'])[0] : null,
                    'avatar' => $user_info['picture'] ?? null,
                    'profile_url' => null
                ];
                
            case 'github':
                return [
                    'id' => (string)$user_info['id'],
                    'email' => $user_info['email'] ?? null,
                    'name' => $user_info['name'] ?? null,
                    'username' => $user_info['login'] ?? null,
                    'avatar' => $user_info['avatar_url'] ?? null,
                    'profile_url' => $user_info['html_url'] ?? null
                ];
                
            case 'discord':
                return [
                    'id' => $user_info['id'],
                    'email' => $user_info['email'] ?? null,
                    'name' => $user_info['global_name'] ?? $user_info['username'],
                    'username' => $user_info['username'] ?? null,
                    'avatar' => $user_info['avatar'] ? 
                        "https://cdn.discordapp.com/avatars/{$user_info['id']}/{$user_info['avatar']}.png" : null,
                    'profile_url' => null
                ];
                
            default:
                return [
                    'id' => (string)$user_info['id'],
                    'email' => $user_info['email'] ?? null,
                    'name' => $user_info['name'] ?? null,
                    'username' => $user_info['username'] ?? null,
                    'avatar' => $user_info['avatar'] ?? $user_info['picture'] ?? null,
                    'profile_url' => $user_info['url'] ?? null
                ];
        }
    }
    
    private function createUserFromSocial($normalized) {
        $user_id = uniqid();
        $username = $this->generateUniqueUsername($normalized['username'] ?? $normalized['name'] ?? 'user');
        
        $stmt = $this->db->prepare("INSERT INTO users (id, username, email, created_at, profile_image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $username,
            $normalized['email'],
            time(),
            $normalized['avatar']
        ]);
        
        return $user_id;
    }
    
    private function generateUniqueUsername($base_username) {
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($base_username));
        $username = substr($username, 0, 20);
        
        if (strlen($username) < 3) {
            $username = 'user' . rand(1000, 9999);
        }
        
        $original = $username;
        $counter = 1;
        
        while (true) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if (!$stmt->fetch()) {
                return $username;
            }
            
            $username = $original . $counter;
            $counter++;
        }
    }
    
    private function createSocialAccount($provider, $normalized, $token_data, $user_id) {
        $stmt = $this->db->prepare("INSERT INTO user_social_accounts 
            (user_id, platform, platform_user_id, platform_username, profile_url, access_token, refresh_token, token_expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $expires_at = isset($token_data['expires_in']) ? time() + $token_data['expires_in'] : null;
        
        $stmt->execute([
            $user_id,
            $provider,
            $normalized['id'],
            $normalized['username'],
            $normalized['profile_url'],
            $token_data['access_token'] ?? null,
            $token_data['refresh_token'] ?? null,
            $expires_at
        ]);
    }
    
    private function updateSocialAccount($provider, $normalized, $token_data, $user_id) {
        $stmt = $this->db->prepare("UPDATE user_social_accounts SET 
            platform_username = ?, profile_url = ?, access_token = ?, refresh_token = ?, 
            token_expires_at = ?, updated_at = strftime('%s', 'now')
            WHERE user_id = ? AND platform = ?");
        
        $expires_at = isset($token_data['expires_in']) ? time() + $token_data['expires_in'] : null;
        
        $stmt->execute([
            $normalized['username'],
            $normalized['profile_url'],
            $token_data['access_token'] ?? null,
            $token_data['refresh_token'] ?? null,
            $expires_at,
            $user_id,
            $provider
        ]);
    }
    
    public function trackSocialShare($paste_id, $platform, $user_id = null, $share_url = null) {
        $stmt = $this->db->prepare("INSERT INTO social_shares (paste_id, platform, shared_by_user_id, share_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$paste_id, $platform, $user_id, $share_url]);
        return $this->db->lastInsertId();
    }
    
    public function incrementShareClick($share_id) {
        $stmt = $this->db->prepare("UPDATE social_shares SET clicks = clicks + 1 WHERE id = ?");
        $stmt->execute([$share_id]);
    }
    
    public function getEnabledProviders() {
        $stmt = $this->db->query("SELECT name, client_id FROM social_login_providers WHERE is_enabled = 1 AND client_id IS NOT NULL AND client_id != ''");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUserSocialAccounts($user_id) {
        $stmt = $this->db->prepare("SELECT platform, platform_username, profile_url, is_active FROM user_social_accounts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function unlinkSocialAccount($user_id, $platform) {
        $stmt = $this->db->prepare("DELETE FROM user_social_accounts WHERE user_id = ? AND platform = ?");
        return $stmt->execute([$user_id, $platform]);
    }
}
?>
