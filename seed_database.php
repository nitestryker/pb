<?php
require_once 'database.php';

class DatabaseSeeder {
    private $db;
    private $faker_names = [
        'Sarah Johnson', 'Michael Chen', 'Alex Rodriguez', 'Emma Wilson', 'David Kim',
        'Lisa Thompson', 'James Miller', 'Maria Garcia', 'Robert Taylor', 'Jennifer Lee',
        'Christopher Brown', 'Amanda Davis', 'Matthew Wilson', 'Jessica Martinez', 'Daniel Anderson',
        'Ashley Thomas', 'Ryan Jackson', 'Nicole White', 'Kevin Harris', 'Stephanie Clark',
        'Brandon Lewis', 'Megan Walker', 'Tyler Hall', 'Kimberly Allen', 'Jason Young',
        'Samantha King', 'Andrew Wright', 'Rachel Green', 'Joshua Lopez', 'Lauren Hill'
    ];
    
    private $usernames = [
        'codemaster92', 'devninja', 'pythonista', 'jsdev2024', 'reactfan',
        'backend_wizard', 'fullstack_dev', 'ui_designer', 'data_scientist', 'ml_engineer',
        'web_artisan', 'code_poet', 'syntax_lover', 'algorithm_ace', 'debug_hero',
        'script_kiddie', 'tech_enthusiast', 'open_source_fan', 'api_builder', 'database_guru',
        'frontend_pro', 'mobile_dev', 'game_coder', 'security_expert', 'devops_ninja',
        'cloud_architect', 'ai_researcher', 'blockchain_dev', 'iot_maker', 'quantum_coder'
    ];
    
    private $taglines = [
        'Building the future, one line at a time',
        'Code is poetry in motion',
        'Turning coffee into code since 2018',
        'Full-stack developer and problem solver',
        'Always learning, always coding',
        'Making the web a better place',
        'Passionate about clean code',
        'Open source enthusiast',
        'Data-driven solutions architect',
        'Creating digital experiences',
        'Debugging the world one bug at a time',
        'Code. Test. Deploy. Repeat.',
        'Innovation through code',
        'Building scalable solutions',
        'Tech lover and lifelong learner'
    ];
    
