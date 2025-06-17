import { createContext, useState, useEffect, useContext } from 'react';
import { checkAuth, logout as apiLogout } from '../utils/api';

const UserContext = createContext();

export const useUser = () => useContext(UserContext);

export const UserProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    // Check if user is authenticated on initial load
    const fetchUser = async () => {
      try {
        const userData = await checkAuth();
        setUser(userData);
      } catch (error) {
        console.error('Error checking authentication:', error);
        setError('Authentication check failed');
      } finally {
        setLoading(false);
      }
    };

    fetchUser();
  }, []);

  const login = (userData) => {
    setUser(userData);
  };

  const logout = async () => {
    try {
      await apiLogout();
      setUser(null);
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  return (
    <UserContext.Provider value={{ user, loading, error, login, logout }}>
      {children}
    </UserContext.Provider>
  );
};