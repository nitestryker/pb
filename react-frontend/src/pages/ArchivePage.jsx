import { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import PasteCard from '../components/PasteCard';

const ArchivePage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [pastes, setPastes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [pagination, setPagination] = useState({
    currentPage: 1,
    totalPages: 1,
    totalItems: 0
  });
  
  // Get filter values from URL params
  const currentPage = parseInt(searchParams.get('page') || '1');
  const language = searchParams.get('language') || '';
  const tag = searchParams.get('tag') || '';
  const search = searchParams.get('search') || '';
  const sortBy = searchParams.get('sort') || 'date';
  const order = searchParams.get('order') || 'desc';

  useEffect(() => {
    const fetchPastes = async () => {
      setIsLoading(true);
      setError('');
      
      try {
        // In a real app, this would be a fetch call to your backend
        // For now, we'll use mock data
        
        // Simulate API call delay
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Mock pastes data
        const mockPastes = [
          {
            id: 1,
            title: 'React Hooks Example',
            content: 'import { useState, useEffect } from "react";\n\nfunction Example() {\n  const [count, setCount] = useState(0);\n\n  useEffect(() => {\n    document.title = `You clicked ${count} times`;\n  });\n\n  return (\n    <div>\n      <p>You clicked {count} times</p>\n      <button onClick={() => setCount(count + 1)}>\n        Click me\n      </button>\n    </div>\n  );\n}',
            language: 'javascript',
            created_at: Math.floor(Date.now() / 1000) - 3600,
            views: 42,
            username: 'reactdev',
            tags: 'react, hooks, javascript'
          },
          {
            id: 2,
            title: 'Python List Comprehension',
            content: 'numbers = [1, 2, 3, 4, 5]\n\n# Using list comprehension\nsquares = [x**2 for x in numbers]\nprint(squares)  # Output: [1, 4, 9, 16, 25]\n\n# Equivalent to:\nsquares = []\nfor x in numbers:\n    squares.append(x**2)',
            language: 'python',
            created_at: Math.floor(Date.now() / 1000) - 7200,
            views: 28,
            username: 'pythonista',
            tags: 'python, list, comprehension'
          },
          {
            id: 3,
            title: 'CSS Flexbox Cheatsheet',
            content: '.container {\n  display: flex;\n  flex-direction: row | row-reverse | column | column-reverse;\n  flex-wrap: nowrap | wrap | wrap-reverse;\n  justify-content: flex-start | flex-end | center | space-between | space-around | space-evenly;\n  align-items: stretch | flex-start | flex-end | center | baseline;\n  align-content: flex-start | flex-end | center | space-between | space-around | stretch;\n}',
            language: 'css',
            created_at: Math.floor(Date.now() / 1000) - 14400,
            views: 65,
            username: 'cssmaster',
            tags: 'css, flexbox, layout'
          },
          {
            id: 4,
            title: 'PHP PDO Database Connection',
            content: '<?php\ntry {\n    $db = new PDO(\'sqlite:database.sqlite\');\n    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n    \n    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");\n    $stmt->execute([1]);\n    $user = $stmt->fetch(PDO::FETCH_ASSOC);\n    \n    print_r($user);\n} catch (PDOException $e) {\n    echo "Database error: " . $e->getMessage();\n}',
            language: 'php',
            created_at: Math.floor(Date.now() / 1000) - 28800,
            views: 37,
            username: 'phpdev',
            tags: 'php, database, pdo'
          },
          {
            id: 5,
            title: 'Java Stream API Example',
            content: 'import java.util.Arrays;\nimport java.util.List;\nimport java.util.stream.Collectors;\n\npublic class StreamExample {\n    public static void main(String[] args) {\n        List<Integer> numbers = Arrays.asList(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);\n        \n        List<Integer> evenSquares = numbers.stream()\n            .filter(n -> n % 2 == 0)\n            .map(n -> n * n)\n            .collect(Collectors.toList());\n        \n        System.out.println(evenSquares); // [4, 16, 36, 64, 100]\n    }\n}',
            language: 'java',
            created_at: Math.floor(Date.now() / 1000) - 43200,
            views: 51,
            username: 'javadev',
            tags: 'java, stream, functional'
          }
        ];
        
        // Filter pastes based on search params
        let filteredPastes = [...mockPastes];
        
        if (language) {
          filteredPastes = filteredPastes.filter(paste => 
            paste.language.toLowerCase() === language.toLowerCase()
          );
        }
        
        if (tag) {
          filteredPastes = filteredPastes.filter(paste => 
            paste.tags && paste.tags.toLowerCase().includes(tag.toLowerCase())
          );
        }
        
        if (search) {
          filteredPastes = filteredPastes.filter(paste => 
            paste.title.toLowerCase().includes(search.toLowerCase()) || 
            paste.content.toLowerCase().includes(search.toLowerCase())
          );
        }
        
        // Sort pastes
        filteredPastes.sort((a, b) => {
          if (sortBy === 'date') {
            return order === 'desc' ? b.created_at - a.created_at : a.created_at - b.created_at;
          } else if (sortBy === 'views') {
            return order === 'desc' ? b.views - a.views : a.views - b.views;
          }
          return 0;
        });
        
        // Pagination
        const itemsPerPage = 10;
        const totalItems = filteredPastes.length;
        const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
        const validPage = Math.min(Math.max(1, currentPage), totalPages);
        
        const startIndex = (validPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const paginatedPastes = filteredPastes.slice(startIndex, endIndex);
        
        setPastes(paginatedPastes);
        setPagination({
          currentPage: validPage,
          totalPages,
          totalItems
        });
      } catch (err) {
        console.error('Error fetching pastes:', err);
        setError('Failed to load pastes. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchPastes();
  }, [currentPage, language, tag, search, sortBy, order]);

  const handlePageChange = (newPage) => {
    searchParams.set('page', newPage.toString());
    setSearchParams(searchParams);
  };

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    if (value) {
      searchParams.set(name, value);
    } else {
      searchParams.delete(name);
    }
    searchParams.delete('page'); // Reset to page 1 when filters change
    setSearchParams(searchParams);
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const searchTerm = formData.get('search');
    
    if (searchTerm) {
      searchParams.set('search', searchTerm);
    } else {
      searchParams.delete('search');
    }
    searchParams.delete('page'); // Reset to page 1 when search changes
    setSearchParams(searchParams);
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-8">
        <h1 className="text-2xl font-bold mb-6 flex items-center">
          <i className="fas fa-archive mr-3 text-blue-500"></i>
          Paste Archive
        </h1>
        
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
          {/* Search Form */}
          <div className="md:col-span-2">
            <form onSubmit={handleSearchSubmit} className="flex">
              <input 
                type="text" 
                name="search" 
                placeholder="Search pastes..."
                defaultValue={search}
                className="flex-1 px-4 py-2 rounded-l-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button 
                type="submit" 
                className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-r-lg"
              >
                <i className="fas fa-search"></i>
              </button>
            </form>
          </div>
          
          {/* Filters */}
          <div className="flex space-x-2">
            <select 
              name="language" 
              value={language} 
              onChange={handleFilterChange}
              className="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
            >
              <option value="">All Languages</option>
              <option value="javascript">JavaScript</option>
              <option value="python">Python</option>
              <option value="php">PHP</option>
              <option value="java">Java</option>
              <option value="css">CSS</option>
              <option value="html">HTML</option>
              <option value="sql">SQL</option>
            </select>
            
            <select 
              name="sort" 
              value={sortBy} 
              onChange={handleFilterChange}
              className="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
            >
              <option value="date">Date</option>
              <option value="views">Views</option>
            </select>
            
            <select 
              name="order" 
              value={order} 
              onChange={handleFilterChange}
              className="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
            >
              <option value="desc">Newest</option>
              <option value="asc">Oldest</option>
            </select>
          </div>
        </div>
        
        {/* Active Filters */}
        {(language || tag || search) && (
          <div className="flex flex-wrap items-center gap-2 mb-6 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <span className="text-sm font-medium text-blue-800 dark:text-blue-200">Active Filters:</span>
            
            {language && (
              <span className="px-3 py-1 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full text-xs flex items-center">
                Language: {language}
                <button 
                  onClick={() => {
                    searchParams.delete('language');
                    setSearchParams(searchParams);
                  }}
                  className="ml-2 text-blue-500 hover:text-blue-700"
                >
                  <i className="fas fa-times"></i>
                </button>
              </span>
            )}
            
            {tag && (
              <span className="px-3 py-1 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full text-xs flex items-center">
                Tag: {tag}
                <button 
                  onClick={() => {
                    searchParams.delete('tag');
                    setSearchParams(searchParams);
                  }}
                  className="ml-2 text-blue-500 hover:text-blue-700"
                >
                  <i className="fas fa-times"></i>
                </button>
              </span>
            )}
            
            {search && (
              <span className="px-3 py-1 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full text-xs flex items-center">
                Search: {search}
                <button 
                  onClick={() => {
                    searchParams.delete('search');
                    setSearchParams(searchParams);
                  }}
                  className="ml-2 text-blue-500 hover:text-blue-700"
                >
                  <i className="fas fa-times"></i>
                </button>
              </span>
            )}
            
            <button 
              onClick={() => {
                setSearchParams({});
              }}
              className="ml-auto text-sm text-blue-600 dark:text-blue-400 hover:underline"
            >
              Clear All Filters
            </button>
          </div>
        )}
        
        {/* Results */}
        {isLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="animate-pulse">
                <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-4"></div>
                <div className="h-32 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 px-4 py-3 rounded">
            <i className="fas fa-exclamation-circle mr-2"></i>{error}
          </div>
        ) : pastes.length === 0 ? (
          <div className="text-center py-12">
            <i className="fas fa-search text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
            <h3 className="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No pastes found</h3>
            <p className="text-gray-500 dark:text-gray-400">
              {language || tag || search 
                ? 'Try adjusting your filters or search terms' 
                : 'There are no public pastes available yet'}
            </p>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              {pastes.map(paste => (
                <PasteCard key={paste.id} paste={paste} />
              ))}
            </div>
            
            {/* Pagination */}
            {pagination.totalPages > 1 && (
              <div className="flex justify-center mt-8">
                <nav className="flex items-center space-x-2">
                  <button 
                    onClick={() => handlePageChange(pagination.currentPage - 1)}
                    disabled={pagination.currentPage === 1}
                    className="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50"
                  >
                    <i className="fas fa-chevron-left"></i>
                  </button>
                  
                  {[...Array(pagination.totalPages)].map((_, i) => {
                    const pageNum = i + 1;
                    // Show first, last, current, and pages around current
                    if (
                      pageNum === 1 || 
                      pageNum === pagination.totalPages || 
                      (pageNum >= pagination.currentPage - 1 && pageNum <= pagination.currentPage + 1)
                    ) {
                      return (
                        <button 
                          key={pageNum}
                          onClick={() => handlePageChange(pageNum)}
                          className={`px-3 py-1 rounded ${
                            pageNum === pagination.currentPage 
                              ? 'bg-blue-500 text-white' 
                              : 'border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700'
                          }`}
                        >
                          {pageNum}
                        </button>
                      );
                    } else if (
                      (pageNum === 2 && pagination.currentPage > 3) || 
                      (pageNum === pagination.totalPages - 1 && pagination.currentPage < pagination.totalPages - 2)
                    ) {
                      return <span key={pageNum}>...</span>;
                    }
                    return null;
                  })}
                  
                  <button 
                    onClick={() => handlePageChange(pagination.currentPage + 1)}
                    disabled={pagination.currentPage === pagination.totalPages}
                    className="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50"
                  >
                    <i className="fas fa-chevron-right"></i>
                  </button>
                </nav>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
};

export default ArchivePage;