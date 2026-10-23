import { logger } from "@/lib/logger";
import type {
  AdvisorReport,
  AdvisorSnapshot,
  Addon,
  AgingResponse,
  AnalyticsEnvelope,
  AnalyticsTab,
  AppNotification,
  BalanceSheetResponse,
  BookingSummary,
  CashFlowResponse,
  Company,
  Contact,
  CoworkingBookingSummary,
  DashboardCrmSummary,
  DashboardProjectSummary,
  DashboardSummary,
  ExecutiveData,
  FinancialYearSales,
  FinancialData,
  GymMemberSummary,
  HrmSummaryResponse,
  LaundryOrderSummary,
  NotificationListResponse,
  OperationsData,
  PaginatedResponse,
  PaymentDueResponse,
  PendingShipmentRow,
  PosSummaryResponse,
  Product,
  ProductCategory,
  ProfitAndLossResponse,
  PurchaseOrder,
  RentalAgreementSummary,
  SalesByCategoryResponse,
  SalesByProductResponse,
  SalesData,
  SalesOrder,
  SalesOrderRow,
  SettingsGroupResponse,
  SettingsResponse,
  StockAlertResponse,
  TeamWorkloadRow,
  TeamWorkloadSummary,
  User,
  VatSummaryResponse,
  Warehouse,
} from "@/types";

// Empty by default so browser requests are same-origin: the Vite dev server
// proxies /api to localhost:8000 and the Nginx image proxies it to the API
// container. Set VITE_API_URL at build time for a split-origin deployment.
const API_BASE = (import.meta.env.VITE_API_URL ?? "").replace(/\/+$/, "");

export class ApiError extends Error {
  status: number;
  data: unknown;

  constructor(status: number, message: string, data?: unknown) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.data = data;
  }
}

/** Reads `company_id` off the authenticated user, the common requirement for every scoped feed. */
export function requireCompanyId(user: User | null | undefined): number {
  const id = user?.company?.id;
  if (!id) throw new Error("No company is linked to this account yet.");
  return id;
}

function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem("auth_token");
}

function setToken(token: string): void {
  localStorage.setItem("auth_token", token);
}

function removeToken(): void {
  localStorage.removeItem("auth_token");
}

/**
 * Called by the auth layer when an authenticated request is rejected with 401,
 * so the session can be dropped without every call site handling it. Registered
 * from `@/lib/auth` to avoid a circular import.
 */
export type UnauthorizedHandler = () => void;

let unauthorizedHandler: UnauthorizedHandler | null = null;

export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  unauthorizedHandler = handler;
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
    ...((options.headers as Record<string, string>) ?? {}),
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  logger.debug(`Request ${options.method ?? "GET"} ${path}`, { params: options.body });

  const res = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
    credentials: "include",
  });

  if (res.status === 204) {
    logger.debug(`Response 204 ${path}`);
    return null as T;
  }

  const body = await res.json();

  if (!res.ok) {
    logger.error(`Request failed ${res.status} ${path}`, { message: body.message });
    if (res.status === 401) {
      unauthorizedHandler?.();
    }
    throw new ApiError(res.status, body.message ?? "Request failed", body);
  }

  logger.debug(`Response ${res.status} ${path}`);
  return body;
}

// ─── Auth (Laravel Passport OAuth2 password grant) ─────

