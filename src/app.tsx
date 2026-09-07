import { Navigate, Route, Routes } from "react-router-dom";

import { ErrorBoundary } from "@/components/error-boundary";
import { Layout } from "@/components/layout";
import { CompaniesPage } from "@/pages/companies";
import { ContactsPage } from "@/pages/contacts";
import { DashboardPage } from "@/pages/dashboard";
import { ProductsPage } from "@/pages/products";
import { PurchaseOrdersPage } from "@/pages/purchase-orders";
import { SalesOrdersPage } from "@/pages/sales-orders";
import { WarehousesPage } from "@/pages/warehouses";

export default function App() {
  return (
    <ErrorBoundary>
      <Layout>
        <Routes>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/companies" element={<CompaniesPage />} />
          <Route path="/contacts" element={<ContactsPage />} />
          <Route path="/products" element={<ProductsPage />} />
          <Route path="/warehouses" element={<WarehousesPage />} />
          <Route path="/sales-orders" element={<SalesOrdersPage />} />
          <Route path="/purchase-orders" element={<PurchaseOrdersPage />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </Layout>
    </ErrorBoundary>
  );
}
