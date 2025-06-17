import { useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';

const ImportExportPage = () => {
  const { user } = useUser();
  const [activeTab, setActiveTab] = useState('import');
  const [importFile, setImportFile] = useState(null);
  const [importMode, setImportMode] = useState('add');
  const [titlePrefix, setTitlePrefix] = useState('');
  const [defaultLanguage, setDefaultLanguage] = useState('');
  const [defaultTags, setDefaultTags] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [importResults, setImportResults] = useState(null);
  const [exportFormat, setExportFormat] = useState('json');
  const [exportSelection, setExportSelection] = useState('all');
  const [selectedPastes, setSelectedPastes] = useState([]);
  
  // Mock pastes for export selection
  const [userPastes, setUserPastes] = useState([
    {
      id: 1,
      title: 'JavaScript Array Methods',
      language: 'javascript',
      created_at: Math.floor(Date.now() / 1000) - 86400,
      views: 42
    },
    {
      id: 2,
      title: 'React Hooks Example',
      language: 'javascript',
      created_at: Math.floor(Date.now() / 1000) - 172800,
      views: 65
    },
    {
      id: 3,
      title: 'Python List Comprehension',
      language: 'python',
      created_at: Math.floor(Date.now() / 1000) - 259200,
      views: 28
    }
  ]);

  // Redirect if not logged in
  if (!user) {
    return <Navigate to="/login" state={{ from: { pathname: '/import-export' } }} />;
  }

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      // Check file size (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        setError('File size exceeds 5MB limit');
        setImportFile(null);
        e.target.value = null;
        return;
      }
      
      // Check file type
      const validTypes = ['.json', '.csv', '.txt'];
      const fileExtension = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
      if (!validTypes.includes(fileExtension)) {
        setError('Invalid file type. Please upload JSON, CSV, or TXT files.');
        setImportFile(null);
        e.target.value = null;
        return;
      }
      
      setImportFile(file);
      setError('');
    }
  };

  const handleImportSubmit = (e) => {
    e.preventDefault();
    if (!importFile) {
      setError('Please select a file to import');
      return;
    }
    
    setIsLoading(true);
    setError('');
    setSuccess('');
    
    // Simulate file import
    setTimeout(() => {
      // Mock import results
      const results = {
        successful: Math.floor(Math.random() * 5) + 1,
        failed: Math.floor(Math.random() * 2),
        skipped: importMode === 'skip_duplicates' ? Math.floor(Math.random() * 3) : 0,
        total: 0
      };
      
      results.total = results.successful + results.failed + results.skipped;
      
      setImportResults(results);
      setSuccess('Import completed successfully!');
      setIsLoading(false);
      
      // Reset form
      setImportFile(null);
      document.getElementById('import-form').reset();
    }, 1500);
  };

  const handleExportSubmit = (e) => {
    e.preventDefault();
    
    setIsLoading(true);
    setError('');
    
    // Simulate export process
    setTimeout(() => {
      // In a real app, this would trigger a file download
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const filename = `pasteforge_export_${timestamp}.${exportFormat}`;
      
      alert(`Export would download file: ${filename}`);
      
      setIsLoading(false);
      setSuccess('Export completed successfully!');
    }, 1000);
  };

  const togglePasteSelection = (pasteId) => {
    if (selectedPastes.includes(pasteId)) {
      setSelectedPastes(selectedPastes.filter(id => id !== pasteId));
    } else {
      setSelectedPastes([...selectedPastes, pasteId]);
    }
  };

  const formatDate = (timestamp) => {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString();
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8">
      {success && (
        <div className="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
          <i className="fas fa-check-circle mr-2"></i>{success}
        </div>
      )}
      
      {error && (
        <div className="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
          <i className="fas fa-exclamation-circle mr-2"></i>{error}
        </div>
      )}
      
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
        {/* Header */}
        <div className="border-b border-gray-200 dark:border-gray-700 p-6">
          <h1 className="text-2xl font-bold flex items-center">
            <i className="fas fa-exchange-alt mr-3 text-blue-500"></i>
            Import & Export
          </h1>
          <p className="text-gray-600 dark:text-gray-400 mt-1">
            Transfer your pastes between PasteForge and other platforms
          </p>
        </div>

        {/* Tabs */}
        <div className="border-b border-gray-200 dark:border-gray-700">
          <nav className="flex space-x-8 px-6" aria-label="Tabs">
            <button 
              onClick={() => setActiveTab('import')} 
              className={`py-4 px-1 text-sm font-medium ${
                activeTab === 'import' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-file-import mr-2"></i>Import
            </button>
            <button 
              onClick={() => setActiveTab('export')} 
              className={`py-4 px-1 text-sm font-medium ${
                activeTab === 'export' 
                  ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' 
                  : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              <i className="fas fa-file-export mr-2"></i>Export
            </button>
          </nav>
        </div>

        {/* Import Tab */}
        <div className={`p-6 ${activeTab !== 'import' ? 'hidden' : ''}`}>
          <h2 className="text-lg font-semibold mb-4">Import Pastes</h2>
          
          {importResults && (
            <div className="mb-6 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
              <h3 className="text-lg font-semibold text-green-800 dark:text-green-200 mb-2">
                <i className="fas fa-check-circle"></i> Import Completed
              </h3>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div className="text-center">
                  <div className="text-2xl font-bold text-green-600">{importResults.successful}</div>
                  <div className="text-gray-600 dark:text-gray-400">Successful</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-red-600">{importResults.failed}</div>
                  <div className="text-gray-600 dark:text-gray-400">Failed</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-yellow-600">{importResults.skipped}</div>
                  <div className="text-gray-600 dark:text-gray-400">Skipped</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-blue-600">{importResults.total}</div>
                  <div className="text-gray-600 dark:text-gray-400">Total</div>
                </div>
              </div>
            </div>
          )}
          
          <div className="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <h3 className="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-2">
              <i className="fas fa-info-circle"></i> Supported Formats
            </h3>
            <ul className="text-sm text-blue-700 dark:text-blue-300 space-y-1">
              <li><strong>JSON:</strong> PasteForge export format or custom JSON with title, content, language fields</li>
              <li><strong>CSV:</strong> Spreadsheet format with Title, Content, Language, Tags, Public columns</li>
              <li><strong>TXT:</strong> Plain text file (creates single paste)</li>
            </ul>
          </div>

          <form id="import-form" onSubmit={handleImportSubmit} className="space-y-6">
            <div>
              <label className="block text-sm font-medium mb-2">Select Import File</label>
              <input 
                type="file" 
                onChange={handleFileChange} 
                accept=".json,.csv,.txt" 
                className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
              />
              <p className="mt-1 text-sm text-gray-500">Supported formats: JSON, CSV, TXT</p>
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">Import Mode</label>
              <div className="space-y-2">
                <label className="flex items-center">
                  <input 
                    type="radio" 
                    name="import_mode" 
                    value="add" 
                    checked={importMode === 'add'} 
                    onChange={() => setImportMode('add')} 
                    className="mr-2"
                  />
                  <span>Add all pastes (allow duplicates)</span>
                </label>
                <label className="flex items-center">
                  <input 
                    type="radio" 
                    name="import_mode" 
                    value="skip_duplicates" 
                    checked={importMode === 'skip_duplicates'} 
                    onChange={() => setImportMode('skip_duplicates')} 
                    className="mr-2"
                  />
                  <span>Skip duplicate pastes (based on title and content)</span>
                </label>
              </div>
            </div>

            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Title Prefix (Optional)</label>
                <input 
                  type="text" 
                  value={titlePrefix}
                  onChange={(e) => setTitlePrefix(e.target.value)}
                  placeholder="e.g. Imported - " 
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                />
                <p className="mt-1 text-sm text-gray-500">Will be added to the beginning of each paste title</p>
              </div>
              
              <div>
                <label className="block text-sm font-medium mb-2">Default Language</label>
                <select 
                  value={defaultLanguage}
                  onChange={(e) => setDefaultLanguage(e.target.value)}
                  className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                >
                  <option value="">Use file's language (if specified)</option>
                  <option value="plaintext">Plain Text</option>
                  <option value="javascript">JavaScript</option>
                  <option value="python">Python</option>
                  <option value="php">PHP</option>
                  <option value="html">HTML</option>
                  <option value="css">CSS</option>
                  <option value="sql">SQL</option>
                  <option value="json">JSON</option>
                  <option value="xml">XML</option>
                  <option value="markdown">Markdown</option>
                  <option value="bash">Bash</option>
                  <option value="java">Java</option>
                  <option value="cpp">C++</option>
                  <option value="c">C</option>
                  <option value="csharp">C#</option>
                </select>
                <p className="mt-1 text-sm text-gray-500">Override language for imported pastes</p>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">Default Tags (Optional)</label>
              <input 
                type="text" 
                value={defaultTags}
                onChange={(e) => setDefaultTags(e.target.value)}
                placeholder="imported, backup, archive" 
                className="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
              />
              <p className="mt-1 text-sm text-gray-500">Comma-separated tags to add to all imported pastes</p>
            </div>

            <div className="flex gap-4">
              <button 
                type="submit" 
                className="bg-blue-500 text-white py-2 px-6 rounded-lg hover:bg-blue-600"
                disabled={isLoading || !importFile}
              >
                {isLoading ? (
                  <>
                    <i className="fas fa-spinner fa-spin mr-2"></i>
                    Importing...
                  </>
                ) : (
                  <>
                    <i className="fas fa-file-import mr-2"></i>
                    Import Pastes
                  </>
                )}
              </button>
              <button 
                type="button" 
                onClick={() => {
                  setImportFile(null);
                  setImportResults(null);
                  document.getElementById('import-form').reset();
                }}
                className="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600"
              >
                Cancel
              </button>
            </div>
          </form>

          <div className="mt-8 border-t dark:border-gray-700 pt-6">
            <h3 className="text-lg font-semibold mb-4">Import Examples</h3>
            
            <div className="space-y-4">
              <div>
                <h4 className="font-medium">JSON Format Example:</h4>
                <pre className="mt-2 p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm overflow-x-auto"><code>{`{
  "pastes": [
    {
      "title": "Hello World",
      "content": "console.log('Hello, World!');",
      "language": "javascript",
      "tags": "example,javascript",
      "is_public": 1
    }
  ]
}`}</code></pre>
              </div>

              <div>
                <h4 className="font-medium">CSV Format Example:</h4>
                <pre className="mt-2 p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm overflow-x-auto"><code>{`Title,Content,Language,Tags,Public
"Hello World","console.log('Hello, World!');","javascript","example,javascript","Yes"
"Python Script","print('Hello Python')","python","example,python","No"`}</code></pre>
              </div>
            </div>
          </div>
        </div>

        {/* Export Tab */}
        <div className={`p-6 ${activeTab !== 'export' ? 'hidden' : ''}`}>
          <h2 className="text-lg font-semibold mb-4">Export Pastes</h2>
          
          <div className="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <h3 className="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-2">
              <i className="fas fa-info-circle"></i> Export Options
            </h3>
            <p className="text-sm text-blue-700 dark:text-blue-300">
              Export your pastes in various formats for backup or transfer to other platforms.
              Choose which pastes to export and your preferred format.
            </p>
          </div>

          <form onSubmit={handleExportSubmit} className="space-y-6">
            <div>
              <label className="block text-sm font-medium mb-2">Export Format</label>
              <div className="grid grid-cols-3 gap-4">
                <label className="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                  <input 
                    type="radio" 
                    name="export_format" 
                    value="json" 
                    checked={exportFormat === 'json'} 
                    onChange={() => setExportFormat('json')} 
                    className="mr-2"
                  />
                  <div>
                    <div className="font-medium">JSON</div>
                    <div className="text-xs text-gray-500">Structured data format</div>
                  </div>
                </label>
                
                <label className="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                  <input 
                    type="radio" 
                    name="export_format" 
                    value="csv" 
                    checked={exportFormat === 'csv'} 
                    onChange={() => setExportFormat('csv')} 
                    className="mr-2"
                  />
                  <div>
                    <div className="font-medium">CSV</div>
                    <div className="text-xs text-gray-500">Spreadsheet compatible</div>
                  </div>
                </label>
                
                <label className="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                  <input 
                    type="radio" 
                    name="export_format" 
                    value="txt" 
                    checked={exportFormat === 'txt'} 
                    onChange={() => setExportFormat('txt')} 
                    className="mr-2"
                  />
                  <div>
                    <div className="font-medium">TXT</div>
                    <div className="text-xs text-gray-500">Plain text format</div>
                  </div>
                </label>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">Pastes to Export</label>
              <div className="space-y-2">
                <label className="flex items-center">
                  <input 
                    type="radio" 
                    name="export_selection" 
                    value="all" 
                    checked={exportSelection === 'all'} 
                    onChange={() => {
                      setExportSelection('all');
                      setSelectedPastes([]);
                    }} 
                    className="mr-2"
                  />
                  <span>All my pastes</span>
                </label>
                <label className="flex items-center">
                  <input 
                    type="radio" 
                    name="export_selection" 
                    value="selected" 
                    checked={exportSelection === 'selected'} 
                    onChange={() => setExportSelection('selected')} 
                    className="mr-2"
                  />
                  <span>Selected pastes only</span>
                </label>
              </div>
            </div>

            {exportSelection === 'selected' && (
              <div>
                <label className="block text-sm font-medium mb-2">Select Pastes</label>
                <div className="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                  <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-700">
                      <tr>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                          <input 
                            type="checkbox" 
                            onChange={(e) => {
                              if (e.target.checked) {
                                setSelectedPastes(userPastes.map(paste => paste.id));
                              } else {
                                setSelectedPastes([]);
                              }
                            }}
                            checked={selectedPastes.length === userPastes.length && userPastes.length > 0}
                            className="mr-2"
                          />
                          Select
                        </th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                          Title
                        </th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                          Language
                        </th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                          Date
                        </th>
                      </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                      {userPastes.map(paste => (
                        <tr key={paste.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                          <td className="px-6 py-4 whitespace-nowrap">
                            <input 
                              type="checkbox" 
                              checked={selectedPastes.includes(paste.id)} 
                              onChange={() => togglePasteSelection(paste.id)}
                            />
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900 dark:text-white">
                              {paste.title}
                            </div>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            <span className="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                              {paste.language}
                            </span>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {formatDate(paste.created_at)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {exportSelection === 'selected' && selectedPastes.length === 0 && (
                  <p className="mt-2 text-sm text-red-500">Please select at least one paste to export</p>
                )}
              </div>
            )}

            <div className="flex gap-4">
              <button 
                type="submit" 
                className="bg-blue-500 text-white py-2 px-6 rounded-lg hover:bg-blue-600"
                disabled={isLoading || (exportSelection === 'selected' && selectedPastes.length === 0)}
              >
                {isLoading ? (
                  <>
                    <i className="fas fa-spinner fa-spin mr-2"></i>
                    Exporting...
                  </>
                ) : (
                  <>
                    <i className="fas fa-file-export mr-2"></i>
                    Export Pastes
                  </>
                )}
              </button>
              <button 
                type="button" 
                onClick={() => {
                  setExportFormat('json');
                  setExportSelection('all');
                  setSelectedPastes([]);
                }}
                className="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600"
              >
                Reset
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default ImportExportPage;