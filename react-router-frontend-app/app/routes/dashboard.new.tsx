import { Form, redirect, useNavigation } from "react-router";
import type { ActionFunctionArgs, LoaderFunctionArgs } from "react-router";
import { apiFetch } from "../lib/api-client";

type VisaApplication = {
  id: number;
  country: string;
  status: string;
  submitted_at: string | null;
};

export async function loader({}: LoaderFunctionArgs) {
  return null;
}

export async function action({ request }: ActionFunctionArgs) {
  const form = await request.formData();
  const country = (form.get("country") as string) || "PG";
  const created = await apiFetch<VisaApplication>(
    "/visa-applications",
    {
      method: "POST",
      body: JSON.stringify({ country }),
    },
    request
  );
  // Redirect to edit page to upload documents
  return redirect(`/dashboard/applications/${created.id}/edit`);
}

export default function NewApplication() {
  const nav = useNavigation();
  const submitting = nav.state === "submitting";

  return (
    <div className="mx-auto max-w-md mt-6">
      <header className="text-center mb-6">
        <h2 className="text-2xl font-semibold text-slate-900">New Application</h2>
        <p className="mt-1 text-sm text-slate-500">
          Choose the destination country and create your dossier.
        </p>
      </header>

      <Form
        method="post"
        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
      >
          <label className="block text-sm font-medium text-slate-700">
            Country
            <select
              name="country"
              defaultValue="PG"
              className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
            >
              <option value="PG">Portugal (PG)</option>
              <option value="SP">Spain (SP)</option>
              <option value="IT">Italy (IT)</option>
            </select>
          </label>

          <div className="flex items-center justify-between gap-3 pt-2">
            <a
              href="/dashboard"
              className="inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              Cancel
            </a>
            <button
              type="submit"
              disabled={submitting}
              className="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {submitting ? "Creating…" : "Create Application"}
            </button>
          </div>
        </Form>
    </div>
  );
}

export const handle = {
  title: "New Application",
  breadcrumb: { label: "New", to: "/visa-application/new" },
};
