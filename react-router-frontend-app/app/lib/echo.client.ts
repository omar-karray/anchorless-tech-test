import type Echo from "laravel-echo";
import Pusher from "pusher-js";

declare global {
  interface Window {
    Pusher: typeof Pusher;
    __anchorlessEcho?: Echo<any>;
  }
}

let echoPromise: Promise<Echo<any> | null> | null = null;

export function ensureEchoInstance(): Promise<Echo<any> | null> {
  if (typeof window === "undefined") {
    return Promise.resolve(null);
  }

  if (window.__anchorlessEcho) {
    return Promise.resolve(window.__anchorlessEcho);
  }

  if (echoPromise) {
    return echoPromise;
  }

  const key = import.meta.env.VITE_REVERB_APP_KEY;

  if (!key) {
    console.warn(
      "[Anchorless] Missing VITE_REVERB_APP_KEY. Realtime features are disabled."
    );
    return Promise.resolve(null);
  }

  echoPromise = import("laravel-echo")
    .then(({ default: Echo }) => {
      const host =
        import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
      const port = Number(import.meta.env.VITE_REVERB_PORT ?? "80");
      const scheme = (
        import.meta.env.VITE_REVERB_SCHEME ?? "http"
      ).toLowerCase();
      const forceTLS = scheme === "https";

      // Get XSRF token from cookies
      const getCsrfToken = () => {
        const cookies = document.cookie.split(';');
        const xsrfCookie = cookies.find(c => c.trim().startsWith('XSRF-TOKEN='));
        return xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : null;
      };

      // Broadcasting auth is on the web routes, not API routes
      const laravelBaseUrl = import.meta.env.VITE_API_BASE_URL?.replace('/api', '') || 'http://localhost';

      window.Pusher = Pusher;

      const echo = new Echo({
        broadcaster: "reverb",
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ["ws", "wss"],
        disableStats: true,
        authEndpoint: `${laravelBaseUrl}/broadcasting/auth`,
        auth: {
          headers: {
            'X-XSRF-TOKEN': getCsrfToken() || '',
            'Accept': 'application/json',
          },
        },
        authorizer: (channel: any) => {
          return {
            authorize: (socketId: string, callback: Function) => {
              fetch(`${laravelBaseUrl}/broadcasting/auth`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                  'X-XSRF-TOKEN': getCsrfToken() || '',
                },
                credentials: 'include',
                body: JSON.stringify({
                  socket_id: socketId,
                  channel_name: channel.name,
                }),
              })
                .then(response => response.json())
                .then(data => callback(null, data))
                .catch(error => callback(error));
            },
          };
        },
      });

      window.__anchorlessEcho = echo;
      return echo;
    })
    .catch((error) => {
      console.error("[Anchorless] Failed to initialise Echo:", error);
      return null;
    });

  return echoPromise;
}
