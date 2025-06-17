import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  // Remove the proxy configuration to run without PHP backend
  server: {
    port: 5173
  }
})