    private $paste_templates = [
        // JavaScript
        [
            'title' => 'React Todo Component',
            'language' => 'javascript',
            'content' => 'import React, { useState } from \'react\';\n\nconst TodoList = () => {\n  const [todos, setTodos] = useState([]);\n  const [input, setInput] = useState(\'\');\n\n  const addTodo = () => {\n    if (input.trim()) {\n      setTodos([...todos, { id: Date.now(), text: input, completed: false }]);\n      setInput(\'\');\n    }\n  };\n\n  const toggleTodo = (id) => {\n    setTodos(todos.map(todo => \n      todo.id === id ? { ...todo, completed: !todo.completed } : todo\n    ));\n  };\n\n  return (\n    <div className=\"todo-app\">\n      <h1>My Todo List</h1>\n      <div className=\"input-section\">\n        <input \n          value={input}\n          onChange={(e) => setInput(e.target.value)}\n          placeholder=\"Add a new todo...\"\n          onKeyPress={(e) => e.key === \'Enter\' && addTodo()}\n        />\n        <button onClick={addTodo}>Add</button>\n      </div>\n      <ul className=\"todo-list\">\n        {todos.map(todo => (\n          <li key={todo.id} className={todo.completed ? \'completed\' : \'\'}>\n            <input \n              type=\"checkbox\" \n              checked={todo.completed}\n              onChange={() => toggleTodo(todo.id)}\n            />\n            <span>{todo.text}</span>\n          </li>\n        ))}\n      </ul>\n    </div>\n  );\n};\n\nexport default TodoList;',
            'tags' => 'react, javascript, todo, hooks, frontend'
        ],
        [
            'title' => 'Express.js REST API',
            'language' => 'javascript',
            'content' => 'const express = require(\'express\');\nconst cors = require(\'cors\');\nconst morgan = require(\'morgan\');\n\nconst app = express();\nconst PORT = process.env.PORT || 3000;\n\n// Middleware\napp.use(cors());\napp.use(express.json());\napp.use(morgan(\'combined\'));\n\n// Sample data\nlet users = [\n  { id: 1, name: \'John Doe\', email: \'john@example.com\' },\n  { id: 2, name: \'Jane Smith\', email: \'jane@example.com\' }\n];\n\n// Routes\napp.get(\'/api/users\', (req, res) => {\n  res.json(users);\n});\n\napp.get(\'/api/users/:id\', (req, res) => {\n  const user = users.find(u => u.id === parseInt(req.params.id));\n  if (!user) {\n    return res.status(404).json({ error: \'User not found\' });\n  }\n  res.json(user);\n});\n\napp.post(\'/api/users\', (req, res) => {\n  const { name, email } = req.body;\n  if (!name || !email) {\n    return res.status(400).json({ error: \'Name and email are required\' });\n  }\n  \n  const newUser = {\n    id: users.length + 1,\n    name,\n    email\n  };\n  \n  users.push(newUser);\n  res.status(201).json(newUser);\n});\n\napp.put(\'/api/users/:id\', (req, res) => {\n  const user = users.find(u => u.id === parseInt(req.params.id));\n  if (!user) {\n    return res.status(404).json({ error: \'User not found\' });\n  }\n  \n  const { name, email } = req.body;\n  if (name) user.name = name;\n  if (email) user.email = email;\n  \n  res.json(user);\n});\n\napp.delete(\'/api/users/:id\', (req, res) => {\n  const index = users.findIndex(u => u.id === parseInt(req.params.id));\n  if (index === -1) {\n    return res.status(404).json({ error: \'User not found\' });\n  }\n  \n  users.splice(index, 1);\n  res.status(204).send();\n});\n\napp.listen(PORT, () => {\n  console.log(`Server running on port ${PORT}`);\n});',
            'tags' => 'express, nodejs, rest, api, backend'
        ],
        
        // Python
        [
            'title' => 'Python Data Analysis with Pandas',
            'language' => 'python',
            'content' => 'import pandas as pd\nimport numpy as np\nimport matplotlib.pyplot as plt\nimport seaborn as sns\nfrom datetime import datetime, timedelta\n\n# Generate sample data\nnp.random.seed(42)\ndates = pd.date_range(\'2023-01-01\', periods=365, freq=\'D\')\ndata = {\n    \'date\': dates,\n    \'sales\': np.random.normal(1000, 200, 365) + np.sin(np.arange(365) * 2 * np.pi / 30) * 100,\n    \'visitors\': np.random.poisson(500, 365),\n    \'conversion_rate\': np.random.beta(2, 8, 365),\n    \'category\': np.random.choice([\'Electronics\', \'Clothing\', \'Books\', \'Home\'], 365)\n}\n\ndf = pd.DataFrame(data)\n\n# Data cleaning and preprocessing\ndf[\'sales\'] = df[\'sales\'].round(2)\ndf[\'month\'] = df[\'date\'].dt.month\ndf[\'day_of_week\'] = df[\'date\'].dt.dayofweek\ndf[\'is_weekend\'] = df[\'day_of_week\'].isin([5, 6])\n\n# Basic statistics\nprint(\"Dataset Overview:\")\nprint(f\"Shape: {df.shape}\")\nprint(f\"Date range: {df[\'date\'].min()} to {df[\'date\'].max()}\")\nprint(\"\\nSummary Statistics:\")\nprint(df[[\'sales\', \'visitors\', \'conversion_rate\']].describe())\n\n# Monthly analysis\nmonthly_stats = df.groupby(\'month\').agg({\n    \'sales\': [\'mean\', \'sum\', \'std\'],\n    \'visitors\': \'mean\',\n    \'conversion_rate\': \'mean\'\n}).round(2)\n\nprint(\"\\nMonthly Performance:\")\nprint(monthly_stats)\n\n# Correlation analysis\ncorrelation_matrix = df[[\'sales\', \'visitors\', \'conversion_rate\']].corr()\nprint(\"\\nCorrelation Matrix:\")\nprint(correlation_matrix)\n\n# Category performance\ncategory_performance = df.groupby(\'category\').agg({\n    \'sales\': [\'mean\', \'sum\'],\n    \'visitors\': \'mean\'\n}).round(2)\n\nprint(\"\\nCategory Performance:\")\nprint(category_performance)\n\n# Weekend vs Weekday analysis\nweekend_analysis = df.groupby(\'is_weekend\')[[\'sales\', \'visitors\', \'conversion_rate\']].mean()\nprint(\"\\nWeekend vs Weekday Analysis:\")\nprint(weekend_analysis)\n\n# Visualization setup\nplt.style.use(\'seaborn-v0_8\')\nfig, axes = plt.subplots(2, 2, figsize=(15, 12))\n\n# Sales trend over time\naxes[0, 0].plot(df[\'date\'], df[\'sales\'], alpha=0.7)\naxes[0, 0].set_title(\'Sales Trend Over Time\')\naxes[0, 0].set_xlabel(\'Date\')\naxes[0, 0].set_ylabel(\'Sales ($)\')\n\n# Sales distribution by category\nsns.boxplot(data=df, x=\'category\', y=\'sales\', ax=axes[0, 1])\naxes[0, 1].set_title(\'Sales Distribution by Category\')\naxes[0, 1].tick_params(axis=\'x\', rotation=45)\n\n# Correlation heatmap\nsns.heatmap(correlation_matrix, annot=True, cmap=\'coolwarm\', center=0, ax=axes[1, 0])\naxes[1, 0].set_title(\'Correlation Heatmap\')\n\n# Monthly sales trend\nmonthly_sales = df.groupby(\'month\')[\'sales\'].sum()\naxes[1, 1].bar(monthly_sales.index, monthly_sales.values)\naxes[1, 1].set_title(\'Total Sales by Month\')\naxes[1, 1].set_xlabel(\'Month\')\naxes[1, 1].set_ylabel(\'Total Sales ($)\')\n\nplt.tight_layout()\nplt.show()\n\n# Advanced analysis: Find best performing days\nbest_days = df.nlargest(10, \'sales\')[[\'date\', \'sales\', \'visitors\', \'category\']]\nprint(\"\\nTop 10 Best Performing Days:\")\nprint(best_days)',
            'tags' => 'python, pandas, data-analysis, matplotlib, statistics'
        ],
        [
            'title' => 'Flask Web Application',
            'language' => 'python',
            'content' => 'from flask import Flask, render_template, request, jsonify, session, redirect, url_for\nfrom werkzeug.security import generate_password_hash, check_password_hash\nfrom datetime import datetime\nimport sqlite3\nimport os\n\napp = Flask(__name__)\napp.secret_key = \'your-secret-key-change-this\'\n\n# Database setup\ndef init_db():\n    with sqlite3.connect(\'app.db\') as conn:\n        conn.execute(\'\'\'\n            CREATE TABLE IF NOT EXISTS users (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                username TEXT UNIQUE NOT NULL,\n                email TEXT UNIQUE NOT NULL,\n                password_hash TEXT NOT NULL,\n                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n            )\n        \'\'\')\n        \n        conn.execute(\'\'\'\n            CREATE TABLE IF NOT EXISTS posts (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                title TEXT NOT NULL,\n                content TEXT NOT NULL,\n                user_id INTEGER NOT NULL,\n                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n                FOREIGN KEY (user_id) REFERENCES users (id)\n            )\n        \'\'\')\n        conn.commit()\n\n# Helper functions\ndef get_db_connection():\n    conn = sqlite3.connect(\'app.db\')\n    conn.row_factory = sqlite3.Row\n    return conn\n\ndef get_user_by_username(username):\n    conn = get_db_connection()\n    user = conn.execute(\'SELECT * FROM users WHERE username = ?\', (username,)).fetchone()\n    conn.close()\n    return user\n\n# Routes\n@app.route(\'/\')\ndef index():\n    conn = get_db_connection()\n    posts = conn.execute(\'\'\'\n        SELECT p.*, u.username \n        FROM posts p \n        JOIN users u ON p.user_id = u.id \n        ORDER BY p.created_at DESC\n    \'\'\').fetchall()\n    conn.close()\n    return render_template(\'index.html\', posts=posts)\n\n@app.route(\'/register\', methods=[\'GET\', \'POST\'])\ndef register():\n    if request.method == \'POST\':\n        username = request.form[\'username\']\n        email = request.form[\'email\']\n        password = request.form[\'password\']\n        \n        if not username or not email or not password:\n            return jsonify({\'error\': \'All fields are required\'}), 400\n        \n        # Check if user already exists\n        if get_user_by_username(username):\n            return jsonify({\'error\': \'Username already exists\'}), 400\n        \n        # Create new user\n        password_hash = generate_password_hash(password)\n        conn = get_db_connection()\n        try:\n            conn.execute(\n                \'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)\',\n                (username, email, password_hash)\n            )\n            conn.commit()\n            conn.close()\n            return jsonify({\'message\': \'User created successfully\'}), 201\n        except sqlite3.IntegrityError:\n            return jsonify({\'error\': \'Username or email already exists\'}), 400\n    \n    return render_template(\'register.html\')\n\n@app.route(\'/login\', methods=[\'GET\', \'POST\'])\ndef login():\n    if request.method == \'POST\':\n        username = request.form[\'username\']\n        password = request.form[\'password\']\n        \n        user = get_user_by_username(username)\n        \n        if user and check_password_hash(user[\'password_hash\'], password):\n            session[\'user_id\'] = user[\'id\']\n            session[\'username\'] = user[\'username\']\n            return redirect(url_for(\'index\'))\n        else:\n            return jsonify({\'error\': \'Invalid credentials\'}), 401\n    \n    return render_template(\'login.html\')\n\n@app.route(\'/logout\')\ndef logout():\n    session.clear()\n    return redirect(url_for(\'index\'))\n\n@app.route(\'/create_post\', methods=[\'GET\', \'POST\'])\ndef create_post():\n    if \'user_id\' not in session:\n        return redirect(url_for(\'login\'))\n    \n    if request.method == \'POST\':\n        title = request.form[\'title\']\n        content = request.form[\'content\']\n        \n        if not title or not content:\n            return jsonify({\'error\': \'Title and content are required\'}), 400\n        \n        conn = get_db_connection()\n        conn.execute(\n            \'INSERT INTO posts (title, content, user_id) VALUES (?, ?, ?)\',\n            (title, content, session[\'user_id\'])\n        )\n        conn.commit()\n        conn.close()\n        \n        return redirect(url_for(\'index\'))\n    \n    return render_template(\'create_post.html\')\n\n@app.route(\'/api/posts\')\ndef api_posts():\n    conn = get_db_connection()\n    posts = conn.execute(\'\'\'\n        SELECT p.id, p.title, p.content, p.created_at, u.username \n        FROM posts p \n        JOIN users u ON p.user_id = u.id \n        ORDER BY p.created_at DESC\n    \'\'\').fetchall()\n    conn.close()\n    \n    return jsonify([dict(post) for post in posts])\n\n@app.route(\'/api/users\')\ndef api_users():\n    conn = get_db_connection()\n    users = conn.execute(\'SELECT id, username, email, created_at FROM users\').fetchall()\n    conn.close()\n    \n    return jsonify([dict(user) for user in users])\n\nif __name__ == \'__main__\':\n    init_db()\n    app.run(debug=True)',
            'tags' => 'flask, python, web-development, sqlite, authentication'
        ],
        
        // Java
        [
            'title' => 'Java Spring Boot REST Controller',
            'language' => 'java',
            'content' => "package com.example.demo.controller;\n\nimport com.example.demo.model.User;\nimport com.example.demo.service.UserService;\nimport org.springframework.beans.factory.annotation.Autowired;\nimport org.springframework.http.HttpStatus;\nimport org.springframework.http.ResponseEntity;\nimport org.springframework.web.bind.annotation.*;\nimport org.springframework.validation.annotation.Validated;\n\nimport javax.validation.Valid;\nimport java.util.List;\nimport java.util.Optional;\n\n@RestController\n@RequestMapping(\"/api/users\")\n@Validated\npublic class UserController {\n\n    private final UserService userService;\n\n    @Autowired\n    public UserController(UserService userService) {\n        this.userService = userService;\n    }\n\n    @GetMapping\n    public ResponseEntity<List<User>> getAllUsers(\n            @RequestParam(defaultValue = \"0\") int page,\n            @RequestParam(defaultValue = \"10\") int size) {\n        List<User> users = userService.getAllUsers(page, size);\n        return ResponseEntity.ok(users);\n    }\n\n    @GetMapping(\"/{id}\")\n    public ResponseEntity<User> getUserById(@PathVariable Long id) {\n        Optional<User> user = userService.getUserById(id);\n        return user.map(ResponseEntity::ok)\n                   .orElse(ResponseEntity.notFound().build());\n    }\n\n    @PostMapping\n    public ResponseEntity<User> createUser(@Valid @RequestBody User user) {\n        try {\n            User createdUser = userService.createUser(user);\n            return ResponseEntity.status(HttpStatus.CREATED).body(createdUser);\n        } catch (IllegalArgumentException e) {\n            return ResponseEntity.badRequest().build();\n        }\n    }\n\n    @PutMapping(\"/{id}\")\n    public ResponseEntity<User> updateUser(\n            @PathVariable Long id, \n            @Valid @RequestBody User userDetails) {\n        try {\n            User updatedUser = userService.updateUser(id, userDetails);\n            return ResponseEntity.ok(updatedUser);\n        } catch (RuntimeException e) {\n            return ResponseEntity.notFound().build();\n        }\n    }\n\n    @DeleteMapping(\"/{id}\")\n    public ResponseEntity<Void> deleteUser(@PathVariable Long id) {\n        try {\n            userService.deleteUser(id);\n            return ResponseEntity.noContent().build();\n        } catch (RuntimeException e) {\n            return ResponseEntity.notFound().build();\n        }\n    }\n\n    @GetMapping(\"/search\")\n    public ResponseEntity<List<User>> searchUsers(\n            @RequestParam String query,\n            @RequestParam(defaultValue = \"0\") int page,\n            @RequestParam(defaultValue = \"10\") int size) {\n        List<User> users = userService.searchUsers(query, page, size);\n        return ResponseEntity.ok(users);\n    }\n\n    @GetMapping(\"/active\")\n    public ResponseEntity<List<User>> getActiveUsers() {\n        List<User> activeUsers = userService.getActiveUsers();\n        return ResponseEntity.ok(activeUsers);\n    }\n\n    @PatchMapping(\"/{id}/status\")\n    public ResponseEntity<User> updateUserStatus(\n            @PathVariable Long id,\n            @RequestParam boolean active) {\n        try {\n            User updatedUser = userService.updateUserStatus(id, active);\n            return ResponseEntity.ok(updatedUser);\n        } catch (RuntimeException e) {\n            return ResponseEntity.notFound().build();\n        }\n    }\n}",
            'tags' => 'java, spring-boot, rest-api, controller, validation'
        ],
        
        // C++
        [
            'title' => 'C++ Binary Search Tree Implementation',
            'language' => 'cpp',
            'content' => "#include <iostream>\n#include <memory>\n#include <queue>\n#include <vector>\n\ntemplate<typename T>\nclass BinarySearchTree {\nprivate:\n    struct Node {\n        T data;\n        std::unique_ptr<Node> left;\n        std::unique_ptr<Node> right;\n        \n        Node(const T& value) : data(value), left(nullptr), right(nullptr) {}\n    };\n    \n    std::unique_ptr<Node> root;\n    size_t size_;\n    \n    void insertHelper(std::unique_ptr<Node>& node, const T& value) {\n        if (!node) {\n            node = std::make_unique<Node>(value);\n            size_++;\n            return;\n        }\n        \n        if (value < node->data) {\n            insertHelper(node->left, value);\n        } else if (value > node->data) {\n            insertHelper(node->right, value);\n        }\n        // Duplicate values are ignored\n    }\n    \n    bool searchHelper(const std::unique_ptr<Node>& node, const T& value) const {\n        if (!node) {\n            return false;\n        }\n        \n        if (value == node->data) {\n            return true;\n        } else if (value < node->data) {\n            return searchHelper(node->left, value);\n        } else {\n            return searchHelper(node->right, value);\n        }\n    }\n    \n    void inorderHelper(const std::unique_ptr<Node>& node, std::vector<T>& result) const {\n        if (node) {\n            inorderHelper(node->left, result);\n            result.push_back(node->data);\n            inorderHelper(node->right, result);\n        }\n    }\n    \n    std::unique_ptr<Node> removeHelper(std::unique_ptr<Node> node, const T& value) {\n        if (!node) {\n            return nullptr;\n        }\n        \n        if (value < node->data) {\n            node->left = removeHelper(std::move(node->left), value);\n        } else if (value > node->data) {\n            node->right = removeHelper(std::move(node->right), value);\n        } else {\n            // Node to be deleted found\n            size_--;\n            \n            if (!node->left) {\n                return std::move(node->right);\n            } else if (!node->right) {\n                return std::move(node->left);\n            }\n            \n            // Node with two children\n            T minValue = findMin(node->right.get());\n            node->data = minValue;\n            node->right = removeHelper(std::move(node->right), minValue);\n            size_++; // Compensate for the decrement above\n        }\n        \n        return node;\n    }\n    \n    T findMin(Node* node) const {\n        while (node->left) {\n            node = node->left.get();\n        }\n        return node->data;\n    }\n    \n    int heightHelper(const std::unique_ptr<Node>& node) const {\n        if (!node) {\n            return -1;\n        }\n        \n        int leftHeight = heightHelper(node->left);\n        int rightHeight = heightHelper(node->right);\n        \n        return 1 + std::max(leftHeight, rightHeight);\n    }\n    \npublic:\n    BinarySearchTree() : root(nullptr), size_(0) {}\n    \n    void insert(const T& value) {\n        insertHelper(root, value);\n    }\n    \n    bool search(const T& value) const {\n        return searchHelper(root, value);\n    }\n    \n    void remove(const T& value) {\n        root = removeHelper(std::move(root), value);\n    }\n    \n    std::vector<T> inorderTraversal() const {\n        std::vector<T> result;\n        inorderHelper(root, result);\n        return result;\n    }\n    \n    std::vector<T> levelOrderTraversal() const {\n        std::vector<T> result;\n        if (!root) {\n            return result;\n        }\n        \n        std::queue<Node*> queue;\n        queue.push(root.get());\n        \n        while (!queue.empty()) {\n            Node* current = queue.front();\n            queue.pop();\n            \n            result.push_back(current->data);\n            \n            if (current->left) {\n                queue.push(current->left.get());\n            }\n            if (current->right) {\n                queue.push(current->right.get());\n            }\n        }\n        \n        return result;\n    }\n    \n    bool isEmpty() const {\n        return root == nullptr;\n    }\n    \n    size_t size() const {\n        return size_;\n    }\n    \n    int height() const {\n        return heightHelper(root);\n    }\n    \n    void clear() {\n        root.reset();\n        size_ = 0;\n    }\n};\n\n// Example usage\nint main() {\n    BinarySearchTree<int> bst;\n    \n    // Insert some values\n    std::vector<int> values = {50, 30, 70, 20, 40, 60, 80, 10, 25, 35, 45};\n    \n    std::cout << \"Inserting values: \";\n    for (int value : values) {\n        std::cout << value << \" \";\n        bst.insert(value);\n    }\n    std::cout << std::endl;\n    \n    std::cout << \"Tree size: \" << bst.size() << std::endl;\n    std::cout << \"Tree height: \" << bst.height() << std::endl;\n    \n    // Test search\n    std::cout << \"\\nSearch results:\" << std::endl;\n    for (int value : {25, 55, 80, 100}) {\n        std::cout << \"Search \" << value << \": \" << (bst.search(value) ? \"Found\" : \"Not found\") << std::endl;\n    }\n    \n    // Inorder traversal (should be sorted)\n    std::cout << \"\\nInorder traversal: \";\n    auto inorder = bst.inorderTraversal();\n    for (int value : inorder) {\n        std::cout << value << \" \";\n    }\n    std::cout << std::endl;\n    \n    // Level order traversal\n    std::cout << \"Level order traversal: \";\n    auto levelOrder = bst.levelOrderTraversal();\n    for (int value : levelOrder) {\n        std::cout << value << \" \";\n    }\n    std::cout << std::endl;\n    \n    // Remove some nodes\n    std::cout << \"\\nRemoving 20, 30, 50...\" << std::endl;\n    bst.remove(20);\n    bst.remove(30);\n    bst.remove(50);\n    \n    std::cout << \"Inorder traversal after removal: \";\n    inorder = bst.inorderTraversal();\n    for (int value : inorder) {\n        std::cout << value << \" \";\n    }\n    std::cout << std::endl;\n    \n    std::cout << \"Tree size after removal: \" << bst.size() << std::endl;\n    \n    return 0;\n}",
            'tags' => 'cpp, data-structures, binary-tree, algorithms, templates'
        ],
        
        // PHP
        [
            'title' => 'PHP User Authentication System',
            'language' => 'php',
            'content' => "<?php\nclass UserAuthentication {\n    private $pdo;\n    private $pepper = 'your_secret_pepper_here';\n    \n    public function __construct($database_config) {\n        $dsn = \"mysql:host={$database_config['host']};dbname={$database_config['database']};charset=utf8mb4\";\n        \n        try {\n            $this->pdo = new PDO($dsn, $database_config['username'], $database_config['password'], [\n                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n                PDO::ATTR_EMULATE_PREPARES => false\n            ]);\n        } catch (PDOException $e) {\n            throw new Exception('Database connection failed: ' . $e->getMessage());\n        }\n    }\n    \n    public function register($username, $email, $password, $firstName, $lastName) {\n        // Validation\n        if (strlen($username) < 3 || strlen($username) > 30) {\n            throw new Exception('Username must be between 3 and 30 characters');\n        }\n        \n        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {\n            throw new Exception('Invalid email format');\n        }\n        \n        if (strlen($password) < 8) {\n            throw new Exception('Password must be at least 8 characters long');\n        }\n        \n        // Check if user already exists\n        if ($this->userExists($username, $email)) {\n            throw new Exception('Username or email already exists');\n        }\n        \n        // Hash password with pepper\n        $passwordWithPepper = $password . $this->pepper;\n        $hashedPassword = password_hash($passwordWithPepper, PASSWORD_ARGON2ID, [\n            'memory_cost' => 65536,\n            'time_cost' => 4,\n            'threads' => 3\n        ]);\n        \n        // Generate verification token\n        $verificationToken = bin2hex(random_bytes(32));\n        \n        try {\n            $this->pdo->beginTransaction();\n            \n            $stmt = $this->pdo->prepare(\"\n                INSERT INTO users (username, email, password_hash, first_name, last_name, verification_token, created_at) \n                VALUES (?, ?, ?, ?, ?, ?, NOW())\n            \");\n            \n            $stmt->execute([\n                $username,\n                $email,\n                $hashedPassword,\n                $firstName,\n                $lastName,\n                $verificationToken\n            ]);\n            \n            $userId = $this->pdo->lastInsertId();\n            \n            // Create user profile\n            $this->createUserProfile($userId);\n            \n            $this->pdo->commit();\n            \n            // Send verification email (implement this method)\n            $this->sendVerificationEmail($email, $verificationToken);\n            \n            return [\n                'success' => true,\n                'message' => 'Registration successful. Please check your email for verification.',\n                'user_id' => $userId\n            ];\n            \n        } catch (Exception $e) {\n            $this->pdo->rollBack();\n            throw new Exception('Registration failed: ' . $e->getMessage());\n        }\n    }\n    \n    public function login($username, $password, $rememberMe = false) {\n        $stmt = $this->pdo->prepare(\"\n            SELECT id, username, email, password_hash, is_verified, is_active, failed_login_attempts, last_failed_login \n            FROM users \n            WHERE (username = ? OR email = ?) AND deleted_at IS NULL\n        \");\n        \n        $stmt->execute([$username, $username]);\n        $user = $stmt->fetch();\n        \n        if (!$user) {\n            $this->logFailedLogin($username);\n            throw new Exception('Invalid credentials');\n        }\n        \n        // Check if account is locked\n        if ($this->isAccountLocked($user)) {\n            throw new Exception('Account is temporarily locked due to multiple failed login attempts');\n        }\n        \n        if (!$user['is_active']) {\n            throw new Exception('Account is deactivated');\n        }\n        \n        if (!$user['is_verified']) {\n            throw new Exception('Please verify your email address before logging in');\n        }\n        \n        // Verify password\n        $passwordWithPepper = $password . $this->pepper;\n        if (!password_verify($passwordWithPepper, $user['password_hash'])) {\n            $this->recordFailedLogin($user['id']);\n            throw new Exception('Invalid credentials');\n        }\n        \n        // Reset failed login attempts\n        $this->resetFailedLoginAttempts($user['id']);\n        \n        // Generate session token\n        $sessionToken = bin2hex(random_bytes(32));\n        $expiresAt = $rememberMe ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+24 hours'));\n        \n        // Store session\n        $this->createSession($user['id'], $sessionToken, $expiresAt);\n        \n        // Update last login\n        $this->updateLastLogin($user['id']);\n        \n        return [\n            'success' => true,\n            'session_token' => $sessionToken,\n            'user' => [\n                'id' => $user['id'],\n                'username' => $user['username'],\n                'email' => $user['email']\n            ]\n        ];\n    }\n    \n    public function logout($sessionToken) {\n        $stmt = $this->pdo->prepare(\"DELETE FROM user_sessions WHERE session_token = ?\");\n        $stmt->execute([$sessionToken]);\n        \n        return ['success' => true, 'message' => 'Logged out successfully'];\n    }\n    \n    public function validateSession($sessionToken) {\n        $stmt = $this->pdo->prepare(\"\n            SELECT us.user_id, us.expires_at, u.username, u.email, u.is_active\n            FROM user_sessions us\n            JOIN users u ON us.user_id = u.id\n            WHERE us.session_token = ? AND us.expires_at > NOW() AND u.deleted_at IS NULL\n        \");\n        \n        $stmt->execute([$sessionToken]);\n        $session = $stmt->fetch();\n        \n        if (!$session || !$session['is_active']) {\n            return false;\n        }\n        \n        // Update session activity\n        $this->updateSessionActivity($sessionToken);\n        \n        return [\n            'user_id' => $session['user_id'],\n            'username' => $session['username'],\n            'email' => $session['email']\n        ];\n    }\n    \n    public function changePassword($userId, $currentPassword, $newPassword) {\n        // Get current password hash\n        $stmt = $this->pdo->prepare(\"SELECT password_hash FROM users WHERE id = ?\");\n        $stmt->execute([$userId]);\n        $user = $stmt->fetch();\n        \n        if (!$user) {\n            throw new Exception('User not found');\n        }\n        \n        // Verify current password\n        $currentPasswordWithPepper = $currentPassword . $this->pepper;\n        if (!password_verify($currentPasswordWithPepper, $user['password_hash'])) {\n            throw new Exception('Current password is incorrect');\n        }\n        \n        // Validate new password\n        if (strlen($newPassword) < 8) {\n            throw new Exception('New password must be at least 8 characters long');\n        }\n        \n        // Hash new password\n        $newPasswordWithPepper = $newPassword . $this->pepper;\n        $newPasswordHash = password_hash($newPasswordWithPepper, PASSWORD_ARGON2ID, [\n            'memory_cost' => 65536,\n            'time_cost' => 4,\n            'threads' => 3\n        ]);\n        \n        // Update password\n        $stmt = $this->pdo->prepare(\"\n            UPDATE users \n            SET password_hash = ?, password_changed_at = NOW() \n            WHERE id = ?\n        \");\n        \n        $stmt->execute([$newPasswordHash, $userId]);\n        \n        // Invalidate all existing sessions except current one\n        $this->invalidateUserSessions($userId);\n        \n        return ['success' => true, 'message' => 'Password changed successfully'];\n    }\n    \n    private function userExists($username, $email) {\n        $stmt = $this->pdo->prepare(\"\n            SELECT COUNT(*) FROM users \n            WHERE (username = ? OR email = ?) AND deleted_at IS NULL\n        \");\n        $stmt->execute([$username, $email]);\n        return $stmt->fetchColumn() > 0;\n    }\n    \n    private function isAccountLocked($user) {\n        if ($user['failed_login_attempts'] >= 5) {\n            $lockoutTime = strtotime($user['last_failed_login']) + (15 * 60); // 15 minutes\n            return time() < $lockoutTime;\n        }\n        return false;\n    }\n    \n    private function recordFailedLogin($userId) {\n        $stmt = $this->pdo->prepare(\"\n            UPDATE users \n            SET failed_login_attempts = failed_login_attempts + 1, last_failed_login = NOW() \n            WHERE id = ?\n        \");\n        $stmt->execute([$userId]);\n    }\n    \n    private function resetFailedLoginAttempts($userId) {\n        $stmt = $this->pdo->prepare(\"\n            UPDATE users \n            SET failed_login_attempts = 0, last_failed_login = NULL \n            WHERE id = ?\n        \");\n        $stmt->execute([$userId]);\n    }\n    \n    private function createSession($userId, $sessionToken, $expiresAt) {\n        $stmt = $this->pdo->prepare(\"\n            INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) \n            VALUES (?, ?, ?, NOW())\n        \");\n        $stmt->execute([$userId, $sessionToken, $expiresAt]);\n    }\n    \n    private function updateLastLogin($userId) {\n        $stmt = $this->pdo->prepare(\"UPDATE users SET last_login = NOW() WHERE id = ?\");\n        $stmt->execute([$userId]);\n    }\n    \n    private function updateSessionActivity($sessionToken) {\n        $stmt = $this->pdo->prepare(\"\n            UPDATE user_sessions \n            SET last_activity = NOW() \n            WHERE session_token = ?\n        \");\n        $stmt->execute([$sessionToken]);\n    }\n}",
            'tags' => 'php, authentication, security, pdo, sessions'
        ],
        
        // CSS
        [
            'title' => 'Modern CSS Grid Layout System',
            'language' => 'css',
            'content' => "/* Modern CSS Grid Layout System */\n\n/* CSS Custom Properties (Variables) */\n:root {\n  /* Colors */\n  --primary-color: #667eea;\n  --secondary-color: #764ba2;\n  --accent-color: #f093fb;\n  --text-color: #333;\n  --text-light: #666;\n  --background-color: #ffffff;\n  --surface-color: #f8f9fa;\n  --border-color: #e1e5e9;\n  --shadow-color: rgba(0, 0, 0, 0.1);\n  \n  /* Spacing */\n  --spacing-xs: 0.25rem;\n  --spacing-sm: 0.5rem;\n  --spacing-md: 1rem;\n  --spacing-lg: 1.5rem;\n  --spacing-xl: 2rem;\n  --spacing-xxl: 3rem;\n  \n  /* Typography */\n  --font-family-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;\n  --font-family-mono: 'Fira Code', 'Monaco', 'Consolas', monospace;\n  --font-size-xs: 0.75rem;\n  --font-size-sm: 0.875rem;\n  --font-size-base: 1rem;\n  --font-size-lg: 1.125rem;\n  --font-size-xl: 1.25rem;\n  --font-size-2xl: 1.5rem;\n  --font-size-3xl: 1.875rem;\n  --font-size-4xl: 2.25rem;\n  \n  /* Border Radius */\n  --radius-sm: 0.25rem;\n  --radius-md: 0.5rem;\n  --radius-lg: 0.75rem;\n  --radius-xl: 1rem;\n  \n  /* Transitions */\n  --transition-fast: 0.15s ease;\n  --transition-normal: 0.25s ease;\n  --transition-slow: 0.35s ease;\n  \n  /* Shadows */\n  --shadow-sm: 0 1px 2px 0 var(--shadow-color);\n  --shadow-md: 0 4px 6px -1px var(--shadow-color);\n  --shadow-lg: 0 10px 15px -3px var(--shadow-color);\n  --shadow-xl: 0 20px 25px -5px var(--shadow-color);\n}\n\n/* Dark theme variables */\n[data-theme=\"dark\"] {\n  --text-color: #f1f3f4;\n  --text-light: #9aa0a6;\n  --background-color: #202124;\n  --surface-color: #303134;\n  --border-color: #5f6368;\n  --shadow-color: rgba(0, 0, 0, 0.3);\n}\n\n/* Reset and base styles */\n*,\n*::before,\n*::after {\n  box-sizing: border-box;\n  margin: 0;\n  padding: 0;\n}\n\nbody {\n  font-family: var(--font-family-primary);\n  font-size: var(--font-size-base);\n  line-height: 1.6;\n  color: var(--text-color);\n  background-color: var(--background-color);\n  transition: background-color var(--transition-normal), color var(--transition-normal);\n}\n\n/* Grid System */\n.container {\n  width: 100%;\n  max-width: 1200px;\n  margin: 0 auto;\n  padding: 0 var(--spacing-md);\n}\n\n.grid {\n  display: grid;\n  gap: var(--spacing-md);\n}\n\n/* Responsive grid layouts */\n.grid-1 { grid-template-columns: 1fr; }\n.grid-2 { grid-template-columns: repeat(2, 1fr); }\n.grid-3 { grid-template-columns: repeat(3, 1fr); }\n.grid-4 { grid-template-columns: repeat(4, 1fr); }\n.grid-5 { grid-template-columns: repeat(5, 1fr); }\n.grid-6 { grid-template-columns: repeat(6, 1fr); }\n\n/* Auto-fit and auto-fill grids */\n.grid-auto-fit {\n  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));\n}\n\n.grid-auto-fill {\n  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));\n}\n\n/* Layout components */\n.sidebar-layout {\n  display: grid;\n  grid-template-columns: 250px 1fr;\n  gap: var(--spacing-lg);\n  min-height: 100vh;\n}\n\n.header-content-footer {\n  display: grid;\n  grid-template-rows: auto 1fr auto;\n  min-height: 100vh;\n}\n\n.hero-section {\n  display: grid;\n  place-items: center;\n  min-height: 60vh;\n  text-align: center;\n  padding: var(--spacing-xxl) 0;\n}\n\n/* Card components */\n.card {\n  background: var(--surface-color);\n  border: 1px solid var(--border-color);\n  border-radius: var(--radius-lg);\n  padding: var(--spacing-lg);\n  box-shadow: var(--shadow-sm);\n  transition: transform var(--transition-fast), box-shadow var(--transition-fast);\n}\n\n.card:hover {\n  transform: translateY(-2px);\n  box-shadow: var(--shadow-md);\n}\n\n.card-header {\n  margin-bottom: var(--spacing-md);\n  padding-bottom: var(--spacing-md);\n  border-bottom: 1px solid var(--border-color);\n}\n\n.card-title {\n  font-size: var(--font-size-xl);\n  font-weight: 600;\n  color: var(--text-color);\n}\n\n.card-subtitle {\n  font-size: var(--font-size-sm);\n  color: var(--text-light);\n  margin-top: var(--spacing-xs);\n}\n\n.card-body {\n  margin-bottom: var(--spacing-md);\n}\n\n.card-footer {\n  margin-top: var(--spacing-md);\n  padding-top: var(--spacing-md);\n  border-top: 1px solid var(--border-color);\n  display: flex;\n  justify-content: space-between;\n  align-items: center;\n}\n\n/* Button system */\n.btn {\n  display: inline-flex;\n  align-items: center;\n  justify-content: center;\n  padding: var(--spacing-sm) var(--spacing-md);\n  border: 2px solid transparent;\n  border-radius: var(--radius-md);\n  font-size: var(--font-size-sm);\n  font-weight: 500;\n  text-decoration: none;\n  cursor: pointer;\n  transition: all var(--transition-fast);\n  user-select: none;\n}\n\n.btn-primary {\n  background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));\n  color: white;\n  border-color: var(--primary-color);\n}\n\n.btn-primary:hover {\n  transform: translateY(-1px);\n  box-shadow: var(--shadow-md);\n}\n\n.btn-secondary {\n  background: var(--surface-color);\n  color: var(--text-color);\n  border-color: var(--border-color);\n}\n\n.btn-secondary:hover {\n  background: var(--border-color);\n}\n\n.btn-ghost {\n  background: transparent;\n  color: var(--primary-color);\n  border-color: var(--primary-color);\n}\n\n.btn-ghost:hover {\n  background: var(--primary-color);\n  color: white;\n}\n\n/* Utility classes */\n.text-center { text-align: center; }\n.text-left { text-align: left; }\n.text-right { text-align: right; }\n\n.hidden { display: none; }\n.block { display: block; }\n.inline-block { display: inline-block; }\n.flex { display: flex; }\n.grid { display: grid; }\n\n.items-center { align-items: center; }\n.items-start { align-items: flex-start; }\n.items-end { align-items: flex-end; }\n\n.justify-center { justify-content: center; }\n.justify-between { justify-content: space-between; }\n.justify-around { justify-content: space-around; }\n\n.gap-xs { gap: var(--spacing-xs); }\n.gap-sm { gap: var(--spacing-sm); }\n.gap-md { gap: var(--spacing-md); }\n.gap-lg { gap: var(--spacing-lg); }\n.gap-xl { gap: var(--spacing-xl); }\n\n/* Responsive utilities */\n@media (max-width: 768px) {\n  .grid-2,\n  .grid-3,\n  .grid-4,\n  .grid-5,\n  .grid-6 {\n    grid-template-columns: 1fr;\n  }\n  \n  .sidebar-layout {\n    grid-template-columns: 1fr;\n  }\n  \n  .container {\n    padding: 0 var(--spacing-sm);\n  }\n  \n  .card {\n    padding: var(--spacing-md);\n  }\n}\n\n@media (max-width: 480px) {\n  :root {\n    --spacing-md: 0.75rem;\n    --spacing-lg: 1rem;\n    --spacing-xl: 1.5rem;\n  }\n  \n  .hero-section {\n    min-height: 40vh;\n    padding: var(--spacing-xl) 0;\n  }\n}\n\n/* Animation classes */\n.fade-in {\n  animation: fadeIn 0.6s ease-out;\n}\n\n.slide-up {\n  animation: slideUp 0.6s ease-out;\n}\n\n.scale-in {\n  animation: scaleIn 0.4s ease-out;\n}\n\n@keyframes fadeIn {\n  from {\n    opacity: 0;\n  }\n  to {\n    opacity: 1;\n  }\n}\n\n@keyframes slideUp {\n  from {\n    opacity: 0;\n    transform: translateY(20px);\n  }\n  to {\n    opacity: 1;\n    transform: translateY(0);\n  }\n}\n\n@keyframes scaleIn {\n  from {\n    opacity: 0;\n    transform: scale(0.9);\n  }\n  to {\n    opacity: 1;\n    transform: scale(1);\n  }\n}\n\n/* Focus states for accessibility */\n.btn:focus,\ninput:focus,\ntextarea:focus,\nselect:focus {\n  outline: 2px solid var(--primary-color);\n  outline-offset: 2px;\n}\n\n/* Print styles */\n@media print {\n  .no-print {\n    display: none !important;\n  }\n  \n  .card {\n    box-shadow: none;\n    border: 1px solid #ccc;\n  }\n  \n  .btn {\n    border: 1px solid #ccc;\n    background: white !important;\n    color: black !important;\n  }\n}",
            'tags' => 'css, grid, layout, responsive, design-system'
        ],
        
        // Go
        [
            'title' => 'Go HTTP Server with Middleware',
            'language' => 'go',
            'content' => "package main\n\nimport (\n\t\"context\"\n\t\"encoding/json\"\n\t\"fmt\"\n\t\"log\"\n\t\"net/http\"\n\t\"os\"\n\t\"os/signal\"\n\t\"strconv\"\n\t\"strings\"\n\t\"syscall\"\n\t\"time\"\n\n\t\"github.com/gorilla/mux\"\n\t\"github.com/rs/cors\"\n)\n\n// User represents a user in our system\ntype User struct {\n\tID       int    `json:\"id\"`\n\tName     string `json:\"name\"`\n\tEmail    string `json:\"email\"`\n\tCreatedAt time.Time `json:\"created_at\"`\n}\n\n// Response represents a standard API response\ntype Response struct {\n\tSuccess bool        `json:\"success\"`\n\tMessage string      `json:\"message,omitempty\"`\n\tData    interface{} `json:\"data,omitempty\"`\n\tError   string      `json:\"error,omitempty\"`\n}\n\n// In-memory storage for demo purposes\nvar users = []User{\n\t{ID: 1, Name: \"John Doe\", Email: \"john@example.com\", CreatedAt: time.Now()},\n\t{ID: 2, Name: \"Jane Smith\", Email: \"jane@example.com\", CreatedAt: time.Now()},\n}\nvar nextID = 3\n\n// Middleware for logging requests\nfunc loggingMiddleware(next http.Handler) http.Handler {\n\treturn http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {\n\t\tstart := time.Now()\n\t\t\n\t\t// Create a wrapped response writer to capture status code\n\t\twrapped := &responseWriter{ResponseWriter: w, statusCode: http.StatusOK}\n\t\t\n\t\tnext.ServeHTTP(wrapped, r)\n\t\t\n\t\tduration := time.Since(start)\n\t\tlog.Printf(\"%s %s %d %v\", r.Method, r.URL.Path, wrapped.statusCode, duration)\n\t})\n}\n\n// Response writer wrapper to capture status code\ntype responseWriter struct {\n\thttp.ResponseWriter\n\tstatusCode int\n}\n\nfunc (rw *responseWriter) WriteHeader(code int) {\n\trw.statusCode = code\n\trw.ResponseWriter.WriteHeader(code)\n}\n\n// Middleware for authentication (simple token-based)\nfunc authMiddleware(next http.Handler) http.Handler {\n\treturn http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {\n\t\t// Skip auth for certain endpoints\n\t\tif r.URL.Path == \"/health\" || r.URL.Path == \"/\" {\n\t\t\tnext.ServeHTTP(w, r)\n\t\t\treturn\n\t\t}\n\t\t\n\t\ttoken := r.Header.Get(\"Authorization\")\n\t\tif token == \"\" {\n\t\t\ttoken = r.URL.Query().Get(\"token\")\n\t\t}\n\t\t\n\t\t// Simple token validation (in production, use proper JWT validation)\n\t\tif !strings.HasPrefix(token, \"Bearer \") && token != \"demo-token\" {\n\t\t\trespondWithError(w, http.StatusUnauthorized, \"Invalid or missing authorization token\")\n\t\t\treturn\n\t\t}\n\t\t\n\t\tnext.ServeHTTP(w, r)\n\t})\n}\n\n// Middleware for rate limiting (simple implementation)\nfunc rateLimitMiddleware(next http.Handler) http.Handler {\n\ttype client struct {\n\t\tlastSeen time.Time\n\t\trequests int\n\t}\n\t\n\tclients := make(map[string]*client)\n\tcleanupTicker := time.NewTicker(time.Minute)\n\t\n\tgo func() {\n\t\tfor range cleanupTicker.C {\n\t\t\tnow := time.Now()\n\t\t\tfor ip, c := range clients {\n\t\t\t\tif now.Sub(c.lastSeen) > time.Minute {\n\t\t\t\t\tdelete(clients, ip)\n\t\t\t\t}\n\t\t\t}\n\t\t}\n\t}()\n\t\n\treturn http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {\n\t\tip := r.RemoteAddr\n\t\tnow := time.Now()\n\t\t\n\t\tc, exists := clients[ip]\n\t\tif !exists {\n\t\t\tclients[ip] = &client{lastSeen: now, requests: 1}\n\t\t\tnext.ServeHTTP(w, r)\n\t\t\treturn\n\t\t}\n\t\t\n\t\t// Reset counter if more than a minute has passed\n\t\tif now.Sub(c.lastSeen) > time.Minute {\n\t\t\tc.requests = 1\n\t\t\tc.lastSeen = now\n\t\t\tnext.ServeHTTP(w, r)\n\t\t\treturn\n\t\t}\n\t\t\n\t\t// Check rate limit (100 requests per minute)\n\t\tif c.requests >= 100 {\n\t\t\trespondWithError(w, http.StatusTooManyRequests, \"Rate limit exceeded\")\n\t\t\treturn\n\t\t}\n\t\t\n\t\tc.requests++\n\t\tc.lastSeen = now\n\t\tnext.ServeHTTP(w, r)\n\t})\n}\n\n// Health check handler\nfunc healthHandler(w http.ResponseWriter, r *http.Request) {\n\tresponse := Response{\n\t\tSuccess: true,\n\t\tMessage: \"Server is healthy\",\n\t\tData: map[string]interface{}{\n\t\t\t\"timestamp\": time.Now(),\n\t\t\t\"uptime\":    time.Since(startTime),\n\t\t\t\"version\":   \"1.0.0\",\n\t\t},\n\t}\n\trespondWithJSON(w, http.StatusOK, response)\n}\n\n// Get all users\nfunc getUsersHandler(w http.ResponseWriter, r *http.Request) {\n\tresponse := Response{\n\t\tSuccess: true,\n\t\tData:    users,\n\t}\n\trespondWithJSON(w, http.StatusOK, response)\n}\n\n// Get user by ID\nfunc getUserHandler(w http.ResponseWriter, r *http.Request) {\n\tvars := mux.Vars(r)\n\tid, err := strconv.Atoi(vars[\"id\"])\n\tif err != nil {\n\t\trespondWithError(w, http.StatusBadRequest, \"Invalid user ID\")\n\t\treturn\n\t}\n\t\n\tfor _, user := range users {\n\t\tif user.ID == id {\n\t\t\tresponse := Response{\n\t\t\t\tSuccess: true,\n\t\t\t\tData:    user,\n\t\t\t}\n\t\t\trespondWithJSON(w, http.StatusOK, response)\n\t\t\treturn\n\t\t}\n\t}\n\t\n\trespondWithError(w, http.StatusNotFound, \"User not found\")\n}\n\n// Create new user\nfunc createUserHandler(w http.ResponseWriter, r *http.Request) {\n\tvar user User\n\tif err := json.NewDecoder(r.Body).Decode(&user); err != nil {\n\t\trespondWithError(w, http.StatusBadRequest, \"Invalid JSON payload\")\n\t\treturn\n\t}\n\t\n\t// Validation\n\tif user.Name == \"\" || user.Email == \"\" {\n\t\trespondWithError(w, http.StatusBadRequest, \"Name and email are required\")\n\t\treturn\n\t}\n\t\n\t// Check if email already exists\n\tfor _, existingUser := range users {\n\t\tif existingUser.Email == user.Email {\n\t\t\trespondWithError(w, http.StatusConflict, \"Email already exists\")\n\t\t\treturn\n\t\t}\n\t}\n\t\n\tuser.ID = nextID\n\tnextID++\n\tuser.CreatedAt = time.Now()\n\t\n\tusers = append(users, user)\n\t\n\tresponse := Response{\n\t\tSuccess: true,\n\t\tMessage: \"User created successfully\",\n\t\tData:    user,\n\t}\n\trespondWithJSON(w, http.StatusCreated, response)\n}\n\n// Update user\nfunc updateUserHandler(w http.ResponseWriter, r *http.Request) {\n\tvars := mux.Vars(r)\n\tid, err := strconv.Atoi(vars[\"id\"])\n\tif err != nil {\n\t\trespondWithError(w, http.StatusBadRequest, \"Invalid user ID\")\n\t\treturn\n\t}\n\t\n\tvar updatedUser User\n\tif err := json.NewDecoder(r.Body).Decode(&updatedUser); err != nil {\n\t\trespondWithError(w, http.StatusBadRequest, \"Invalid JSON payload\")\n\t\treturn\n\t}\n\t\n\tfor i, user := range users {\n\t\tif user.ID == id {\n\t\t\tif updatedUser.Name != \"\" {\n\t\t\t\tusers[i].Name = updatedUser.Name\n\t\t\t}\n\t\t\tif updatedUser.Email != \"\" {\n\t\t\t\tusers[i].Email = updatedUser.Email\n\t\t\t}\n\t\t\t\n\t\t\tresponse := Response{\n\t\t\t\tSuccess: true,\n\t\t\t\tMessage: \"User updated successfully\",\n\t\t\t\tData:    users[i],\n\t\t\t}\n\t\t\trespondWithJSON(w, http.StatusOK, response)\n\t\t\treturn\n\t\t}\n\t}\n\t\n\trespondWithError(w, http.StatusNotFound, \"User not found\")\n}\n\n// Delete user\nfunc deleteUserHandler(w http.ResponseWriter, r *http.Request) {\n\tvars := mux.Vars(r)\n\tid, err := strconv.Atoi(vars[\"id\"])\n\tif err != nil {\n\t\trespondWithError(w, http.StatusBadRequest, \"Invalid user ID\")\n\t\treturn\n\t}\n\t\n\tfor i, user := range users {\n\t\tif user.ID == id {\n\t\t\t// Remove user from slice\n\t\t\tusers = append(users[:i], users[i+1:]...)\n\t\t\t\n\t\t\tresponse := Response{\n\t\t\t\tSuccess: true,\n\t\t\t\tMessage: \"User deleted successfully\",\n\t\t\t}\n\t\t\trespondWithJSON(w, http.StatusOK, response)\n\t\t\treturn\n\t\t}\n\t}\n\t\n\trespondWithError(w, http.StatusNotFound, \"User not found\")\n}\n\n// Helper functions\nfunc respondWithJSON(w http.ResponseWriter, code int, payload interface{}) {\n\tw.Header().Set(\"Content-Type\", \"application/json\")\n\tw.WriteHeader(code)\n\tjson.NewEncoder(w).Encode(payload)\n}\n\nfunc respondWithError(w http.ResponseWriter, code int, message string) {\n\tresponse := Response{\n\t\tSuccess: false,\n\t\tError:   message,\n\t}\n\trespondWithJSON(w, code, response)\n}\n\nvar startTime = time.Now()\n\nfunc main() {\n\t// Create router\n\tr := mux.NewRouter()\n\t\n\t// Apply middleware\n\tr.Use(loggingMiddleware)\n\tr.Use(rateLimitMiddleware)\n\tr.Use(authMiddleware)\n\t\n\t// Routes\n\tr.HandleFunc(\"/\", func(w http.ResponseWriter, r *http.Request) {\n\t\tresponse := Response{\n\t\t\tSuccess: true,\n\t\t\tMessage: \"Welcome to the Go API Server\",\n\t\t\tData: map[string]string{\n\t\t\t\t\"version\": \"1.0.0\",\n\t\t\t\t\"docs\":    \"/api/users\",\n\t\t\t},\n\t\t}\n\t\trespondWithJSON(w, http.StatusOK, response)\n\t}).Methods(\"GET\")\n\t\n\tr.HandleFunc(\"/health\", healthHandler).Methods(\"GET\")\n\tr.HandleFunc(\"/api/users\", getUsersHandler).Methods(\"GET\")\n\tr.HandleFunc(\"/api/users/{id}\", getUserHandler).Methods(\"GET\")\n\tr.HandleFunc(\"/api/users\", createUserHandler).Methods(\"POST\")\n\tr.HandleFunc(\"/api/users/{id}\", updateUserHandler).Methods(\"PUT\")\n\tr.HandleFunc(\"/api/users/{id}\", deleteUserHandler).Methods(\"DELETE\")\n\t\n\t// CORS configuration\n\tc := cors.New(cors.Options{\n\t\tAllowedOrigins: []string{\"*\"},\n\t\tAllowedMethods: []string{\"GET\", \"POST\", \"PUT\", \"DELETE\", \"OPTIONS\"},\n\t\tAllowedHeaders: []string{\"*\"},\n\t})\n\t\n\thandler := c.Handler(r)\n\t\n\t// Server configuration\n\tport := os.Getenv(\"PORT\")\n\tif port == \"\" {\n\t\tport = \"8080\"\n\t}\n\t\n\tsrv := &http.Server{\n\t\tAddr:         \":\" + port,\n\t\tHandler:      handler,\n\t\tReadTimeout:  15 * time.Second,\n\t\tWriteTimeout: 15 * time.Second,\n\t\tIdleTimeout:  60 * time.Second,\n\t}\n\t\n\t// Graceful shutdown\n\tgo func() {\n\t\tlog.Printf(\"Server starting on port %s\", port)\n\t\tlog.Printf(\"Health check available at http://localhost:%s/health\", port)\n\t\tlog.Printf(\"API documentation at http://localhost:%s/api/users\", port)\n\t\tlog.Printf(\"Use token 'demo-token' or 'Bearer your-token' for authentication\")\n\t\t\n\t\tif err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {\n\t\t\tlog.Fatalf(\"Server failed to start: %v\", err)\n\t\t}\n\t}()\n\t\n\t// Wait for interrupt signal to gracefully shutdown the server\n\tquit := make(chan os.Signal, 1)\n\tsignal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)\n\t<-quit\n\tlog.Println(\"Shutting down server...\")\n\t\n\tctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)\n\tdefer cancel()\n\t\n\tif err := srv.Shutdown(ctx); err != nil {\n\t\tlog.Fatal(\"Server forced to shutdown:\", err)\n\t}\n\t\n\tlog.Println(\"Server exited\")\n}",
            'tags' => 'go, http-server, rest-api, middleware, gorilla-mux'
        ],
        
        // SQL
        [
            'title' => 'Advanced SQL Queries for E-commerce Analytics',
            'language' => 'sql',
            'content' => "-- Advanced SQL Queries for E-commerce Analytics\n-- This script demonstrates complex SQL operations for business intelligence\n\n-- 1. Create sample tables (PostgreSQL syntax)\nCREATE TABLE IF NOT EXISTS customers (\n    customer_id SERIAL PRIMARY KEY,\n    email VARCHAR(255) UNIQUE NOT NULL,\n    first_name VARCHAR(100) NOT NULL,\n    last_name VARCHAR(100) NOT NULL,\n    registration_date DATE NOT NULL,\n    country VARCHAR(50),\n    city VARCHAR(100),\n    age INTEGER,\n    gender VARCHAR(10)\n);\n\nCREATE TABLE IF NOT EXISTS products (\n    product_id SERIAL PRIMARY KEY,\n    product_name VARCHAR(255) NOT NULL,\n    category VARCHAR(100) NOT NULL,\n    subcategory VARCHAR(100),\n    price DECIMAL(10,2) NOT NULL,\n    cost DECIMAL(10,2) NOT NULL,\n    supplier_id INTEGER,\n    created_date DATE NOT NULL\n);\n\nCREATE TABLE IF NOT EXISTS orders (\n    order_id SERIAL PRIMARY KEY,\n    customer_id INTEGER REFERENCES customers(customer_id),\n    order_date DATE NOT NULL,\n    total_amount DECIMAL(10,2) NOT NULL,\n    status VARCHAR(20) DEFAULT 'pending',\n    shipping_cost DECIMAL(8,2) DEFAULT 0,\n    discount_amount DECIMAL(8,2) DEFAULT 0\n);\n\nCREATE TABLE IF NOT EXISTS order_items (\n    order_item_id SERIAL PRIMARY KEY,\n    order_id INTEGER REFERENCES orders(order_id),\n    product_id INTEGER REFERENCES products(product_id),\n    quantity INTEGER NOT NULL,\n    unit_price DECIMAL(10,2) NOT NULL,\n    discount_percent DECIMAL(5,2) DEFAULT 0\n);\n\n-- 2. Customer Lifetime Value (CLV) Analysis\nWITH customer_metrics AS (\n    SELECT \n        c.customer_id,\n        c.email,\n        c.first_name,\n        c.last_name,\n        c.registration_date,\n        COUNT(DISTINCT o.order_id) as total_orders,\n        SUM(o.total_amount) as total_spent,\n        AVG(o.total_amount) as avg_order_value,\n        MAX(o.order_date) as last_order_date,\n        MIN(o.order_date) as first_order_date,\n        EXTRACT(DAYS FROM (MAX(o.order_date) - MIN(o.order_date))) as customer_lifespan_days\n    FROM customers c\n    LEFT JOIN orders o ON c.customer_id = o.customer_id\n    WHERE o.status = 'completed'\n    GROUP BY c.customer_id, c.email, c.first_name, c.last_name, c.registration_date\n),\nclv_calculation AS (\n    SELECT \n        *,\n        CASE \n            WHEN customer_lifespan_days > 0 THEN \n                (total_spent / NULLIF(customer_lifespan_days, 0)) * 365 -- Annualized CLV\n            ELSE total_spent\n        END as estimated_annual_clv,\n        CASE\n            WHEN total_spent >= 1000 THEN 'High Value'\n            WHEN total_spent >= 500 THEN 'Medium Value'\n            WHEN total_spent >= 100 THEN 'Low Value'\n            ELSE 'New Customer'\n        END as customer_segment\n    FROM customer_metrics\n)\nSELECT \n    customer_segment,\n    COUNT(*) as customer_count,\n    AVG(total_spent) as avg_total_spent,\n    AVG(total_orders) as avg_total_orders,\n    AVG(avg_order_value) as avg_order_value,\n    AVG(estimated_annual_clv) as avg_estimated_clv\nFROM clv_calculation\nGROUP BY customer_segment\nORDER BY avg_total_spent DESC;\n\n-- 3. Product Performance Analysis with Rankings\nWITH product_sales AS (\n    SELECT \n        p.product_id,\n        p.product_name,\n        p.category,\n        p.subcategory,\n        p.price,\n        p.cost,\n        (p.price - p.cost) as profit_per_unit,\n        COUNT(DISTINCT oi.order_id) as total_orders,\n        SUM(oi.quantity) as total_quantity_sold,\n        SUM(oi.quantity * oi.unit_price * (1 - oi.discount_percent/100)) as total_revenue,\n        SUM(oi.quantity * p.cost) as total_cost,\n        AVG(oi.unit_price) as avg_selling_price\n    FROM products p\n    LEFT JOIN order_items oi ON p.product_id = oi.product_id\n    LEFT JOIN orders o ON oi.order_id = o.order_id\n    WHERE o.status = 'completed'\n    GROUP BY p.product_id, p.product_name, p.category, p.subcategory, p.price, p.cost\n),\nproduct_rankings AS (\n    SELECT \n        *,\n        (total_revenue - total_cost) as total_profit,\n        ROUND(((total_revenue - total_cost) / NULLIF(total_revenue, 0) * 100), 2) as profit_margin_percent,\n        ROW_NUMBER() OVER (ORDER BY total_revenue DESC) as revenue_rank,\n        ROW_NUMBER() OVER (ORDER BY total_quantity_sold DESC) as quantity_rank,\n        ROW_NUMBER() OVER (ORDER BY (total_revenue - total_cost) DESC) as profit_rank,\n        ROW_NUMBER() OVER (PARTITION BY category ORDER BY total_revenue DESC) as category_revenue_rank\n    FROM product_sales\n    WHERE total_quantity_sold > 0\n)\nSELECT \n    product_name,\n    category,\n    subcategory,\n    total_revenue,\n    total_quantity_sold,\n    total_profit,\n    profit_margin_percent,\n    revenue_rank,\n    profit_rank,\n    category_revenue_rank\nFROM product_rankings\nWHERE revenue_rank <= 20\nORDER BY revenue_rank;\n\n-- 4. Monthly Sales Trend Analysis with Growth Rates\nWITH monthly_sales AS (\n    SELECT \n        DATE_TRUNC('month', o.order_date) as sales_month,\n        COUNT(DISTINCT o.order_id) as total_orders,\n        COUNT(DISTINCT o.customer_id) as unique_customers,\n        SUM(o.total_amount) as total_revenue,\n        AVG(o.total_amount) as avg_order_value,\n        SUM(oi.quantity) as total_items_sold\n    FROM orders o\n    JOIN order_items oi ON o.order_id = oi.order_id\n    WHERE o.status = 'completed'\n    GROUP BY DATE_TRUNC('month', o.order_date)\n),\nmonthly_growth AS (\n    SELECT \n        sales_month,\n        total_orders,\n        unique_customers,\n        total_revenue,\n        avg_order_value,\n        total_items_sold,\n        LAG(total_revenue) OVER (ORDER BY sales_month) as prev_month_revenue,\n        LAG(total_orders) OVER (ORDER BY sales_month) as prev_month_orders,\n        LAG(unique_customers) OVER (ORDER BY sales_month) as prev_month_customers\n    FROM monthly_sales\n)\nSELECT \n    TO_CHAR(sales_month, 'YYYY-MM') as month,\n    total_orders,\n    unique_customers,\n    ROUND(total_revenue, 2) as total_revenue,\n    ROUND(avg_order_value, 2) as avg_order_value,\n    total_items_sold,\n    CASE \n        WHEN prev_month_revenue IS NOT NULL THEN \n            ROUND(((total_revenue - prev_month_revenue) / prev_month_revenue * 100), 2)\n        ELSE NULL\n    END as revenue_growth_percent,\n    CASE \n        WHEN prev_month_orders IS NOT NULL THEN \n            ROUND(((total_orders - prev_month_orders) / prev_month_orders::DECIMAL * 100), 2)\n        ELSE NULL\n    END as order_growth_percent,\n    CASE \n        WHEN prev_month_customers IS NOT NULL THEN \n            ROUND(((unique_customers - prev_month_customers) / prev_month_customers::DECIMAL * 100), 2)\n        ELSE NULL\n    END as customer_growth_percent\nFROM monthly_growth\nORDER BY sales_month;\n\n-- 5. Customer Cohort Analysis\nWITH customer_cohorts AS (\n    SELECT \n        customer_id,\n        DATE_TRUNC('month', MIN(order_date)) as cohort_month\n    FROM orders\n    WHERE status = 'completed'\n    GROUP BY customer_id\n),\ncohort_data AS (\n    SELECT \n        cc.cohort_month,\n        DATE_TRUNC('month', o.order_date) as order_month,\n        COUNT(DISTINCT o.customer_id) as customers,\n        SUM(o.total_amount) as revenue\n    FROM customer_cohorts cc\n    JOIN orders o ON cc.customer_id = o.customer_id\n    WHERE o.status = 'completed'\n    GROUP BY cc.cohort_month, DATE_TRUNC('month', o.order_date)\n),\ncohort_table AS (\n    SELECT \n        cohort_month,\n        order_month,\n        EXTRACT(EPOCH FROM (order_month - cohort_month)) / (30 * 24 * 60 * 60) as month_number,\n        customers,\n        revenue\n    FROM cohort_data\n),\ncohort_sizes AS (\n    SELECT \n        cohort_month,\n        customers as cohort_size\n    FROM cohort_table\n    WHERE month_number = 0\n)\nSELECT \n    TO_CHAR(ct.cohort_month, 'YYYY-MM') as cohort,\n    CAST(ct.month_number as INTEGER) as month,\n    ct.customers,\n    cs.cohort_size,\n    ROUND((ct.customers::DECIMAL / cs.cohort_size * 100), 2) as retention_rate,\n    ROUND(ct.revenue, 2) as revenue\nFROM cohort_table ct\nJOIN cohort_sizes cs ON ct.cohort_month = cs.cohort_month\nWHERE ct.month_number <= 12\nORDER BY ct.cohort_month, ct.month_number;\n\n-- 6. Advanced Customer Segmentation using RFM Analysis\nWITH rfm_data AS (\n    SELECT \n        c.customer_id,\n        c.email,\n        EXTRACT(DAYS FROM (CURRENT_DATE - MAX(o.order_date))) as recency_days,\n        COUNT(DISTINCT o.order_id) as frequency,\n        SUM(o.total_amount) as monetary_value\n    FROM customers c\n    JOIN orders o ON c.customer_id = o.customer_id\n    WHERE o.status = 'completed'\n    GROUP BY c.customer_id, c.email\n),\nrfm_scores AS (\n    SELECT \n        *,\n        NTILE(5) OVER (ORDER BY recency_days ASC) as recency_score,\n        NTILE(5) OVER (ORDER BY frequency DESC) as frequency_score,\n        NTILE(5) OVER (ORDER BY monetary_value DESC) as monetary_score\n    FROM rfm_data\n),\nrfm_segments AS (\n    SELECT \n        *,\n        CONCAT(recency_score, frequency_score, monetary_score) as rfm_score,\n        CASE \n            WHEN recency_score >= 4 AND frequency_score >= 4 AND monetary_score >= 4 THEN 'Champions'\n            WHEN recency_score >= 3 AND frequency_score >= 3 AND monetary_score >= 3 THEN 'Loyal Customers'\n            WHEN recency_score >= 3 AND frequency_score <= 2 AND monetary_score >= 3 THEN 'Potential Loyalists'\n            WHEN recency_score >= 4 AND frequency_score <= 2 AND monetary_score <= 2 THEN 'New Customers'\n            WHEN recency_score <= 2 AND frequency_score >= 3 AND monetary_score >= 3 THEN 'At Risk'\n            WHEN recency_score <= 2 AND frequency_score <= 2 AND monetary_value >= 3 THEN 'Cannot Lose Them'\n            WHEN recency_score <= 2 AND frequency_score <= 2 AND monetary_score <= 2 THEN 'Lost Customers'\n            ELSE 'Others'\n        END as customer_segment\n    FROM rfm_scores\n)\nSELECT \n    customer_segment,\n    COUNT(*) as customer_count,\n    ROUND(AVG(recency_days), 1) as avg_recency_days,\n    ROUND(AVG(frequency), 1) as avg_frequency,\n    ROUND(AVG(monetary_value), 2) as avg_monetary_value,\n    ROUND(SUM(monetary_value), 2) as total_revenue\nFROM rfm_segments\nGROUP BY customer_segment\nORDER BY total_revenue DESC;\n\n-- 7. Inventory Analysis with ABC Classification\nWITH product_abc AS (\n    SELECT \n        p.product_id,\n        p.product_name,\n        p.category,\n        SUM(oi.quantity * oi.unit_price) as total_revenue,\n        SUM(oi.quantity) as total_quantity,\n        COUNT(DISTINCT oi.order_id) as order_frequency\n    FROM products p\n    JOIN order_items oi ON p.product_id = oi.product_id\n    JOIN orders o ON oi.order_id = o.order_id\n    WHERE o.status = 'completed'\n    GROUP BY p.product_id, p.product_name, p.category\n),\nrevenue_percentiles AS (\n    SELECT \n        *,\n        SUM(total_revenue) OVER () as total_company_revenue,\n        SUM(total_revenue) OVER (ORDER BY total_revenue DESC ROWS UNBOUNDED PRECEDING) as cumulative_revenue,\n        (SUM(total_revenue) OVER (ORDER BY total_revenue DESC ROWS UNBOUNDED PRECEDING) / \n         SUM(total_revenue) OVER ()) * 100 as cumulative_revenue_percent\n    FROM product_abc\n)\nSELECT \n    product_name,\n    category,\n    total_revenue,\n    total_quantity,\n    order_frequency,\n    ROUND(cumulative_revenue_percent, 2) as cumulative_revenue_percent,\n    CASE \n        WHEN cumulative_revenue_percent <= 80 THEN 'A (High Value)'\n        WHEN cumulative_revenue_percent <= 95 THEN 'B (Medium Value)'\n        ELSE 'C (Low Value)'\n    END as abc_classification\nFROM revenue_percentiles\nORDER BY total_revenue DESC;\n\n-- 8. Create indexes for better performance\nCREATE INDEX IF NOT EXISTS idx_orders_customer_date ON orders(customer_id, order_date);\nCREATE INDEX IF NOT EXISTS idx_orders_status_date ON orders(status, order_date);\nCREATE INDEX IF NOT EXISTS idx_order_items_product ON order_items(product_id);\nCREATE INDEX IF NOT EXISTS idx_customers_registration ON customers(registration_date);\nCREATE INDEX IF NOT EXISTS idx_products_category ON products(category, subcategory);",
            'tags' => 'sql, analytics, business-intelligence, postgresql, data-analysis'
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function seedUsers($count = 30) {
        echo "Seeding $count users...\n";
        
        for ($i = 0; $i < $count; $i++) {
            $name = $this->faker_names[$i % count($this->faker_names)];
            $username = $this->usernames[$i % count($this->usernames)];
            $tagline = $this->taglines[$i % count($this->taglines)];
            
            // Make username unique by adding number if needed
            $originalUsername = $username;
            $counter = 1;
            while ($this->userExists($username)) {
                $username = $originalUsername . $counter;
                $counter++;
            }
            
            $userId = uniqid();
            $password = password_hash('password123', PASSWORD_DEFAULT);
            $email = strtolower(str_replace(' ', '.', $name)) . '@example.com';
            
            $stmt = $this->db->prepare("
                INSERT INTO users (id, username, password, email, tagline, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $username,
                $password,
                $email,
                $tagline,
                time() - rand(0, 365 * 24 * 60 * 60) // Random time in the past year
            ]);
            
            echo "Created user: " . $username . " (" . $name . ")\n";
        }
    }
    
    public function seedPastes($count = 100) {
        echo "Seeding $count pastes...\n";
        
        // Get all users
        $stmt = $this->db->prepare("SELECT id FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($users)) {
            echo "No users found. Please seed users first.\n";
            return;
        }
        
        for ($i = 0; $i < $count; $i++) {
            $template = $this->paste_templates[$i % count($this->paste_templates)];
            
            // Add variety to titles
            $titleSuffix = rand(1, 999);
            $title = $template['title'] . ' v' . $titleSuffix;
            
            // Add some variations to content
            $content = $template['content'];
            if (rand(0, 3) == 0) {
                $content .= "\n\n// Modified on " . date('Y-m-d H:i:s');
            }
            
            // Random user (some pastes will be anonymous)
            $userId = (rand(0, 4) == 0) ? null : $users[array_rand($users)];
            
            // Random visibility
            $isPublic = rand(0, 9) < 8; // 80% public
            
            // Random expiration (most don't expire)
            $expireTime = null;
            if (rand(0, 9) == 0) { // 10% chance of expiration
                $expireTime = time() + rand(7, 90) * 24 * 60 * 60; // 7-90 days
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO pastes (title, content, language, tags, is_public, user_id, expire_time, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $content,
                $template['language'],
                $template['tags'],
                $isPublic ? 1 : 0,
                $userId,
                $expireTime,
                time() - rand(0, 180 * 24 * 60 * 60) // Random time in the past 6 months
            ]);
            
            $pasteId = $this->db->lastInsertId();
            
            // Add random views
            $this->addRandomViews($pasteId);
            
            echo "Created paste: " . $title . " (ID: " . $pasteId . ", Language: " . $template['language'] . ")\n";
        }
    }
    
    public function seedComments($count = 200) {
        echo "Seeding $count comments...\n";
        
        // Get all users and pastes
        $stmt = $this->db->prepare("SELECT id FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $this->db->prepare("SELECT id FROM pastes WHERE is_public = 1");
        $stmt->execute();
        $pastes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($users) || empty($pastes)) {
            echo "Need users and pastes to create comments.\n";
            return;
        }
        
        $comments = [
            "Great code! Thanks for sharing.",
            "This helped me solve a similar problem.",
            "Nice implementation, very clean.",
            "Could you explain the logic behind this part?",
            "This is exactly what I was looking for!",
            "Excellent example of best practices.",
            "Have you considered using a different approach?",
            "This code is well documented and easy to follow.",
            "Thanks for the detailed explanation.",
            "Brilliant solution! I learned something new today.",
            "Very efficient implementation.",
            "This will save me hours of work!",
            "Clear and concise code. Well done!",
            "Interesting approach, I hadn't thought of that.",
            "Perfect timing, I needed this for my project.",
            "Love the way you structured this.",
            "This is a good starting point for beginners.",
            "Can this be optimized further?",
            "Great use of modern syntax features.",
            "This deserves more attention!"
        ];
        
        for ($i = 0; $i < $count; $i++) {
            $pasteId = $pastes[array_rand($pastes)];
            $userId = (rand(0, 4) == 0) ? null : $users[array_rand($users)]; // 20% anonymous
            $comment = $comments[array_rand($comments)];
            
            $stmt = $this->db->prepare("
                INSERT INTO comments (paste_id, user_id, content, created_at) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $pasteId,
                $userId,
                $comment,
                time() - rand(0, 90 * 24 * 60 * 60) // Random time in the past 3 months
            ]);
            
            echo "Added comment to paste " . $pasteId . "\n";
        }
    }
    
    private function userExists($username) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function addRandomViews($pasteId) {
        $viewCount = rand(0, 100);
        
        // Update paste views count
        $stmt = $this->db->prepare("UPDATE pastes SET views = ? WHERE id = ?");
        $stmt->execute([$viewCount, $pasteId]);
        
        // Add some unique view records (simplified - using random IPs)
        for ($i = 0; $i < min($viewCount, 20); $i++) {
            $fakeIp = rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
            
            try {
                $stmt = $this->db->prepare("
                    INSERT OR IGNORE INTO paste_views (paste_id, ip_address, created_at) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $pasteId, 
                    $fakeIp, 
                    time() - rand(0, 30 * 24 * 60 * 60)
                ]);
            } catch (Exception $e) {
                // Ignore duplicate IP entries
            }
        }
    }
    
