import { useState, useEffect } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';
import { getUserCollections, createCollection } from '../utils/api';

const CollectionsPage = () => {
  const { user } = useUser();
  const [collections, setCollections] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [newCollection, setNewCollection] = useState({
    name: '',
    description: '',
    is_public: true
  });
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/collections' } }} />;
  }

  useEffect(() => {
    const fetchCollections = async () => {
      setIsLoading(true);
      setError('');
      
      try {
        const response = await getUserCollections();
        
        if (response.success) {
          setCollections(response.collections || []);
        } else {
          throw new Error(response.message || 'Failed to load collections');
        }
      } catch (err) {
        console.error('Error fetching collections:', err);
        setError('Failed to load collections. Please try again later.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchCollections();
  }, []);

  const handleCreateSubmit = async (e) => {
    e.preventDefault();
    
    try {
      setIsSubmitting(true);
      
      // Validate form data
      if (!newCollection.name.trim()) {
        throw new Error('Collection name is required');
      }
      
      // Send the collection data to the backend
      const response = await createCollection(newCollection);
      
      if (response.success) {
        // Add the new collection to the list
        setCollections([response.collection, ...collections]);
        
        // Reset form
        setNewCollection({
          name: '',
          description: '',
          is_public: true
        });
        
        // Hide form
        setShowCreateForm(false);
      } else {
        throw new Error(response.message || 'Failed to create collection');
      }
    } catch (err) {
      console.error('Error creating collection:', err);
      setError(err.message || 'Failed to create collection. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setNewCollection(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString();
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold flex items-center">
            <i className="fas fa-folder-open mr-3 text-blue-500"></i>
            My Collections
          </h1>
          <button 
            onClick={() => setShowCreateForm(!showCreateForm)} 
            className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors"
          >
            <i className={`fas ${showCreateForm ? 'fa-times' : 'fa-plus'} mr-2`}></i>
            {showCreateForm ? 'Cancel' : 'New Collection'}
          </button>
        </div>
        
        {error && (
          <div className="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
            <i className="fas fa-exclamation-circle mr-2"></i>{error}
          </div>
        )}
        
        {/* Create Collection Form */}
        {showCreateForm && (
          <div className="mb-8 p-6 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <h2 className="text-lg font-semibold mb-4">Create New Collection</h2>
            <form onSubmit={handleCreateSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-2">
                  Collection Name <span className="text-red-500">*</span>
                </label>
                <input 
                  type="text" 
                  name="name" 
                  value={newCollection.name} 
                  onChange={handleInputChange} 
                  required
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                  placeholder="e.g., JavaScript Snippets"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium mb-2">
                  Description (optional)
                </label>
                <textarea 
                  name="description" 
                  value={newCollection.description} 
                  onChange={handleInputChange} 
                  rows="3"
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                  placeholder="Describe what this collection is about..."
                ></textarea>
              </div>
              
              <div>
                <label className="flex items-center space-x-2">
                  <input 
                    type="checkbox" 
                    name="is_public" 
                    checked={newCollection.is_public} 
                    onChange={handleInputChange} 
                    className="rounded"
                  />
                  <span>Make collection public</span>
                </label>
                <p className="text-sm text-gray-500 mt-1 ml-6">
                  Public collections are visible to everyone. Private collections are only visible to you.
                </p>
              </div>
              
              <div className="flex space-x-3">
                <button 
                  type="submit" 
                  className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors"
                  disabled={isSubmitting}
                >
                  {isSubmitting ? (
                    <>
                      <i className="fas fa-spinner fa-spin mr-2"></i>
                      Creating...
                    </>
                  ) : (
                    <>
                      <i className="fas fa-folder-plus mr-2"></i>
                      Create Collection
                    </>
                  )}
                </button>
                <button 
                  type="button" 
                  onClick={() => setShowCreateForm(false)} 
                  className="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        )}
        
        {/* Collections List */}
        {isLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="animate-pulse">
                <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-4"></div>
                <div className="h-20 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
              </div>
            ))}
          </div>
        ) : collections.length === 0 ? (
          <div className="text-center py-12">
            <i className="fas fa-folder-open text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
            <h3 className="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No collections yet</h3>
            <p className="text-gray-500 dark:text-gray-400 mb-6">
              Collections help you organize your pastes into groups
            </p>
            <button 
              onClick={() => setShowCreateForm(true)} 
              className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors"
            >
              <i className="fas fa-folder-plus mr-2"></i>
              Create Your First Collection
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {collections.map(collection => (
              <div key={collection.id} className="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden hover:shadow-md transition-shadow">
                <div className="p-6">
                  <div className="flex items-start justify-between mb-2">
                    <h3 className="text-lg font-semibold">
                      <Link to={`/collections/${collection.id}`} className="text-blue-600 dark:text-blue-400 hover:underline">
                        {collection.name}
                      </Link>
                    </h3>
                    <span className={`px-2 py-1 text-xs rounded-full ${
                      collection.is_public 
                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                        : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                    }`}>
                      {collection.is_public ? 'Public' : 'Private'}
                    </span>
                  </div>
                  
                  <p className="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                    {collection.description || 'No description'}
                  </p>
                  
                  <div className="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span>
                      <i className="fas fa-paste mr-1"></i>
                      {collection.paste_count} pastes
                    </span>
                    <span>
                      <i className="fas fa-calendar mr-1"></i>
                      {formatDate(collection.created_at)}
                    </span>
                  </div>
                </div>
                
                <div className="bg-gray-100 dark:bg-gray-600 px-6 py-3 flex justify-between items-center">
                  <Link to={`/collections/${collection.id}`} className="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                    View Collection
                  </Link>
                  <div className="flex space-x-2">
                    <button className="text-gray-500 hover:text-blue-500 dark:text-gray-400 dark:hover:text-blue-400">
                      <i className="fas fa-edit"></i>
                    </button>
                    <button className="text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400">
                      <i className="fas fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default CollectionsPage;