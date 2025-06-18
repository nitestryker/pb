// API utility functions for connecting to the backend

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:3001/api';

/**
 * Make a GET request to the API
 * @param {string} endpoint - The API endpoint
 * @param {Object} params - Query parameters
 * @returns {Promise<Object>} - The response data
 */
export const apiGet = async (endpoint, params = {}) => {
  const url = new URL(`${API_BASE_URL}${endpoint}`);
  
  // Add query parameters
  Object.keys(params).forEach(key => {
    if (params[key] !== undefined && params[key] !== null) {
      url.searchParams.append(key, params[key]);
    }
  });
  
  const response = await fetch(url.toString(), {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json'
    },
    credentials: 'include'
  });
  
  if (!response.ok) {
    throw new Error(`API error: ${response.status} ${response.statusText}`);
  }
  
  return await response.json();
};

/**
 * Make a POST request to the API
 * @param {string} endpoint - The API endpoint
 * @param {Object} data - The request body
 * @returns {Promise<Object>} - The response data
 */
export const apiPost = async (endpoint, data = {}) => {
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data),
    credentials: 'include'
  });
  
  if (!response.ok) {
    throw new Error(`API error: ${response.status} ${response.statusText}`);
  }
  
  return await response.json();
};

/**
 * Check if the user is authenticated
 * @returns {Promise<Object>} - The user data if authenticated
 */
export const checkAuth = async () => {
  try {
    const response = await apiGet('/auth/check');
    return response.success ? response.user : null;
  } catch (error) {
    console.error('Auth check failed:', error);
    return null;
  }
};

/**
 * Login a user
 * @param {Object} credentials - The login credentials
 * @returns {Promise<Object>} - The response data
 */
export const login = async (credentials) => {
  return await apiPost('/auth/login', credentials);
};

/**
 * Register a new user
 * @param {Object} userData - The user registration data
 * @returns {Promise<Object>} - The response data
 */
export const register = async (userData) => {
  return await apiPost('/auth/register', userData);
};

/**
 * Logout the current user
 * @returns {Promise<void>}
 */
export const logout = async () => {
  await apiGet('/auth/logout');
};

/**
 * Create a new paste
 * @param {Object} pasteData - The paste data
 * @returns {Promise<Object>} - The created paste
 */
export const createPaste = async (pasteData) => {
  return await apiPost('/pastes', pasteData);
};

/**
 * Get a paste by ID
 * @param {string|number} id - The paste ID
 * @returns {Promise<Object>} - The paste data
 */
export const getPaste = async (id) => {
  return await apiGet(`/pastes/${id}`);
};

/**
 * Get recent public pastes
 * @param {number} limit - The number of pastes to retrieve
 * @returns {Promise<Array>} - The pastes
 */
export const getRecentPastes = async (limit = 5) => {
  return await apiGet('/pastes/recent', { limit });
};

/**
 * Get archive pastes with filtering and pagination
 * @param {Object} params - The filter parameters
 * @returns {Promise<Object>} - The pastes and pagination data
 */
export const getArchivePastes = async (params = {}) => {
  return await apiGet('/pastes', params);
};

/**
 * Add a comment to a paste
 * @param {string|number} pasteId - The paste ID
 * @param {string} content - The comment content
 * @returns {Promise<Object>} - The created comment
 */
export const addComment = async (pasteId, content) => {
  return await apiPost(`/pastes/${pasteId}/comments`, { content });
};

/**
 * Get comments for a paste
 * @param {string|number} pasteId - The paste ID
 * @returns {Promise<Array>} - The comments
 */
export const getComments = async (pasteId) => {
  return await apiGet(`/pastes/${pasteId}/comments`);
};

/**
 * Get related pastes for a paste
 * @param {string|number} pasteId - The paste ID
 * @returns {Promise<Array>} - The related pastes
 */
export const getRelatedPastes = async (pasteId) => {
  return await apiGet(`/pastes/${pasteId}/related`);
};

/**
 * Get user collections
 * @returns {Promise<Array>} - The user's collections
 */
export const getUserCollections = async () => {
  return await apiGet('/collections');
};

/**
 * Create a new collection
 * @param {Object} collectionData - The collection data
 * @returns {Promise<Object>} - The created collection
 */
export const createCollection = async (collectionData) => {
  return await apiPost('/collections', collectionData);
};

/**
 * Get user account data
 * @returns {Promise<Object>} - The user account data
 */
export const getUserAccount = async () => {
  return await apiGet('/account');
};

/**
 * Update user settings
 * @param {string} settingType - The type of settings to update
 * @param {Object} settingsData - The settings data
 * @returns {Promise<Object>} - The updated settings
 */
export const updateUserSettings = async (settingType, settingsData) => {
  return await apiPost(`/settings/${settingType}`, settingsData);
};

/**
 * Get user notifications
 * @returns {Promise<Object>} - The notifications
 */
export const getNotifications = async () => {
  return await apiGet('/notifications');
};

/**
 * Mark a notification as read
 * @param {number} notificationId - The notification ID
 * @param {string} notificationType - The notification type
 * @returns {Promise<Object>} - The result
 */
export const markNotificationAsRead = async (notificationId, notificationType) => {
  return await apiPost('/notifications/mark-read', { notification_id: notificationId, notification_type: notificationType });
};

/**
 * Delete a notification
 * @param {number} notificationId - The notification ID
 * @param {string} notificationType - The notification type
 * @returns {Promise<Object>} - The result
 */
export const deleteNotification = async (notificationId, notificationType) => {
  return await apiPost('/notifications/delete', { notification_id: notificationId, notification_type: notificationType });
};

/**
 * Get user profile
 * @param {string} username - The username
 * @returns {Promise<Object>} - The user profile
 */
export const getUserProfile = async (username) => {
  return await apiGet(`/users/${username}`);
};

/**
 * Update user profile
 * @param {FormData} formData - The profile data
 * @returns {Promise<Object>} - The result
 */
export const updateUserProfile = async (formData) => {
  const response = await fetch(`${API_BASE_URL}/profile`, {
    method: 'POST',
    body: formData,
    credentials: 'include'
  });
  
  if (!response.ok) {
    throw new Error(`API error: ${response.status} ${response.statusText}`);
  }
  
  return await response.json();
};