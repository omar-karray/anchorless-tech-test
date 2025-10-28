import { Form, redirect, useLoaderData, useNavigation, useRevalidator, useFetcher } from "react-router";
import type { ActionFunctionArgs, LoaderFunctionArgs } from "react-router";
import { apiFetch, apiUpload } from "../lib/api-client";
import { useState, useEffect } from "react";
import { ensureEchoInstance } from "../lib/echo.client";

type VisaApplication = {
  id: number;
  country: string;
  status: string;
  submitted_at: string | null;
};

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
  category: {
    id: number;
    name: string;
    slug: string;
  };
  stored_name: string;
  path: string;
  disk: string;
  created_at: string;
  visa_application: {
    id: number;
    country: string;
    status: string;
  };
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
  console.log('📂 Loader fetched data:', { application, categories, filesCount: files.length });
  return { application, categories, files };
}

export async function action({ params, request }: ActionFunctionArgs) {
  const { id } = params;
  const formData = await request.formData();
  const intent = formData.get("intent");

  if (intent === "update") {
    const country = formData.get("country") as string;
    await apiFetch(
      `/visa-applications/${id}`,
      {
        method: "PUT",
        body: JSON.stringify({ country }),
      },
      request
    );
    return { success: true };
  }

  if (intent === "upload") {
    const file = formData.get("file") as File;
    const file_category_id = formData.get("file_category_id") as string;
    
    const uploadFormData = new FormData();
    uploadFormData.append("file", file);
    uploadFormData.append("file_category_id", file_category_id);

    await apiUpload(
      `/visa-applications/${id}/files`,
      uploadFormData,
      request
    );
    
    return { success: true };
  }

  if (intent === "delete-file") {
    const fileId = formData.get("fileId") as string;
    await apiFetch(
      `/visa-applications/${id}/files/${fileId}`,
      {
        method: "DELETE",
      },
      request
    );
    return { success: true };
  }

  if (intent === "submit") {
    await apiFetch(
      `/visa-applications/${id}/submit`,
      {
        method: "POST",
      },
      request
    );
    return { success: true, submitted: true };
  }

  if (intent === "delete-application") {
    await apiFetch(
      `/visa-applications/${id}`,
      {
        method: "DELETE",
      },
      request
    );
    return redirect("/dashboard");
  }

  return null;
}

