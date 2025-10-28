import { redirect } from "react-router";
import type { LoaderFunctionArgs } from "./+types/_index";
import { apiFetch } from "../lib/api-client";
import type { AuthUser } from "../lib/auth-storage";

export async function loader({ request }: LoaderFunctionArgs) {
  const url = new URL(request.url);
  try {
    await apiFetch<AuthUser>("/auth/me", {}, request);
    throw redirect("/dashboard");
  } catch {
    throw redirect(`/login${url.search}`);
  }
}

export default function IndexRedirect() {
  return null;
}
