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
const AuditLogsPage = lazy(() =>
  import("@/pages/audit-logs").then((module) => ({ default: module.AuditLogsPage })),
);
const ProfilePage = lazy(() =>
  import("@/pages/profile").then((module) => ({ default: module.ProfilePage })),
);
const ForgotPasswordPage = lazy(() =>
  import("@/pages/forgot-password").then((module) => ({ default: module.ForgotPasswordPage })),
);
const ResetPasswordPage = lazy(() =>
  import("@/pages/reset-password").then((module) => ({ default: module.ResetPasswordPage })),
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
const InvoicesPage = lazy(() =>
  import("@/pages/invoices").then((module) => ({ default: module.InvoicesPage })),
);
const PaymentsPage = lazy(() =>
  import("@/pages/payments").then((module) => ({ default: module.PaymentsPage })),
);
const QuotationsPage = lazy(() =>
  import("@/pages/quotations").then((module) => ({ default: module.QuotationsPage })),
);
const CreditNotesPage = lazy(() =>
  import("@/pages/credit-notes").then((module) => ({ default: module.CreditNotesPage })),
);
const DebitNotesPage = lazy(() =>
  import("@/pages/debit-notes").then((module) => ({ default: module.DebitNotesPage })),
);
const PurchaseReceiptsPage = lazy(() =>
  import("@/pages/purchase-receipts").then((module) => ({ default: module.PurchaseReceiptsPage })),
);
const ChartOfAccountsPage = lazy(() =>
  import("@/pages/chart-of-accounts").then((module) => ({ default: module.ChartOfAccountsPage })),
);
const JournalEntriesPage = lazy(() =>
  import("@/pages/journal-entries").then((module) => ({ default: module.JournalEntriesPage })),
);
const ExpensesPage = lazy(() =>
  import("@/pages/expenses").then((module) => ({ default: module.ExpensesPage })),
);
const IncomePage = lazy(() =>
  import("@/pages/income").then((module) => ({ default: module.IncomePage })),
);
const BankAccountsPage = lazy(() =>
  import("@/pages/bank-accounts").then((module) => ({ default: module.BankAccountsPage })),
);
const BankTransactionsPage = lazy(() =>
  import("@/pages/bank-transactions").then((module) => ({ default: module.BankTransactionsPage })),
);
const ReconciliationsPage = lazy(() =>
  import("@/pages/reconciliations").then((module) => ({ default: module.ReconciliationsPage })),
);
const LeadsPage = lazy(() =>
  import("@/pages/leads").then((module) => ({ default: module.LeadsPage })),
);
const DealsPage = lazy(() =>
  import("@/pages/deals").then((module) => ({ default: module.DealsPage })),
);
const EmployeesPage = lazy(() =>
  import("@/pages/employees").then((module) => ({ default: module.EmployeesPage })),
);
const DepartmentsPage = lazy(() =>
  import("@/pages/departments").then((module) => ({ default: module.DepartmentsPage })),
);
const DesignationsPage = lazy(() =>
  import("@/pages/designations").then((module) => ({ default: module.DesignationsPage })),
);
const AttendancePage = lazy(() =>
  import("@/pages/attendance").then((module) => ({ default: module.AttendancePage })),
);
const LeavesPage = lazy(() =>
  import("@/pages/leaves").then((module) => ({ default: module.LeavesPage })),
);
const ProjectsPage = lazy(() =>
  import("@/pages/projects").then((module) => ({ default: module.ProjectsPage })),
);
const ProjectTasksPage = lazy(() =>
  import("@/pages/project-tasks").then((module) => ({ default: module.ProjectTasksPage })),
);
const StockAdjustmentsPage = lazy(() =>
  import("@/pages/stock-adjustments").then((module) => ({ default: module.StockAdjustmentsPage })),
);
const StockTransfersPage = lazy(() =>
  import("@/pages/stock-transfers").then((module) => ({ default: module.StockTransfersPage })),
);
const UsersPage = lazy(() =>
  import("@/pages/users").then((module) => ({ default: module.UsersPage })),
);
const RolesPage = lazy(() =>
  import("@/pages/roles").then((module) => ({ default: module.RolesPage })),
);
const TicketsPage = lazy(() =>
  import("@/pages/tickets").then((module) => ({ default: module.TicketsPage })),
);
const AssetsPage = lazy(() =>
  import("@/pages/assets").then((module) => ({ default: module.AssetsPage })),
);
const BudgetsPage = lazy(() =>
  import("@/pages/budgets").then((module) => ({ default: module.BudgetsPage })),
);
const GoalsPage = lazy(() =>
  import("@/pages/goals").then((module) => ({ default: module.GoalsPage })),
);
const TrainingsPage = lazy(() =>
  import("@/pages/trainings").then((module) => ({ default: module.TrainingsPage })),
);
const NoticesPage = lazy(() =>
  import("@/pages/notices").then((module) => ({ default: module.NoticesPage })),
);
const AnnouncementsPage = lazy(() =>
  import("@/pages/announcements").then((module) => ({ default: module.AnnouncementsPage })),
);
const TodosPage = lazy(() =>
  import("@/pages/todos").then((module) => ({ default: module.TodosPage })),
);
const EventsPage = lazy(() =>
  import("@/pages/events").then((module) => ({ default: module.EventsPage })),
);
const DocumentsPage = lazy(() =>
  import("@/pages/documents").then((module) => ({ default: module.DocumentsPage })),
);
const FormsPage = lazy(() =>
  import("@/pages/forms").then((module) => ({ default: module.FormsPage })),
);
const ProposalsPage = lazy(() =>
  import("@/pages/proposals").then((module) => ({ default: module.ProposalsPage })),
);
const AwardsPage = lazy(() =>
  import("@/pages/awards").then((module) => ({ default: module.AwardsPage })),
);
const CoworkingPage = lazy(() =>
  import("@/pages/coworking").then((module) => ({ default: module.CoworkingPage })),
);
const LaundryPage = lazy(() =>
  import("@/pages/laundry").then((module) => ({ default: module.LaundryPage })),
);
const GymPage = lazy(() =>
  import("@/pages/gym").then((module) => ({ default: module.GymPage })),
);
const RentalPage = lazy(() =>
  import("@/pages/rental").then((module) => ({ default: module.RentalPage })),
);
const SalesTargetsPage = lazy(() =>
  import("@/pages/sales-targets").then((module) => ({ default: module.SalesTargetsPage })),
);
const SalesReturnsPage = lazy(() =>
  import("@/pages/sales-returns").then((module) => ({ default: module.SalesReturnsPage })),
);
const PerformanceReviewsPage = lazy(() =>
  import("@/pages/performance-reviews").then((module) => ({ default: module.PerformanceReviewsPage })),
);
const JobPostingsPage = lazy(() =>
  import("@/pages/job-postings").then((module) => ({ default: module.JobPostingsPage })),
);
const JobCandidatesPage = lazy(() =>
  import("@/pages/job-candidates").then((module) => ({ default: module.JobCandidatesPage })),
);
const ConversationsPage = lazy(() =>
  import("@/pages/conversations").then((module) => ({ default: module.ConversationsPage })),
);
const KbArticlesPage = lazy(() =>
  import("@/pages/kb-articles").then((module) => ({ default: module.KbArticlesPage })),
);
const CompanyPoliciesPage = lazy(() =>
  import("@/pages/company-policies").then((module) => ({ default: module.CompanyPoliciesPage })),
);
const TimesheetsPage = lazy(() =>
  import("@/pages/timesheets").then((module) => ({ default: module.TimesheetsPage })),
);
const TimeEntriesPage = lazy(() =>
  import("@/pages/time-entries").then((module) => ({ default: module.TimeEntriesPage })),
);
const CommissionsPage = lazy(() =>
  import("@/pages/commissions").then((module) => ({ default: module.CommissionsPage })),
);
const NotesPage = lazy(() =>
  import("@/pages/notes").then((module) => ({ default: module.NotesPage })),
);
const BookingsPage = lazy(() =>
  import("@/pages/bookings").then((module) => ({ default: module.BookingsPage })),
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
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password" element={<ResetPasswordPage />} />

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
              <Route path="/invoices" element={<InvoicesPage />} />
              <Route path="/payments" element={<PaymentsPage />} />
              <Route path="/quotations" element={<QuotationsPage />} />
              <Route path="/credit-notes" element={<CreditNotesPage />} />
              <Route path="/debit-notes" element={<DebitNotesPage />} />
              <Route path="/purchase-receipts" element={<PurchaseReceiptsPage />} />
              <Route path="/chart-of-accounts" element={<ChartOfAccountsPage />} />
              <Route path="/journal-entries" element={<JournalEntriesPage />} />
              <Route path="/expenses" element={<ExpensesPage />} />
              <Route path="/income" element={<IncomePage />} />
              <Route path="/bank-accounts" element={<BankAccountsPage />} />
              <Route path="/bank-transactions" element={<BankTransactionsPage />} />
              <Route path="/reconciliations" element={<ReconciliationsPage />} />
              <Route path="/leads" element={<LeadsPage />} />
              <Route path="/deals" element={<DealsPage />} />
              <Route path="/employees" element={<EmployeesPage />} />
              <Route path="/departments" element={<DepartmentsPage />} />
              <Route path="/designations" element={<DesignationsPage />} />
              <Route path="/attendance" element={<AttendancePage />} />
              <Route path="/leaves" element={<LeavesPage />} />
              <Route path="/projects" element={<ProjectsPage />} />
              <Route path="/project-tasks" element={<ProjectTasksPage />} />
              <Route path="/stock-adjustments" element={<StockAdjustmentsPage />} />
              <Route path="/stock-transfers" element={<StockTransfersPage />} />
              <Route path="/users" element={<UsersPage />} />
              <Route path="/roles" element={<RolesPage />} />
              <Route path="/tickets" element={<TicketsPage />} />
              <Route path="/assets" element={<AssetsPage />} />
              <Route path="/budgets" element={<BudgetsPage />} />
              <Route path="/goals" element={<GoalsPage />} />
              <Route path="/trainings" element={<TrainingsPage />} />
              <Route path="/notices" element={<NoticesPage />} />
              <Route path="/announcements" element={<AnnouncementsPage />} />
              <Route path="/todos" element={<TodosPage />} />
              <Route path="/events" element={<EventsPage />} />
              <Route path="/documents" element={<DocumentsPage />} />
              <Route path="/forms" element={<FormsPage />} />
              <Route path="/proposals" element={<ProposalsPage />} />
              <Route path="/awards" element={<AwardsPage />} />
              <Route path="/coworking" element={<CoworkingPage />} />
              <Route path="/laundry" element={<LaundryPage />} />
              <Route path="/gym" element={<GymPage />} />
              <Route path="/rental" element={<RentalPage />} />
              <Route path="/sales-targets" element={<SalesTargetsPage />} />
              <Route path="/sales-returns" element={<SalesReturnsPage />} />
              <Route path="/performance-reviews" element={<PerformanceReviewsPage />} />
              <Route path="/job-postings" element={<JobPostingsPage />} />
              <Route path="/job-candidates" element={<JobCandidatesPage />} />
              <Route path="/conversations" element={<ConversationsPage />} />
              <Route path="/kb-articles" element={<KbArticlesPage />} />
              <Route path="/company-policies" element={<CompanyPoliciesPage />} />
              <Route path="/timesheets" element={<TimesheetsPage />} />
              <Route path="/time-entries" element={<TimeEntriesPage />} />
              <Route path="/commissions" element={<CommissionsPage />} />
              <Route path="/notes" element={<NotesPage />} />
              <Route path="/bookings" element={<BookingsPage />} />
              <Route path="/audit-logs" element={<AuditLogsPage />} />
              <Route path="/profile" element={<ProfilePage />} />
              <Route path="/settings" element={<SettingsPage />} />
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Route>
          </Routes>
        </Suspense>
      </AuthProvider>
    </ErrorBoundary>
  );
}




