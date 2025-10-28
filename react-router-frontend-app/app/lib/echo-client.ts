import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
if (typeof window !== 'undefined') {
  (window as any).Pusher = Pusher;
}

let echoInstance: Echo<any> | null = null;

export function getEcho(): Echo<any> | null {
  // Only run on client-side
  if (typeof window === 'undefined') {
    return null;
  }

  if (echoInstance) {
    return echoInstance;
  }

  // Get CSRF token from cookies
  const getCsrfToken = () => {
    const cookies = document.cookie.split(';');
    const xsrfCookie = cookies.find(c => c.trim().startsWith('XSRF-TOKEN='));
    return xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : null;
  };

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'anchorless-app-key',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
      headers: {
        'X-XSRF-TOKEN': getCsrfToken() || '',
      },
    },
    authEndpoint: '/api/broadcasting/auth',
  });

  return echoInstance;
}

export function disconnectEcho() {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}
