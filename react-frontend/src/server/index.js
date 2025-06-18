import express from 'express';
import cors from 'cors';
import bodyParser from 'body-parser';
import sqlite3 from 'sqlite3';
import { open } from 'sqlite3';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import cookieParser from 'cookie-parser';
import dotenv from 'dotenv';

// Load environment variables
dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Initialize express app
const app = express();
const PORT = process.env.PORT || 3001;

// Middleware
app.use(cors({
  origin: 'http://localhost:5173',
  credentials: true
}));
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(cookieParser());

// Database setup
const dbPath = join(process.cwd(), process.env.DATABASE_PATH || 'src/database/database.sqlite');
console.log(`Using database at: ${dbPath}`);

const db = new sqlite3.Database(dbPath, (err) => {
  if (err) {
    console.error('Database connection error:', err.message);
  } else {
    console.log('Connected to the SQLite database');
  }
});

// Authentication middleware
const authenticateToken = (req, res, next) => {
  const token = req.cookies.token || req.headers.authorization?.split(' ')[1];
  
  if (!token) {
    return res.status(401).json({ success: false, message: 'Authentication required' });
  }

  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = decoded;
    next();
  } catch (err) {
    return res.status(403).json({ success: false, message: 'Invalid or expired token' });
  }
};

// API Routes

// Check authentication status
app.get('/api/auth/check', (req, res) => {
  const token = req.cookies.token || req.headers.authorization?.split(' ')[1];
  
  if (!token) {
    return res.json({ success: false, message: 'Not authenticated' });
  }

  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    
    // Get user data from database
    db.get('SELECT id, username, email, profile_image, created_at FROM users WHERE id = ?', [decoded.id], (err, user) => {
      if (err || !user) {
        return res.json({ success: false, message: 'User not found' });
      }
      
      res.json({ success: true, user });
    });
  } catch (err) {
    res.json({ success: false, message: 'Invalid or expired token' });
  }
});

// Login
app.post('/api/auth/login', (req, res) => {
  const { username, password } = req.body;
  
  if (!username || !password) {
    return res.status(400).json({ success: false, message: 'Username and password are required' });
  }
  
  db.get('SELECT * FROM users WHERE username = ?', [username], (err, user) => {
    if (err) {
      return res.status(500).json({ success: false, message: 'Database error' });
    }
    
    if (!user) {
      return res.status(401).json({ success: false, message: 'Invalid username or password' });
    }
    
    // Verify password
    const passwordValid = bcrypt.compareSync(password, user.password);
    if (!passwordValid) {
      return res.status(401).json({ success: false, message: 'Invalid username or password' });
    }
    
    // Create token
    const token = jwt.sign({ id: user.id, username: user.username }, process.env.JWT_SECRET, { expiresIn: '7d' });
    
    // Remove password from user object
    delete user.password;
    
    // Set cookie
    res.cookie('token', token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      maxAge: 7 * 24 * 60 * 60 * 1000 // 7 days
    });
    
    res.json({ success: true, user, token });
  });
});

// Register
app.post('/api/auth/register', (req, res) => {
  const { username, email, password } = req.body;
  
  if (!username || !password) {
    return res.status(400).json({ success: false, message: 'Username and password are required' });
  }
  
  // Check if username already exists
  db.get('SELECT 1 FROM users WHERE username = ?', [username], (err, result) => {
    if (err) {
      return res.status(500).json({ success: false, message: 'Database error' });
    }
    
    if (result) {
      return res.status(400).json({ success: false, message: 'Username already taken' });
    }
    
    // Check if email already exists (if provided)
    if (email) {
      db.get('SELECT 1 FROM users WHERE email = ?', [email], (err, result) => {
        if (err) {
          return res.status(500).json({ success: false, message: 'Database error' });
        }
        
        if (result) {
          return res.status(400).json({ success: false, message: 'Email already in use' });
        }
        
        createUser();
      });
    } else {
      createUser();
    }
    
    function createUser() {
      const user_id = Date.now().toString();
      const hashedPassword = bcrypt.hashSync(password, 10);
      
      db.run('INSERT INTO users (id, username, email, password, created_at) VALUES (?, ?, ?, ?, ?)', 
        [user_id, username, email, hashedPassword, Math.floor(Date.now() / 1000)], 
        function(err) {
          if (err) {
            return res.status(500).json({ success: false, message: 'Failed to create account' });
          }
          
          // Create token
          const token = jwt.sign({ id: user_id, username }, process.env.JWT_SECRET, { expiresIn: '7d' });
          
          // Set cookie
          res.cookie('token', token, {
            httpOnly: true,
            secure: process.env.NODE_ENV === 'production',
            maxAge: 7 * 24 * 60 * 60 * 1000 // 7 days
          });
          
          res.json({ 
            success: true, 
            user: {
              id: user_id,
              username,
              email,
              created_at: Math.floor(Date.now() / 1000)
            },
            token
          });
        }
      );
    }
  });
});

