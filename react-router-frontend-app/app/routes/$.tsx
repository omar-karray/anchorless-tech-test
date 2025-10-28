import { redirect } from "react-router";
import type { Route } from "./+types/$";
import { apiFetch } from "../lib/api-client";
import type { AuthUser } from "../lib/auth-storage";

/**
 * Catch-all route for 404/undefined paths.
 * If authenticated, show 404. If not, redirect to login.
 */
export async function loader({ request }: Route.LoaderArgs) {
  try {
    // Check if user is authenticated
    await apiFetch<AuthUser>("/auth/me", {}, request);
    
    // User is authenticated, return data to show 404 page
    return { isAuthenticated: true };
  } catch (error) {
    // User not authenticated, redirect to login
    const url = new URL(request.url);
    return redirect(`/login?redirectTo=${encodeURIComponent(url.pathname)}`);
  }
}

export default function CatchAll() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50">
      <div className="text-center">
        <h1 className="text-6xl font-bold text-slate-300">404</h1>
        <p className="mt-4 text-lg text-slate-600">Page not found</p>
        <a 
          href="/dashboard" 
          className="mt-6 inline-block rounded-lg bg-blue-600 px-6 py-2 text-white hover:bg-blue-700"
        >
          Go to Dashboard
        </a>
      </div>
    </div>
  );
}