export default function EditApplication() {
  const { application, categories, files } = useLoaderData<LoaderData>();
  const nav = useNavigation();
  const revalidator = useRevalidator();
  const fetcher = useFetcher();
  const submitting = nav.state === "submitting";
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [successCategory, setSuccessCategory] = useState<number | null>(null);
  const [dragOverCategory, setDragOverCategory] = useState<number | null>(null);
  const [uploadingCategories, setUploadingCategories] = useState<Set<number>>(new Set());
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showSubmitModal, setShowSubmitModal] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Handle submit
  const handleSubmit = async () => {
    setIsSubmitting(true);
    try {
      await apiFetch(
        `/visa-applications/${application.id}`,
        { 
          method: "PUT",
          body: JSON.stringify({ status: "submitted" })
        },
        undefined as any
      );
      setShowSubmitModal(false);
      // Redirect to view page after submission
      window.location.href = `/dashboard/applications/${application.id}`;
    } catch (error) {
      console.error("Submit failed:", error);
      setUploadError("Failed to submit application. Please try again.");
      setIsSubmitting(false);
    }
  };

  // Group files by category
  const filesByCategory = files.reduce((acc, file) => {
    const categoryId = file.category.id;
    if (!acc[categoryId]) {
      acc[categoryId] = [];
    }
    acc[categoryId].push(file);
    return acc;
  }, {} as Record<number, VisaFile[]>);

  // Check if all categories have at least one file
  const allCategoriesHaveFiles = categories.every(category => {
    const categoryFiles = filesByCategory[category.id];
    return categoryFiles && categoryFiles.length > 0;
  });

  // Show success message and auto-hide after 3 seconds
  const showSuccess = (message: string, categoryId: number) => {
    setSuccessMessage(message);
    setSuccessCategory(categoryId);
    setTimeout(() => {
      setSuccessMessage(null);
      setSuccessCategory(null);
    }, 3000);
  };

  // Auto-submit when file is selected
  const handleFileSelect = async (categoryId: number, file: File) => {
    if (!file) return;

    setUploadingCategories(prev => new Set(prev).add(categoryId));
    setUploadError(null);

    const uploadFormData = new FormData();
    uploadFormData.append("file", file);
    uploadFormData.append("file_category_id", categoryId.toString());

    try {
      await apiUpload(
        `/visa-applications/${application.id}/files`,
        uploadFormData,
        undefined as any
      );
      
      // Don't show success here - wait for WebSocket broadcast
      // The useEffect hook will handle the success message when broadcast arrives
    } catch (error) {
      setUploadError("Failed to upload file. Please try again.");
      setUploadingCategories(prev => {
        const next = new Set(prev);
        next.delete(categoryId);
        return next;
      });
    }
  };

  // Handle drag and drop
  const handleDrop = (e: React.DragEvent, categoryId: number) => {
    e.preventDefault();
    setDragOverCategory(null);
    
    const file = e.dataTransfer.files[0];
    if (file) {
      handleFileSelect(categoryId, file);
    }
  };

  // Listen to WebSocket events for file upload completion
  useEffect(() => {
    let channel: any = null;

    ensureEchoInstance().then((echo) => {
      if (!echo) return;

      channel = echo.private(`visa-applications.${application.id}`);

      // Listen for successful file storage
      channel.listen('VisaApplicantFileStored', (event: any) => {
        showSuccess(`File "${event.file.original_name}" uploaded successfully!`, event.file.category.id);
        setUploadingCategories(prev => {
          const next = new Set(prev);
          next.delete(event.file.category.id);
          return next;
        });
        revalidator.revalidate();
      });

      // Listen for failed file storage
      channel.listen('VisaApplicantFileFailed', (event: any) => {
        setUploadError(`Upload failed: ${event.reason}`);
        if (event.file?.category?.id) {
          setUploadingCategories(prev => {
            const next = new Set(prev);
            next.delete(event.file.category.id);
            return next;
          });
        }
      });
    });

    return () => {
      ensureEchoInstance().then((echo) => {
        if (echo && channel) {
          echo.leave(`visa-applications.${application.id}`);
        }
      });
    };
  }, [application.id, revalidator]);

  // Prevent body scroll when modal is open
  useEffect(() => {
    if (showDeleteModal) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'unset';
    }
    return () => {
      document.body.style.overflow = 'unset';
    };
  }, [showDeleteModal]);

  return (
    <div className="space-y-6">
      {/* Header with Title, Country, Status */}
      <header className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <h2 className="text-2xl font-semibold text-slate-900 mb-2">
              Edit Application #{application.id}
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
                <div className="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium bg-blue-50 text-blue-700 border border-blue-200">
                  {application.status}
                </div>
              </div>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setShowDeleteModal(true)}
              className="inline-flex items-center rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
            >
              <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Delete
            </button>
            <button
              type="button"
              onClick={() => setShowSubmitModal(true)}
              disabled={!allCategoriesHaveFiles || application.status !== 'draft'}
              className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              title={
                application.status !== 'draft' 
                  ? 'Application already submitted' 
                  : !allCategoriesHaveFiles 
                  ? 'Please upload at least one document for each category' 
                  : ''
              }
            >
              {application.status !== 'draft' ? 'Submitted' : 'Submit Application'}
            </button>
          </div>
        </div>
      </header>

      {/* File Upload */}
      <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 className="text-lg font-semibold text-slate-800 mb-4">Required Documents</h3>
        {uploadError && (
          <div className="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
            {uploadError}
          </div>
        )}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {categories.map((category) => {
            const categoryFiles = filesByCategory[category.id] || [];
            const hasFiles = categoryFiles.length > 0;
            const isDragging = dragOverCategory === category.id;
            const isUploading = uploadingCategories.has(category.id);

            return (
              <div 
                key={category.id} 
                className={`rounded-lg border p-4 transition-colors ${
                  isDragging 
                    ? 'border-blue-500 bg-blue-50' 
                    : 'border-slate-200 bg-slate-50'
                }`}
                onDragOver={(e) => {
                  e.preventDefault();
                  setDragOverCategory(category.id);
                }}
                onDragLeave={() => setDragOverCategory(null)}
                onDrop={(e) => handleDrop(e, category.id)}
              >
                <div className="flex items-start justify-between mb-3">
                  <div className="flex-1">
                    <h4 className="text-sm font-semibold text-slate-800">{category.name}</h4>
                    <p className="text-xs text-slate-500 mt-0.5">{category.description}</p>
                  </div>
                </div>

                {/* Success Message for this category */}
                {successMessage && successCategory === category.id && (
                  <div className="mb-3 rounded-md bg-green-50 border border-green-200 p-3 flex items-center animate-fade-in">
                    <svg className="w-4 h-4 text-green-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                    </svg>
                    <p className="text-xs font-medium text-green-800">{successMessage}</p>
                  </div>
                )}

                {/* Files List */}
                {hasFiles ? (
                  <div className="space-y-2 bg-white rounded-md border border-slate-200 p-3 mb-3">
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
                          <Form method="post">
                            <input type="hidden" name="intent" value="delete-file" />
                            <input type="hidden" name="fileId" value={file.id} />
                            <button
                              type="submit"
                              className="ml-3 inline-flex items-center rounded-md border border-red-300 bg-white px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 transition-colors"
                            >
                              Delete
                            </button>
                          </Form>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : null}

                {/* Upload Area */}
                <div className={`rounded-md border-2 border-dashed p-6 text-center transition-colors ${
                  isDragging 
                    ? 'border-blue-400 bg-blue-50' 
                    : isUploading
                    ? 'border-slate-300 bg-slate-100'
                    : 'border-slate-300 bg-white hover:border-slate-400'
                }`}>
                  {isUploading ? (
                    <div className="py-2">
                      <svg className="animate-spin w-10 h-10 text-blue-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      <p className="text-sm font-medium text-slate-700">Uploading...</p>
                    </div>
                  ) : (
                    <>
                      <input
                        type="file"
                        id={`file-${category.id}`}
                        className="hidden"
                        accept=".pdf,.png,.jpg,.jpeg"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) handleFileSelect(category.id, file);
                        }}
                      />
                      <label 
                        htmlFor={`file-${category.id}`}
                        className="cursor-pointer block"
                      >
                        <svg className="w-10 h-10 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p className="text-sm font-medium text-slate-700 mb-1">
                          {isDragging ? 'Drop file here' : 'Click to upload or drag and drop'}
                        </p>
                        <p className="text-xs text-slate-500">
                          PDF, PNG, JPG up to 4MB
                        </p>
                      </label>
                    </>
                  )}
                </div>
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

      {/* Delete Confirmation Modal */}
      {showDeleteModal && (
        <div className="fixed inset-0 z-[100] overflow-hidden">
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-slate-900/40"
            onClick={() => setShowDeleteModal(false)}
          ></div>
          
          {/* Modal */}
          <div className="fixed inset-0 overflow-y-auto pointer-events-none">
            <div className="flex min-h-full items-center justify-center p-4">
              <div className="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 pointer-events-auto">
                <div className="flex items-start">
                <div className="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-100">
                  <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <div className="ml-4 flex-1">
                  <h3 className="text-lg font-semibold text-slate-900 mb-2">
                    Delete Application
                  </h3>
                  <p className="text-sm text-slate-600 mb-4">
                    Are you sure you want to delete this visa application? This action cannot be undone. All uploaded documents will be permanently removed.
                  </p>
                  <div className="flex gap-3 justify-end">
                    <button
                      type="button"
                      onClick={() => setShowDeleteModal(false)}
                      className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                    >
                      Cancel
                    </button>
                    <Form method="post">
                      <input type="hidden" name="intent" value="delete-application" />
                      <button
                        type="submit"
                        className="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
                      >
                        Delete Application
                      </button>
                    </Form>
                  </div>
                </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Submit Confirmation Modal */}
      {showSubmitModal && (
        <div className="fixed inset-0 z-[100] overflow-hidden">
          {/* Backdrop */}
          <div 
            className="fixed inset-0 bg-slate-900/40"
            onClick={() => setShowSubmitModal(false)}
          ></div>
          
          {/* Modal */}
          <div className="fixed inset-0 overflow-y-auto pointer-events-none">
            <div className="flex min-h-full items-center justify-center p-4">
              <div className="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 pointer-events-auto">
                <div className="flex items-start">
                <div className="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-blue-100">
                  <svg className="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="ml-4 flex-1">
                  <h3 className="text-lg font-semibold text-slate-900 mb-2">
                    Submit Application
                  </h3>
                  <p className="text-sm text-slate-600 mb-4">
                    Are you sure you want to submit this visa application? Once submitted, you will not be able to edit or add more documents. The application will be sent for review.
                  </p>
                  <div className="flex gap-3 justify-end">
                    <button
                      type="button"
                      onClick={() => setShowSubmitModal(false)}
                      className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={handleSubmit}
                      disabled={isSubmitting}
                      className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      {isSubmitting ? "Submitting..." : "Submit Application"}
                    </button>
                  </div>
                </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
