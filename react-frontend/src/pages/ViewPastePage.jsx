import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import PasteView from '../components/PasteView';
import CommentSection from '../components/CommentSection';
import RelatedPastes from '../components/RelatedPastes';

const ViewPastePage = () => {
  const { id } = useParams();
  const [paste, setPaste] = useState(null);
  const [comments, setComments] = useState([]);
  const [relatedPastes, setRelatedPastes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchPaste = async () => {
      setIsLoading(true);
      setError('');
      
      try {
        // In a real app, this would be a fetch call to your backend
        // For now, we'll use mock data
        
        // Simulate API call delay
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Mock paste data
        const mockPaste = {
          id: parseInt(id),
          title: 'Example React Component',
          content: `import React, { useState, useEffect } from 'react';

function ExampleComponent({ initialCount = 0 }) {
  // State declaration
  const [count, setCount] = useState(initialCount);
  const [isActive, setIsActive] = useState(false);
  
  // Side effect with cleanup
  useEffect(() => {
    document.title = \`Count: \${count}\`;
    
    // Optional cleanup function
    return () => {
      document.title = 'React App';
    };
  }, [count]);
  
  // Event handlers
  const increment = () => setCount(prevCount => prevCount + 1);
  const decrement = () => setCount(prevCount => prevCount - 1);
  const reset = () => setCount(initialCount);
  const toggleActive = () => setIsActive(!isActive);
  
  return (
    <div className={isActive ? 'active' : ''}>
      <h2>Counter: {count}</h2>
      <button onClick={increment}>Increment</button>
      <button onClick={decrement}>Decrement</button>
      <button onClick={reset}>Reset</button>
      <button onClick={toggleActive}>
        {isActive ? 'Deactivate' : 'Activate'}
      </button>
    </div>
  );
}

export default ExampleComponent;`,
          language: 'javascript',
          created_at: Math.floor(Date.now() / 1000) - 86400,
          views: 128,
          username: 'reactdev',
          user_id: 'user123',
          is_public: true,
          tags: 'react, javascript, hooks, component'
        };
        
        // Mock comments
        const mockComments = [
          {
            id: 1,
            paste_id: parseInt(id),
            user_id: 'user456',
            username: 'codereviewer',
            content: 'Great example of React hooks! Very clean implementation.',
            created_at: Math.floor(Date.now() / 1000) - 43200
          },
          {
            id: 2,
            paste_id: parseInt(id),
            user_id: 'user789',
            username: 'reactnewbie',
            content: 'Thanks for sharing this! I was confused about useEffect cleanup, but this makes it clear.',
            created_at: Math.floor(Date.now() / 1000) - 21600
          }
        ];
        
        // Mock related pastes
        const mockRelatedPastes = [
          {
            id: 101,
            title: 'React useContext Example',
            content: '// Example of React Context API usage...',
            language: 'javascript',
            created_at: Math.floor(Date.now() / 1000) - 172800,
            views: 95,
            username: 'reactdev'
          },
          {
            id: 102,
            title: 'React Custom Hooks',
            content: '// Creating reusable custom hooks...',
            language: 'javascript',
            created_at: Math.floor(Date.now() / 1000) - 259200,
            views: 112,
            username: 'hookmaster'
          },
          {
            id: 103,
            title: 'React Performance Optimization',
            content: '// Tips for optimizing React components...',
            language: 'javascript',
            created_at: Math.floor(Date.now() / 1000) - 345600,
            views: 203,
            username: 'perfexpert'
          }
        ];
        
        setPaste(mockPaste);
        setComments(mockComments);
        setRelatedPastes(mockRelatedPastes);
      } catch (err) {
        console.error('Error fetching paste:', err);
        setError('Failed to load paste. It may have been removed or is private.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchPaste();
  }, [id]);

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {error ? (
        <div className="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      ) : (
        <div className="space-y-8">
          <PasteView paste={paste} />
          
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div className="lg:col-span-2">
              <CommentSection pasteId={id} comments={comments} />
            </div>
            
            <div className="space-y-6">
              <RelatedPastes relatedPastes={relatedPastes} />
              
              {/* Additional sidebar components could go here */}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ViewPastePage;