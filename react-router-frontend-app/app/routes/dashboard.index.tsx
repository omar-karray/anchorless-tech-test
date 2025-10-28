import { useOutletContext } from "react-router";
import type { AuthUser } from "../lib/auth-storage";
import { apiFetch } from "../lib/api-client";
import { useEffect, useState } from "react";

type DashboardOutletContext = {
  user: AuthUser;
};

type VisaApp = {
  id: number;
  country: string;
  status: string;
  submitted_at: string | null;
};

export default function DashboardHome() {
  const { user } = useOutletContext<DashboardOutletContext>();
  const [apps, setApps] = useState<VisaApp[] | null>(null);
  const [appsError, setAppsError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    apiFetch<VisaApp[]>("/visa-applications")
      .then((data) => {
        if (!cancelled) setApps(data);
      })
      .catch(() => {
        if (!cancelled) setAppsError("Failed to load applications");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <section className="space-y-6">
      <header>
        <h2 className="text-2xl font-semibold text-slate-900">
          Welcome back, {user.name.split(" ")[0]}.
        </h2>
        <p className="text-sm text-slate-500">
          Track visa applications, monitor required documents, and receive live updates as your team collaborates.
        </p>
      </header>

      <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-base font-semibold text-slate-800">Your Visa Applications</h3>
          <a
            href="/dashboard/new"
            className="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-700"
          >
            New Application
          </a>
        </div>
        {appsError && (
          <p className="text-sm text-red-600">{appsError}</p>
        )}
        {apps && apps.length > 0 ? (
          <ul className="divide-y divide-slate-200">
            {apps.map((app) => (
              <li key={app.id} className="flex items-center justify-between py-3">
                <div>
                  <p className="text-sm font-medium text-slate-900 flex items-center gap-2">
                    <span className="inline-flex h-6 w-8 items-center justify-center rounded bg-slate-100 text-slate-700 text-[11px] font-semibold">{app.country}</span>
                    <span>Application #{app.id}</span>
                  </p>
                  <p className="mt-1 text-xs text-slate-500 flex items-center gap-2">
                    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${app.status === 'submitted' ? 'bg-green-100 text-green-700' : app.status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700'}`}>{app.status}</span>
                    {app.submitted_at ? <span>Submitted {new Date(app.submitted_at).toLocaleDateString()}</span> : null}
                  </p>
                </div>
                <div className="flex gap-2">
                  {app.status === 'draft' && (
                    <a href={`/dashboard/applications/${app.id}/edit`} className="inline-flex items-center rounded-md border border-blue-300 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100">Edit</a>
                  )}
                  <a href={`/visa-application/${app.id}`} className="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">View</a>
                </div>
              </li>
            ))}
          </ul>
        ) : (
          <div className="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
            {apps ? (
              <>No applications yet. Click "New Application" to get started.</>
            ) : (
              <>Loading applications…</>
            )}
          </div>
        )}
      </section>
    </section>
  );
}
