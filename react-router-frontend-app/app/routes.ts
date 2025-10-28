import { type RouteConfig, index, route } from "@react-router/dev/routes";

export default [
  index("routes/_index.tsx"),
  route("login", "routes/login.tsx"),
  route("logout", "routes/logout.tsx"),
  route("dashboard", "routes/dashboard.tsx", [
    index("routes/dashboard.index.tsx"),
    route("new", "routes/dashboard.new.tsx"),
    route("applications/:id", "routes/dashboard.application.tsx"),
    route("applications/:id/edit", "routes/dashboard.applications.$id.edit.tsx"),
  ]),
  // Stable aliases that redirect into the working dashboard routes (robust for SSR + HMR)
  route("visa-application/new", "routes/va-alias-new.tsx"),
  route("visa-application/:id", "routes/va-alias-id.tsx"),
  // Catch-all for 404 - must be last
  route("*", "routes/$.tsx"),
] satisfies RouteConfig;
