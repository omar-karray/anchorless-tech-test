import type { LoaderFunctionArgs } from "react-router";
import { useLoaderData } from "react-router";
import { apiFetch } from "../lib/api-client";

type FileCategory = {
  id: number;
  name: string;
  slug: string;
  description: string;
};

type VisaFile = {
  id: number;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  created_at: string;
  category: {
    id: number;
    name: string;
    slug: string;
  };
  stored_name: string;
  path: string;
  disk: string;
  visa_application: {
    id: number;
    country: string;
    status: string;
  };
};

type VisaApplication = {
  id: number;
  country: string;
  status: string;
  submitted_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

type LoaderData = {
  application: VisaApplication;
  categories: FileCategory[];
  files: VisaFile[];
};

export async function loader({ params, request }: LoaderFunctionArgs) {
  const { id } = params;
  const [application, categories, files] = await Promise.all([
    apiFetch<VisaApplication>(`/visa-applications/${id}`, {}, request),
    apiFetch<FileCategory[]>("/file-categories", {}, request),
    apiFetch<VisaFile[]>(`/visa-applications/${id}/files`, {}, request),
  ]);
  return { application, categories, files };
}

export default function ApplicationDetail() {
  const { application, categories, files } = useLoaderData<LoaderData>();

  // Group files by category
  const filesByCategory = files.reduce((acc, file) => {
    const categoryId = file.category.id;
    if (!acc[categoryId]) {
      acc[categoryId] = [];
    }
    acc[categoryId].push(file);
    return acc;
  }, {} as Record<number, VisaFile[]>);

  return (
    <div className="space-y-6">
      {/* Header with Title, Country, Status */}
      <header className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <h2 className="text-2xl font-semibold text-slate-900 mb-2">
              Application #{application.id}
            </h2>
            <div className="flex items-center gap-4 mt-3">
              <div className="flex items-center gap-2">
                <span className="text-sm font-medium text-slate-700">Country:</span>
                <div className="inline-flex items-center rounded-md border border-slate-300 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-700">
                  {application.country === 'PG' && '🇵🇹 Portugal'}
                  {application.country === 'SP' && '🇪🇸 Spain'}
                  {application.country === 'IT' && '🇮🇹 Italy'}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-sm font-medium text-slate-700">Status:</span>
                <div className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium border ${
                  application.status === 'submitted' 
                    ? 'bg-green-50 text-green-700 border-green-200' 
                    : 'bg-blue-50 text-blue-700 border-blue-200'
                }`}>
                  {application.status}
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      {/* Documents Section */}
      <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 className="text-lg font-semibold text-slate-800 mb-4">Documents</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {categories.map((category) => {
            const categoryFiles = filesByCategory[category.id] || [];
            const hasFiles = categoryFiles.length > 0;

            return (
              <div 
                key={category.id} 
                className="rounded-lg border border-slate-200 bg-slate-50 p-4"
              >
                <div className="flex items-start justify-between mb-3">
                  <div className="flex-1">
                    <h4 className="text-sm font-semibold text-slate-800">{category.name}</h4>
                    <p className="text-xs text-slate-500 mt-0.5">{category.description}</p>
                  </div>
                </div>

                {/* Files List */}
                {hasFiles ? (
                  <div className="space-y-2 bg-white rounded-md border border-slate-200 p-3">
                    <p className="text-xs font-medium text-slate-600 mb-2">
                      {categoryFiles.length} file{categoryFiles.length !== 1 ? 's' : ''} uploaded
                    </p>
                    <ul className="space-y-2">
                      {categoryFiles.map((file) => (
                        <li key={file.id} className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 border border-slate-200">
                          <div className="flex-1 min-w-0 flex items-center">
                            <svg className="w-5 h-5 text-blue-600 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div className="flex-1 min-w-0">
                              <p className="text-sm font-medium text-slate-900 truncate">{file.original_name}</p>
                              <p className="text-xs text-slate-500">
                                {(file.size_bytes / 1024).toFixed(2)} KB
                              </p>
                            </div>
                          </div>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : (
                  <div className="text-center py-4 text-sm text-slate-500 bg-white rounded-md border border-slate-200">
                    No files uploaded
                  </div>
                )}
              </div>
            );
          })}
        </div>
        <div className="mt-6 pt-4 border-t border-slate-200">
          <a
            href="/dashboard"
            className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
          >
            ← Back to Dashboard
          </a>
        </div>
      </section>
    </div>
  );
}

export const handle = {
  title: (m: any) => `Application #${m.params.id}`,
  breadcrumb: (m: any) => ({ label: `#${m.params.id}`, to: `/visa-application/${m.params.id}` }),
};
