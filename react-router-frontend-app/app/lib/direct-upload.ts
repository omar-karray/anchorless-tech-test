import { apiFetch } from "./api-client";

const FILE_SIZE_THRESHOLD = 50 * 1024 * 1024; // 50MB
const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks for multipart

type DirectUploadInitResponse = {
  success: boolean;
  data: {
    file_key: string;
    presigned_url: string;
    expiry: string;
  };
  message: string;
};

type MultipartUploadInitResponse = {
  success: boolean;
  data: {
    upload_id: string;
    key: string;
    presigned_urls: Array<{
      part_number: number;
      url: string;
    }>;
    expires_in: number;
  };
};

type UploadCompleteResponse = {
  success: boolean;
  data: {
    file: {
      id: number;
      file_name: string;
      file_size: number;
      mime_type: string;
      created_at: string;
    };
  };
  message: string;
};

type UploadProgressCallback = (progress: {
  loaded: number;
  total: number;
  percentage: number;
}) => void;

/**
 * Upload file directly to MinIO using pre-signed URLs
 * Automatically chooses between direct upload (< 50MB) or multipart (≥ 50MB)
 */
export async function uploadFileDirect(
  file: File,
  visaApplicationId: number,
  fileCategoryId: number,
  onProgress?: UploadProgressCallback
): Promise<UploadCompleteResponse> {
  if (file.size < FILE_SIZE_THRESHOLD) {
    return await uploadDirect(file, visaApplicationId, fileCategoryId, onProgress);
  } else {
    return await uploadMultipart(file, visaApplicationId, fileCategoryId, onProgress);
  }
}

/**
 * Direct upload for files < 50MB
 */
async function uploadDirect(
  file: File,
  visaApplicationId: number,
  fileCategoryId: number,
  onProgress?: UploadProgressCallback
): Promise<UploadCompleteResponse> {
  // 1. Request pre-signed URL
  // NOTE: apiFetch already extracts .data from the response, so initResponse is the data object
  let file_key: string;
  let presigned_url: string;
  
  try {
    const initData = await apiFetch<{
      file_key: string;
      presigned_url: string;
      expiry: string;
    }>(
      `/visa-applications/${visaApplicationId}/files/direct-upload/initiate`,
      {
        method: "POST",
        body: JSON.stringify({
          filename: file.name,
          content_type: file.type,
          file_size: file.size,
        }),
      }
    );

    if (!initData || !initData.file_key || !initData.presigned_url) {
      console.error("Invalid initiate response:", initData);
      throw new Error("Failed to initiate upload - missing required fields");
    }

    file_key = initData.file_key;
    presigned_url = initData.presigned_url;
  } catch (error) {
    console.error("Direct upload initiation failed:", error);
    if (error instanceof Response) {
      const errorData = await error.json();
      console.error("Validation errors:", errorData);
      throw new Error(errorData?.errors?.message || "Failed to initiate upload");
    }
    throw error;
  }

  // 2. Upload to MinIO with progress tracking
  await new Promise<void>((resolve, reject) => {
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener("progress", (e) => {
      if (e.lengthComputable && onProgress) {
        onProgress({
          loaded: e.loaded,
          total: e.total,
          percentage: Math.round((e.loaded / e.total) * 100),
        });
      }
    });

    xhr.addEventListener("load", () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve();
      } else {
        reject(new Error(`Upload failed with status ${xhr.status}`));
      }
    });

    xhr.addEventListener("error", () => {
      reject(new Error("Network error during upload"));
    });

    xhr.open("PUT", presigned_url);
    xhr.setRequestHeader("Content-Type", file.type);
    xhr.send(file);
  });

  // 3. Complete upload
  const completeResponse = await apiFetch<UploadCompleteResponse>(
    `/visa-applications/${visaApplicationId}/files/direct-upload/complete`,
    {
      method: "POST",
      body: JSON.stringify({
        file_key,
        filename: file.name,
        content_type: file.type,
        file_size: file.size,
        file_category_id: fileCategoryId,
      }),
    }
  );

  return completeResponse;
}

