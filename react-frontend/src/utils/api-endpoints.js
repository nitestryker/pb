/**
 * API endpoint definitions for the PHP backend
 * This file centralizes all API endpoint paths
 */

// Base API endpoints
export const ENDPOINTS = {
  // Authentication
  CHECK_AUTH: '/?check_auth=1',
  LOGIN: '/',
  REGISTER: '/',
  LOGOUT: '/?logout=1',
  
  // Pastes
  CREATE_PASTE: '/',
  GET_PASTE: '/',
  GET_RECENT_PASTES: '/',
  GET_ARCHIVE_PASTES: '/',
  
  // Comments
  ADD_COMMENT: '/',
  GET_COMMENTS: '/',
  
  // Related pastes
  GET_RELATED_PASTES: '/',
  
  // Collections
  GET_USER_COLLECTIONS: '/',
  CREATE_COLLECTION: '/',
  
  // User account
  GET_USER_ACCOUNT: '/',
  UPDATE_USER_SETTINGS: '/',
  
  // Profile
  GET_USER_PROFILE: '/',
  
  // Raw and download
  GET_RAW_PASTE: '/',
  DOWNLOAD_PASTE: '/',
};

/**
 * Build a URL for a GET request with query parameters
 * @param {string} endpoint - The API endpoint
 * @param {Object} params - Query parameters
 * @returns {string} - The complete URL
 */
export const buildUrl = (endpoint, params = {}) => {
  const url = new URL(endpoint, window.location.origin);
  
  Object.keys(params).forEach(key => {
    if (params[key] !== undefined && params[key] !== null) {
      url.searchParams.append(key, params[key]);
    }
  });
  
  return url.toString();
};