// Configuration
const API_URL = 'https://your-api-domain.com/api';  // Change to your API URL

// Format time (MM:SS)
function formatTime(seconds) {
    if (!seconds) return '0:00';
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}