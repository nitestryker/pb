import { Link } from 'react-router-dom';
import { useTheme } from '../contexts/ThemeContext';

const NotFoundPage = () => {
  const { theme } = useTheme();

  return (
    <div className="min-h-screen flex items-center justify-center p-4">
      <div className="max-w-lg mx-auto text-center">
        <div className="mb-8">
          <div className="relative mb-6">
            <i className="fas fa-search text-8xl text-gray-300 dark:text-gray-600"></i>
            <i className="fas fa-times-circle text-3xl text-red-500 absolute -top-2 -right-2"></i>
          </div>
          <h1 className="text-6xl font-bold mb-2 text-gray-800 dark:text-white">404</h1>
          <h2 className="text-2xl font-semibold mb-4 text-gray-700 dark:text-gray-300">Page Not Found</h2>
          <p className="text-gray-600 dark:text-gray-400 mb-8 leading-relaxed">
            The paste or page you're looking for might have been moved, deleted, or expired. 
            Don't worry, let's get you back on track!
          </p>
        </div>
        
        <div className="space-y-4">
          <Link to="/" className="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-8 rounded-lg transition-all transform hover:scale-105">
            <i className="fas fa-home mr-2"></i>Return Home
          </Link>
          
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link to="/archive" className="text-blue-500 hover:text-blue-700 font-medium">
              <i className="fas fa-archive mr-2"></i>Browse Archive
            </Link>
            <button onClick={() => window.history.back()} className="text-blue-500 hover:text-blue-700 font-medium">
              <i className="fas fa-arrow-left mr-2"></i>Go Back
            </button>
          </div>
        </div>
        
        <div className="mt-12 p-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg">
          <h3 className="text-lg font-semibold mb-4">Quick Actions</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Link to="/" className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              <i className="fas fa-plus text-green-500 text-xl mb-2"></i>
              <div className="font-medium">Create New Paste</div>
              <div className="text-sm text-gray-500">Start fresh with a new paste</div>
            </Link>
            <Link to="/archive" className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
              <i className="fas fa-search text-blue-500 text-xl mb-2"></i>
              <div className="font-medium">Search Pastes</div>
              <div className="text-sm text-gray-500">Find what you're looking for</div>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default NotFoundPage;