import { useState, useEffect } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';

const FollowingPage = () => {
  const { user } = useUser();
  const [activeTab, setActiveTab] = useState('following');
  const [following, setFollowing] = useState([]);
  const [followers, setFollowers] = useState([]);
  const [suggested, setSuggested] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/following' } }} />;
  }

  useEffect(() => {
    const fetchData = async () => {
      setIsLoading(true);
      setError('');
      
      try {
        // Mock data for following
        const mockFollowing = [
          {
            id: 'user456',
            username: 'pythondev',
            profile_image: null,
            tagline: 'Python developer and data science enthusiast',
            paste_count: 15,
            followed_at: Math.floor(Date.now() / 1000) - 604800 // 1 week ago
          },
          {
            id: 'user789',
            username: 'reactfan',
            profile_image: null,
            tagline: 'Frontend developer specializing in React',
            paste_count: 8,
            followed_at: Math.floor(Date.now() / 1000) - 1209600 // 2 weeks ago
          }
        ];
        
        // Mock data for followers
        const mockFollowers = [
          {
            id: 'user101',
            username: 'cssmaster',
            profile_image: null,
            tagline: 'CSS wizard and design enthusiast',
            paste_count: 12,
            followed_at: Math.floor(Date.now() / 1000) - 432000 // 5 days ago
          },
          {
            id: 'user102',
            username: 'phpdev',
            profile_image: null,
            tagline: 'Backend developer with PHP expertise',
            paste_count: 7,
            followed_at: Math.floor(Date.now() / 1000) - 864000 // 10 days ago
          }
        ];
        
        // Mock data for suggested users
        const mockSuggested = [
          {
            id: 'user201',
            username: 'javascriptguru',
            profile_image: null,
            tagline: 'JavaScript expert and open source contributor',
            paste_count: 25,
            followers_count: 18
          },
          {
            id: 'user202',
            username: 'sqlmaster',
            profile_image: null,
            tagline: 'Database specialist and SQL enthusiast',
            paste_count: 14,
            followers_count: 9
          },
          {
            id: 'user203',
            username: 'devopsninja',
            profile_image: null,
            tagline: 'DevOps engineer and cloud specialist',
            paste_count: 19,
            followers_count: 12
          }
        ];
        
        setFollowing(mockFollowing);
        setFollowers(mockFollowers);
        setSuggested(mockSuggested);
      } catch (err) {
        console.error('Error fetching data:', err);
        setError('Failed to load data. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };
    
    fetchData();
  }, []);

  const handleFollow = (userId, username) => {
    // In a real app, you would call an API to follow the user
    setSuccess(`You are now following ${username}`);
    
    // Update the suggested users list
    setSuggested(prev => prev.filter(user => user.id !== userId));
    
    // Add to following list
    const userToFollow = suggested.find(user => user.id === userId);
    if (userToFollow) {
      setFollowing(prev => [
        {
          ...userToFollow,
          followed_at: Math.floor(Date.now() / 1000)
        },
        ...prev
      ]);
    }
    
    // Clear success message after 3 seconds
    setTimeout(() => {
      setSuccess('');
    }, 3000);
  };

  const handleUnfollow = (userId, username) => {
    // In a real app, you would call an API to unfollow the user
    setSuccess(`You have unfollowed ${username}`);
    
    // Remove from following list
    setFollowing(prev => prev.filter(user => user.id !== userId));
    
    // Clear success message after 3 seconds
    setTimeout(() => {
      setSuccess('');
    }, 3000);
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {success && (
        <div className="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
          <i className="fas fa-check-circle mr-2"></i>{success}
        </div>
      )}
      
      {error && (
        <div className="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      )}
      
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
        {/* Header */}
        <div className="border-b border-gray-200 dark:border-gray-700 p-6">
          <h1 className="text-2xl font-bold flex items-center">
            <i className="fas fa-users mr-3 text-blue-500"></i>
            Social Network
          </h1>
          <div className="mt-2 flex items-center gap-6 text-sm text-gray-600 dark:text-gray-400">
            <span><strong>{following.length}</strong> Following</span>
            <span><strong>{followers.length}</strong> Followers</span>
          </div>
        </div>

        {/* Tabs */}
        <div className="border-b border-gray-200 dark:border-gray-700">
          <nav className="flex space-x-8 px-6" aria-label="Tabs">
            <button 
              onClick={() => setActiveTab('following')} 
              className={`py-4 px-1 text-sm font-medium ${
                activeTab === 'following' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-user-friends mr-2"></i>Following ({following.length})
            </button>
            <button 
              onClick={() => setActiveTab('followers')} 
              className={`py-4 px-1 text-sm font-medium ${
                activeTab === 'followers' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-users mr-2"></i>Followers ({followers.length})
            </button>
            <button 
              onClick={() => setActiveTab('discover')} 
              className={`py-4 px-1 text-sm font-medium ${
                activeTab === 'discover' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-search mr-2"></i>Discover
            </button>
          </nav>
        </div>

        {/* Content */}
        <div className="p-6">
          {isLoading ? (
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {[...Array(3)].map((_, i) => (
                <div key={i} className="animate-pulse">
                  <div className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div className="flex items-center gap-3 mb-3">
                      <div className="w-12 h-12 bg-gray-200 dark:bg-gray-600 rounded-full"></div>
                      <div className="flex-1">
                        <div className="h-4 bg-gray-200 dark:bg-gray-600 rounded w-3/4 mb-2"></div>
                        <div className="h-3 bg-gray-200 dark:bg-gray-600 rounded w-1/2"></div>
                      </div>
                    </div>
                    <div className="h-4 bg-gray-200 dark:bg-gray-600 rounded w-full mb-3"></div>
                    <div className="flex justify-between items-center">
                      <div className="h-3 bg-gray-200 dark:bg-gray-600 rounded w-1/3"></div>
                      <div className="h-8 bg-gray-200 dark:bg-gray-600 rounded w-24"></div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <>
              {/* Following Tab */}
              {activeTab === 'following' && (
                <>
                  {following.length === 0 ? (
                    <div className="text-center py-8">
                      <i className="fas fa-user-friends text-4xl text-gray-400 mb-4"></i>
                      <p className="text-gray-500 text-lg mb-4">You're not following anyone yet.</p>
                      <button 
                        onClick={() => setActiveTab('discover')} 
                        className="text-blue-500 hover:text-blue-700"
                      >
                        Discover users to follow
                      </button>
                    </div>
                  ) : (
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                      {following.map(user => (
                        <div key={user.id} className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                          <div className="flex items-center gap-3 mb-3">
                            <img 
                              src={user.profile_image || `https://www.gravatar.com/avatar/${user.username}?d=mp&s=48`} 
                              className="w-12 h-12 rounded-full" 
                              alt="Profile" 
                            />
                            <div className="flex-1">
                              <Link to={`/profile/${user.username}`} className="font-medium text-blue-500 hover:text-blue-700">
                                @{user.username}
                              </Link>
                              <div className="text-sm text-gray-500">
                                {user.paste_count} pastes
                              </div>
                            </div>
                          </div>
                          {user.tagline && (
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-3">
                              {user.tagline}
                            </p>
                          )}
                          <div className="flex justify-between items-center">
                            <span className="text-xs text-gray-500">
                              Following since {new Date(user.followed_at * 1000).toLocaleDateString(undefined, { month: 'short', year: 'numeric' })}
                            </span>
                            <button 
                              onClick={() => handleUnfollow(user.id, user.username)} 
                              className="text-sm bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600"
                            >
                              Unfollow
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}

              {/* Followers Tab */}
              {activeTab === 'followers' && (
                <>
                  {followers.length === 0 ? (
                    <div className="text-center py-8">
                      <i className="fas fa-users text-4xl text-gray-400 mb-4"></i>
                      <p className="text-gray-500 text-lg mb-4">No followers yet.</p>
                      <p className="text-gray-400">Share your profile to get followers!</p>
                    </div>
                  ) : (
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                      {followers.map(user => (
                        <div key={user.id} className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                          <div className="flex items-center gap-3 mb-3">
                            <img 
                              src={user.profile_image || `https://www.gravatar.com/avatar/${user.username}?d=mp&s=48`} 
                              className="w-12 h-12 rounded-full" 
                              alt="Profile" 
                            />
                            <div className="flex-1">
                              <Link to={`/profile/${user.username}`} className="font-medium text-blue-500 hover:text-blue-700">
                                @{user.username}
                              </Link>
                              <div className="text-sm text-gray-500">
                                {user.paste_count} pastes
                              </div>
                            </div>
                          </div>
                          {user.tagline && (
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-3">
                              {user.tagline}
                            </p>
                          )}
                          <div className="text-xs text-gray-500">
                            Followed you {new Date(user.followed_at * 1000).toLocaleDateString()}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}

              {/* Discover Tab */}
              {activeTab === 'discover' && (
                <>
                  {suggested.length === 0 ? (
                    <div className="text-center py-8">
                      <i className="fas fa-search text-4xl text-gray-400 mb-4"></i>
                      <p className="text-gray-500 text-lg mb-4">No new users to discover right now.</p>
                    </div>
                  ) : (
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                      {suggested.map(user => (
                        <div key={user.id} className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                          <div className="flex items-center gap-3 mb-3">
                            <img 
                              src={user.profile_image || `https://www.gravatar.com/avatar/${user.username}?d=mp&s=48`} 
                              className="w-12 h-12 rounded-full" 
                              alt="Profile" 
                            />
                            <div className="flex-1">
                              <Link to={`/profile/${user.username}`} className="font-medium text-blue-500 hover:text-blue-700">
                                @{user.username}
                              </Link>
                              <div className="text-sm text-gray-500">
                                {user.paste_count} pastes · {user.followers_count} followers
                              </div>
                            </div>
                          </div>
                          {user.tagline && (
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-3">
                              {user.tagline}
                            </p>
                          )}
                          <button 
                            onClick={() => handleFollow(user.id, user.username)} 
                            className="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                          >
                            <i className="fas fa-user-plus mr-2"></i>Follow
                          </button>
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default FollowingPage;