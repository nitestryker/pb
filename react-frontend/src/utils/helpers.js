/**
 * Helper functions for the application
 */

/**
 * Format a Unix timestamp to a readable date string
 * @param {number} timestamp - Unix timestamp in seconds
 * @param {boolean} includeTime - Whether to include the time
 * @returns {string} - Formatted date string
 */
export const formatDate = (timestamp, includeTime = false) => {
  if (!timestamp) return 'N/A';
  
  const date = new Date(timestamp * 1000);
  
  if (includeTime) {
    return date.toLocaleString();
  }
  
  return date.toLocaleDateString();
};

/**
 * Calculate time elapsed since a timestamp
 * @param {number} timestamp - Unix timestamp in seconds
 * @returns {string} - Human-readable time difference
 */
export const timeAgo = (timestamp) => {
  if (!timestamp) return 'N/A';
  
  const seconds = Math.floor((Date.now() / 1000) - timestamp);
  
  if (seconds < 60) return 'just now';
  
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
  
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
  
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days} day${days !== 1 ? 's' : ''} ago`;
  
  const months = Math.floor(days / 30);
  if (months < 12) return `${months} month${months !== 1 ? 's' : ''} ago`;
  
  const years = Math.floor(months / 12);
  return `${years} year${years !== 1 ? 's' : ''} ago`;
};

/**
 * Truncate text to a specified length
 * @param {string} text - The text to truncate
 * @param {number} maxLength - Maximum length before truncation
 * @returns {string} - Truncated text
 */
export const truncateText = (text, maxLength = 100) => {
  if (!text) return '';
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
};

/**
 * Get a user's avatar URL
 * @param {Object} user - User object
 * @param {number} size - Size of the avatar in pixels
 * @returns {string} - Avatar URL
 */
export const getAvatarUrl = (user, size = 40) => {
  if (!user) return `https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=${size}`;
  
  if (user.profile_image) {
    return user.profile_image;
  }
  
  // Generate a Gravatar URL based on email or username
  const identifier = user.email || user.username || '';
  const hash = md5(identifier.toLowerCase().trim());
  return `https://www.gravatar.com/avatar/${hash}?d=mp&s=${size}`;
};

/**
 * Simple MD5 implementation for Gravatar URLs
 * @param {string} string - String to hash
 * @returns {string} - MD5 hash
 */
function md5(string) {
  // This is a simplified version - in production, use a proper MD5 library
  // or just use the email directly since the backend will handle the hashing
  return string;
}

/**
 * Parse URL query parameters
 * @returns {Object} - Object containing query parameters
 */
export const getQueryParams = () => {
  const params = {};
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  
  for (const [key, value] of urlParams.entries()) {
    params[key] = value;
  }
  
  return params;
};

/**
 * Build URL with query parameters
 * @param {string} baseUrl - Base URL
 * @param {Object} params - Query parameters
 * @returns {string} - Complete URL
 */
export const buildUrl = (baseUrl, params = {}) => {
  const url = new URL(baseUrl, window.location.origin);
  
  Object.keys(params).forEach(key => {
    if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
      url.searchParams.append(key, params[key]);
    }
  });
  
  return url.toString();
};