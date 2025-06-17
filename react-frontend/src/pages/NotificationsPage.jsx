import { useState, useEffect } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';
import { getNotifications, markNotificationAsRead, deleteNotification } from '../utils/api';

const NotificationsPage = () => {
  const { user } = useUser();
  const [notifications, setNotifications] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/notifications' } }} />;
  }

  useEffect(() => {
    const fetchNotifications = async () => {
      try {
        setIsLoading(true);
        const response = await getNotifications();
        
        if (response.success) {
          setNotifications(response.notifications || []);
        } else {
          throw new Error(response.message || 'Failed to load notifications');
        }
      } catch (error) {
        console.error('Error fetching notifications:', error);
        setError('Failed to load notifications. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchNotifications();
  }, []);

  const handleMarkAsRead = async (notificationId, notificationType = 'comment') => {
    try {
      const response = await markNotificationAsRead(notificationId, notificationType);
      
      if (response.success) {
        // Update the notification in the state
        setNotifications(prev => 
          prev.map(notification => 
            notification.id === notificationId 
              ? { ...notification, is_read: 1 } 
              : notification
          )
        );
      }
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
  };

  const handleDeleteNotification = async (notificationId, notificationType = 'comment') => {
    try {
      const response = await deleteNotification(notificationId, notificationType);
      
      if (response.success) {
        // Remove the notification from the state
        setNotifications(prev => 
          prev.filter(notification => notification.id !== notificationId)
        );
      }
    } catch (error) {
      console.error('Error deleting notification:', error);
    }
  };

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleString();
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold">
            <i className="fas fa-bell mr-2"></i>Notifications 
            {user.unreadNotifications > 0 && (
              <span className="bg-red-500 text-white text-sm px-2 py-1 rounded-full ml-2">
                {user.unreadNotifications}
              </span>
            )}
          </h1>
          <div className="flex gap-2">
            {notifications.some(n => !n.is_read) && (
              <button 
                onClick={() => {
                  // In a real app, you would call an API to mark all as read
                  setNotifications(prev => 
                    prev.map(notification => ({ ...notification, is_read: 1 }))
                  );
                }}
                className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
              >
                <i className="fas fa-check-double mr-2"></i>Mark All Read
              </button>
            )}
            {notifications.length > 0 && (
              <button 
                onClick={() => {
                  if (confirm('Are you sure you want to delete all notifications? This cannot be undone.')) {
                    // In a real app, you would call an API to delete all notifications
                    setNotifications([]);
                  }
                }}
                className="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600"
              >
                <i className="fas fa-trash mr-2"></i>Delete All
              </button>
            )}
          </div>
        </div>

        {error && (
          <div className="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
            <i className="fas fa-exclamation-circle mr-2"></i>{error}
          </div>
        )}

        <div className="space-y-4">
          {isLoading ? (
            <div className="space-y-4">
              {[...Array(3)].map((_, i) => (
                <div key={i} className="animate-pulse flex items-start gap-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                  <div className="flex-shrink-0 w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full"></div>
                  <div className="flex-1">
                    <div className="h-4 bg-gray-200 dark:bg-gray-600 rounded w-3/4 mb-2"></div>
                    <div className="h-3 bg-gray-200 dark:bg-gray-600 rounded w-1/2 mb-2"></div>
                    <div className="h-10 bg-gray-200 dark:bg-gray-600 rounded mb-2"></div>
                    <div className="h-3 bg-gray-200 dark:bg-gray-600 rounded w-1/4"></div>
                  </div>
                </div>
              ))}
            </div>
          ) : notifications.length === 0 ? (
            <div className="text-center py-8">
              <i className="fas fa-bell-slash text-4xl text-gray-400 mb-4"></i>
              <p className="text-gray-500 text-lg mb-4">No notifications yet.</p>
              <p className="text-gray-400">You'll receive notifications when someone comments on your pastes or replies to your comments.</p>
            </div>
          ) : (
            notifications.map(notification => (
              <div 
                key={notification.id} 
                className={`flex items-start gap-4 p-4 rounded-lg ${
                  notification.is_read 
                    ? 'bg-gray-50 dark:bg-gray-700' 
                    : 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500'
                }`}
              >
                <div className="flex-shrink-0">
                  {notification.notification_type === 'message' ? (
                    <i className="fas fa-envelope text-green-500 text-xl"></i>
                  ) : notification.notification_type === 'expiration' ? (
                    <i className="fas fa-clock text-orange-500 text-xl"></i>
                  ) : (
                    <i className={`fas ${notification.type === 'comment' ? 'fa-comment' : 'fa-reply'} text-blue-500 text-xl`}></i>
                  )}
                </div>
                <div className="flex-1">
                  <div className="font-medium mb-1">
                    {notification.message}
                  </div>
                  {notification.notification_type === 'comment' && (
                    <div className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                      On paste: <Link to={`/view/${notification.paste_id}`} className="text-blue-500 hover:text-blue-700 font-medium">
                        "{notification.paste_title}"
                      </Link>
                    </div>
                  )}
                  {notification.notification_type === 'expiration' && (
                    <div className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                      Paste: <Link to={`/view/${notification.paste_id}`} className="text-blue-500 hover:text-blue-700 font-medium">
                        "{notification.paste_title}"
                      </Link>
                      <span className="text-orange-600 dark:text-orange-400 font-medium ml-2">
                        Expires: {formatDate(notification.expires_at)}
                      </span>
                    </div>
                  )}
                  {notification.related_content && (
                    <div className="text-sm text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 p-3 rounded mb-2">
                      <i className="fas fa-quote-left text-gray-400 mr-1"></i>
                      {notification.related_content.length > 150 
                        ? notification.related_content.substring(0, 150) + '...' 
                        : notification.related_content}
                    </div>
                  )}
                  <div className="text-xs text-gray-500 flex items-center gap-4">
                    <span>
                      <i className="fas fa-clock mr-1"></i>
                      {formatDate(notification.created_at)}
                    </span>
                    {notification.username && (
                      <span>
                        <i className="fas fa-user mr-1"></i>
                        by @{notification.username}
                      </span>
                    )}
                  </div>
                </div>
                <div className="flex-shrink-0 flex flex-col gap-2">
                  {notification.notification_type === 'message' ? (
                    <Link 
                      to={`/messages?thread=${notification.message_id}`}
                      className="px-3 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600 transition-colors"
                      onClick={() => handleMarkAsRead(notification.id, 'message')}
                    >
                      <i className="fas fa-eye mr-1"></i>View Message
                    </Link>
                  ) : notification.notification_type === 'expiration' ? (
                    <Link 
                      to={`/view/${notification.paste_id}`}
                      className="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600 transition-colors"
                      onClick={() => handleMarkAsRead(notification.id, 'expiration')}
                    >
                      <i className="fas fa-eye mr-1"></i>View Paste
                    </Link>
                  ) : (
                    <Link 
                      to={`/view/${notification.paste_id}${notification.target_comment_id ? '#comment-' + notification.target_comment_id : ''}`}
                      className="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors"
                      onClick={() => handleMarkAsRead(notification.id, 'comment')}
                    >
                      <i className="fas fa-eye mr-1"></i>View
                    </Link>
                  )}
                  {!notification.is_read && (
                    <button 
                      onClick={() => handleMarkAsRead(notification.id, notification.notification_type)}
                      className="px-3 py-1 bg-gray-500 text-white text-sm rounded hover:bg-gray-600 transition-colors w-full"
                    >
                      <i className="fas fa-check mr-1"></i>Mark Read
                    </button>
                  )}
                  <button 
                    onClick={() => handleDeleteNotification(notification.id, notification.notification_type)}
                    className="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition-colors w-full"
                  >
                    <i className="fas fa-trash mr-1"></i>Delete
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
};

export default NotificationsPage;