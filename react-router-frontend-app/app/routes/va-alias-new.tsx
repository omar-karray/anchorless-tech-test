import { redirect } from "react-router";
import type { LoaderFunctionArgs } from "react-router";

export async function loader({}: LoaderFunctionArgs) {
  return redirect("/dashboard/new");
}

export default function VisaApplicationNewAlias() {
  return null;
}

