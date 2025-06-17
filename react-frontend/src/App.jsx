import { useState, useEffect } from 'react'
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import Header from './components/Header'
import Footer from './components/Footer'
import HomePage from './pages/HomePage'
import ArchivePage from './pages/ArchivePage'
import ViewPastePage from './pages/ViewPastePage'
import LoginPage from './pages/LoginPage'
import SignupPage from './pages/SignupPage'
import AccountPage from './pages/AccountPage'
import SettingsPage from './pages/SettingsPage'
import CollectionsPage from './pages/CollectionsPage'
import NotificationsPage from './pages/NotificationsPage'
import MessagesPage from './pages/MessagesPage'
import ProfilePage from './pages/ProfilePage'
import EditProfilePage from './pages/EditProfilePage'
import PricingPage from './pages/PricingPage'
import NotFoundPage from './pages/NotFoundPage'
import { UserProvider, useUser } from './contexts/UserContext'
import { ThemeProvider } from './contexts/ThemeContext'

// Protected route component
const ProtectedRoute = ({ children }) => {
  const { user, loading } = useUser();
  
  if (loading) {
    return <div className="flex justify-center items-center h-screen">
      <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
    </div>;
  }
  
  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }
  
  return children;
};

function App() {
  return (
    <ThemeProvider>
      <UserProvider>
        <Router>
          <div className="min-h-screen flex flex-col bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            <Header />
            <main className="flex-grow">
              <Routes>
                <Route path="/" element={<HomePage />} />
                <Route path="/archive" element={<ArchivePage />} />
                <Route path="/view/:id" element={<ViewPastePage />} />
                <Route path="/login" element={<LoginPage />} />
                <Route path="/signup" element={<SignupPage />} />
                <Route path="/notifications" element={
                  <ProtectedRoute>
                    <NotificationsPage />
                  </ProtectedRoute>
                } />
                <Route path="/account" element={
                  <ProtectedRoute>
                    <AccountPage />
                  </ProtectedRoute>
                } />
                <Route path="/settings" element={
                  <ProtectedRoute>
                    <SettingsPage />
                  </ProtectedRoute>
                } />
                <Route path="/collections" element={
                  <ProtectedRoute>
                    <CollectionsPage />
                  </ProtectedRoute>
                } />
                <Route path="/messages" element={
                  <ProtectedRoute>
                    <MessagesPage />
                  </ProtectedRoute>
                } />
                <Route path="/profile/:username" element={<ProfilePage />} />
                <Route path="/profile/edit" element={
                  <ProtectedRoute>
                    <EditProfilePage />
                  </ProtectedRoute>
                } />
                <Route path="/pricing" element={<PricingPage />} />
                <Route path="*" element={<NotFoundPage />} />
              </Routes>
            </main>
            <Footer />
          </div>
        </Router>
      </UserProvider>
    </ThemeProvider>
  )
}

export default App