// Logout
app.get('/api/auth/logout', (req, res) => {
  res.clearCookie('token');
  res.json({ success: true });
});

// Get recent pastes
app.get('/api/pastes/recent', (req, res) => {
  const limit = req.query.limit || 5;
  
  db.all(`
    SELECT p.*, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.is_public = 1 
    AND (p.expire_time IS NULL OR p.expire_time > ?) 
    ORDER BY p.created_at DESC 
    LIMIT ?
  `, [Math.floor(Date.now() / 1000), limit], (err, pastes) => {
    if (err) {
      return res.status(500).json({ success: false, message: 'Database error' });
    }
    
    res.json({ success: true, pastes });
  });
});

// Get paste by ID
app.get('/api/pastes/:id', (req, res) => {
  const paste_id = req.params.id;
  
  db.get(`
    SELECT p.*, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
  `, [paste_id], (err, paste) => {
    if (err) {
      return res.status(500).json({ success: false, message: 'Database error' });
    }
    
    if (!paste) {
      return res.status(404).json({ success: false, message: 'Paste not found' });
    }
    
    // Check if paste is password protected
    if (paste.password && !req.user) {
      return res.json({
        success: false,
        message: 'Password required',
        requires_password: true
      });
    }
    
    // Check if paste is expired
    if (paste.expire_time && paste.expire_time < Math.floor(Date.now() / 1000)) {
      return res.status(410).json({ success: false, message: 'Paste has expired' });
    }
    
    // Check if paste is private and user is not the owner
    if (!paste.is_public && paste.user_id !== (req.user?.id || null)) {
      return res.status(403).json({ success: false, message: 'This paste is private' });
    }
    
    // Increment view count
    db.run('UPDATE pastes SET views = views + 1 WHERE id = ?', [paste_id]);
    
    // Check if burn after read
    if (paste.burn_after_read) {
      db.run('DELETE FROM pastes WHERE id = ?', [paste_id]);
      paste.burn_after_read_viewed = true;
    }
    
    res.json({ success: true, paste });
  });
});

// Create paste
app.post('/api/pastes', (req, res) => {
  const { title, content, language, expire_time, is_public, password, tags, burn_after_read, zero_knowledge } = req.body;
  
  if (!content) {
    return res.status(400).json({ success: false, message: 'Content is required' });
  }
  
  // Calculate expiration time
  let expiry = null;
  if (expire_time && expire_time > 0) {
    expiry = Math.floor(Date.now() / 1000) + parseInt(expire_time);
  }
  
  // Hash password if provided
  let hashedPassword = null;
  if (password) {
    hashedPassword = bcrypt.hashSync(password, 10);
  }
  
  const user_id = req.user?.id || null;
  
  db.run(`
    INSERT INTO pastes (
      title, content, language, password, expire_time, created_at, 
      is_public, tags, user_id, burn_after_read, zero_knowledge, current_version
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
  `, [
    title || null,
    content,
    language || 'plaintext',
    hashedPassword,
    expiry,
    Math.floor(Date.now() / 1000),
    is_public ? 1 : 0,
    tags || '',
    user_id,
    burn_after_read ? 1 : 0,
    zero_knowledge ? 1 : 0
  ], function(err) {
    if (err) {
      return res.status(500).json({ success: false, message: 'Failed to create paste' });
    }
    
    res.json({ success: true, paste_id: this.lastID });
  });
});

