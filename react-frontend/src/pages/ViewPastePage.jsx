import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import PasteView from '../components/PasteView';
import CommentSection from '../components/CommentSection';
import RelatedPastes from '../components/RelatedPastes';
import { getPaste, getComments, getRelatedPastes } from '../utils/api';

const ViewPastePage = () => {
  const { id } = useParams();
  const [paste, setPaste] = useState(null);
  const [comments, setComments] = useState([]);
  const [relatedPastes, setRelatedPastes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchPasteData = async () => {
      setIsLoading(true);
      setError('');
      
      try {
        // Fetch the paste data
        const pasteResponse = await getPaste(id);
        
        if (!pasteResponse.success) {
          throw new Error(pasteResponse.message || 'Failed to load paste');
        }
        
        setPaste(pasteResponse.paste);
        
        // Fetch comments if the paste exists
        if (pasteResponse.paste) {
          try {
            const commentsResponse = await getComments(id);
            setComments(commentsResponse.comments || []);
          } catch (commentsError) {
            console.error('Error fetching comments:', commentsError);
            // Don't fail the whole page load if comments fail
          }
          
          // Fetch related pastes
          try {
            const relatedResponse = await getRelatedPastes(id);
            setRelatedPastes(relatedResponse.related_pastes || []);
          } catch (relatedError) {
            console.error('Error fetching related pastes:', relatedError);
            // Don't fail the whole page load if related pastes fail
          }
        }
      } catch (err) {
        console.error('Error fetching paste:', err);
        setError(err.message || 'Failed to load paste. It may have been removed or is private.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchPasteData();
  }, [id]);

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {error ? (
        <div className="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      ) : isLoading ? (
        <div className="space-y-8 animate-pulse">
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-4"></div>
            <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-6"></div>
            <div className="h-64 bg-gray-200 dark:bg-gray-700 rounded"></div>
          </div>
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