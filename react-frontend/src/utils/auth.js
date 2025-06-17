/**
 * Authentication utility functions
 */

import { apiGet, apiPost } from './api';

/**
 * Check if the user is authenticated
 * @returns {Promise<Object|null>} - The user data if authenticated, null otherwise
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
 * @returns {Promise<Object>} - The response data
 */
export const login = async (credentials) => {
  const formData = new FormData();
  formData.append('login', '1');
  Object.keys(credentials).forEach(key => {
    formData.append(key, credentials[key]);
  });

  return await apiPost('/', formData);
};

/**
 * Register a new user
 * @param {Object} userData - The user registration data
 * @returns {Promise<Object>} - The response data
 */
export const register = async (userData) => {
  const formData = new FormData();
  formData.append('register', '1');
  Object.keys(userData).forEach(key => {
    formData.append(key, userData[key]);
  });

  return await apiPost('/', formData);
};

/**
 * Logout the current user
 * @returns {Promise<void>}
 */
export const logout = async () => {
  await apiGet('/?logout=1');
};

/**
 * Protect a route - redirect to login if not authenticated
 * This is a utility function for use with React Router
 * @param {Object} user - The user object from context
 * @param {boolean} loading - Whether the auth check is still loading
 * @param {string} redirectTo - Where to redirect if not authenticated
 * @returns {Object} - The redirect object or null
 */
export const protectRoute = (user, loading, redirectTo = '/login') => {
  if (loading) return { loading: true };
  if (!user) return { redirect: redirectTo };
  return { allowed: true };
};