    public function displayStats() {
        echo "\n=== Database Statistics ===\n";
        
        $stats = [
            'users' => "SELECT COUNT(*) FROM users",
            'pastes' => "SELECT COUNT(*) FROM pastes",
            'public_pastes' => "SELECT COUNT(*) FROM pastes WHERE is_public = 1",
            'comments' => "SELECT COUNT(*) FROM comments",
            'total_views' => "SELECT SUM(views) FROM pastes"
        ];
        
        foreach ($stats as $label => $query) {
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo ucfirst(str_replace('_', ' ', $label)) . ": " . number_format($count) . "\n";
        }
        
        // Language distribution
        echo "\n=== Language Distribution ===\n";
        $stmt = $this->db->prepare("
            SELECT language, COUNT(*) as count 
            FROM pastes 
            GROUP BY language 
            ORDER BY count DESC
        ");
        $stmt->execute();
        $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($languages as $lang) {
            echo $lang['language'] . ": " . $lang['count'] . " pastes\n";
        }
    }
    
    public function run() {
        echo "Starting database seeding process...\n\n";
        
        try {
            $this->seedUsers(30);
            echo "\n";
            
            $this->seedPastes(150);
            echo "\n";
            
            $this->seedComments(300);
            echo "\n";
            
            $this->displayStats();
            
            echo "\n✅ Database seeding completed successfully!\n";
            echo "You now have a fully populated database for testing and development.\n";
            
        } catch (Exception $e) {
            echo "❌ Error during seeding: " . $e->getMessage() . "\n";
        }
    }
}

// Run the seeder
if (php_sapi_name() === 'cli') {
    $seeder = new DatabaseSeeder();
    $seeder->run();
} else {
    echo "This script should be run from the command line.\n";
}
?>
