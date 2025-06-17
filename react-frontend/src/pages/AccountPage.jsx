import { useState, useEffect } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';
import { getUserAccount } from '../utils/api';

const AccountPage = () => {
  const { user } = useUser();
  const [userData, setUserData] = useState(null);
  const [stats, setStats] = useState({
    totalPastes: 0,
    publicPastes: 0,
    totalViews: 0,
    collections: 0,
    following: 0,
    followers: 0
  });
  const [recentPastes, setRecentPastes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/account' } }} />;
  }

  useEffect(() => {
    const fetchUserData = async () => {
      try {
        setIsLoading(true);
        const response = await getUserAccount();
        
        if (response.success) {
          setUserData(response.user || null);
          setStats(response.stats || {});
          setRecentPastes(response.recent_pastes || []);
        } else {
          throw new Error(response.message || 'Failed to load account data');
        }
      } catch (error) {
        console.error('Error fetching user data:', error);
        setError('Failed to load account data. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchUserData();
  }, [user.id]);

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString();
  };

  // Calculate account age in days
  const accountCreatedDate = userData?.created_at 
    ? new Date(userData.created_at * 1000) 
    : new Date();
  const accountAgeDays = Math.floor((Date.now() - accountCreatedDate) / (1000 * 60 * 60 * 24));

  return (
    <div className="max-w-7xl mx-auto py-8 px-4">
      {/* Page Header */}
      <div className="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h1 className="text-3xl font-bold flex items-center text-gray-900 dark:text-white">
          <i className="fas fa-crown mr-3 text-yellow-500"></i>
          Account Overview
        </h1>
        <p className="text-gray-600 dark:text-gray-400 mt-2">View your account information and usage statistics</p>
      </div>

      {error && (
        <div className="mb-8 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      )}

      {isLoading ? (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-pulse">
          <div className="lg:col-span-1 space-y-6">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <div className="flex flex-col items-center">
                <div className="w-24 h-24 bg-gray-200 dark:bg-gray-700 rounded-full mb-4"></div>
                <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-2"></div>
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-4"></div>
              </div>
            </div>
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/3 mb-4"></div>
              <div className="space-y-3">
                {[...Array(5)].map((_, i) => (
                  <div key={i} className="flex justify-between">
                    <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
                    <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                  </div>
                ))}
              </div>
            </div>
          </div>
          <div className="lg:col-span-2 space-y-6">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/4 mb-6"></div>
                <div className="space-y-4">
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-full"></div>
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-full"></div>
                  <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Left Column - Profile & Quick Stats */}
          <div className="lg:col-span-1 space-y-6">
            {/* Profile Card */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <div className="text-center">
                <div className="relative inline-block">
                  <img 
                    src={userData?.profile_image || `https://www.gravatar.com/avatar/${userData?.email || user.username}?d=mp&s=128`} 
                    className="w-24 h-24 rounded-full mx-auto mb-4" 
                    alt="Profile" 
                  />
                  <div className="absolute bottom-0 right-0 w-6 h-6 bg-green-500 rounded-full border-2 border-white dark:border-gray-800"></div>
                </div>
                <h2 className="text-xl font-bold text-gray-900 dark:text-white">{userData?.username || user.username}</h2>
                <p className="text-gray-600 dark:text-gray-400">{userData?.email || user.email || 'No email set'}</p>
                <div className="mt-4 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                  <i className="fas fa-check-circle mr-1"></i>
                  Active Account
                </div>
              </div>
            </div>

            {/* Quick Stats */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Quick Stats</h3>
              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Total Pastes</span>
                  <span className="font-semibold text-gray-900 dark:text-white">{stats.totalPastes}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Total Views</span>
                  <span className="font-semibold text-gray-900 dark:text-white">{stats.totalViews}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Collections</span>
                  <span className="font-semibold text-gray-900 dark:text-white">{stats.collections}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Following</span>
                  <span className="font-semibold text-gray-900 dark:text-white">{stats.following}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Followers</span>
                  <span className="font-semibold text-gray-900 dark:text-white">{stats.followers}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Right Column - Detailed Information */}
          <div className="lg:col-span-2 space-y-6">
            {/* Account Overview */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
              <div className="border-b border-gray-200 dark:border-gray-700 p-6">
                <h3 className="text-xl font-semibold flex items-center text-gray-900 dark:text-white">
                  <i className="fas fa-info-circle mr-2 text-blue-500"></i>
                  Account Overview
                </h3>
              </div>
              <div className="p-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                        Account Status
                      </label>
                      <div className="mt-1 flex items-center">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                          <i className="fas fa-check-circle mr-1"></i>
                          Member Since: {formatDate(userData?.created_at || Date.now()/1000)}
                        </span>
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                        Account Type
                      </label>
                      <div className="mt-1">
                        <span className="text-blue-600 dark:text-blue-400 font-medium">
                          {userData?.role === 'premium' ? 'Premium Account' : 'Free Account'}
                        </span>
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                        Account Age
                      </label>
                      <div className="mt-1">
                        <span className="font-medium text-gray-900 dark:text-white">{accountAgeDays} days</span>
                      </div>
                    </div>
                  </div>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                        User ID
                      </label>
                      <div className="mt-1">
                        <span className="font-mono text-sm text-gray-800 dark:text-gray-200">{userData?.id || user.id}</span>
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                        Website
                      </label>
                      <div className="mt-1">
                        {userData?.website ? (
                          <a href={userData.website} target="_blank" rel="noopener noreferrer" className="text-blue-600 dark:text-blue-400 hover:underline">
                            {userData.website}
                          </a>
                        ) : (
                          <span className="text-gray-500">Not set</span>
                        )}
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                        Most Used Language
                      </label>
                      <div className="mt-1">
                        <span className="font-medium text-gray-900 dark:text-white">
                          {userData?.top_language || 'None'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Feature Usage */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
              <div className="border-b border-gray-200 dark:border-gray-700 p-6">
                <h3 className="text-xl font-semibold flex items-center text-gray-900 dark:text-white">
                  <i className="fas fa-chart-bar mr-2 text-green-500"></i>
                  Feature Usage
                </h3>
              </div>
              <div className="p-6">
                <div className="space-y-6">
                  {/* All Features */}
                  <div>
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-sm font-medium text-gray-900 dark:text-white">All Features</span>
                      <span className="text-sm text-gray-600 dark:text-gray-400">Available</span>
                    </div>
                    <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div className="bg-green-500 h-2 rounded-full" style={{ width: '100%' }}></div>
                    </div>
                  </div>

                  {/* Free AI Tools */}
                  <div>
                    <div className="flex justify-between items-center mb-2">
                      <span className="text-sm font-medium text-gray-900 dark:text-white">Free AI Tools</span>
                      <span className="text-sm text-green-600 dark:text-green-400">Available</span>
                    </div>
                    <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div className="bg-green-500 h-2 rounded-full" style={{ width: '100%' }}></div>
                    </div>
                  </div>
                </div>

                {/* Premium Features */}
                <div className="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                  <h4 className="font-semibold text-blue-800 dark:text-blue-200 mb-2">
                    <i className="fas fa-star mr-2"></i>Upgrade to Premium
                  </h4>
                  <p className="text-sm text-blue-700 dark:text-blue-300 mb-3">
                    Unlock advanced features and enhance your experience
                  </p>
                  <ul className="text-sm text-blue-700 dark:text-blue-300 space-y-1 mb-4">
                    <li><i className="fas fa-check mr-2"></i>Unlimited private pastes</li>
                    <li><i className="fas fa-check mr-2"></i>Advanced analytics</li>
                    <li><i className="fas fa-check mr-2"></i>Custom themes and branding</li>
                    <li><i className="fas fa-check mr-2"></i>Priority support</li>
                  </ul>
                  <Link to="/pricing" className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                    <i className="fas fa-crown mr-2"></i>Upgrade Now
                  </Link>
                </div>
              </div>
            </div>

            {/* Recent Activity */}
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
              <div className="border-b border-gray-200 dark:border-gray-700 p-6">
                <h3 className="text-xl font-semibold flex items-center text-gray-900 dark:text-white">
                  <i className="fas fa-clock mr-2 text-purple-500"></i>
                  Recent Activity
                </h3>
              </div>
              <div className="p-6">
                {recentPastes.length === 0 ? (
                  <div className="text-center py-8">
                    <i className="fas fa-clipboard text-4xl text-gray-400 mb-4"></i>
                    <p className="text-gray-500 dark:text-gray-400 mb-4">No recent activity to show</p>
                    <Link to="/" className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                      <i className="fas fa-plus mr-2"></i>
                      Create Your First Paste
                    </Link>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {recentPastes.map(paste => (
                      <div key={paste.id} className="flex items-center space-x-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div className="flex-shrink-0">
                          <div className="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i className="fas fa-code text-blue-600 dark:text-blue-400"></i>
                          </div>
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                            <Link to={`/view/${paste.id}`} className="hover:text-blue-500">
                              {paste.title || 'Untitled Paste'}
                            </Link>
                          </p>
                          <p className="text-sm text-gray-500 dark:text-gray-400">
                            {paste.language ? paste.language : 'Plain Text'} • 
                            {paste.views} views • 
                            {formatDate(paste.created_at)}
                          </p>
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {recentPastes.length > 0 && (
                  <div className="mt-6 text-center">
                    <Link to="/archive" className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                      <i className="fas fa-archive mr-2"></i>
                      View All Pastes
                    </Link>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AccountPage;