/**
 * Multipart upload for files ≥ 50MB
 */
async function uploadMultipart(
  file: File,
  visaApplicationId: number,
  fileCategoryId: number,
  onProgress?: UploadProgressCallback
): Promise<UploadCompleteResponse> {
  const totalParts = Math.ceil(file.size / CHUNK_SIZE);

  // 1. Initiate multipart upload
  // NOTE: apiFetch already extracts .data from the response
  const initData = await apiFetch<{
    upload_id: string;
    key: string;
    presigned_urls: Array<{
      part_number: number;
      url: string;
    }>;
    expires_in: number;
  }>(
    `/visa-applications/${visaApplicationId}/files/multipart/initiate`,
    {
      method: "POST",
      body: JSON.stringify({
        file_category_id: fileCategoryId,
        file_name: file.name,
        file_size: file.size,
        mime_type: file.type,
        total_parts: totalParts,
      }),
    }
  );

  const { upload_id, presigned_urls } = initData;

  // Track progress across all parts
  const partProgress = new Map<number, number>();
  const updateTotalProgress = () => {
    if (!onProgress) return;
    const totalLoaded = Array.from(partProgress.values()).reduce((sum, val) => sum + val, 0);
    onProgress({
      loaded: totalLoaded,
      total: file.size,
      percentage: Math.round((totalLoaded / file.size) * 100),
    });
  };

  // 2. Upload parts in parallel (with concurrency limit)
  const uploadPart = async (partNumber: number, url: string): Promise<{ part_number: number; etag: string }> => {
    const start = (partNumber - 1) * CHUNK_SIZE;
    const end = Math.min(start + CHUNK_SIZE, file.size);
    const chunk = file.slice(start, end);

    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();

      xhr.upload.addEventListener("progress", (e) => {
        if (e.lengthComputable) {
          partProgress.set(partNumber, e.loaded);
          updateTotalProgress();
        }
      });

      xhr.addEventListener("load", () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          const etag = xhr.getResponseHeader("ETag")?.replace(/"/g, "") || "";
          partProgress.set(partNumber, chunk.size); // Mark as fully uploaded
          updateTotalProgress();
          resolve({ part_number: partNumber, etag });
        } else {
          reject(new Error(`Part ${partNumber} upload failed with status ${xhr.status}`));
        }
      });

      xhr.addEventListener("error", () => {
        reject(new Error(`Network error uploading part ${partNumber}`));
      });

      xhr.open("PUT", url);
      xhr.send(chunk);
    });
  };

  // Upload all parts with concurrency limit of 3
  const parts: Array<{ part_number: number; etag: string }> = [];
  const CONCURRENCY = 3;
  
  for (let i = 0; i < presigned_urls.length; i += CONCURRENCY) {
    const batch = presigned_urls.slice(i, i + CONCURRENCY);
    const batchResults = await Promise.all(
      batch.map(({ part_number, url }) => uploadPart(part_number, url))
    );
    parts.push(...batchResults);
  }

  // Sort parts by part_number
  parts.sort((a, b) => a.part_number - b.part_number);

  // 3. Complete multipart upload
  const completeResponse = await apiFetch<UploadCompleteResponse>(
    `/visa-applications/${visaApplicationId}/files/multipart/complete`,
    {
      method: "POST",
      body: JSON.stringify({
        upload_id,
        parts,
      }),
    }
  );

  return completeResponse;
}

/**
 * Abort a multipart upload
 */
export async function abortMultipartUpload(
  visaApplicationId: number,
  uploadId: string
): Promise<void> {
  await apiFetch(
    `/visa-applications/${visaApplicationId}/files/multipart/abort`,
    {
      method: "POST",
      body: JSON.stringify({
        upload_id: uploadId,
      }),
    }
  );
}
