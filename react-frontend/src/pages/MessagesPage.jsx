import { useState, useEffect } from 'react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';

const MessagesPage = () => {
  const { user } = useUser();
  const [searchParams] = useSearchParams();
  const [view, setView] = useState('inbox');
  const [threads, setThreads] = useState([]);
  const [currentThread, setCurrentThread] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [replyContent, setReplyContent] = useState('');
  const [showComposeForm, setShowComposeForm] = useState(false);
  const [composeData, setComposeData] = useState({
    recipients: '',
    subject: '',
    content: ''
  });
  const [recipientSuggestions, setRecipientSuggestions] = useState([]);

  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/messages' } }} />;
  }

  // Mock data for threads
  const mockThreads = [
    {
      thread_id: 1,
      subject: 'Question about your JavaScript paste',
      sender_username: 'pythondev',
      recipients: 'johndoe',
      latest_date: Math.floor(Date.now() / 1000) - 3600,
      latest_content: 'Hey, I was looking at your JavaScript array methods paste and had a question about the reduce method. Could you explain how it works in more detail?',
      message_count: 3,
      unread_count: 1
    },
    {
      thread_id: 2,
      subject: 'Collaboration opportunity',
      sender_username: 'reactfan',
      recipients: 'johndoe',
      latest_date: Math.floor(Date.now() / 1000) - 86400,
      latest_content: 'I saw your React hooks example and was impressed. Would you be interested in collaborating on a project I\'m working on?',
      message_count: 2,
      unread_count: 0
    }
  ];

  // Mock data for thread messages
  const mockThreadMessages = {
    1: [
      {
        id: 101,
        sender_id: 'user456',
        sender_username: 'pythondev',
        subject: 'Question about your JavaScript paste',
        content: 'Hey, I was looking at your JavaScript array methods paste and had a question about the reduce method. Could you explain how it works in more detail?',
        created_at: Math.floor(Date.now() / 1000) - 7200,
        recipients: 'johndoe'
      },
      {
        id: 102,
        sender_id: 'user123',
        sender_username: 'johndoe',
        subject: 'Re: Question about your JavaScript paste',
        content: 'Sure! The reduce method is used to accumulate values from an array into a single value. The first parameter is a callback function that takes an accumulator and the current value, and the second parameter is the initial value for the accumulator.\n\nFor example:\n\nconst sum = [1, 2, 3].reduce((acc, val) => acc + val, 0);\n\nThis will sum all values in the array, starting with 0.',
        created_at: Math.floor(Date.now() / 1000) - 5400,
        recipients: 'pythondev'
      },
      {
        id: 103,
        sender_id: 'user456',
        sender_username: 'pythondev',
        subject: 'Re: Question about your JavaScript paste',
        content: 'That makes sense! Thanks for the explanation. I\'ll try using it in my code.',
        created_at: Math.floor(Date.now() / 1000) - 3600,
        recipients: 'johndoe'
      }
    ],
    2: [
      {
        id: 201,
        sender_id: 'user789',
        sender_username: 'reactfan',
        subject: 'Collaboration opportunity',
        content: 'I saw your React hooks example and was impressed. Would you be interested in collaborating on a project I\'m working on?',
        created_at: Math.floor(Date.now() / 1000) - 172800,
        recipients: 'johndoe'
      },
      {
        id: 202,
        sender_id: 'user123',
        sender_username: 'johndoe',
        subject: 'Re: Collaboration opportunity',
        content: 'Thanks for reaching out! I\'d be interested in learning more about your project. What kind of collaboration did you have in mind?',
        created_at: Math.floor(Date.now() / 1000) - 86400,
        recipients: 'reactfan'
      }
    ]
  };

  useEffect(() => {
    // Check if there's a thread ID in the URL
    const threadId = searchParams.get('thread');
    
    // Set the view based on URL or default to inbox
    const viewParam = searchParams.get('view');
    if (viewParam === 'sent') {
      setView('sent');
    }
    
    // Simulate loading threads
    setIsLoading(true);
    
    setTimeout(() => {
      setThreads(mockThreads);
      
      // If there's a thread ID in the URL, load that thread
      if (threadId && mockThreadMessages[threadId]) {
        setCurrentThread({
          id: parseInt(threadId),
          messages: mockThreadMessages[threadId]
        });
      }
      
      setIsLoading(false);
    }, 500);
  }, [searchParams]);

  const handleComposeChange = (e) => {
    const { name, value } = e.target;
    setComposeData(prev => ({
      ...prev,
      [name]: value
    }));
    
    // Show recipient suggestions when typing in recipients field
    if (name === 'recipients' && value.trim()) {
      // Mock user search
      const suggestions = ['pythondev', 'reactfan', 'cssmaster', 'phpdev']
        .filter(username => username.includes(value.toLowerCase()));
      setRecipientSuggestions(suggestions);
    } else {
      setRecipientSuggestions([]);
    }
  };

  const handleSelectRecipient = (username) => {
    setComposeData(prev => ({
      ...prev,
      recipients: username
    }));
    setRecipientSuggestions([]);
  };

  const handleComposeSubmit = (e) => {
    e.preventDefault();
    // In a real app, you would send the message to the backend
    alert(`Message to ${composeData.recipients} sent successfully!`);
    setShowComposeForm(false);
    setComposeData({
      recipients: '',
      subject: '',
      content: ''
    });
  };

  const handleReplySubmit = (e) => {
    e.preventDefault();
    if (!replyContent.trim()) return;
    
    // In a real app, you would send the reply to the backend
    alert('Reply sent successfully!');
    setReplyContent('');
    
    // Simulate adding the reply to the thread
    if (currentThread) {
      const newMessage = {
        id: Date.now(),
        sender_id: user.id,
        sender_username: user.username,
        subject: `Re: ${currentThread.messages[0].subject}`,
        content: replyContent,
        created_at: Math.floor(Date.now() / 1000),
        recipients: currentThread.messages[0].sender_username
      };
      
      setCurrentThread(prev => ({
        ...prev,
        messages: [...prev.messages, newMessage]
      }));
    }
  };

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleString();
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
        {/* Header */}
        <div className="border-b border-gray-200 dark:border-gray-700 p-6">
          <div className="flex justify-between items-center">
            <h1 className="text-2xl font-bold flex items-center">
              <i className="fas fa-envelope mr-3 text-blue-500"></i>
              Messages
            </h1>
            <button 
              onClick={() => setShowComposeForm(!showComposeForm)} 
              className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors"
            >
              <i className={`fas ${showComposeForm ? 'fa-times' : 'fa-pen'} mr-2`}></i>
              {showComposeForm ? 'Cancel' : 'Compose'}
            </button>
          </div>

          {/* Tab Navigation */}
          <div className="flex space-x-4 mt-4">
            <button 
              onClick={() => setView('inbox')} 
              className={`px-4 py-2 rounded-lg ${view === 'inbox' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600'}`}
            >
              <i className="fas fa-inbox mr-2"></i>Inbox
            </button>
            <button 
              onClick={() => setView('sent')} 
              className={`px-4 py-2 rounded-lg ${view === 'sent' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600'}`}
            >
              <i className="fas fa-paper-plane mr-2"></i>Sent
            </button>
          </div>
        </div>

        {/* Compose Form */}
        {showComposeForm && (
          <div className="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 className="text-lg font-semibold mb-4">Compose Message</h3>
            <form onSubmit={handleComposeSubmit} className="space-y-4">
              <div className="relative">
                <label className="block text-sm font-medium mb-2">To:</label>
                <input 
                  type="text" 
                  name="recipients" 
                  value={composeData.recipients} 
                  onChange={handleComposeChange} 
                  required
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                  placeholder="Enter username"
                />
                {recipientSuggestions.length > 0 && (
                  <div className="absolute z-10 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg mt-1 max-h-48 overflow-y-auto">
                    {recipientSuggestions.map(username => (
                      <div 
                        key={username} 
                        className="px-4 py-2 hover:bg-blue-100 dark:hover:bg-blue-900 cursor-pointer"
                        onClick={() => handleSelectRecipient(username)}
                      >
                        {username}
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Subject:</label>
                <input 
                  type="text" 
                  name="subject" 
                  value={composeData.subject} 
                  onChange={handleComposeChange} 
                  required
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Message:</label>
                <textarea 
                  name="content" 
                  value={composeData.content} 
                  onChange={handleComposeChange} 
                  required
                  rows="6" 
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                ></textarea>
              </div>

              <div className="flex gap-2">
                <button 
                  type="submit" 
                  className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors"
                >
                  <i className="fas fa-paper-plane mr-2"></i>Send
                </button>
                <button 
                  type="button" 
                  onClick={() => setShowComposeForm(false)} 
                  className="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Thread View or List View */}
        <div className="p-6">
          {isLoading ? (
            <div className="flex justify-center items-center h-64">
              <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
            </div>
          ) : currentThread ? (
            /* Thread Detail View */
            <div>
              <div className="mb-4">
                <button 
                  onClick={() => setCurrentThread(null)} 
                  className="text-blue-500 hover:text-blue-700"
                >
                  <i className="fas fa-arrow-left mr-2"></i>Back to {view === 'inbox' ? 'Inbox' : 'Sent'}
                </button>
              </div>

              <div className="space-y-4">
                {currentThread.messages.map(message => (
                  <div 
                    key={message.id} 
                    className={`bg-gray-50 dark:bg-gray-700 rounded-lg p-4 ${message.sender_id === user.id ? 'ml-8' : 'mr-8'}`}
                  >
                    <div className="flex justify-between items-start mb-2">
                      <div>
                        <strong className={message.sender_id === user.id ? 'text-blue-600' : 'text-green-600'}>
                          {message.sender_id === user.id ? 'You' : message.sender_username}
                        </strong>
                        {message.recipients && (
                          <span className="text-gray-500 text-sm">
                            to {message.recipients}
                          </span>
                        )}
                      </div>
                      <span className="text-gray-500 text-sm">
                        {formatDate(message.created_at)}
                      </span>
                    </div>
                    <h4 className="font-medium mb-2">{message.subject}</h4>
                    <div className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{message.content}</div>
                  </div>
                ))}
              </div>

              {/* Reply Form */}
              <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h4 className="font-medium mb-4">Reply to this thread</h4>
                <form onSubmit={handleReplySubmit} className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium mb-2">Message:</label>
                    <textarea 
                      value={replyContent} 
                      onChange={(e) => setReplyContent(e.target.value)} 
                      rows="4" 
                      required
                      className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                    ></textarea>
                  </div>
                  <button 
                    type="submit" 
                    className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors"
                  >
                    <i className="fas fa-reply mr-2"></i>Send Reply
                  </button>
                </form>
              </div>
            </div>
          ) : threads.length === 0 ? (
            /* Empty State */
            <div className="text-center py-8">
              <i className="fas fa-envelope text-4xl text-gray-400 mb-4"></i>
              <p className="text-gray-500 text-lg mb-2">
                {view === 'inbox' ? 'No messages received yet.' : 'No messages sent yet.'}
              </p>
              <p className="text-gray-400">
                {view === 'inbox' 
                  ? 'Messages from other users will appear here.' 
                  : 'Click compose to send your first message.'}
              </p>
            </div>
          ) : (
            /* List View */
            <div className="space-y-2">
              {threads.map(thread => (
                <div 
                  key={thread.thread_id} 
                  className="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                  onClick={() => setCurrentThread({
                    id: thread.thread_id,
                    messages: mockThreadMessages[thread.thread_id] || []
                  })}
                >
                  <div className="flex-1">
                    <div className="flex items-center justify-between mb-1">
                      <h3 className={`font-medium truncate ${view === 'inbox' && thread.unread_count > 0 ? 'font-bold' : ''}`}>
                        {thread.subject}
                      </h3>
                      <div className="flex items-center space-x-2">
                        {view === 'inbox' && thread.unread_count > 0 && (
                          <span className="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                            {thread.unread_count} new
                          </span>
                        )}
                        <span className="text-gray-500 text-sm">
                          {formatDate(thread.latest_date)}
                        </span>
                      </div>
                    </div>
                    <div className="flex items-center justify-between">
                      <p className="text-gray-600 dark:text-gray-400 text-sm">
                        {view === 'inbox' 
                          ? `From: ${thread.sender_username}` 
                          : `To: ${thread.recipients}`}
                      </p>
                      <span className="text-gray-500 text-xs">
                        {thread.message_count} message{thread.message_count > 1 ? 's' : ''}
                      </span>
                    </div>
                    <p className="text-gray-500 text-sm overflow-hidden max-h-10 mt-1">
                      {thread.latest_content.length > 100 
                        ? thread.latest_content.substring(0, 100) + '...' 
                        : thread.latest_content}
                    </p>
                  </div>
                  <div className="ml-4 flex items-center space-x-2">
                    <button 
                      onClick={(e) => {
                        e.stopPropagation();
                        // In a real app, you would call an API to delete the thread
                        setThreads(prev => prev.filter(t => t.thread_id !== thread.thread_id));
                      }} 
                      className="text-red-500 hover:text-red-700 p-1"
                    >
                      <i className="fas fa-trash"></i>
                    </button>
                    <i className="fas fa-chevron-right text-gray-400"></i>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default MessagesPage;