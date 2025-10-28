import { clearSession } from "./auth-storage";

const DEFAULT_API_BASE = "/api";
function getApiBase(): string {
  const browserBase = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? DEFAULT_API_BASE;
  const serverBase = (import.meta.env.VITE_API_BASE_URL_SERVER as string | undefined) ?? browserBase;
  return typeof window === "undefined" ? serverBase : browserBase;
}

function resolveUrl(input: RequestInfo | URL): RequestInfo | URL {
  if (typeof input !== "string") {
    return input;
  }

  if (input.startsWith("http://") || input.startsWith("https://")) {
    return input;
  }

  const base = getApiBase();
  const trimmedBase = base.endsWith("/") ? base.slice(0, -1) : base;
  const path = input.startsWith("/") ? input : `/${input}`;
  return `${trimmedBase}${path}`;
}

function parseCookie(cookieStr: string | null | undefined): Record<string, string> {
  const out: Record<string, string> = {};
  if (!cookieStr) return out;
  cookieStr.split(";").forEach((pair) => {
    const [rawKey, ...rest] = pair.trim().split("=");
    const key = rawKey?.trim();
    const value = rest.join("=");
    if (key) out[key] = value;
  });
  return out;
}

function getXsrfTokenFromCookies(cookieHeader?: string | null): string | null {
  try {
    if (typeof document !== "undefined") {
      const map = parseCookie(document.cookie);
      const token = map["XSRF-TOKEN"];
      return token ? decodeURIComponent(token) : null;
    }
  } catch {
    // ignore DOM cookie parsing errors
  }

  if (cookieHeader) {
    const map = parseCookie(cookieHeader);
    const token = map["XSRF-TOKEN"];
    return token ? decodeURIComponent(token) : null;
  }
  return null;
}

type ApiSuccessEnvelope<T> = {
  data: T;
  errors: null;
};

type ApiErrorEnvelope = {
  data: null;
  errors: {
    message: string;
    details?: Record<string, unknown>;
  };
};

export async function apiFetch<T>(
  input: RequestInfo | URL,
  init: RequestInit = {},
  ssrRequest?: Request
): Promise<T> {
  const cookieHeader = ssrRequest?.headers?.get?.("cookie") ?? undefined;
  const xsrf = getXsrfTokenFromCookies(cookieHeader);
  const originFromSSR = (() => {
    if (!ssrRequest) return undefined;
    const h = ssrRequest.headers?.get?.("origin");
    if (h) return h;
    try {
      const u = new URL(ssrRequest.url);
      return `${u.protocol}//${u.host}`;
    } catch {
      return undefined;
    }
  })();

  const headers: HeadersInit = {
    Accept: "application/json",
    "Content-Type": "application/json",
    ...(init.headers || {}),
    ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}),
    ...(originFromSSR ? { Origin: originFromSSR, Referer: originFromSSR } : {}),
  };

  // Forward incoming cookies during SSR so the backend can auth
  if (cookieHeader && typeof window === "undefined") {
    (headers as Record<string, string>)["Cookie"] = cookieHeader;
  }

  const response = await fetch(resolveUrl(input), {
    credentials: "include",
    ...init,
    headers,
  });

  if (response.status === 204) {
    return null as T;
  }

  const contentType = response.headers.get("Content-Type") ?? "";
  const isJson = contentType.includes("application/json");
  const payload = isJson ? await response.json() : null;

  if (!response.ok || (payload && payload.errors)) {
    if (response.status === 401) {
      clearSession();
    }

    throw new Response(JSON.stringify(payload), {
      status: response.status,
      statusText: response.statusText,
    });
  }

  return (payload as ApiSuccessEnvelope<T>).data;
}

/**
 * Upload file with FormData (multipart/form-data)
 * Don't set Content-Type header - let browser set it with boundary
 */
export async function apiUpload<T>(
  input: RequestInfo | URL,
  formData: FormData,
  ssrRequest?: Request
): Promise<T> {
  const cookieHeader = ssrRequest?.headers?.get?.("cookie") ?? undefined;
  const xsrf = getXsrfTokenFromCookies(cookieHeader);
  const originFromSSR = (() => {
    if (!ssrRequest) return undefined;
    const h = ssrRequest.headers?.get?.("origin");
    if (h) return h;
    try {
      const u = new URL(ssrRequest.url);
      return `${u.protocol}//${u.host}`;
    } catch {
      return undefined;
    }
  })();

  const headers: HeadersInit = {
    Accept: "application/json",
    ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}),
    ...(originFromSSR ? { Origin: originFromSSR, Referer: originFromSSR } : {}),
  };

  // Forward incoming cookies during SSR so the backend can auth
  if (cookieHeader && typeof window === "undefined") {
    (headers as Record<string, string>)["Cookie"] = cookieHeader;
  }

  const response = await fetch(resolveUrl(input), {
    method: "POST",
    credentials: "include",
    headers,
    body: formData,
  });

  if (response.status === 204) {
    return null as T;
  }

  const contentType = response.headers.get("Content-Type") ?? "";
  const isJson = contentType.includes("application/json");
  const payload = isJson ? await response.json() : null;

  if (!response.ok || (payload && payload.errors)) {
    if (response.status === 401) {
      clearSession();
    }

    throw new Response(JSON.stringify(payload), {
      status: response.status,
      statusText: response.statusText,
    });
  }

  return (payload as ApiSuccessEnvelope<T>).data;
}
