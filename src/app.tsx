import { lazy, Suspense } from "react";
import { Navigate, Outlet, Route, Routes } from "react-router-dom";

import { AuthProvider } from "@/components/auth-provider";
import { ErrorBoundary } from "@/components/error-boundary";
import { Layout } from "@/components/layout";
import { RequireAuth } from "@/components/require-auth";
import { LoadingState } from "@/components/ui/states";

// Route-level code splitting keeps the initial bundle small; each page is only
// fetched when a user actually navigates to it.
const LoginPage = lazy(() =>
  import("@/pages/login").then((module) => ({ default: module.LoginPage })),
);
const DashboardPage = lazy(() =>
  import("@/pages/dashboard").then((module) => ({ default: module.DashboardPage })),
);
const SmartDashboardPage = lazy(() =>
  import("@/pages/smart-dashboard").then((module) => ({ default: module.SmartDashboardPage })),
);
const HealthPage = lazy(() =>
  import("@/pages/health").then((module) => ({ default: module.HealthPage })),
);
const ReceivablesPage = lazy(() =>
  import("@/pages/receivables").then((module) => ({ default: module.ReceivablesPage })),
);
const ReportsPage = lazy(() =>
  import("@/pages/reports").then((module) => ({ default: module.ReportsPage })),
);
const PosPage = lazy(() => import("@/pages/pos").then((module) => ({ default: module.PosPage })));
const VerticalsPage = lazy(() =>
  import("@/pages/verticals").then((module) => ({ default: module.VerticalsPage })),
);
const CompaniesPage = lazy(() =>
  import("@/pages/companies").then((module) => ({ default: module.CompaniesPage })),
);
const ContactsPage = lazy(() =>
  import("@/pages/contacts").then((module) => ({ default: module.ContactsPage })),
);
const ProductsPage = lazy(() =>
  import("@/pages/products").then((module) => ({ default: module.ProductsPage })),
);
const WarehousesPage = lazy(() =>
  import("@/pages/warehouses").then((module) => ({ default: module.WarehousesPage })),
);
const SalesOrdersPage = lazy(() =>
  import("@/pages/sales-orders").then((module) => ({ default: module.SalesOrdersPage })),
);
const PurchaseOrdersPage = lazy(() =>
  import("@/pages/purchase-orders").then((module) => ({ default: module.PurchaseOrdersPage })),
);
const NotificationsPage = lazy(() =>
  import("@/pages/notifications").then((module) => ({ default: module.NotificationsPage })),
);
const SettingsPage = lazy(() =>
  import("@/pages/settings").then((module) => ({ default: module.SettingsPage })),
);

/** Wraps every authenticated route in the app shell (sidebar + main). */
function AppShell() {
  return (
    <RequireAuth>
      <Layout>
        <Suspense fallback={<LoadingState label="Loading page…" />}>
          <Outlet />
        </Suspense>
      </Layout>
    </RequireAuth>
  );
}

export default function App() {
  return (
    <ErrorBoundary>
      <AuthProvider>
        <Suspense fallback={<LoadingState label="Loading page…" />}>
          <Routes>
            {/* Public: the sign-in screen sits outside the app shell. */}
            <Route path="/login" element={<LoginPage />} />

            {/* Everything below requires a session. */}
            <Route element={<AppShell />}>
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/smart-dashboard" element={<SmartDashboardPage />} />
              <Route path="/health" element={<HealthPage />} />
              <Route path="/receivables" element={<ReceivablesPage />} />
              <Route path="/reports" element={<ReportsPage />} />
              <Route path="/pos" element={<PosPage />} />
              <Route path="/verticals" element={<VerticalsPage />} />
              <Route path="/companies" element={<CompaniesPage />} />
              <Route path="/contacts" element={<ContactsPage />} />
              <Route path="/products" element={<ProductsPage />} />
              <Route path="/warehouses" element={<WarehousesPage />} />
              <Route path="/sales-orders" element={<SalesOrdersPage />} />
              <Route path="/purchase-orders" element={<PurchaseOrdersPage />} />
              <Route path="/notifications" element={<NotificationsPage />} />
              <Route path="/settings" element={<SettingsPage />} />
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Route>
          </Routes>
        </Suspense>
      </AuthProvider>
    </ErrorBoundary>
  );
}
