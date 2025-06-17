
<?php
// Setup script for paste templates system
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create templates table for built-in and user templates
    $db->exec("CREATE TABLE IF NOT EXISTS paste_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        content TEXT NOT NULL,
        language TEXT DEFAULT 'plaintext',
        category TEXT DEFAULT 'general',
        is_public BOOLEAN DEFAULT 1,
        created_by TEXT,
        created_at INTEGER DEFAULT (strftime('%s', 'now')),
        usage_count INTEGER DEFAULT 0,
        FOREIGN KEY(created_by) REFERENCES users(id)
    )");
    
    // Insert built-in templates
    $templates = [
        [
            'name' => 'HTML5 Boilerplate',
            'description' => 'Basic HTML5 document structure',
            'content' => "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>Document</title>\n</head>\n<body>\n    \n</body>\n</html>",
            'language' => 'html',
            'category' => 'web'
        ],
        [
            'name' => 'React Component',
            'description' => 'Basic React functional component',
            'content' => "import React from 'react';\n\nconst ComponentName = () => {\n    return (\n        <div>\n            <h1>Hello World</h1>\n        </div>\n    );\n};\n\nexport default ComponentName;",
            'language' => 'javascript',
            'category' => 'react'
        ],
        [
            'name' => 'Python Function',
            'description' => 'Basic Python function template',
            'content' => "def function_name(parameter):\n    \"\"\"\n    Function description\n    \n    Args:\n        parameter: Description of parameter\n    \n    Returns:\n        Description of return value\n    \"\"\"\n    # Your code here\n    return result",
            'language' => 'python',
            'category' => 'python'
        ],
        [
            'name' => 'Express.js Route',
            'description' => 'Basic Express.js route handler',
            'content' => "const express = require('express');\nconst router = express.Router();\n\n// GET route\nrouter.get('/', (req, res) => {\n    res.json({ message: 'Hello World' });\n});\n\n// POST route\nrouter.post('/', (req, res) => {\n    const { data } = req.body;\n    res.json({ received: data });\n});\n\nmodule.exports = router;",
            'language' => 'javascript',
            'category' => 'nodejs'
        ],
        [
            'name' => 'CSS Grid Layout',
            'description' => 'Basic CSS Grid layout template',
            'content' => ".container {\n    display: grid;\n    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));\n    gap: 1rem;\n    padding: 1rem;\n}\n\n.item {\n    background: #f0f0f0;\n    padding: 1rem;\n    border-radius: 8px;\n}",
            'language' => 'css',
            'category' => 'web'
        ],
        [
            'name' => 'PHP Class',
            'description' => 'Basic PHP class template',
            'content' => "<?php\n\nclass ClassName\n{\n    private \$property;\n    \n    public function __construct(\$property = null)\n    {\n        \$this->property = \$property;\n    }\n    \n    public function getProperty()\n    {\n        return \$this->property;\n    }\n    \n    public function setProperty(\$property)\n    {\n        \$this->property = \$property;\n    }\n}",
            'language' => 'php',
            'category' => 'php'
        ],
        [
            'name' => 'SQL Table Creation',
            'description' => 'Basic SQL table creation template',
            'content' => "CREATE TABLE table_name (\n    id INTEGER PRIMARY KEY AUTOINCREMENT,\n    name VARCHAR(255) NOT NULL,\n    email VARCHAR(255) UNIQUE,\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n);",
            'language' => 'sql',
            'category' => 'database'
        ],
        [
            'name' => 'Docker Dockerfile',
            'description' => 'Basic Dockerfile template',
            'content' => "FROM node:16-alpine\n\nWORKDIR /app\n\nCOPY package*.json ./\nRUN npm install\n\nCOPY . .\n\nEXPOSE 3000\n\nCMD [\"npm\", \"start\"]",
            'language' => 'docker',
            'category' => 'devops'
        ]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO paste_templates (name, description, content, language, category) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($templates as $template) {
        $stmt->execute([
            $template['name'],
            $template['description'],
            $template['content'],
            $template['language'],
            $template['category']
        ]);
    }
    
    echo "Templates database setup complete!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
