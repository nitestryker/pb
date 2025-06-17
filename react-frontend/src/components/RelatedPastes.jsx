import { Link } from 'react-router-dom';

const RelatedPastes = ({ relatedPastes = [] }) => {
  if (relatedPastes.length === 0) {
    return null;
  }

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString();
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
      <h3 className="text-xl font-semibold mb-4 flex items-center">
        <i className="fas fa-lightbulb text-yellow-500 mr-2"></i>
        Related Pastes
      </h3>

      <div className="space-y-3">
        {relatedPastes.map(paste => (
          <div key={paste.id} className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <h4 className="font-medium mb-1">
                  <Link to={`/view/${paste.id}`} className="text-blue-500 hover:text-blue-700">
                    {paste.title || 'Untitled'}
                  </Link>
                </h4>
                <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                  <span className="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs">
                    {paste.language}
                  </span>
                  {paste.username ? (
                    <span>by @{paste.username}</span>
                  ) : (
                    <span>by Anonymous</span>
                  )}
                  <span>•</span>
                  <span>{formatDate(paste.created_at)}</span>
                  <span>•</span>
                  <span>{paste.views} views</span>
                </div>
              </div>
              <div className="ml-4">
                <Link 
                  to={`/view/${paste.id}`} 
                  className="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600"
                >
                  View
                </Link>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default RelatedPastes;