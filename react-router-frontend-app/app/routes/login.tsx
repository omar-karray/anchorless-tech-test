import { Form, useActionData, useNavigation, redirect } from "react-router";
import type { Route } from "./+types/login";
import { apiFetch } from "../lib/api-client";
import type { AuthUser } from "../lib/auth-storage";
import { useEffect, useState } from "react";
import { useNavigate } from "react-router";

type MeResponse = AuthUser;

export async function loader({ request }: Route.LoaderArgs) {
  // If already authenticated via cookies, skip login and go to dashboard
  try {
    await apiFetch<MeResponse>("/auth/me", {}, request);
    const url = new URL(request.url);
    const redirectTo = url.searchParams.get("redirectTo") ?? "/dashboard";
    throw redirect(redirectTo);
  } catch {
    return null;
  }
}

export async function action({ request }: Route.ActionArgs) {
  const formData = await request.formData();
  const payload = Object.fromEntries(formData);
  const url = new URL(request.url);
  const redirectTo = url.searchParams.get("redirectTo") ?? "/dashboard";

  return {
    success: true,
    payload,
    redirectTo,
  } satisfies ActionSuccess;
}

type ActionSuccess = {
  success: true;
  payload: Record<string, FormDataEntryValue>;
  redirectTo: string;
};

type ActionError = {
  error: string;
};

export default function Login() {
  const navigation = useNavigation();
  const actionData = useActionData<ActionSuccess | ActionError | undefined>();
  const navigate = useNavigate();
  const [submitting, setSubmitting] = useState(false);
  const [clientError, setClientError] = useState<string | null>(null);

  function getApiOrigin(): string {
    const base = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? "/api";
    try {
      const u = new URL(base, typeof window === "undefined" ? "http://localhost" : window.location.href);
      return `${u.protocol}//${u.host}`;
    } catch {
      return "";
    }
  }

  function getXsrfFromCookies(): string | null {
    try {
      const map = Object.fromEntries(document.cookie.split(";").map((c) => {
        const [k, ...rest] = c.trim().split("=");
        return [k, rest.join("=")];
      }));
      const raw = map["XSRF-TOKEN"];
      return raw ? decodeURIComponent(raw) : null;
    } catch {
      return null;
    }
  }

  useEffect(() => {
    async function runLogin() {
      if (!actionData || !("success" in actionData) || !actionData.success) return;
      const origin = getApiOrigin();
      const body = JSON.stringify({
        email: actionData.payload["email"],
        password: actionData.payload["password"],
      });

      setSubmitting(true);
      setClientError(null);
      try {
        // Initialize CSRF cookie
        await fetch(`${origin}/sanctum/csrf-cookie`, { credentials: "include" });
        const xsrf = getXsrfFromCookies();
        const res = await fetch(`${origin}/api/login`, {
          method: "POST",
          credentials: "include",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}),
          },
          body,
        });

        if (!res.ok) {
          let msg = "Unable to sign in.";
          try {
            const data = await res.json();
            msg = data?.errors?.message ?? msg;
          } catch {}
          setClientError(msg);
          return;
        }

        const destination = actionData.redirectTo ?? "/dashboard";
        if (typeof window !== "undefined") {
          window.location.replace(destination);
        } else {
          navigate(destination, { replace: true });
        }
      } catch (e) {
        // No-op here; server action will have rendered the error path on next submit
        console.error(e);
      } finally {
        setSubmitting(false);
      }
    }

    void runLogin();
  }, [actionData, navigate]);

  return (
    <main className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
      <div className="w-full max-w-sm space-y-6 rounded-xl border border-slate-200 bg-white p-8 shadow-lg">
        <header className="space-y-1 text-center">
          <h1 className="text-2xl font-semibold text-slate-900">
            Anchorless Portal
          </h1>
          <p className="text-sm text-slate-600">
            Sign in with your team credentials.
          </p>
        </header>

        {(actionData && "error" in actionData && (
          <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
            {actionData.error}
          </p>
        )) || (clientError && (
          <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{clientError}</p>
        ))}

        <Form method="post" className="space-y-4">
          <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
            Email
            <input
              name="email"
              type="email"
              required
              autoComplete="email"
              className="rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
          </label>

          <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
            Password
            <input
              name="password"
              type="password"
              required
              autoComplete="current-password"
              className="rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
          </label>

          <button
            type="submit"
            disabled={navigation.state === "submitting" || submitting}
            className="w-full rounded-lg bg-blue-600 py-2 text-center text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 disabled:opacity-60"
          >
            {navigation.state === "submitting" || submitting ? "Signing in…" : "Sign in"}
          </button>
        </Form>

        <p className="text-center text-xs text-slate-500">
          Need access? Contact your Anchorless administrator.
        </p>
      </div>
    </main>
  );
}
