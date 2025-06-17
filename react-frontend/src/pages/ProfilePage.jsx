import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';
import PasteCard from '../components/PasteCard';
import { getUserProfile } from '../utils/api';

const ProfilePage = () => {
  const { username } = useParams();
  const { user } = useUser();
  const [profile, setProfile] = useState(null);
  const [userPastes, setUserPastes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState('pastes');

  useEffect(() => {
    const fetchProfile = async () => {
      try {
        setIsLoading(true);
        const response = await getUserProfile(username);
        
        if (response.success) {
          setProfile(response.profile);
          setUserPastes(response.pastes || []);
        } else {
          throw new Error(response.message || 'Failed to load profile');
        }
      } catch (error) {
        console.error('Error fetching profile:', error);
        setError('Failed to load profile. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchProfile();
  }, [username]);

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString();
  };

  const isOwnProfile = user && profile && user.id === profile.id;

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {error ? (
        <div className="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      ) : isLoading ? (
        <div className="animate-pulse">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
            <div className="flex flex-col md:flex-row gap-6">
              <div className="w-32 h-32 bg-gray-200 dark:bg-gray-700 rounded-full mx-auto md:mx-0"></div>
              <div className="flex-1">
                <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-1/3 mb-4"></div>
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-2"></div>
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4 mb-4"></div>
                <div className="flex gap-4">
                  <div className="h-10 bg-gray-200 dark:bg-gray-700 rounded w-24"></div>
                  <div className="h-10 bg-gray-200 dark:bg-gray-700 rounded w-24"></div>
                </div>
              </div>
            </div>
          </div>
          
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/4 mb-6"></div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="h-40 bg-gray-200 dark:bg-gray-700 rounded"></div>
              ))}
            </div>
          </div>
        </div>
      ) : (
        <>
          {/* Profile Header */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
            <div className="flex flex-col md:flex-row gap-6">
              <div className="flex-shrink-0">
                <img 
                  src={profile.profile_image || `https://www.gravatar.com/avatar/${profile.email || profile.username}?d=mp&s=128`} 
                  alt={profile.username} 
                  className="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-700 mx-auto md:mx-0"
                />
              </div>
              
              <div className="flex-1 text-center md:text-left">
                <h1 className="text-2xl font-bold mb-2">
                  {profile.username}
                  {profile.role === 'premium' && (
                    <span className="ml-2 text-sm bg-yellow-400 text-yellow-900 px-2 py-1 rounded-full">
                      <i className="fas fa-crown mr-1"></i>Premium
                    </span>
                  )}
                </h1>
                
                {profile.tagline && (
                  <p className="text-gray-600 dark:text-gray-400 mb-2">{profile.tagline}</p>
                )}
                
                <div className="flex flex-wrap gap-4 mb-4 justify-center md:justify-start">
                  <div className="text-sm text-gray-600 dark:text-gray-400">
                    <i className="fas fa-calendar mr-1"></i>
                    Joined {formatDate(profile.created_at)}
                  </div>
                  
                  {profile.website && (
                    <a 
                      href={profile.website} 
                      target="_blank" 
                      rel="noopener noreferrer" 
                      className="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                    >
                      <i className="fas fa-globe mr-1"></i>
                      {profile.website.replace(/^https?:\/\/(www\.)?/, '')}
                    </a>
                  )}
                  
                  {profile.show_paste_count && (
                    <div className="text-sm text-gray-600 dark:text-gray-400">
                      <i className="fas fa-paste mr-1"></i>
                      {userPastes.length} public pastes
                    </div>
                  )}
                </div>
                
                <div className="flex flex-wrap gap-3 justify-center md:justify-start">
                  {isOwnProfile ? (
                    <Link 
                      to="/profile/edit" 
                      className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm"
                    >
                      <i className="fas fa-edit mr-2"></i>
                      Edit Profile
                    </Link>
                  ) : (
                    <>
                      <button className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i className="fas fa-user-plus mr-2"></i>
                        Follow
                      </button>
                      
                      <button className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i className="fas fa-envelope mr-2"></i>
                        Message
                      </button>
                    </>
                  )}
                </div>
              </div>
            </div>
          </div>
          
          {/* Tabs and Content */}
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <div className="border-b border-gray-200 dark:border-gray-700">
              <nav className="flex space-x-8 px-6" aria-label="Tabs">
                <button 
                  onClick={() => setActiveTab('pastes')} 
                  className={`py-4 px-1 text-sm font-medium ${
                    activeTab === 'pastes' 
                      ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                      : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <i className="fas fa-paste mr-2"></i>
                  Pastes
                </button>
                
                <button 
                  onClick={() => setActiveTab('collections')} 
                  className={`py-4 px-1 text-sm font-medium ${
                    activeTab === 'collections' 
                      ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                      : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <i className="fas fa-folder mr-2"></i>
                  Collections
                </button>
                
                <button 
                  onClick={() => setActiveTab('activity')} 
                  className={`py-4 px-1 text-sm font-medium ${
                    activeTab === 'activity' 
                      ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                      : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <i className="fas fa-chart-line mr-2"></i>
                  Activity
                </button>
              </nav>
            </div>
            
            <div className="p-6">
              {/* Pastes Tab */}
              {activeTab === 'pastes' && (
                <>
                  {userPastes.length === 0 ? (
                    <div className="text-center py-12">
                      <i className="fas fa-paste text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                      <h3 className="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No public pastes yet</h3>
                      <p className="text-gray-500 dark:text-gray-400">
                        {isOwnProfile 
                          ? 'You haven\'t created any public pastes yet' 
                          : `${profile.username} hasn't created any public pastes yet`}
                      </p>
                      {isOwnProfile && (
                        <Link to="/" className="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                          <i className="fas fa-plus mr-2"></i>
                          Create a Paste
                        </Link>
                      )}
                    </div>
                  ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      {userPastes.map(paste => (
                        <PasteCard key={paste.id} paste={paste} />
                      ))}
                    </div>
                  )}
                </>
              )}
              
              {/* Collections Tab */}
              {activeTab === 'collections' && (
                <div className="text-center py-12">
                  <i className="fas fa-folder-open text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                  <h3 className="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No public collections yet</h3>
                  <p className="text-gray-500 dark:text-gray-400">
                    {isOwnProfile 
                      ? 'You haven\'t created any public collections yet' 
                      : `${profile.username} hasn't created any public collections yet`}
                  </p>
                  {isOwnProfile && (
                    <Link to="/collections" className="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                      <i className="fas fa-folder-plus mr-2"></i>
                      Create a Collection
                    </Link>
                  )}
                </div>
              )}
              
              {/* Activity Tab */}
              {activeTab === 'activity' && (
                <div className="text-center py-12">
                  <i className="fas fa-chart-line text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                  <h3 className="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">Activity feed coming soon</h3>
                  <p className="text-gray-500 dark:text-gray-400">
                    This feature is currently under development
                  </p>
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default ProfilePage;