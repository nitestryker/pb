import { useState, useEffect } from 'react';
import PasteForm from '../components/PasteForm';
import PasteCard from '../components/PasteCard';

const HomePage = () => {
  const [recentPastes, setRecentPastes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchRecentPastes = async () => {
      try {
        // In a real app, this would be a fetch call to your backend
        // For now, we'll use mock data
        const mockPastes = [
          {
            id: 1,
            title: 'React Hooks Example',
            content: 'import { useState, useEffect } from "react";\n\nfunction Example() {\n  const [count, setCount] = useState(0);\n\n  useEffect(() => {\n    document.title = `You clicked ${count} times`;\n  });\n\n  return (\n    <div>\n      <p>You clicked {count} times</p>\n      <button onClick={() => setCount(count + 1)}>\n        Click me\n      </button>\n    </div>\n  );\n}',
            language: 'javascript',
            created_at: Math.floor(Date.now() / 1000) - 3600,
            views: 42,
            username: 'reactdev'
          },
          {
            id: 2,
            title: 'Python List Comprehension',
            content: 'numbers = [1, 2, 3, 4, 5]\n\n# Using list comprehension\nsquares = [x**2 for x in numbers]\nprint(squares)  # Output: [1, 4, 9, 16, 25]\n\n# Equivalent to:\nsquares = []\nfor x in numbers:\n    squares.append(x**2)',
            language: 'python',
            created_at: Math.floor(Date.now() / 1000) - 7200,
            views: 28,
            username: 'pythonista'
          },
          {
            id: 3,
            title: 'CSS Flexbox Cheatsheet',
            content: '.container {\n  display: flex;\n  flex-direction: row | row-reverse | column | column-reverse;\n  flex-wrap: nowrap | wrap | wrap-reverse;\n  justify-content: flex-start | flex-end | center | space-between | space-around | space-evenly;\n  align-items: stretch | flex-start | flex-end | center | baseline;\n  align-content: flex-start | flex-end | center | space-between | space-around | stretch;\n}',
            language: 'css',
            created_at: Math.floor(Date.now() / 1000) - 14400,
            views: 65,
            username: 'cssmaster'
          }
        ];
        
        // Simulate API call delay
        await new Promise(resolve => setTimeout(resolve, 500));
        
        setRecentPastes(mockPastes);
      } catch (error) {
        console.error('Error fetching recent pastes:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchRecentPastes();
  }, []);

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2">
          <PasteForm />
        </div>
        
        <div className="space-y-6">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 className="text-xl font-bold mb-4 flex items-center">
              <i className="fas fa-clock mr-2 text-blue-500"></i>
              Recent Public Pastes
            </h2>
            
            {isLoading ? (
              <div className="space-y-4">
                {[...Array(3)].map((_, i) => (
                  <div key={i} className="animate-pulse">
                    <div className="h-6 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-4"></div>
                    <div className="h-20 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                  </div>
                ))}
              </div>
            ) : recentPastes.length === 0 ? (
              <div className="text-center py-6">
                <i className="fas fa-paste text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
                <p className="text-gray-500 dark:text-gray-400">No public pastes yet</p>
              </div>
            ) : (
              <div className="space-y-4">
                {recentPastes.map(paste => (
                  <div key={paste.id} className="border-b border-gray-200 dark:border-gray-700 pb-4 last:border-0 last:pb-0">
                    <h3 className="font-medium mb-1">
                      <a href={`/view/${paste.id}`} className="text-blue-600 dark:text-blue-400 hover:underline">
                        {paste.title || 'Untitled Paste'}
                      </a>
                    </h3>
                    <div className="flex items-center text-xs text-gray-500 dark:text-gray-400 mb-2 space-x-3">
                      <span>
                        <i className="fas fa-clock mr-1"></i>
                        {new Date(paste.created_at * 1000).toLocaleString()}
                      </span>
                      <span>
                        <i className="fas fa-eye mr-1"></i>
                        {paste.views} views
                      </span>
                      {paste.language && (
                        <span className="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs">
                          {paste.language}
                        </span>
                      )}
                    </div>
                    <div className="bg-gray-50 dark:bg-gray-700 p-2 rounded text-xs font-mono overflow-hidden max-h-20">
                      <pre className="whitespace-pre-wrap break-all">
                        {paste.content.length > 100 
                          ? paste.content.substring(0, 100) + '...' 
                          : paste.content}
                      </pre>
                    </div>
                  </div>
                ))}
                
                <div className="text-center pt-2">
                  <a href="/archive" className="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                    View all public pastes <i className="fas fa-arrow-right ml-1"></i>
                  </a>
                </div>
              </div>
            )}
          </div>
          
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 className="text-xl font-bold mb-4 flex items-center">
              <i className="fas fa-info-circle mr-2 text-green-500"></i>
              About PasteForge
            </h2>
            <p className="text-gray-700 dark:text-gray-300 mb-4">
              PasteForge is a secure platform for sharing code snippets, text, and more. Create public or private pastes with syntax highlighting for over 200 languages.
            </p>
            <div className="grid grid-cols-2 gap-4 text-center">
              <div className="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                <i className="fas fa-shield-alt text-xl text-blue-500 mb-2"></i>
                <div className="text-sm font-medium">Secure Sharing</div>
              </div>
              <div className="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                <i className="fas fa-code text-xl text-purple-500 mb-2"></i>
                <div className="text-sm font-medium">Syntax Highlighting</div>
              </div>
              <div className="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                <i className="fas fa-lock text-xl text-green-500 mb-2"></i>
                <div className="text-sm font-medium">Private Pastes</div>
              </div>
              <div className="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                <i className="fas fa-history text-xl text-orange-500 mb-2"></i>
                <div className="text-sm font-medium">Expiration Control</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default HomePage;