export async function login(
  email: string,
  password: string,
): Promise<{ user: User; token: string; refreshToken: string }> {
  const data = await request<{
    user: User;
    access_token: string;
    refresh_token: string;
  }>("/api/v1/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
  setToken(data.access_token);
  return {
    user: data.user,
    token: data.access_token,
    refreshToken: data.refresh_token,
  };
}

export async function logout(): Promise<void> {
  await request("/api/v1/logout", { method: "POST" });
  removeToken();
}

export async function getUser(): Promise<User> {
  return request<User>("/api/v1/user");
}

// ─── Generic CRUD helper ──────────────────────────────

function createCrud<T extends { id: number }>(resource: string) {
  const base = `/api/v1/${resource}`;
  return {
    list(params?: Record<string, string | number>) {
      const qs = params
        ? "?" + new URLSearchParams(params as Record<string, string>).toString()
        : "";
      return request<PaginatedResponse<T>>(`${base}${qs}`);
    },
    get(id: number) {
      return request<T>(`${base}/${id}`);
    },
    create(data: Record<string, unknown>) {
      return request<T>(base, {
        method: "POST",
        body: JSON.stringify(data),
      });
    },
    update(id: number, data: Record<string, unknown>) {
      return request<T>(`${base}/${id}`, {
        method: "PUT",
        body: JSON.stringify(data),
      });
    },
    remove(id: number) {
      return request<null>(`${base}/${id}`, { method: "DELETE" });
    },
  };
}

// ─── Resources ────────────────────────────────────────

export const companies = createCrud<Company>("companies");
export const contacts = createCrud<Contact>("contacts");
export const productCategories = createCrud<ProductCategory>("product-categories");
export const products = createCrud<Product>("products");
export const warehouses = createCrud<Warehouse>("warehouses");
export const salesOrders = createCrud<SalesOrder>("sales-orders");
export const purchaseOrders = createCrud<PurchaseOrder>("purchase-orders");

// --- Notifications -------------------------------------

export function getNotifications(
  params: { unread?: boolean; perPage?: number } = {},
): Promise<NotificationListResponse> {
  const search = new URLSearchParams();
  if (params.unread) search.set("unread", "1");
  if (params.perPage) search.set("per_page", String(params.perPage));
  const qs = search.toString();
  return request<NotificationListResponse>(`/api/v1/notifications${qs ? `?${qs}` : ""}`);
}

export function getUnreadNotificationCount(): Promise<{ unread_count: number }> {
  return request<{ unread_count: number }>("/api/v1/notifications/unread-count");
}

export function markAllNotificationsRead(): Promise<{ updated: number }> {
  return request<{ updated: number }>("/api/v1/notifications/read-all", { method: "POST" });
}

export function markNotificationRead(id: number): Promise<{ data: AppNotification }> {
  return request<{ data: AppNotification }>(`/api/v1/notifications/${id}`, {
    method: "PUT",
    body: JSON.stringify({ is_read: true }),
  });
}

// ─── Health ───────────────────────────────────────────

export function healthCheck() {
  return request<{ status: string }>("/api/health");
}

// ─── Settings ─────────────────────────────────────────

export function getSettings(): Promise<SettingsResponse> {
  return request<SettingsResponse>("/api/v1/settings");
}

export function updateSettingsGroup(
  group: string,
  values: Record<string, unknown>,
): Promise<SettingsGroupResponse> {
  return request<SettingsGroupResponse>(`/api/v1/settings/${group}`, {
    method: "PUT",
    body: JSON.stringify(values),
  });
}

export function clearSettingsCache(): Promise<{ message: string; cache_size: string }> {
  return request<{ message: string; cache_size: string }>("/api/v1/settings/cache/clear", {
    method: "POST",
  });
}

export function sendTestEmail(email: string): Promise<{ message: string }> {
  return request<{ message: string }>("/api/v1/settings/email/test", {
    method: "POST",
    body: JSON.stringify({ email }),
  });
}

// ─── Smart Dashboard (add-on) ──────────────────────

export function getAddons(): Promise<{ data: Addon[] }> {
  return request<{ data: Addon[] }>("/api/v1/addons");
}

export function getAnalytics<TData = Record<string, unknown>>(
  tab: AnalyticsTab,
  companyId: number,
  options: { limit?: number; fresh?: boolean } = {},
): Promise<AnalyticsEnvelope<TData>> {
  const params = new URLSearchParams({ company_id: String(companyId) });
  if (options.limit !== undefined) {
    params.set("limit", String(options.limit));
  }
  if (options.fresh) {
    params.set("fresh", "1");
  }
  return request<AnalyticsEnvelope<TData>>(`/api/v1/analytics/${tab}?${params.toString()}`);
}

export function getExecutiveAnalytics(
  companyId: number,
  options?: { limit?: number; fresh?: boolean },
) {
  return getAnalytics<ExecutiveData>("executive", companyId, options);
}

export function getFinancialAnalytics(
  companyId: number,
  options?: { limit?: number; fresh?: boolean },
) {
  return getAnalytics<FinancialData>("financial", companyId, options);
}

export function getSalesAnalytics(
  companyId: number,
  options?: { limit?: number; fresh?: boolean },
) {
  return getAnalytics<SalesData>("sales", companyId, options);
}

export function getOperationsAnalytics(
  companyId: number,
  options?: { limit?: number; fresh?: boolean },
) {
  return getAnalytics<OperationsData>("operations", companyId, options);
}

// ─── Dashboard card feeds ─────────────────────────────

interface DashboardQuery {
  company_id: number;
  from?: string;
  to?: string;
  days?: number;
  limit?: number;
  date?: string;
}

function dashboardParams(query: DashboardQuery): string {
  const params = new URLSearchParams({ company_id: String(query.company_id) });
  if (query.from) params.set("from", query.from);
  if (query.to) params.set("to", query.to);
  if (query.days !== undefined) params.set("days", String(query.days));
  if (query.limit !== undefined) params.set("limit", String(query.limit));
  if (query.date) params.set("date", query.date);
  return params.toString();
}

export function getDashboardSummary(query: DashboardQuery): Promise<DashboardSummary> {
  return request<DashboardSummary>(`/api/v1/dashboard?${dashboardParams(query)}`);
}

export function getFinancialYearSales(query: DashboardQuery): Promise<FinancialYearSales> {
  return request<FinancialYearSales>(
    `/api/v1/dashboard/financial-year-sales?${dashboardParams(query)}`,
  );
}

export function getSalesPaymentDue(query: DashboardQuery): Promise<PaymentDueResponse> {
  return request<PaymentDueResponse>(
    `/api/v1/dashboard/sales-payment-due?${dashboardParams(query)}`,
  );
}

export function getPurchasePaymentDue(query: DashboardQuery): Promise<PaymentDueResponse> {
  return request<PaymentDueResponse>(
    `/api/v1/dashboard/purchase-payment-due?${dashboardParams(query)}`,
  );
}

export function getStockAlert(query: DashboardQuery): Promise<StockAlertResponse> {
  return request<StockAlertResponse>(`/api/v1/dashboard/stock-alert?${dashboardParams(query)}`);
}

export function getDashboardSalesOrders(query: DashboardQuery): Promise<{
  company_id: number;
  total: number;
  limit: number;
  data: SalesOrderRow[];
}> {
  return request(`/api/v1/dashboard/sales-orders?${dashboardParams(query)}`);
}

export function getPendingShipments(query: DashboardQuery): Promise<{
  company_id: number;
  total: number;
  limit: number;
  data: PendingShipmentRow[];
}> {
  return request(`/api/v1/dashboard/pending-shipments?${dashboardParams(query)}`);
}

export function getDashboardCrmSummary(query: DashboardQuery): Promise<DashboardCrmSummary> {
  return request<DashboardCrmSummary>(`/api/v1/dashboard/crm-summary?${dashboardParams(query)}`);
}

export function getDashboardProjectSummary(
  query: DashboardQuery,
): Promise<DashboardProjectSummary> {
  return request<DashboardProjectSummary>(
    `/api/v1/dashboard/project-summary?${dashboardParams(query)}`,
  );
}

export function getPosSummary(query: DashboardQuery): Promise<PosSummaryResponse> {
  return request<PosSummaryResponse>(`/api/v1/dashboard/pos-summary?${dashboardParams(query)}`);
}

// ─── Financial reports ────────────────────────────────

interface ReportQuery {
  company_id: number;
  from?: string;
  to?: string;
  as_of?: string;
  type?: "receivable" | "payable";
}

function reportParams(query: ReportQuery): string {
  const params = new URLSearchParams({ company_id: String(query.company_id) });
  if (query.from) params.set("from", query.from);
  if (query.to) params.set("to", query.to);
  if (query.as_of) params.set("as_of", query.as_of);
  if (query.type) params.set("type", query.type);
  return params.toString();
}

export function getProfitAndLoss(query: ReportQuery): Promise<ProfitAndLossResponse> {
  return request<ProfitAndLossResponse>(`/api/v1/reports/profit-and-loss?${reportParams(query)}`);
}

export function getBalanceSheet(query: ReportQuery): Promise<BalanceSheetResponse> {
  return request<BalanceSheetResponse>(`/api/v1/reports/balance-sheet?${reportParams(query)}`);
}

export function getCashFlow(query: ReportQuery): Promise<CashFlowResponse> {
  return request<CashFlowResponse>(`/api/v1/reports/cash-flow?${reportParams(query)}`);
}

export function getAging(query: ReportQuery): Promise<AgingResponse> {
  return request<AgingResponse>(`/api/v1/reports/aging?${reportParams(query)}`);
}

export function getSalesByProduct(query: ReportQuery): Promise<SalesByProductResponse> {
  return request<SalesByProductResponse>(`/api/v1/reports/sales-by-product?${reportParams(query)}`);
}

export function getSalesByCategory(query: ReportQuery): Promise<SalesByCategoryResponse> {
  return request<SalesByCategoryResponse>(
    `/api/v1/reports/sales-by-category?${reportParams(query)}`,
  );
}

export function getVatSummary(query: ReportQuery): Promise<VatSummaryResponse> {
  return request<VatSummaryResponse>(`/api/v1/reports/vat-summary?${reportParams(query)}`);
}

// ─── Business advisor ─────────────────────────────────

export function getAdvisorReport(companyId: number): Promise<AdvisorReport> {
  return request<AdvisorReport>(`/api/v1/business-advisor/report?company_id=${companyId}`);
}

export function getAdvisorSnapshots(
  companyId: number,
): Promise<PaginatedResponse<AdvisorSnapshot>> {
  return request<PaginatedResponse<AdvisorSnapshot>>(
    `/api/v1/business-advisor/snapshots?company_id=${companyId}`,
  );
}

// ─── HRM / workload ───────────────────────────────────

export function getHrmSummary(companyId: number): Promise<HrmSummaryResponse> {
  return request<HrmSummaryResponse>(`/api/v1/hrm-employees/summary?company_id=${companyId}`);
}

export function getTeamWorkload(companyId: number): Promise<{ data: TeamWorkloadRow[] }> {
  return request<{ data: TeamWorkloadRow[] }>(`/api/v1/team-workload?company_id=${companyId}`);
}

export function getTeamWorkloadSummary(companyId: number): Promise<TeamWorkloadSummary> {
  return request<TeamWorkloadSummary>(`/api/v1/team-workload/summary?company_id=${companyId}`);
}

// ─── Vertical add-on summaries ────────────────────────

export function getCoworkingSummary(companyId: number): Promise<CoworkingBookingSummary> {
  return request<CoworkingBookingSummary>(
    `/api/v1/coworking-bookings/summary?company_id=${companyId}`,
  );
}

export function getLaundrySummary(companyId: number): Promise<LaundryOrderSummary> {
  return request<LaundryOrderSummary>(`/api/v1/laundry-orders/summary?company_id=${companyId}`);
}

export function getGymSummary(companyId: number): Promise<GymMemberSummary> {
  return request<GymMemberSummary>(`/api/v1/gym-members/summary?company_id=${companyId}`);
}

export function getRentalSummary(companyId: number): Promise<RentalAgreementSummary> {
  return request<RentalAgreementSummary>(
    `/api/v1/rental-agreements/summary?company_id=${companyId}`,
  );
}

export function getBookingSummary(companyId: number): Promise<BookingSummary> {
  return request<BookingSummary>(`/api/v1/bookings/summary?company_id=${companyId}`);
}
