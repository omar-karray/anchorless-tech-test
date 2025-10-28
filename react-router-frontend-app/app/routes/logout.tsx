import { useEffect, useState } from "react";
import { redirect } from "react-router";
import type { Route } from "./+types/logout";
import { apiFetch } from "../lib/api-client";
import { clearSession } from "../lib/auth-storage";

// Handle GET requests (direct navigation to /logout)
export async function loader({ request }: Route.LoaderArgs) {
  // Server-side: just call logout API
  if (typeof window === "undefined") {
    try {
      await apiFetch("/logout", { method: "POST" }, request);
    } catch (error) {
      console.error("Logout API error:", error);
    }
    
    const url = new URL(request.url);
    return redirect(`${url.protocol}//${url.host}/login`);
  }
  
  // Client-side: return empty data, component will handle logout
  return { clientLogout: true };
}

// Handle POST requests (form submission)
export async function action({ request }: Route.ActionArgs) {
  // Server-side: just call logout API
  if (typeof window === "undefined") {
    try {
      await apiFetch("/logout", { method: "POST" }, request);
    } catch (error) {
      console.error("Logout API error:", error);
    }
    
    const url = new URL(request.url);
    return redirect(`${url.protocol}//${url.host}/login`);
  }
  
  // Client-side: return empty data, component will handle logout
  return { clientLogout: true };
}

export default function LogoutAction() {
  const [status, setStatus] = useState("Logging out...");
  
  useEffect(() => {
    // Client-side logout - MUST complete before redirect
    const performLogout = async () => {
      try {
        console.log("Starting logout...");
        setStatus("Calling logout API...");
        
        // Call logout and WAIT for it to complete
        await apiFetch("/logout", { method: "POST" });
        
        console.log("Logout API completed");
        setStatus("Clearing session...");
        
        // Clear local storage
        clearSession();
        
        console.log("Session cleared, redirecting...");
        setStatus("Redirecting...");
        
        // Small delay to ensure everything is processed
        await new Promise(resolve => setTimeout(resolve, 200));
        
        // Now redirect with full page load
        window.location.href = "/login";
      } catch (error) {
        console.error("Logout error:", error);
        // Even if logout fails, clear session and redirect
        clearSession();
        window.location.href = "/login";
      }
    };
    
    performLogout();
  }, []);
  
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50">
      <div className="text-center">
        <div className="mb-4 text-lg text-slate-700">{status}</div>
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-blue-500 border-t-transparent mx-auto"></div>
      </div>
    </div>
  );
}
