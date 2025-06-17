// API utility functions with mock data for standalone React app

// Mock data for pastes
const mockPastes = [
  {
    id: 1,
    title: 'JavaScript Array Methods',
    content: 'const arr = [1, 2, 3];\narr.map(x => x * 2); // [2, 4, 6]\narr.filter(x => x > 1); // [2, 3]\narr.reduce((acc, x) => acc + x, 0); // 6',
    language: 'javascript',
    created_at: Math.floor(Date.now() / 1000) - 3600,
    views: 42,
    username: 'johndoe',
    tags: 'javascript,arrays,methods',
    is_public: 1
  },
  {
    id: 2,
    title: 'Python List Comprehension',
    content: 'numbers = [1, 2, 3, 4, 5]\n\n# Using list comprehension\nsquares = [x**2 for x in numbers]\nprint(squares)  # [1, 4, 9, 16, 25]\n\n# With conditional\neven_squares = [x**2 for x in numbers if x % 2 == 0]\nprint(even_squares)  # [4, 16]',
    language: 'python',
    created_at: Math.floor(Date.now() / 1000) - 7200,
    views: 28,
    username: 'pythondev',
    tags: 'python,list,comprehension',
    is_public: 1
  },
  {
    id: 3,
    title: 'React Hooks Example',
    content: 'import React, { useState, useEffect } from "react";\n\nfunction Counter() {\n  const [count, setCount] = useState(0);\n\n  useEffect(() => {\n    document.title = `You clicked ${count} times`;\n  }, [count]);\n\n  return (\n    <div>\n      <p>You clicked {count} times</p>\n      <button onClick={() => setCount(count + 1)}>\n        Click me\n      </button>\n    </div>\n  );\n}',
    language: 'javascript',
    created_at: Math.floor(Date.now() / 1000) - 10800,
    views: 65,
    username: 'reactfan',
    tags: 'react,hooks,javascript',
    is_public: 1
  },
  {
    id: 4,
    title: 'CSS Flexbox Layout',
    content: '.container {\n  display: flex;\n  justify-content: space-between;\n  align-items: center;\n  flex-wrap: wrap;\n}\n\n.item {\n  flex: 1 1 200px;\n  margin: 10px;\n  padding: 20px;\n  background-color: #f0f0f0;\n  border-radius: 4px;\n}',
    language: 'css',
    created_at: Math.floor(Date.now() / 1000) - 14400,
    views: 37,
    username: 'cssmaster',
    tags: 'css,flexbox,layout',
    is_public: 1
  },
  {
    id: 5,
    title: 'PHP PDO Database Connection',
    content: '<?php\ntry {\n    $db = new PDO(\'sqlite:database.sqlite\');\n    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n    \n    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");\n    $stmt->execute([$username]);\n    $user = $stmt->fetch(PDO::FETCH_ASSOC);\n    \n    echo "User found: " . $user[\'username\'];\n} catch (PDOException $e) {\n    echo "Database error: " . $e->getMessage();\n}',
    language: 'php',
    created_at: Math.floor(Date.now() / 1000) - 18000,
    views: 52,
    username: 'phpdev',
    tags: 'php,pdo,database',
    is_public: 1
  }
];

// Mock data for comments
const mockComments = [
  {
    id: 1,
    paste_id: 1,
    user_id: 'user123',
    username: 'pythondev',
    content: 'Great explanation of array methods!',
    created_at: Math.floor(Date.now() / 1000) - 1800
  },
  {
    id: 2,
    paste_id: 1,
    user_id: 'user456',
    username: 'reactfan',
    content: 'You might also want to include the forEach method.',
    created_at: Math.floor(Date.now() / 1000) - 900
  },
  {
    id: 3,
    paste_id: 2,
    user_id: 'user789',
    username: 'johndoe',
    content: 'List comprehensions are one of my favorite Python features!',
    created_at: Math.floor(Date.now() / 1000) - 2700
  }
];

// Mock data for collections
const mockCollections = [
  {
    id: 1,
    name: 'JavaScript Snippets',
    description: 'Useful JavaScript code snippets and examples',
    user_id: 'user123',
    is_public: 1,
    created_at: Math.floor(Date.now() / 1000) - 86400,
    updated_at: Math.floor(Date.now() / 1000) - 3600,
    paste_count: 3
  },
  {
    id: 2,
    name: 'CSS Tricks',
    description: 'Helpful CSS techniques and layouts',
    user_id: 'user123',
    is_public: 1,
    created_at: Math.floor(Date.now() / 1000) - 172800,
    updated_at: Math.floor(Date.now() / 1000) - 7200,
    paste_count: 2
  },
  {
    id: 3,
    name: 'Private Notes',
    description: 'Personal code snippets and notes',
    user_id: 'user123',
    is_public: 0,
    created_at: Math.floor(Date.now() / 1000) - 259200,
    updated_at: Math.floor(Date.now() / 1000) - 14400,
    paste_count: 5
  }
];

