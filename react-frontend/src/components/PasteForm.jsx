import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';

const PasteForm = () => {
  const { user } = useUser();
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    title: '',
    content: '',
    language: 'plaintext',
    expiry: '604800', // 1 week default
    isPublic: true,
    password: '',
    tags: '',
    burnAfterRead: false,
    zeroKnowledge: false
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');

    try {
      // In a real app, this would be a fetch call to your backend
      // For now, we'll simulate a successful paste creation
      console.log('Submitting paste:', formData);
      
      // Simulate API call delay
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // Simulate successful paste creation with a random ID
      const pasteId = Math.floor(Math.random() * 1000);
      
      // Redirect to the paste view page
      navigate(`/view/${pasteId}`);
    } catch (err) {
      console.error('Error creating paste:', err);
      setError('Failed to create paste. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
      <h2 className="text-2xl font-bold mb-6">Create New Paste</h2>
      
      {error && (
        <div className="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      )}
      
      <form onSubmit={handleSubmit} className="space-y-6">
        <div>
          <label className="block text-sm font-medium mb-2">Title (optional)</label>
          <input 
            type="text" 
            name="title" 
            value={formData.title} 
            onChange={handleChange} 
            className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
            placeholder="Untitled Paste"
          />
        </div>

        <div>
          <label className="block text-sm font-medium mb-2">Content</label>
          <textarea 
            name="content" 
            value={formData.content} 
            onChange={handleChange} 
            required
            rows="12" 
            className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 font-mono"
            placeholder="Paste your code or text here..."
          ></textarea>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium mb-2">Syntax Highlighting</label>
            <select 
              name="language" 
              value={formData.language} 
              onChange={handleChange} 
              className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
            >
              <option value="plaintext">Plain Text</option>
              <option value="javascript">JavaScript</option>
              <option value="python">Python</option>
              <option value="php">PHP</option>
              <option value="java">Java</option>
              <option value="cpp">C++</option>
              <option value="c">C</option>
              <option value="csharp">C#</option>
              <option value="html">HTML</option>
              <option value="css">CSS</option>
              <option value="sql">SQL</option>
              <option value="json">JSON</option>
              <option value="xml">XML</option>
              <option value="bash">Bash</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium mb-2">Expiration</label>
            <select 
              name="expiry" 
              value={formData.expiry} 
              onChange={handleChange} 
              className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
            >
              <option value="0">Never</option>
              <option value="600">10 Minutes</option>
              <option value="3600">1 Hour</option>
              <option value="86400">1 Day</option>
              <option value="604800">1 Week</option>
              <option value="2592000">1 Month</option>
            </select>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium mb-2">Tags (comma separated)</label>
            <input 
              type="text" 
              name="tags" 
              value={formData.tags} 
              onChange={handleChange} 
              className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
              placeholder="javascript, react, code"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-2">Password (optional)</label>
            <input 
              type="password" 
              name="password" 
              value={formData.password} 
              onChange={handleChange} 
              className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
              placeholder="Leave blank for no password"
            />
          </div>
        </div>

        <div className="space-y-3">
          <div className="flex items-center">
            <input 
              type="checkbox" 
              id="isPublic" 
              name="isPublic" 
              checked={formData.isPublic} 
              onChange={handleChange} 
              className="mr-2"
            />
            <label htmlFor="isPublic">Public paste (visible in archive and search)</label>
          </div>

          <div className="flex items-center">
            <input 
              type="checkbox" 
              id="burnAfterRead" 
              name="burnAfterRead" 
              checked={formData.burnAfterRead} 
              onChange={handleChange} 
              className="mr-2"
            />
            <label htmlFor="burnAfterRead">Burn after read (delete after first view)</label>
          </div>

          <div className="flex items-center">
            <input 
              type="checkbox" 
              id="zeroKnowledge" 
              name="zeroKnowledge" 
              checked={formData.zeroKnowledge} 
              onChange={handleChange} 
              className="mr-2"
            />
            <label htmlFor="zeroKnowledge">Zero knowledge (encrypted in browser)</label>
          </div>
        </div>

        <div className="flex justify-between items-center pt-4">
          <button 
            type="submit" 
            className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium flex items-center"
            disabled={isLoading}
          >
            {isLoading ? (
              <>
                <i className="fas fa-spinner fa-spin mr-2"></i>
                Creating...
              </>
            ) : (
              <>
                <i className="fas fa-paper-plane mr-2"></i>
                Create Paste
              </>
            )}
          </button>

          {user && (
            <div className="text-sm text-gray-600 dark:text-gray-400">
              Posting as <span className="font-medium">{user.username}</span>
            </div>
          )}
        </div>
      </form>
    </div>
  );
};

export default PasteForm;