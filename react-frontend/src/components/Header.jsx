import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';
import { useTheme } from '../contexts/ThemeContext';

const Header = () => {
  const { user, logout } = useUser();
  const { toggleTheme } = useTheme();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  return (
    <nav className="bg-blue-600 dark:bg-blue-800 text-white shadow-lg">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex justify-between h-16">
          <div className="flex items-center space-x-6">
            <Link to="/" className="flex items-center space-x-3">
              <i className="fas fa-paste text-2xl"></i>
              <span className="text-xl font-bold">PasteForge</span>
            </Link>
            <div className="hidden md:flex space-x-4">
              <Link to="/" className="hover:bg-blue-700 px-3 py-2 rounded">Home</Link>
              <Link to="/archive" className="hover:bg-blue-700 px-3 py-2 rounded">Archive</Link>
              {user && (
                <Link to="/collections" className="hover:bg-blue-700 px-3 py-2 rounded">Collections</Link>
              )}
            </div>
          </div>
          <div className="flex items-center space-x-4">
            {/* Notification Bell - Only show if user is logged in */}
            {user && (
              <Link to="/notifications" className="relative p-2 rounded hover:bg-blue-700 transition-colors">
                <i className="fas fa-bell text-lg"></i>
                {/* Notification badge - show if there are unread notifications */}
                {user.unreadNotifications > 0 && (
                  <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] animate-pulse">
                    {user.unreadNotifications > 99 ? '99+' : user.unreadNotifications}
                  </span>
                )}
              </Link>
            )}
            
            {/* Theme Toggle */}
            <button onClick={toggleTheme} className="p-2 rounded hover:bg-blue-700">
              <i className="fas fa-moon"></i>
            </button>
            
            {!user ? (
              <div className="hidden md:flex items-center space-x-2">
                <Link to="/login" className="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                  <i className="fas fa-sign-in-alt"></i>
                  <span>Login</span>
                </Link>
                <Link to="/signup" className="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                  <i className="fas fa-user-plus"></i>
                  <span>Sign Up</span>
                </Link>
              </div>
            ) : (
              <div className="relative">
                <button 
                  onClick={() => setIsMenuOpen(!isMenuOpen)} 
                  className="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded"
                >
                  <img 
                    src={user.profile_image || `https://www.gravatar.com/avatar/${user.email || user.username}?d=mp&s=32`} 
                    className="w-8 h-8 rounded-full" 
                    alt="Profile" 
                  />
                  <span className="hidden md:inline">{user.username}</span>
                  <i className="fas fa-chevron-down ml-1"></i>
                </button>
                {isMenuOpen && (
                  <div className="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-10">
                    <div className="py-1">
                      {/* Account Group */}
                      <div className="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Account</div>
                      <Link to="/profile/edit" className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-user-edit mr-2"></i> Edit Profile
                      </Link>
                      <Link to={`/profile/${user.username}`} className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-user mr-2"></i> View Profile
                      </Link>
                      <Link to="/account" className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-crown mr-2"></i> Account
                      </Link>
                      <Link to="/settings" className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-cog mr-2"></i> Edit Settings
                      </Link>

                      <hr className="my-1 border-gray-200 dark:border-gray-700" />

                      {/* Messages Group */}
                      <div className="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Messages</div>
                      <Link to="/messages" className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-envelope mr-2"></i> My Messages
                      </Link>

                      <hr className="my-1 border-gray-200 dark:border-gray-700" />

                      {/* Tools Group */}
                      <div className="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tools</div>
                      <Link to="/projects" className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-folder-tree mr-2"></i> Projects
                      </Link>
                      <Link to="/following" className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-users mr-2"></i> Following
                      </Link>
                      <Link to="/import-export" className="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i className="fas fa-exchange-alt mr-2"></i> Import/Export
                      </Link>

                      <hr className="my-1 border-gray-200 dark:border-gray-700" />

                      {/* Logout */}
                      <button 
                        onClick={handleLogout}
                        className="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700"
                      >
                        <i className="fas fa-sign-out-alt mr-2"></i> Logout
                      </button>
                    </div>
                  </div>
                )}
              </div>
            )}
            
            {/* Mobile menu button */}
            <div className="md:hidden">
              <button 
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                className="p-2 rounded hover:bg-blue-700"
              >
                <i className={`fas ${isMobileMenuOpen ? 'fa-times' : 'fa-bars'}`}></i>
              </button>
            </div>
          </div>
        </div>
        
        {/* Mobile menu */}
        {isMobileMenuOpen && (
          <div className="md:hidden py-3 border-t border-blue-700">
            <Link to="/" className="block px-3 py-2 rounded hover:bg-blue-700">Home</Link>
            <Link to="/archive" className="block px-3 py-2 rounded hover:bg-blue-700">Archive</Link>
            {user && (
              <Link to="/collections" className="block px-3 py-2 rounded hover:bg-blue-700">Collections</Link>
            )}
            {!user ? (
              <>
                <Link to="/login" className="block px-3 py-2 rounded hover:bg-blue-700">Login</Link>
                <Link to="/signup" className="block px-3 py-2 rounded hover:bg-blue-700">Sign Up</Link>
              </>
            ) : (
              <>
                <Link to="/account" className="block px-3 py-2 rounded hover:bg-blue-700">My Account</Link>
                <button 
                  onClick={handleLogout}
                  className="block w-full text-left px-3 py-2 rounded hover:bg-blue-700"
                >
                  Logout
                </button>
              </>
            )}
          </div>
        )}
      </div>
    </nav>
  );
};

export default Header;