// Get comments for a paste
app.get('/api/pastes/:id/comments', (req, res) => {
  const paste_id = req.params.id;
  
  db.all(`
    SELECT c.*, u.username 
    FROM comments c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.paste_id = ? 
    ORDER BY c.created_at ASC
  `, [paste_id], (err, comments) => {
    if (err) {
      return res.status(500).json({ success: false, message: 'Database error' });
    }
    
    res.json({ success: true, comments });
  });
});

// Add comment to a paste
app.post('/api/pastes/:id/comments', authenticateToken, (req, res) => {
  const paste_id = req.params.id;
  const { content } = req.body;
  
  if (!content) {
    return res.status(400).json({ success: false, message: 'Comment content is required' });
  }
  
  db.run(`
    INSERT INTO comments (paste_id, user_id, content, created_at) 
    VALUES (?, ?, ?, ?)
  `, [paste_id, req.user.id, content, Math.floor(Date.now() / 1000)], function(err) {
    if (err) {
      return res.status(500).json({ success: false, message: 'Failed to add comment' });
    }
    
    // Get the comment with username
    db.get(`
      SELECT c.*, u.username 
      FROM comments c 
      LEFT JOIN users u ON c.user_id = u.id 
      WHERE c.id = ?
    `, [this.lastID], (err, comment) => {
      if (err) {
        return res.status(500).json({ success: false, message: 'Failed to retrieve comment' });
      }
      
      res.json({ success: true, comment });
    });
  });
});

// Get related pastes
app.get('/api/pastes/:id/related', (req, res) => {
  const paste_id = req.params.id;
  
  // Get current paste metadata
  db.get('SELECT user_id, language, tags FROM pastes WHERE id = ?', [paste_id], (err, current_paste) => {
    if (err || !current_paste) {
      return res.status(500).json({ success: false, message: 'Failed to get paste data' });
    }
    
    // Build query for related pastes
    let query = `
      SELECT p.id, p.title, p.created_at, p.language, p.views, u.username
      FROM pastes p
      LEFT JOIN users u ON p.user_id = u.id
      WHERE p.id != ? AND p.is_public = 1 AND (p.expire_time IS NULL OR p.expire_time > ?)
    `;
    
    const params = [paste_id, Math.floor(Date.now() / 1000)];
    
    // Add conditions for related content
    const conditions = [];
    
    // Same user
    if (current_paste.user_id) {
      conditions.push('p.user_id = ?');
      params.push(current_paste.user_id);
    }
    
    // Same language
    if (current_paste.language) {
      conditions.push('p.language = ?');
      params.push(current_paste.language);
    }
    
    // Similar tags
    if (current_paste.tags) {
      const tags = current_paste.tags.split(',').map(tag => tag.trim()).filter(tag => tag);
      
      if (tags.length > 0) {
        const tagConditions = tags.map(() => 'p.tags LIKE ?');
        conditions.push(`(${tagConditions.join(' OR ')})`);
        
        tags.forEach(tag => {
          params.push(`%${tag}%`);
        });
      }
    }
    
    if (conditions.length > 0) {
      query += ` AND (${conditions.join(' OR ')})`;
    }
    
    query += ' ORDER BY CASE WHEN p.user_id = ? THEN 1 WHEN p.language = ? THEN 2 ELSE 3 END, p.created_at DESC LIMIT 5';
    params.push(current_paste.user_id || '', current_paste.language || '');
    
    db.all(query, params, (err, related_pastes) => {
      if (err) {
        return res.status(500).json({ success: false, message: 'Database error' });
      }
      
      res.json({ success: true, related_pastes });
    });
  });
});

