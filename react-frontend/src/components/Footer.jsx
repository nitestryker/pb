import React from 'react';
import { Link } from 'react-router-dom';

const Footer = () => {
  return (
    <footer className="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-6">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex flex-col md:flex-row justify-between items-center">
          <div className="mb-4 md:mb-0">
            <Link to="/" className="flex items-center space-x-2">
              <i className="fas fa-paste text-blue-600 dark:text-blue-400"></i>
              <span className="font-bold text-gray-800 dark:text-white">PasteForge</span>
            </Link>
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Share code snippets securely and easily
            </p>
          </div>
          
          <div className="flex flex-wrap justify-center gap-4">
            <Link to="/about" className="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 text-sm">
              About
            </Link>
            <Link to="/privacy" className="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 text-sm">
              Privacy
            </Link>
            <Link to="/terms" className="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 text-sm">
              Terms
            </Link>
            <Link to="/contact" className="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 text-sm">
              Contact
            </Link>
          </div>
        </div>
        
        <div className="mt-6 text-center text-xs text-gray-500 dark:text-gray-500">
          &copy; {new Date().getFullYear()} PasteForge. All rights reserved.
        </div>
      </div>
    </footer>
  );
};

export default Footer;