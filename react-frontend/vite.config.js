import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // Proxy API requests to the PHP backend
      '/': {
        target: 'http://localhost:8000', // Adjust this to your PHP server URL
        changeOrigin: true,
      }
    }
  }
})