// Mock user data
const mockUser = {
  id: 'user123',
  username: 'johndoe',
  email: 'john@example.com',
  profile_image: null,
  created_at: Math.floor(Date.now() / 1000) - 2592000, // 30 days ago
  role: 'free',
  unreadNotifications: 2
};

/**
 * Check if the user is authenticated
 * @returns {Promise<Object>} - The user data if authenticated
 */
export const checkAuth = async () => {
  // For standalone mode, return mock user data
  return Promise.resolve(mockUser);
};

/**
 * Login a user
 * @param {Object} credentials - The login credentials
 * @returns {Promise<Object>} - The user data
 */
export const login = async (credentials) => {
  // Simulate login validation
  if (credentials.username === 'johndoe' && credentials.password === 'password') {
    return {
      success: true,
      user: mockUser
    };
  }
  
  return {
    success: false,
    message: 'Invalid username or password'
  };
};

/**
 * Register a new user
 * @param {Object} userData - The user registration data
 * @returns {Promise<Object>} - The user data
 */
export const register = async (userData) => {
  // Simulate registration
  if (userData.username === 'johndoe') {
    return {
      success: false,
      message: 'Username already taken'
    };
  }
  
  return {
    success: true,
    user: {
      id: 'new_user_' + Date.now(),
      username: userData.username,
      email: userData.email,
      created_at: Math.floor(Date.now() / 1000),
      role: 'free',
      unreadNotifications: 0
    }
  };
};

/**
 * Logout the current user
 * @returns {Promise<void>}
 */
export const logout = async () => {
  // Simulate logout
  return Promise.resolve();
};

/**
 * Create a new paste
 * @param {Object} pasteData - The paste data
 * @returns {Promise<Object>} - The created paste
 */
export const createPaste = async (pasteData) => {
  // Simulate paste creation
  const newPaste = {
    id: mockPastes.length + 1,
    title: pasteData.title || 'Untitled',
    content: pasteData.content,
    language: pasteData.language || 'plaintext',
    created_at: Math.floor(Date.now() / 1000),
    views: 0,
    username: mockUser.username,
    tags: pasteData.tags || '',
    is_public: pasteData.is_public ? 1 : 0
  };
  
  mockPastes.unshift(newPaste);
  
  return {
    success: true,
    paste_id: newPaste.id
  };
};

/**
 * Get a paste by ID
 * @param {string|number} id - The paste ID
 * @returns {Promise<Object>} - The paste data
 */
export const getPaste = async (id) => {
  // Find paste in mock data
  const paste = mockPastes.find(p => p.id.toString() === id.toString());
  
  if (!paste) {
    return {
      success: false,
      message: 'Paste not found'
    };
  }
  
  // Increment view count
  paste.views += 1;
  
  return {
    success: true,
    paste
  };
};

/**
 * Get recent public pastes
 * @param {number} limit - The number of pastes to retrieve
 * @returns {Promise<Array>} - The pastes
 */
export const getRecentPastes = async (limit = 5) => {
  // Sort by created_at and take the most recent
  const recentPastes = [...mockPastes]
    .filter(p => p.is_public)
    .sort((a, b) => b.created_at - a.created_at)
    .slice(0, limit);
  
  return {
    success: true,
    pastes: recentPastes
  };
};

/**
 * Get archive pastes with filtering and pagination
 * @param {Object} params - The filter parameters
 * @returns {Promise<Object>} - The pastes and pagination data
 */
export const getArchivePastes = async (params = {}) => {
  const page = parseInt(params.p) || 1;
  const limit = 10;
  const language = params.language || '';
  const tag = params.tag || '';
  const search = params.search || '';
  const sort = params.sort || 'date';
  const order = params.order || 'desc';
  
  // Filter pastes
  let filteredPastes = [...mockPastes].filter(p => p.is_public);
  
  if (language) {
    filteredPastes = filteredPastes.filter(p => p.language === language);
  }
  
  if (tag) {
    filteredPastes = filteredPastes.filter(p => p.tags && p.tags.includes(tag));
  }
  
  if (search) {
    const searchLower = search.toLowerCase();
    filteredPastes = filteredPastes.filter(p => 
      (p.title && p.title.toLowerCase().includes(searchLower)) || 
      (p.content && p.content.toLowerCase().includes(searchLower))
    );
  }
  
  // Sort pastes
  filteredPastes.sort((a, b) => {
    const aValue = sort === 'views' ? a.views : a.created_at;
    const bValue = sort === 'views' ? b.views : b.created_at;
    
    return order === 'asc' ? aValue - bValue : bValue - aValue;
  });
  
  // Paginate
  const total = filteredPastes.length;
  const totalPages = Math.ceil(total / limit);
  const offset = (page - 1) * limit;
  const paginatedPastes = filteredPastes.slice(offset, offset + limit);
  
  return {
    success: true,
    pastes: paginatedPastes,
    pagination: {
      current_page: page,
      total_pages: totalPages,
      total_items: total,
      items_per_page: limit
    }
  };
};

