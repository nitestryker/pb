import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Prism as SyntaxHighlighter } from 'react-syntax-highlighter';
import { tomorrow } from 'react-syntax-highlighter/dist/esm/styles/prism';
import { useTheme } from '../contexts/ThemeContext';

const PasteView = ({ paste }) => {
  const { theme } = useTheme();
  const [copied, setCopied] = useState(false);
  
  // If paste is not provided, show loading state
  if (!paste) {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 animate-pulse">
        <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-4"></div>
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-6"></div>
        <div className="h-64 bg-gray-200 dark:bg-gray-700 rounded mb-4"></div>
      </div>
    );
  }

  const copyToClipboard = () => {
    navigator.clipboard.writeText(paste.content);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleString();
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
      <div className="p-6 border-b border-gray-200 dark:border-gray-700">
        <div className="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
              {paste.title || 'Untitled Paste'}
            </h1>
            <div className="flex flex-wrap items-center gap-3 mt-2 text-sm text-gray-600 dark:text-gray-400">
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
              {paste.username && (
                <span>
                  <i className="fas fa-user mr-1"></i>
                  <Link to={`/profile/${paste.username}`} className="hover:text-blue-500">
                    {paste.username}
                  </Link>
                </span>
              )}
            </div>
          </div>
          
          <div className="flex flex-wrap gap-2">
            <button 
              onClick={copyToClipboard}
              className="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-sm flex items-center"
            >
              <i className={`fas ${copied ? 'fa-check' : 'fa-copy'} mr-2`}></i>
              {copied ? 'Copied!' : 'Copy'}
            </button>
            <a 
              href={`/?id=${paste.id}&raw=1`}
              target="_blank"
              rel="noopener noreferrer"
              className="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-sm flex items-center"
            >
              <i className="fas fa-code mr-2"></i>
              Raw
            </a>
            <a 
              href={`/?id=${paste.id}&download=1`}
              className="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-sm flex items-center"
            >
              <i className="fas fa-download mr-2"></i>
              Download
            </a>
            <button 
              className="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-sm flex items-center"
            >
              <i className="fas fa-share-alt mr-2"></i>
              Share
            </button>
          </div>
        </div>
      </div>
      
      <div className="p-6">
        <div className="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
          <div className="bg-gray-50 dark:bg-gray-750 px-4 py-2 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <span className="font-mono text-sm text-gray-600 dark:text-gray-400">
              {paste.language || 'plaintext'}
            </span>
            <span className="text-sm text-gray-500">
              {paste.content.split('\n').length} lines
            </span>
          </div>
          <div className="overflow-x-auto">
            <SyntaxHighlighter 
              language={paste.language || 'text'} 
              style={tomorrow}
              showLineNumbers={true}
              customStyle={{
                margin: 0,
                padding: '1rem',
                background: theme === 'dark' ? '#1e1e1e' : '#ffffff',
              }}
            >
              {paste.content}
            </SyntaxHighlighter>
          </div>
        </div>
        
        {paste.tags && (
          <div className="mt-6">
            <h3 className="text-sm font-medium mb-2">Tags:</h3>
            <div className="flex flex-wrap gap-2">
              {paste.tags.split(',').map((tag, index) => (
                <Link 
                  key={index}
                  to={`/archive?tag=${tag.trim()}`}
                  className="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs hover:bg-blue-200 dark:hover:bg-blue-800"
                >
                  {tag.trim()}
                </Link>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default PasteView;