// API utility functions for making requests to the PHP backend

/**
 * Base API URL - adjust this to match your PHP backend
 * For local development with Vite, we can use a relative URL
 * which will be proxied to the PHP backend
 */
const API_BASE_URL = '';

/**
 * Make a GET request to the API
 * @param {string} endpoint - The API endpoint to request
 * @param {Object} params - Query parameters to include
 * @returns {Promise<any>} - The response data
 */
export const apiGet = async (endpoint, params = {}) => {
  try {
    // Build URL with query parameters
    const url = new URL(API_BASE_URL + endpoint, window.location.origin);
    Object.keys(params).forEach(key => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    // Make the request
    const response = await fetch(url.toString(), {
      method: 'GET',
      credentials: 'include', // Include cookies for session-based auth
      headers: {
        'Accept': 'application/json',
      }
    });

    // Handle non-2xx responses
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(errorData.message || `API error: ${response.status}`);
    }

    // Parse and return the response data
    return await response.json();
  } catch (error) {
    console.error(`API GET error for ${endpoint}:`, error);
    throw error;
  }
};

/**
 * Make a POST request to the API
 * @param {string} endpoint - The API endpoint to request
 * @param {Object} data - The data to send in the request body
 * @returns {Promise<any>} - The response data
 */
export const apiPost = async (endpoint, data = {}) => {
  try {
    // Convert data to FormData if it's not already
    let body;
    let headers = {
      'Accept': 'application/json',
    };

    if (data instanceof FormData) {
      body = data;
    } else {
      body = new FormData();
      Object.keys(data).forEach(key => {
        if (data[key] !== undefined && data[key] !== null) {
          if (typeof data[key] === 'boolean') {
            body.append(key, data[key] ? '1' : '0');
          } else {
            body.append(key, data[key]);
          }
        }
      });
    }

    // Make the request
    const response = await fetch(API_BASE_URL + endpoint, {
      method: 'POST',
      credentials: 'include', // Include cookies for session-based auth
      headers,
      body
    });

    // Handle non-2xx responses
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(errorData.message || `API error: ${response.status}`);
    }

    // Parse and return the response data
    return await response.json();
  } catch (error) {
    console.error(`API POST error for ${endpoint}:`, error);
    throw error;
  }
};

/**
 * Check if the user is authenticated
 * @returns {Promise<Object>} - The user data if authenticated
 */
export const checkAuth = async () => {
  try {
    const response = await apiGet('/?check_auth=1');
    return response.user || null;
  } catch (error) {
    console.error('Auth check failed:', error);
    return null;
  }
};

/**
 * Login a user
 * @param {Object} credentials - The login credentials
 * @returns {Promise<Object>} - The user data
 */
export const login = async (credentials) => {
  const formData = new FormData();
  formData.append('login', '1');
  Object.keys(credentials).forEach(key => {
    formData.append(key, credentials[key]);
  });

  const response = await apiPost('/', formData);
  return response;
};

/**
 * Register a new user
 * @param {Object} userData - The user registration data
 * @returns {Promise<Object>} - The user data
 */
export const register = async (userData) => {
  const formData = new FormData();
  formData.append('register', '1');
  Object.keys(userData).forEach(key => {
    formData.append(key, userData[key]);
  });

  const response = await apiPost('/', formData);
  return response;
};

/**
 * Logout the current user
 * @returns {Promise<void>}
 */
export const logout = async () => {
  await apiGet('/?logout=1');
};

/**
 * Create a new paste
 * @param {Object} pasteData - The paste data
 * @returns {Promise<Object>} - The created paste
 */
export const createPaste = async (pasteData) => {
  const formData = new FormData();
  formData.append('create_paste', '1');
  Object.keys(pasteData).forEach(key => {
    if (pasteData[key] !== undefined && pasteData[key] !== null) {
      if (typeof pasteData[key] === 'boolean') {
        formData.append(key, pasteData[key] ? '1' : '0');
      } else {
        formData.append(key, pasteData[key]);
      }
    }
  });

  const response = await apiPost('/', formData);
  return response;
};

/**
 * Get a paste by ID
 * @param {string|number} id - The paste ID
 * @returns {Promise<Object>} - The paste data
 */
export const getPaste = async (id) => {
  return await apiGet('/', { id });
};

/**
 * Get recent public pastes
 * @param {number} limit - The number of pastes to retrieve
 * @returns {Promise<Array>} - The pastes
 */
export const getRecentPastes = async (limit = 5) => {
  return await apiGet('/', { recent: '1', limit });
};

/**
 * Get archive pastes with filtering and pagination
 * @param {Object} params - The filter parameters
 * @returns {Promise<Object>} - The pastes and pagination data
 */
export const getArchivePastes = async (params = {}) => {
  return await apiGet('/', { page: 'archive', ...params });
};

/**
 * Add a comment to a paste
 * @param {string|number} pasteId - The paste ID
 * @param {string} content - The comment content
 * @returns {Promise<Object>} - The created comment
 */
export const addComment = async (pasteId, content) => {
  const formData = new FormData();
  formData.append('add_comment', '1');
  formData.append('paste_id', pasteId);
  formData.append('content', content);

  const response = await apiPost('/', formData);
  return response;
};

/**
 * Get comments for a paste
 * @param {string|number} pasteId - The paste ID
 * @returns {Promise<Array>} - The comments
 */
export const getComments = async (pasteId) => {
  return await apiGet('/', { id: pasteId, comments: '1' });
};

/**
 * Get related pastes for a paste
 * @param {string|number} pasteId - The paste ID
 * @returns {Promise<Array>} - The related pastes
 */
export const getRelatedPastes = async (pasteId) => {
  return await apiGet('/', { id: pasteId, related: '1' });
};

/**
 * Get user collections
 * @returns {Promise<Array>} - The user's collections
 */
export const getUserCollections = async () => {
  return await apiGet('/', { page: 'collections' });
};

/**
 * Create a new collection
 * @param {Object} collectionData - The collection data
 * @returns {Promise<Object>} - The created collection
 */
export const createCollection = async (collectionData) => {
  const formData = new FormData();
  formData.append('create_collection', '1');
  Object.keys(collectionData).forEach(key => {
    if (typeof collectionData[key] === 'boolean') {
      formData.append(key, collectionData[key] ? '1' : '0');
    } else {
      formData.append(key, collectionData[key]);
    }
  });

  const response = await apiPost('/', formData);
  return response;
};

/**
 * Get user account data
 * @returns {Promise<Object>} - The user account data
 */
export const getUserAccount = async () => {
  return await apiGet('/', { page: 'account' });
};

/**
 * Update user settings
 * @param {string} settingType - The type of settings to update
 * @param {Object} settingsData - The settings data
 * @returns {Promise<Object>} - The updated settings
 */
export const updateUserSettings = async (settingType, settingsData) => {
  const formData = new FormData();
  formData.append('update_settings', '1');
  formData.append('setting_type', settingType);
  Object.keys(settingsData).forEach(key => {
    if (typeof settingsData[key] === 'boolean') {
      formData.append(key, settingsData[key] ? '1' : '0');
    } else {
      formData.append(key, settingsData[key]);
    }
  });

  const response = await apiPost('/', formData);
  return response;
};