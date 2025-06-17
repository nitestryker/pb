import { Link } from 'react-router-dom';

const PasteCard = ({ paste }) => {
  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString();
  };

  const truncateContent = (content, maxLength = 150) => {
    if (content.length <= maxLength) return content;
    return content.substring(0, maxLength) + '...';
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow p-4 border border-gray-200 dark:border-gray-700">
      <Link to={`/view/${paste.id}`} className="block">
        <h3 className="text-lg font-semibold mb-2 text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">
          {paste.title || 'Untitled Paste'}
        </h3>
      </Link>
      
      <div className="flex items-center text-sm text-gray-600 dark:text-gray-400 mb-3 space-x-3">
        <span>
          <i className="fas fa-clock mr-1"></i>
          {formatDate(paste.created_at)}
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
      
      <div className="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg mb-3 font-mono text-sm overflow-hidden">
        <pre className="whitespace-pre-wrap break-all">
          {truncateContent(paste.content)}
        </pre>
      </div>
      
      <div className="flex justify-between items-center">
        <div className="text-sm">
          {paste.username ? (
            <span>
              <i className="fas fa-user mr-1"></i>
              <Link to={`/profile/${paste.username}`} className="text-blue-600 dark:text-blue-400 hover:underline">
                {paste.username}
              </Link>
            </span>
          ) : (
            <span className="text-gray-500 dark:text-gray-400">
              <i className="fas fa-user-secret mr-1"></i>
              Anonymous
            </span>
          )}
        </div>
        
        <Link to={`/view/${paste.id}`} className="text-blue-600 dark:text-blue-400 hover:underline text-sm">
          View <i className="fas fa-arrow-right ml-1"></i>
        </Link>
      </div>
    </div>
  );
};

export default PasteCard;