import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';

const CommentSection = ({ pasteId, comments: initialComments = [] }) => {
  const { user } = useUser();
  const [comments, setComments] = useState(initialComments);
  const [newComment, setNewComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!newComment.trim()) return;
    
    setIsSubmitting(true);
    setError('');
    
    try {
      // In a real app, this would be a fetch call to your backend
      // For now, we'll simulate adding a comment
      const newCommentObj = {
        id: Date.now(),
        paste_id: pasteId,
        user_id: user?.id,
        username: user?.username,
        content: newComment,
        created_at: Math.floor(Date.now() / 1000)
      };
      
      // Simulate API call delay
      await new Promise(resolve => setTimeout(resolve, 500));
      
      setComments([...comments, newCommentObj]);
      setNewComment('');
    } catch (err) {
      console.error('Error posting comment:', err);
      setError('Failed to post comment. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleString();
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
      <h2 className="text-xl font-bold mb-6 flex items-center">
        <i className="fas fa-comments mr-2 text-blue-500"></i>
        Comments ({comments.length})
      </h2>
      
      {error && (
        <div className="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      )}
      
      {user ? (
        <form onSubmit={handleSubmit} className="mb-8">
          <div className="mb-3">
            <label htmlFor="comment" className="block text-sm font-medium mb-2">
              Add a comment
            </label>
            <textarea
              id="comment"
              rows="3"
              value={newComment}
              onChange={(e) => setNewComment(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700"
              placeholder="Write your comment here..."
              required
            ></textarea>
          </div>
          <button
            type="submit"
            disabled={isSubmitting}
            className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg disabled:opacity-50"
          >
            {isSubmitting ? (
              <>
                <i className="fas fa-spinner fa-spin mr-2"></i>
                Posting...
              </>
            ) : (
              <>
                <i className="fas fa-paper-plane mr-2"></i>
                Post Comment
              </>
            )}
          </button>
        </form>
      ) : (
        <div className="mb-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
          <p className="text-blue-800 dark:text-blue-200 mb-2">
            <i className="fas fa-info-circle mr-2"></i>
            You need to be logged in to post comments
          </p>
          <Link to="/login" className="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
            Log in to comment
          </Link>
        </div>
      )}
      
      {comments.length === 0 ? (
        <div className="text-center py-8">
          <i className="fas fa-comments text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
          <p className="text-gray-500 dark:text-gray-400">No comments yet. Be the first to comment!</p>
        </div>
      ) : (
        <div className="space-y-6">
          {comments.map(comment => (
            <div key={comment.id} className="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
              <div className="flex items-start space-x-3">
                <img 
                  src={`https://www.gravatar.com/avatar/${comment.username}?d=mp&s=40`} 
                  alt={comment.username} 
                  className="w-10 h-10 rounded-full"
                />
                <div className="flex-1">
                  <div className="flex justify-between items-center mb-2">
                    <Link to={`/profile/${comment.username}`} className="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                      {comment.username}
                    </Link>
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                      {formatDate(comment.created_at)}
                    </span>
                  </div>
                  <p className="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
                    {comment.content}
                  </p>
                  <div className="mt-2 flex space-x-4 text-sm">
                    <button className="text-gray-500 hover:text-blue-500">
                      <i className="fas fa-reply mr-1"></i>
                      Reply
                    </button>
                    <button className="text-gray-500 hover:text-red-500">
                      <i className="fas fa-flag mr-1"></i>
                      Report
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default CommentSection;