import { Outlet, redirect, useLoaderData, Form, useNavigate } from "react-router";
import type { LoaderFunctionArgs } from "react-router";
import { apiFetch } from "../lib/api-client";
import { clearSession, saveUser, type AuthUser } from "../lib/auth-storage";
import { useEffect, useState, type FormEvent } from "react";

type LoaderData = {
  user: AuthUser | null;
  needsLogin?: boolean;
  redirectTo?: string;
};

export async function loader({ request }: LoaderFunctionArgs) {
  const url = new URL(request.url);
  const redirectTo = url.pathname + url.search;

  try {
    const user = await apiFetch<AuthUser>("/auth/me", {}, request);
    saveUser(user);
    return { user } satisfies LoaderData;
  } catch (error) {
    clearSession();
    // On SSR, a hard redirect here can loop if cookies aren't forwarded.
    // Defer redirect to the client to avoid bounce.
    return { user: null, needsLogin: true, redirectTo } satisfies LoaderData;
  }
}

export default function DashboardLayout() {
  const { user, needsLogin, redirectTo } = useLoaderData() as LoaderData;
  const navigate = useNavigate();
  useEffect(() => {
    if (needsLogin) {
      const dest = `/login?redirectTo=${encodeURIComponent(redirectTo ?? "/dashboard")}`;
      navigate(dest, { replace: true });
    }
  }, [needsLogin, redirectTo, navigate]);
  const [isMenuOpen, setMenuOpen] = useState(false);

  if (needsLogin || !user) {
    return <div className="p-6 text-slate-600">Redirecting to sign in…</div>;
  }

  return (
    <div className="min-h-screen bg-slate-100 text-slate-900">
      <header className="border-b border-slate-200 bg-white/80 backdrop-blur supports-backdrop-blur:bg-white/60">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
          <div>
            <p className="text-xs uppercase tracking-wide text-blue-600">Anchorless</p>
            <h1 className="text-lg font-semibold">Visa Operations Portal</h1>
          </div>

          <div className="relative">
            <button
              onClick={() => setMenuOpen((prev) => !prev)}
              className="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-medium shadow-sm transition hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-300"
            >
              <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                {user.name.slice(0, 2).toUpperCase()}
              </span>
              <span className="hidden sm:block text-left">
                <span className="block text-sm font-semibold">{user.name}</span>
                <span className="block text-xs text-slate-500">{user.email}</span>
              </span>
            </button>

            {isMenuOpen && (
              <div className="absolute right-0 mt-2 w-48 rounded-lg border border-slate-200 bg-white p-2 text-sm shadow-lg">
                <Form method="post" action="/logout">
                  <button
                    type="submit"
                    className="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-slate-700 transition hover:bg-slate-100"
                  >
                    Sign out
                    <span aria-hidden>→</span>
                  </button>
                </Form>
              </div>
            )}
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-4 py-8">
        <Outlet context={{ user }} />
      </main>
    </div>
  );
}