/**
 * Add a comment to a paste
 * @param {string|number} pasteId - The paste ID
 * @param {string} content - The comment content
 * @returns {Promise<Object>} - The created comment
 */
export const addComment = async (pasteId, content) => {
  // Create new comment
  const newComment = {
    id: mockComments.length + 1,
    paste_id: parseInt(pasteId),
    user_id: mockUser.id,
    username: mockUser.username,
    content,
    created_at: Math.floor(Date.now() / 1000)
  };
  
  mockComments.push(newComment);
  
  return {
    success: true,
    comment: newComment
  };
};

/**
 * Get comments for a paste
 * @param {string|number} pasteId - The paste ID
 * @returns {Promise<Array>} - The comments
 */
export const getComments = async (pasteId) => {
  // Filter comments for this paste
  const pasteComments = mockComments.filter(c => c.paste_id.toString() === pasteId.toString());
  
  return {
    success: true,
    comments: pasteComments
  };
};

/**
 * Get related pastes for a paste
 * @param {string|number} pasteId - The paste ID
 * @returns {Promise<Array>} - The related pastes
 */
export const getRelatedPastes = async (pasteId) => {
  // Find the current paste
  const currentPaste = mockPastes.find(p => p.id.toString() === pasteId.toString());
  
  if (!currentPaste) {
    return {
      success: false,
      message: 'Paste not found'
    };
  }
  
  // Find pastes with the same language or tags
  let relatedPastes = mockPastes.filter(p => 
    p.id !== currentPaste.id && 
    p.is_public && 
    (p.language === currentPaste.language || 
     (p.tags && currentPaste.tags && p.tags.split(',').some(tag => 
       currentPaste.tags.split(',').includes(tag.trim())
     ))
    )
  );
  
  // Sort by relevance (same language is more relevant)
  relatedPastes.sort((a, b) => {
    const aRelevance = a.language === currentPaste.language ? 2 : 1;
    const bRelevance = b.language === currentPaste.language ? 2 : 1;
    return bRelevance - aRelevance;
  });
  
  // Limit to 5 pastes
  relatedPastes = relatedPastes.slice(0, 5);
  
  return {
    success: true,
    related_pastes: relatedPastes
  };
};

/**
 * Get user collections
 * @returns {Promise<Array>} - The user's collections
 */
export const getUserCollections = async () => {
  return {
    success: true,
    collections: mockCollections
  };
};

/**
 * Create a new collection
 * @param {Object} collectionData - The collection data
 * @returns {Promise<Object>} - The created collection
 */
export const createCollection = async (collectionData) => {
  // Create new collection
  const newCollection = {
    id: mockCollections.length + 1,
    name: collectionData.name,
    description: collectionData.description || '',
    user_id: mockUser.id,
    is_public: collectionData.is_public ? 1 : 0,
    created_at: Math.floor(Date.now() / 1000),
    updated_at: Math.floor(Date.now() / 1000),
    paste_count: 0
  };
  
  mockCollections.push(newCollection);
  
  return {
    success: true,
    collection: newCollection
  };
};

/**
 * Get user account data
 * @returns {Promise<Object>} - The user account data
 */
export const getUserAccount = async () => {
  // Get user stats
  const stats = {
    totalPastes: mockPastes.filter(p => p.username === mockUser.username).length,
    publicPastes: mockPastes.filter(p => p.username === mockUser.username && p.is_public).length,
    totalViews: mockPastes.filter(p => p.username === mockUser.username).reduce((sum, p) => sum + p.views, 0),
    collections: mockCollections.length,
    following: 5,
    followers: 8
  };
  
  // Get recent pastes
  const recentPastes = mockPastes
    .filter(p => p.username === mockUser.username)
    .sort((a, b) => b.created_at - a.created_at)
    .slice(0, 5);
  
  return {
    success: true,
    user: {
      ...mockUser,
      top_language: 'JavaScript'
    },
    stats,
    recent_pastes: recentPastes
  };
};

/**
 * Update user settings
 * @param {string} settingType - The type of settings to update
 * @param {Object} settingsData - The settings data
 * @returns {Promise<Object>} - The updated settings
 */
export const updateUserSettings = async (settingType, settingsData) => {
  // Simulate settings update
  return {
    success: true,
    message: `${settingType} settings updated successfully`
  };
};