// Get archive pastes with filtering and pagination
app.get('/api/pastes', (req, res) => {
  const page = parseInt(req.query.p) || 1;
  const limit = 10;
  const offset = (page - 1) * limit;
  
  const language = req.query.language || '';
  const tag = req.query.tag || '';
  const search = req.query.search || '';
  const sort = req.query.sort || 'date';
  const order = req.query.order || 'desc';
  
  // Build query
  let query = `
    SELECT p.*, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.is_public = 1 AND (p.expire_time IS NULL OR p.expire_time > ?)
  `;
  
  const params = [Math.floor(Date.now() / 1000)];
  
  if (language) {
    query += ' AND p.language = ?';
    params.push(language);
  }
  
  if (tag) {
    query += ' AND p.tags LIKE ?';
    params.push(`%${tag}%`);
  }
  
  if (search) {
    query += ' AND (p.title LIKE ? OR p.content LIKE ?)';
    params.push(`%${search}%`, `%${search}%`);
  }
  
  // Count total matching pastes
  db.get(`SELECT COUNT(*) as total FROM (${query})`, params, (err, result) => {
    if (err) {
      return res.status(500).json({ success: false, message: 'Database error' });
    }
    
    const total = result.total;
    const totalPages = Math.ceil(total / limit);
    
    // Determine sort order
    const orderBy = sort === 'views' ? 'p.views' : 'p.created_at';
    const orderDir = order === 'asc' ? 'ASC' : 'DESC';
    
    // Get paginated results
    query += ` ORDER BY ${orderBy} ${orderDir} LIMIT ? OFFSET ?`;
    params.push(limit, offset);
    
    db.all(query, params, (err, pastes) => {
      if (err) {
        return res.status(500).json({ success: false, message: 'Database error' });
      }
      
      res.json({
        success: true,
        pastes,
        pagination: {
          current_page: page,
          total_pages: totalPages,
          total_items: total,
          items_per_page: limit
        }
      });
    });
  });
});

// Get user account data
app.get('/api/account', authenticateToken, (req, res) => {
  const user_id = req.user.id;
  
  // Get user data
  db.get('SELECT * FROM users WHERE id = ?', [user_id], (err, user) => {
    if (err || !user) {
      return res.status(500).json({ success: false, message: 'User not found' });
    }
    
    // Remove sensitive data
    delete user.password;
    
    // Get user statistics
    const stats = {
      totalPastes: 0,
      publicPastes: 0,
      totalViews: 0,
      collections: 0,
      following: 0,
      followers: 0
    };
    
    // Get counts in parallel
    const promises = [
      new Promise((resolve) => {
        db.get('SELECT COUNT(*) as count FROM pastes WHERE user_id = ?', [user_id], (err, result) => {
          if (!err && result) stats.totalPastes = result.count;
          resolve();
        });
      }),
      new Promise((resolve) => {
        db.get('SELECT COUNT(*) as count FROM pastes WHERE user_id = ? AND is_public = 1', [user_id], (err, result) => {
          if (!err && result) stats.publicPastes = result.count;
          resolve();
        });
      }),
      new Promise((resolve) => {
        db.get('SELECT SUM(views) as total FROM pastes WHERE user_id = ?', [user_id], (err, result) => {
          if (!err && result && result.total) stats.totalViews = result.total;
          resolve();
        });
      }),
      new Promise((resolve) => {
        db.get('SELECT COUNT(*) as count FROM collections WHERE user_id = ?', [user_id], (err, result) => {
          if (!err && result) stats.collections = result.count;
          resolve();
        });
      }),
      new Promise((resolve) => {
        db.get('SELECT COUNT(*) as count FROM user_follows WHERE follower_id = ?', [user_id], (err, result) => {
          if (!err && result) stats.following = result.count;
          resolve();
        });
      }),
      new Promise((resolve) => {
        db.get('SELECT COUNT(*) as count FROM user_follows WHERE following_id = ?', [user_id], (err, result) => {
          if (!err && result) stats.followers = result.count;
          resolve();
        });
      })
    ];
    
    Promise.all(promises).then(() => {
      // Get recent pastes
      db.all('SELECT * FROM pastes WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', [user_id], (err, recent_pastes) => {
        res.json({
          success: true,
          user,
          stats,
          recent_pastes: recent_pastes || []
        });
      });
    });
  });
});

// Get user profile
app.get('/api/users/:username', (req, res) => {
  const username = req.params.username;
  
  // Get user profile
  db.get('SELECT id, username, email, profile_image, website, tagline, created_at, role, show_paste_count FROM users WHERE username = ?', 
    [username], (err, profile) => {
      if (err || !profile) {
        return res.status(404).json({ success: false, message: 'User not found' });
      }
      
      // Get user's public pastes
      db.all(`
        SELECT p.*, u.username 
        FROM pastes p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.user_id = ? AND p.is_public = 1 
        ORDER BY p.created_at DESC
      `, [profile.id], (err, pastes) => {
        res.json({
          success: true,
          profile,
          pastes: pastes || []
        });
      });
    });
});

// Start the server
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});