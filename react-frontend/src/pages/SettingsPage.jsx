import { useState, useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';
import { useTheme } from '../contexts/ThemeContext';
import { updateUserSettings } from '../utils/api';

const SettingsPage = () => {
  const { user } = useUser();
  const { theme, setTheme } = useTheme();
  const [activeTab, setActiveTab] = useState('security');
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  
  // Form states
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    new_password: '',
    confirm_password: ''
  });
  
  const [preferencesForm, setPreferencesForm] = useState({
    theme_preference: theme,
    email_notifications: true,
    default_paste_expiry: '604800',
    default_paste_public: true,
    timezone: 'UTC'
  });
  
  const [privacyForm, setPrivacyForm] = useState({
    profile_visibility: 'public',
    show_paste_count: true,
    allow_messages: true
  });
  
  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/settings' } }} />;
  }
  
  // Load user settings on initial render
  useEffect(() => {
    // In a real app, you would fetch the user's settings from the backend
    // For now, we'll use default values
    setPreferencesForm(prev => ({
      ...prev,
      theme_preference: theme
    }));
  }, [theme]);
  
  const handlePasswordChange = (e) => {
    const { name, value } = e.target;
    setPasswordForm(prev => ({
      ...prev,
      [name]: value
    }));
  };
  
  const handlePreferencesChange = (e) => {
    const { name, value, type, checked } = e.target;
    setPreferencesForm(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
    
    // Apply theme change immediately
    if (name === 'theme_preference') {
      setTheme(value);
    }
  };
  
  const handlePrivacyChange = (e) => {
    const { name, value, type, checked } = e.target;
    setPrivacyForm(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };
  
  const handlePasswordSubmit = async (e) => {
    e.preventDefault();
    setSuccessMessage('');
    setErrorMessage('');
    setIsLoading(true);
    
    try {
      // Validate passwords
      if (passwordForm.new_password !== passwordForm.confirm_password) {
        throw new Error('New passwords do not match');
      }
      
      if (passwordForm.new_password.length < 6) {
        throw new Error('New password must be at least 6 characters long');
      }
      
      // Send the password update request to the backend
      const response = await updateUserSettings('password', passwordForm);
      
      if (response.success) {
        setSuccessMessage('Password updated successfully!');
        setPasswordForm({
          current_password: '',
          new_password: '',
          confirm_password: ''
        });
      } else {
        throw new Error(response.message || 'Failed to update password');
      }
    } catch (err) {
      console.error('Password update error:', err);
      setErrorMessage(err.message || 'Failed to update password. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };
  
  const handlePreferencesSubmit = async (e) => {
    e.preventDefault();
    setSuccessMessage('');
    setErrorMessage('');
    setIsLoading(true);
    
    try {
      // Send the preferences update request to the backend
      const response = await updateUserSettings('preferences', preferencesForm);
      
      if (response.success) {
        setSuccessMessage('Preferences updated successfully!');
      } else {
        throw new Error(response.message || 'Failed to update preferences');
      }
    } catch (err) {
      console.error('Preferences update error:', err);
      setErrorMessage(err.message || 'Failed to update preferences. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };
  
  const handlePrivacySubmit = async (e) => {
    e.preventDefault();
    setSuccessMessage('');
    setErrorMessage('');
    setIsLoading(true);
    
    try {
      // Send the privacy settings update request to the backend
      const response = await updateUserSettings('privacy', privacyForm);
      
      if (response.success) {
        setSuccessMessage('Privacy settings updated successfully!');
      } else {
        throw new Error(response.message || 'Failed to update privacy settings');
      }
    } catch (err) {
      console.error('Privacy settings update error:', err);
      setErrorMessage(err.message || 'Failed to update privacy settings. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
        {/* Header */}
        <div className="border-b border-gray-200 dark:border-gray-700 p-6">
          <h1 className="text-2xl font-bold flex items-center">
            <i className="fas fa-cogs mr-3 text-blue-500"></i>
            Account Settings
          </h1>
          <p className="text-gray-600 dark:text-gray-400 mt-1">Manage your account preferences and security settings</p>
        </div>

        {/* Success/Error Messages */}
        {successMessage && (
          <div className="m-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
            <i className="fas fa-check-circle mr-2"></i>{successMessage}
          </div>
        )}

        {errorMessage && (
          <div className="m-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
            <i className="fas fa-exclamation-circle mr-2"></i>{errorMessage}
          </div>
        )}

        {/* Settings Tabs */}
        <div className="border-b border-gray-200 dark:border-gray-700">
          <nav className="flex space-x-8 px-6" aria-label="Tabs">
            <button 
              onClick={() => setActiveTab('security')} 
              className={`tab-button py-4 px-1 text-sm font-medium ${
                activeTab === 'security' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-shield-alt mr-2"></i>Security
            </button>
            <button 
              onClick={() => setActiveTab('preferences')} 
              className={`tab-button py-4 px-1 text-sm font-medium ${
                activeTab === 'preferences' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-sliders-h mr-2"></i>Preferences
            </button>
            <button 
              onClick={() => setActiveTab('privacy')} 
              className={`tab-button py-4 px-1 text-sm font-medium ${
                activeTab === 'privacy' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-user-shield mr-2"></i>Privacy
            </button>
          </nav>
        </div>

        {/* Security Tab */}
        <div className={`p-6 ${activeTab !== 'security' ? 'hidden' : ''}`}>
          <h2 className="text-lg font-semibold mb-4">Security Settings</h2>

          {/* Change Password */}
          <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
            <h3 className="text-md font-medium mb-4 flex items-center">
              <i className="fas fa-key mr-2 text-yellow-500"></i>
              Change Password
            </h3>
            <form onSubmit={handlePasswordSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-2">Current Password</label>
                <input 
                  type="password" 
                  name="current_password" 
                  value={passwordForm.current_password}
                  onChange={handlePasswordChange}
                  required 
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">New Password</label>
                <input 
                  type="password" 
                  name="new_password" 
                  value={passwordForm.new_password}
                  onChange={handlePasswordChange}
                  required 
                  minLength="6"
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                />
                <p className="text-sm text-gray-500 mt-1">Minimum 6 characters</p>
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Confirm New Password</label>
                <input 
                  type="password" 
                  name="confirm_password" 
                  value={passwordForm.confirm_password}
                  onChange={handlePasswordChange}
                  required 
                  minLength="6"
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <button 
                type="submit" 
                className="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors"
                disabled={isLoading}
              >
                {isLoading ? (
                  <>
                    <i className="fas fa-spinner fa-spin mr-2"></i>
                    Updating...
                  </>
                ) : (
                  <>
                    <i className="fas fa-save mr-2"></i>
                    Update Password
                  </>
                )}
              </button>
            </form>
          </div>

          {/* Account Information */}
          <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
            <h3 className="text-md font-medium mb-4 flex items-center">
              <i className="fas fa-info-circle mr-2 text-blue-500"></i>
              Account Information
            </h3>
            <div className="grid md:grid-cols-2 gap-4 text-sm">
              <div>
                <span className="font-medium text-gray-600 dark:text-gray-400">Username:</span>
                <span className="ml-2">{user.username}</span>
              </div>
              <div>
                <span className="font-medium text-gray-600 dark:text-gray-400">Email:</span>
                <span className="ml-2">{user.email || 'Not set'}</span>
              </div>
              <div>
                <span className="font-medium text-gray-600 dark:text-gray-400">Member Since:</span>
                <span className="ml-2">{new Date(user.created_at * 1000).toLocaleDateString()}</span>
              </div>
              <div>
                <span className="font-medium text-gray-600 dark:text-gray-400">User ID:</span>
                <span className="ml-2 font-mono text-xs">{user.id}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Preferences Tab */}
        <div className={`p-6 ${activeTab !== 'preferences' ? 'hidden' : ''}`}>
          <h2 className="text-lg font-semibold mb-4">User Preferences</h2>

          <form onSubmit={handlePreferencesSubmit} className="space-y-6">
            {/* Theme Settings */}
            <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
              <h3 className="text-md font-medium mb-4 flex items-center">
                <i className="fas fa-palette mr-2 text-purple-500"></i>
                Theme & Display
              </h3>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2">Preferred Theme</label>
                  <select 
                    name="theme_preference" 
                    value={preferencesForm.theme_preference}
                    onChange={handlePreferencesChange}
                    className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600"
                  >
                    <option value="system">System Default</option>
                    <option value="light">Light Mode</option>
                    <option value="dark">Dark Mode</option>
                  </select>
                  <p className="text-sm text-gray-500 mt-1">Choose your preferred color scheme</p>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-2">Timezone</label>
                  <select 
                    name="timezone" 
                    value={preferencesForm.timezone}
                    onChange={handlePreferencesChange}
                    className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600"
                  >
                    <option value="UTC">UTC</option>
                    <option value="America/New_York">Eastern Time</option>
                    <option value="America/Chicago">Central Time</option>
                    <option value="America/Denver">Mountain Time</option>
                    <option value="America/Los_Angeles">Pacific Time</option>
                    <option value="Europe/London">London</option>
                    <option value="Europe/Paris">Paris</option>
                    <option value="Asia/Tokyo">Tokyo</option>
                  </select>
                </div>
              </div>
            </div>

            {/* Paste Defaults */}
            <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
              <h3 className="text-md font-medium mb-4 flex items-center">
                <i className="fas fa-code mr-2 text-green-500"></i>
                Default Paste Settings
              </h3>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2">Default Paste Expiry</label>
                  <select 
                    name="default_paste_expiry" 
                    value={preferencesForm.default_paste_expiry}
                    onChange={handlePreferencesChange}
                    className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600"
                  >
                    <option value="0">Never</option>
                    <option value="600">10 minutes</option>
                    <option value="3600">1 hour</option>
                    <option value="86400">1 day</option>
                    <option value="604800">1 week</option>
                  </select>
                </div>

                <div>
                  <label className="flex items-center space-x-2">
                    <input 
                      type="checkbox" 
                      name="default_paste_public" 
                      checked={preferencesForm.default_paste_public}
                      onChange={handlePreferencesChange}
                      className="rounded"
                    />
                    <span>Make pastes public by default</span>
                  </label>
                </div>
              </div>
            </div>

            {/* Notifications */}
            <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
              <h3 className="text-md font-medium mb-4 flex items-center">
                <i className="fas fa-bell mr-2 text-orange-500"></i>
                Notifications
              </h3>

              <div>
                <label className="flex items-center space-x-2">
                  <input 
                    type="checkbox" 
                    name="email_notifications" 
                    checked={preferencesForm.email_notifications}
                    onChange={handlePreferencesChange}
                    className="rounded"
                  />
                  <span>Receive email notifications for comments and messages</span>
                </label>
              </div>
            </div>

            <button 
              type="submit" 
              className="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors"
              disabled={isLoading}
            >
              {isLoading ? (
                <>
                  <i className="fas fa-spinner fa-spin mr-2"></i>
                  Saving...
                </>
              ) : (
                <>
                  <i className="fas fa-save mr-2"></i>
                  Save Preferences
                </>
              )}
            </button>
          </form>
        </div>

        {/* Privacy Tab */}
        <div className={`p-6 ${activeTab !== 'privacy' ? 'hidden' : ''}`}>
          <h2 className="text-lg font-semibold mb-4">Privacy Settings</h2>

          <form onSubmit={handlePrivacySubmit} className="space-y-6">
            {/* Profile Privacy */}
            <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
              <h3 className="text-md font-medium mb-4 flex items-center">
                <i className="fas fa-user-shield mr-2 text-indigo-500"></i>
                Profile Privacy
              </h3>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium mb-2">Profile Visibility</label>
                  <select 
                    name="profile_visibility" 
                    value={privacyForm.profile_visibility}
                    onChange={handlePrivacyChange}
                    className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600"
                  >
                    <option value="public">Public</option>
                    <option value="limited">Limited (Hide some details)</option>
                    <option value="private">Private (Username only)</option>
                  </select>
                  <p className="text-sm text-gray-500 mt-1">Control who can see your profile information</p>
                </div>

                <div>
                  <label className="flex items-center space-x-2">
                    <input 
                      type="checkbox" 
                      name="show_paste_count" 
                      checked={privacyForm.show_paste_count}
                      onChange={handlePrivacyChange}
                      className="rounded"
                    />
                    <span>Show paste count on profile</span>
                  </label>
                </div>

                <div>
                  <label className="flex items-center space-x-2">
                    <input 
                      type="checkbox" 
                      name="allow_messages" 
                      checked={privacyForm.allow_messages}
                      onChange={handlePrivacyChange}
                      className="rounded"
                    />
                    <span>Allow other users to send me messages</span>
                  </label>
                </div>
              </div>
            </div>

            <button 
              type="submit" 
              className="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors"
              disabled={isLoading}
            >
              {isLoading ? (
                <>
                  <i className="fas fa-spinner fa-spin mr-2"></i>
                  Saving...
                </>
              ) : (
                <>
                  <i className="fas fa-save mr-2"></i>
                  Save Privacy Settings
                </>
              )}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default SettingsPage;