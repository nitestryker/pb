# PasteForge React Frontend

This is a React-based frontend for the PasteForge application, converted from the original PHP implementation.

## Project Structure

- `src/components/`: Reusable UI components
- `src/pages/`: Page components that correspond to routes
- `src/contexts/`: React context providers for state management
- `src/styles/`: CSS styles (if applicable)
- `public/`: Static assets

## Available Scripts

In the project directory, you can run:

### `npm run dev`

Runs the app in development mode.\
Open [http://localhost:5173](http://localhost:5173) to view it in your browser.

### `npm run build`

Builds the app for production to the `dist` folder.

### `npm run preview`

Locally preview the production build.

## Features

- Create and view code pastes with syntax highlighting
- User authentication (login/signup)
- Archive of public pastes
- User collections
- Account management
- Dark/light theme support

## API Integration

This frontend uses placeholder data and mock API calls. To connect to the actual PHP backend:

1. Update fetch calls in components to point to the correct API endpoints
2. Ensure CORS is properly configured on the backend
3. Implement proper error handling for API responses

## Notes

- This is a client-side only application and requires the PHP backend for full functionality
- The original styling and layout has been preserved using the same Tailwind CSS classes