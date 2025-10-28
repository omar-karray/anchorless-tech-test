import { redirect } from "react-router";
import type { LoaderFunctionArgs } from "react-router";

export async function loader({ params }: LoaderFunctionArgs) {
  const id = params.id as string;
  return redirect(`/dashboard/applications/${encodeURIComponent(id)}`);
}

export default function VisaApplicationIdAlias() {
  return null;
}

