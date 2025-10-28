# Frontend Realtime Integration

The React Router frontend interacts with Laravel Reverb via a small collection of utilities and hooks. This section outlines how the pieces fit together so the team can confidently extend the realtime experience.

## Echo Initialisation (`app/lib/echo.client.ts`)

- `ensureEchoInstance()` lazily creates a single `laravel-echo` instance in the browser and caches it on `window.__anchorlessEcho`.
- Configuration is drawn from the `VITE_REVERB_*` env variables. If the app key is missing, the helper logs a warning and resolves to `null`.
- Uses the Reverb broadcaster (Pusher transport) with `/broadcasting/auth` as the auth endpoint, so Sanctum cookies secure private channels automatically.
- The helper is safe to call from anywhere in client code; it will either return the cached Echo instance or create it on demand.

```ts
const echo = await ensureEchoInstance();
if (echo) {
  echo.private(`visa-applications.${id}`).listen(...);
}
```

## Connection Hook (`app/hooks/useEchoConnection.ts`)

- Provides `{ status, echo, error }` to React components:
  - `status`: `"idle" | "connecting" | "connected" | "unavailable" | "error"`.
  - `echo`: the Echo instance (or `null` when unavailable).
  - `error`: the last connection error, if any.
- Internally calls `ensureEchoInstance()` and subscribes to Pusher connection events (`connected`, `disconnected`, `error`).
- Automatically cleans up listeners when the component unmounts.

Usage example:

```tsx
const { status, echo } = useEchoConnection();

useEffect(() => {
  if (!echo) return;
  const channel = echo.private(`visa-applications.${visaId}`);

  channel.listen("VisaApplicantFileStored", (event) => {
    // handle event.file...
  });

  return () => {
    channel.stopListening("VisaApplicantFileStored");
    echo.leaveChannel(`private-visa-applications.${visaId}`);
  };
}, [echo, visaId]);
```

## UI Feedback (`app/routes/home.tsx`)

- The home route invokes `useEchoConnection()` and displays a status banner:
  - “Connecting…” while the socket bootstraps.
  - “Connected” when Reverb is available.
  - “Unavailable” if config is missing.
  - “Connection lost” for disconnect/error states.
- This gives immediate feedback during development and acts as a canary for production issues.

## Extending the Pattern

- Create specialised hooks that build on `ensureEchoInstance()` (e.g. `usePrivateVisaApplicationChannel`) to subscribe/unsubscribe automatically.
- When listening to broadcast events (`VisaApplicantFileStored` / `VisaApplicantFileFailed`), remember to clean up with `channel.stopListening(...)` and `echo.leaveChannel(...)` to avoid duplicated handlers during navigation.
- Because the connection is initialised once, no additional throttling or guards are needed—multiple components can safely call `ensureEchoInstance()` or `useEchoConnection()` simultaneously.

This foundation pairs with the backend’s Reverb broadcasts, enabling the frontend to react to queued file uploads (and future realtime features) without re-implementing socket setup in every component.
