import { useState, useEffect } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';

const ProjectsPage = () => {
  const { user } = useUser();
  const [projects, setProjects] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [newProject, setNewProject] = useState({
    name: '',
    description: '',
    is_public: true,
    license_type: 'MIT'
  });

  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/projects' } }} />;
  }

  useEffect(() => {
    // Simulate loading projects
    setIsLoading(true);
    
    // Mock projects data
    const mockProjects = [
      {
        id: 1,
        name: 'React Component Library',
        description: 'A collection of reusable React components with Tailwind CSS',
        readme_content: '# React Component Library\n\nA collection of reusable React components styled with Tailwind CSS.',
        license_type: 'MIT',
        is_public: true,
        default_branch: 'main',
        created_at: Math.floor(Date.now() / 1000) - 604800, // 1 week ago
        updated_at: Math.floor(Date.now() / 1000) - 86400, // 1 day ago
        file_count: 12,
        branch_count: 2,
        contributor_count: 1
      },
      {
        id: 2,
        name: 'Node.js API Boilerplate',
        description: 'Starter template for Node.js REST APIs with Express',
        readme_content: '# Node.js API Boilerplate\n\nA starter template for building RESTful APIs with Node.js and Express.',
        license_type: 'MIT',
        is_public: true,
        default_branch: 'main',
        created_at: Math.floor(Date.now() / 1000) - 1209600, // 2 weeks ago
        updated_at: Math.floor(Date.now() / 1000) - 172800, // 2 days ago
        file_count: 8,
        branch_count: 1,
        contributor_count: 1
      }
    ];
    
    setTimeout(() => {
      setProjects(mockProjects);
      setIsLoading(false);
    }, 500);
  }, []);

  const handleCreateSubmit = (e) => {
    e.preventDefault();
    
    // Validate form
    if (!newProject.name.trim()) {
      setError('Project name is required');
      return;
    }
    
    // Create new project (mock implementation)
    const newProjectObj = {
      id: Date.now(),
      ...newProject,
      default_branch: 'main',
      created_at: Math.floor(Date.now() / 1000),
      updated_at: Math.floor(Date.now() / 1000),
      file_count: 0,
      branch_count: 1,
      contributor_count: 1
    };
    
    setProjects([newProjectObj, ...projects]);
    setShowCreateForm(false);
    setNewProject({
      name: '',
      description: '',
      is_public: true,
      license_type: 'MIT'
    });
  };

  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setNewProject(prev => ({
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
            <i className="fas fa-folder-tree mr-3 text-blue-500"></i>
            My Projects
          </h1>
          <button 
            onClick={() => setShowCreateForm(!showCreateForm)} 
            className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors"
          >
            <i className={`fas ${showCreateForm ? 'fa-times' : 'fa-plus'} mr-2`}></i>
            {showCreateForm ? 'Cancel' : 'New Project'}
          </button>
        </div>
        
        {error && (
          <div className="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
            <i className="fas fa-exclamation-circle mr-2"></i>{error}
          </div>
        )}
        
        {/* Create Project Form */}
        {showCreateForm && (
          <div className="mb-8 p-6 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <h2 className="text-lg font-semibold mb-4">Create New Project</h2>
            <form onSubmit={handleCreateSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-2">
                  Project Name <span className="text-red-500">*</span>
                </label>
                <input 
                  type="text" 
                  name="name" 
                  value={newProject.name} 
                  onChange={handleInputChange} 
                  required
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                  placeholder="e.g., My Awesome Project"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium mb-2">
                  Description (optional)
                </label>
                <textarea 
                  name="description" 
                  value={newProject.description} 
                  onChange={handleInputChange} 
                  rows="3"
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                  placeholder="Describe your project..."
                ></textarea>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-2">
                    License
                  </label>
                  <select 
                    name="license_type" 
                    value={newProject.license_type} 
                    onChange={handleInputChange}
                    className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="MIT">MIT License</option>
                    <option value="Apache-2.0">Apache License 2.0</option>
                    <option value="GPL-3.0">GNU GPL v3</option>
                    <option value="BSD-3-Clause">BSD 3-Clause</option>
                    <option value="Unlicense">The Unlicense</option>
                  </select>
                </div>
                
                <div className="flex items-center">
                  <label className="flex items-center space-x-2">
                    <input 
                      type="checkbox" 
                      name="is_public" 
                      checked={newProject.is_public} 
                      onChange={handleInputChange} 
                      className="rounded"
                    />
                    <span>Make project public</span>
                  </label>
                </div>
              </div>
              
              <div className="flex space-x-3">
                <button 
                  type="submit" 
                  className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors"
                >
                  <i className="fas fa-folder-plus mr-2"></i>
                  Create Project
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
        
        {/* Projects List */}
        {isLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {[...Array(2)].map((_, i) => (
              <div key={i} className="animate-pulse">
                <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-4"></div>
                <div className="h-20 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
              </div>
            ))}
          </div>
        ) : projects.length === 0 ? (
          <div className="text-center py-12">
            <i className="fas fa-folder-open text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
            <h3 className="text-xl font-medium text-gray-700 dark:text-gray-300 mb-2">No projects yet</h3>
            <p className="text-gray-500 dark:text-gray-400 mb-6">
              Projects help you organize your code into structured repositories
            </p>
            <button 
              onClick={() => setShowCreateForm(true)} 
              className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors"
            >
              <i className="fas fa-folder-plus mr-2"></i>
              Create Your First Project
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {projects.map(project => (
              <div key={project.id} className="bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden hover:shadow-md transition-shadow">
                <div className="p-6">
                  <div className="flex items-start justify-between mb-2">
                    <h3 className="text-lg font-semibold">
                      <Link to={`/projects/${project.id}`} className="text-blue-600 dark:text-blue-400 hover:underline">
                        {project.name}
                      </Link>
                    </h3>
                    <span className={`px-2 py-1 text-xs rounded-full ${
                      project.is_public 
                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                        : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                    }`}>
                      {project.is_public ? 'Public' : 'Private'}
                    </span>
                  </div>
                  
                  <p className="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                    {project.description || 'No description'}
                  </p>
                  
                  <div className="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                    <div>
                      <i className="fas fa-file mr-1"></i>
                      {project.file_count} files
                    </div>
                    <div>
                      <i className="fas fa-code-branch mr-1"></i>
                      {project.branch_count} branches
                    </div>
                    <div>
                      <i className="fas fa-users mr-1"></i>
                      {project.contributor_count} contributors
                    </div>
                  </div>
                </div>
                
                <div className="bg-gray-100 dark:bg-gray-600 px-6 py-3 flex justify-between items-center">
                  <div className="text-sm text-gray-500 dark:text-gray-400">
                    <i className="fas fa-calendar mr-1"></i>
                    Updated {formatDate(project.updated_at)}
                  </div>
                  <div className="flex space-x-2">
                    <Link to={`/projects/${project.id}`} className="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                      View Project
                    </Link>
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

export default ProjectsPage;