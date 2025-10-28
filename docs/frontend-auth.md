# Frontend Authentication Flow

The React Router frontend now implements a token-based authentication flow against the Laravel API. This page explains the moving parts so new contributors can extend the behaviour safely.

## Overview

- Laravel issues Sanctum access tokens via `POST /api/auth/login`. The response includes the token and a minimal user payload.
- The frontend stores the token and user in `sessionStorage` (mirrored in memory) via helpers in `app/lib/auth-storage.ts`.
- All API requests go through `app/lib/api-client.ts`, which automatically attaches the `Authorization: Bearer …` header when a token is present. On `401` responses it clears the session and throws, allowing loaders/actions to redirect back to `/login`.
- Protected React Router routes declare loaders that call `/api/auth/me`. If the request fails, the loader clears the session and redirects the user to the login page.

## Route Structure

```
app/
  lib/
    api-client.ts        # fetch wrapper with token + error handling
    auth-storage.ts      # token/user persistence helpers
  routes/
    _index.tsx           # redirects to /login or /dashboard based on token
    login.tsx            # login form + action
    logout.tsx           # POST action to revoke the token
    dashboard.tsx        # protected layout + loader (provides user context)
    dashboard.index.tsx  # default dashboard screen
```

- `login.tsx` exports both a loader (redirects authenticated users away) and an action that posts credentials to `/api/auth/login`, stores the returned token/user, and redirects to `/dashboard` (respecting an optional `redirectTo` query param).
- `dashboard.tsx` runs a loader before rendering. If no token exists, it redirects to `/login`. Otherwise it calls `/api/auth/me` using the API client. The returned user is exposed to child routes through the outlet context.
- `logout.tsx` only exposes an action. The dashboard layout renders a `<Form method="post" action="/logout">` button which calls the Laravel logout endpoint, clears session storage, and redirects back to `/login`.

## API Configuration

- Frontend requests default to the same origin `/api` prefix. Set `VITE_API_BASE_URL` if the API lives elsewhere (the `.env.example` file points to `http://localhost/api` for local development).
- Vite optimised dependencies can be refreshed with `rm -rf node_modules/.vite && npm run dev -- --host --force` if Safari caches become stale.

## Extending the Flow

- New protected pages should be nested under `dashboard.tsx` so they inherit the loader and have access to the authenticated user via `useOutletContext<{ user: AuthUser }>()`.
- Use the shared `apiFetch` helper inside loaders, actions, or client-side `useEffect` hooks to guarantee the token is attached and 401s trigger a logout.
- To persist additional user metadata, extend the `AuthUser` type in `auth-storage.ts` and update the backend `AuthController@login` / `me` responses accordingly.

This setup keeps authentication concerns centralised, prevents duplicated `fetch` boilerplate, and plays nicely with React Router v7 actions/loaders.
