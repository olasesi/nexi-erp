export interface Address {
  id: number;
  type: string;
  label: string | null;
  street: string;
  street2: string | null;
  city: string;
  state: string | null;
  postal_code: string | null;
  country: string;
  is_default: boolean;
  created_at: string;
}

export interface Company {
  id: number;
  name: string;
  legal_name: string | null;
  email: string | null;
  phone: string | null;
  tax_id: string | null;
  registration_number: string | null;
  website: string | null;
  notes: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Contact {
  id: number;
  company_id: number;
  type: "customer" | "supplier" | "lead" | "both";
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  mobile: string | null;
  job_title: string | null;
  department: string | null;
  language: string | null;
  notes: string | null;
  is_active: boolean;
  addresses: Address[];
  created_at: string;
  updated_at: string;
}

export interface ProductCategory {
  id: number;
  company_id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  products_count?: number;
  created_at: string;
  updated_at: string;
}

export interface Product {
  id: number;
  company_id: number;
  category_id: number | null;
  category: ProductCategory | null;
  name: string;
  sku: string | null;
  barcode: string | null;
  type: "product" | "service" | "digital" | "bundle";
  unit: string;
  sale_price: number;
  purchase_price: number;
  cost_price: number;
  tax_rate: number;
  stock_quantity: number;
  min_stock_level: number;
  description: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Warehouse {
  id: number;
  company_id: number;
  name: string;
  code: string;
  location: string | null;
  phone: string | null;
  notes: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export type OrderStatus =
  "draft" | "confirmed" | "processing" | "shipped" | "delivered" | "cancelled" | "refunded";

export type PaymentStatus = "pending" | "partial" | "paid" | "overdue" | "refunded";

export interface SalesOrderItem {
  id: number;
  product_id: number;
  product_name: string;
  product_sku: string | null;
  quantity: number;
  unit_price: number;
  tax_rate: number;
  tax_amount: number;
  discount_amount: number;
  subtotal: number;
  total: number;
}

export interface SalesOrder {
  id: number;
  company_id: number;
  contact_id: number;
  contact: Contact | null;
  warehouse_id: number | null;
  order_number: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  currency: string;
  notes: string | null;
  terms: string | null;
  order_date: string;
  delivery_date: string | null;
  items: SalesOrderItem[];
  created_at: string;
  updated_at: string;
}

export interface PurchaseOrderItem {
  id: number;
  product_id: number;
  product_name: string;
  product_sku: string | null;
  quantity: number;
  unit_price: number;
  tax_rate: number;
  tax_amount: number;
  discount_amount: number;
  subtotal: number;
  total: number;
}

export interface PurchaseOrder {
  id: number;
  company_id: number;
  contact_id: number;
  contact: Contact | null;
  warehouse_id: number | null;
  order_number: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  currency: string;
  notes: string | null;
  terms: string | null;
  order_date: string;
  expected_date: string | null;
  items: PurchaseOrderItem[];
  created_at: string;
  updated_at: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  username?: string | null;
  phone?: string | null;
  roles?: string[];
  permissions?: string[];
  company: Company | null;
}

export interface AuditLogRow {
  id: number;
  company_id: number | null;
  user_id: number | null;
  event: string;
  auditable_type: string;
  auditable_id: number | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  created_at: string | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export type SettingsValue = string | number | boolean | null;

export type SettingsGroup = Record<string, SettingsValue>;

export type AppSettings = Record<string, SettingsGroup>;

export interface CurrencyOption {
  name: string;
  code: string;
  symbol: string;
  is_default: boolean;
}

export interface EmailProviderOption {
  code: string;
  name: string;
}

export interface SettingsMeta {
  company_id: number | null;
  currencies: CurrencyOption[];
  default_currency: string;
  available_languages: Record<string, string>;
  date_formats: string[];
  time_formats: string[];
  calendar_start_days: string[];
  themes: string[];
  dashboard_widgets: Record<
    string,
    { label: string; type: string; size: { w: number; h: number } }
  >;
  dashboard_layouts: Record<string, { label: string; description: string; widgets: string[] }>;
  theme_colors: string[];
  sidebar_variants: string[];
  layout_directions: string[];
  currency_formats: string[];
  currency_symbol_positions: string[];
  storage_types: string[];
  email_providers: EmailProviderOption[];
  cache_size: string;
}

export interface SettingsResponse {
  data: AppSettings;
  meta: SettingsMeta;
}

export interface SettingsGroupResponse {
  message?: string;
  data: SettingsGroup;
}

export interface Addon {
  id: string;
  label: string;
  description: string | null;
  category: string | null;
  status: string;
  installed: boolean;
  enabled: boolean;
  enabled_by_default: boolean;
}

export type AnalyticsTab = "executive" | "financial" | "sales" | "operations";

export interface AnalyticsPeriod {
  from: string;
  to: string;
}

export interface AnalyticsEnvelope<TData = Record<string, unknown>> {
  tab: AnalyticsTab;
  company_id: number;
  period: AnalyticsPeriod;
  generated_at: string;
  cached: boolean;
  data: TData;
}

export interface KpiMetric {
  value: number;
  previous?: number;
  change_pct?: number;
  margin_pct?: number;
}

export interface TrendPoint {
  month: string;
  revenue: number;
  expenses: number;
  net: number;
}

export interface AccountBalance {
  code: string;
  name: string;
  balance: number;
}

export interface RevenueEntry {
  entry_id: number;
  entry_number: string;
  description: string | null;
  entry_date: string;
  amount: number;
  accounts: string[];
}

export interface TopCustomer {
  contact_id: number;
  name: string;
  total: number;
  invoices: number;
}

export interface AnalyticsInsight {
  key: string;
  severity: string;
  domain: string;
  title: string;
  text: string;
}

export interface HealthCategory {
  score: number | null;
  label?: string;
  weight?: number;
  status?: string;
  signals?: Record<string, number>;
}

export interface HealthReport {
  company_id: number;
  score_date: string;
  generated_by: string;
  overall_score: number | null;
  categories: Record<string, HealthCategory>;
  coverage: { scored: number; total: number };
  signals: Array<Record<string, number>>;
  alerts: Array<{
    key: string;
    severity: string;
    domain: string;
    title: string;
    detail: string;
  }>;
  insights: unknown[];
  recommendations: unknown[];
}

export interface ModuleSummaries {
  crm: { active_leads: number; conversion_rate: number; pipeline_value: number };
  account: { cash_balance: number; pending_invoices: number; pending_bills: number };
  sales: { monthly_revenue: number; quotations: number; orders: number };
  inventory: { low_stock: number; out_of_stock: number; total_stock_value: number };
}

export interface ExecutiveKpis {
  monthly_revenue: KpiMetric;
  monthly_expense: KpiMetric;
  monthly_net_profit: KpiMetric;
  sales_pipeline: {
    value: number;
    active_leads: number;
    open_deals: number;
    conversion_rate: number;
  };
  customers: number;
  products: number;
}

export interface ExecutiveData {
  kpis: ExecutiveKpis;
  health: HealthReport;
  insights: AnalyticsInsight[];
  module_summaries: ModuleSummaries;
  top_customers: TopCustomer[];
  recent_transactions: RevenueEntry[];
}

export interface FinancialData {
  revenue: {
    total: number;
    previous_total: number;
    growth_pct: number;
    avg_daily: number;
  };
  expenses: {
    total: number;
    previous_total: number;
    growth_pct: number;
  };
  profitability: { net: number; margin_pct: number };
  cash_flow: { inflow: number; outflow: number; net: number };
  revenue_by_source: AccountBalance[];
  trend: TrendPoint[];
  transactions: { total: number; limit: number; data: RevenueEntry[] };
}

export interface SalesKpis {
  active_leads: number;
  pipeline_value: number;
  conversion_rate: number;
  active_deals: number;
  quotations: number;
  sales_orders: number;
  invoices: number;
}

export interface MonthlyLeadsPoint {
  month: string;
  total: number;
  converted: number;
}

export interface SalesVsPurchasePoint {
  month: string;
  sales: number;
  purchases: number;
}

export interface LeadFunnelBucket {
  status: string;
  count: number;
}

export interface LeadSourceBreakdown {
  source: string;
  count: number;
  converted: number;
  conversion_rate: number;
}

export interface SalesData {
  kpis: SalesKpis;
  lead_funnel: LeadFunnelBucket[];
  leads_by_source: LeadSourceBreakdown[];
  monthly_leads: MonthlyLeadsPoint[];
  sales_vs_purchase: SalesVsPurchasePoint[];
}

export interface OperationsKpis {
  total_products: number;
  stock_value: number;
  out_of_stock: number;
  low_stock: number;
  active_warehouses: number;
}

export interface StockGroup {
  name: string;
  quantity: number;
  value: number;
}

export interface Vendor {
  vendor_id: number;
  vendor: string;
  orders: number;
  value: number;
}

export interface PurchaseSummary {
  open_orders: number;
  order_value: number;
  pending_receipts: number;
  top_vendors: Vendor[];
}

export interface ProductListing {
  product_id: number;
  name: string;
  sku: string | null;
  category: string;
  type: string;
  unit: string | null;
  stock_quantity: number;
  cost_price: number;
  sale_price: number;
  stock_value: number;
  min_stock_level: number;
  status: string;
}

export interface ProductList {
  total: number;
  limit: number;
  data: ProductListing[];
}

export interface OperationsData {
  kpis: OperationsKpis;
  stock_by_category: StockGroup[];
  stock_by_warehouse: StockGroup[];
  purchases: PurchaseSummary;
  products: ProductList;
}

// --- Dashboard summary (GET /api/v1/dashboard) --------

export interface DatedTotal {
  date: string;
  total: number;
}

export interface MonthTotal {
  month: string;
  total: number;
}

export interface RecentLedgerEntry {
  id: number;
  title: string;
  description: string | null;
  amount: number;
  date: string;
}

export interface DashboardSummary {
  company_id: number;
  as_of: string;
  total_invoiced: number;
  outstanding_receivables: number;
  outstanding_payables: number;
  month_revenue: number;
  month_expense: number;
  month_net_profit: number;
  period: AnalyticsPeriod;
  total_sales: number;
  total_sell_return: number;
  total_purchase: number;
  total_purchase_return: number;
  expense: number;
  net: number;
  todays_profit: number;
  daily_sales: DatedTotal[];
  total_clients: number;
  total_vendors: number;
  total_customer_payment: number;
  total_vendor_payment: number;
  open_sales_orders: number;
  low_stock_products: number;
  total_products: number;
  recent_revenue: number;
  monthly_customer_payments: MonthTotal[];
  monthly_vendor_payments: MonthTotal[];
  recent_revenues: RecentLedgerEntry[];
  recent_expenses: RecentLedgerEntry[];
}

export interface FinancialYearSalesPoint {
  month: string;
  date: string;
  sales: number;
  sell_return: number;
  net: number;
}

export interface FinancialYearSales {
  company_id: number;
  financial_year: { start_month: number; start_date: string; end_date: string; label: string };
  total_sales: number;
  total_sell_return: number;
  net_sales: number;
  series: FinancialYearSalesPoint[];
}

// --- Payment due / stock / order feeds -----------------

export interface PaymentDueRow {
  document_id: number;
  document_number: string;
  party: string;
  issue_date: string;
  due_date: string;
  status: string;
  is_overdue: boolean;
  days_overdue: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  contact: { id: number; name: string; phone: string | null; mobile: string | null };
}

export interface PaymentDueResponse {
  company_id: number;
  window_days: number;
  due_before: string;
  total: number;
  total_due: number;
  limit: number;
  data: PaymentDueRow[];
}

export interface StockAlertRow {
  product_id: number;
  name: string;
  sku: string | null;
  unit: string | null;
  current_stock: number;
  alert_level: number;
  shortfall: number;
  is_out_of_stock: boolean;
}

export interface StockAlertResponse {
  company_id: number;
  total: number;
  limit: number;
  data: StockAlertRow[];
}

export interface SalesOrderRow {
  sales_order_id: number;
  order_number: string;
  order_date: string;
  delivery_date: string | null;
  status: string;
  payment_status: string;
  location: string | null;
  customer: { id: number; name: string; phone: string | null };
  ordered_quantity: number;
  invoiced_quantity: number;
  quantity_remaining: number;
  total: number;
  balance_due: number;
  added_by: string | null;
}

export interface PendingShipmentRow {
  invoice_id: number;
  invoice_number: string;
  issue_date: string;
  shipping_status: string;
  shipped_at: string | null;
  tracking_number: string | null;
  payment_status: string;
  total: number;
  balance_due: number;
  customer: { id: number; name: string; phone: string | null } | null;
  service_staff: string | null;
}

// --- CRM / project / POS dashboard summaries -----------

export interface CrmSummaryMetrics {
  total_leads: number;
  active_leads: number;
  converted_leads: number;
  lost_leads: number;
  overdue_follow_ups: number;
  open_deals: number;
  pipeline_value: number;
  won_deals: number;
  won_value: number;
  lost_deals: number;
  conversion_rate: number;
}

export interface PipelineStageRow {
  stage_id: number;
  stage: string;
  open_deals: number;
  pipeline_value: number;
}

export interface DashboardCrmSummary {
  company_id: number;
  limit: number;
  summary: CrmSummaryMetrics;
  lead_funnel: LeadFunnelBucket[];
  leads_by_source: Array<{ source: string; count: number }>;
  pipeline_by_stage: PipelineStageRow[];
}

export interface DashboardProjectSummary {
  company_id: number;
  limit: number;
  summary: {
    total_projects: number;
    active_projects: number;
    completed_projects: number;
    on_hold_projects: number;
    overdue_projects: number;
    by_status: Array<{ status: string; count: number }>;
    total_budget: number;
    total_tasks: number;
    open_tasks: number;
    completed_tasks: number;
    overdue_tasks: number;
    tasks_by_status: Array<{ status: string; count: number }>;
    task_completion_rate: number;
    total_milestones: number;
    upcoming_milestones: number;
    overdue_milestones: number;
  };
}

export interface PosSummaryResponse {
  company_id: number;
  limit: number;
  date: string;
  todays_sales: number;
  todays_transactions: number;
  todays_items_sold: number;
  todays_tax: number;
  todays_discount: number;
  average_sale: number;
  by_payment_method: Array<{ method: string; count: number; total: number }>;
  top_products: Array<{
    product_id: number;
    product_name: string;
    quantity: number;
    total: number;
  }>;
}

// --- Financial reports ---------------------------------

export interface ProfitAndLossResponse {
  from: string;
  to: string;
  revenue: AccountBalance[];
  total_revenue: number;
  expenses: AccountBalance[];
  total_expenses: number;
  net_income: number;
}

export interface BalanceSheetSide {
  account_id: number;
  code: string;
  name: string;
  type: string;
  normal_balance: string;
  debit: number;
  credit: number;
  balance: number;
}

export interface BalanceSheetResponse {
  as_of: string;
  net_income: number;
  assets: BalanceSheetSide[];
  total_assets: number;
  liabilities: BalanceSheetSide[];
  total_liabilities: number;
  equity: BalanceSheetSide[];
  total_equity: number;
  total_liabilities_and_equity: number;
}

export interface CashFlowResponse {
  from: string;
  to: string;
  inflow: number;
  outflow: number;
  net_cash_flow: number;
}

export interface AgingBucket {
  amount: number;
  invoices: Array<{
    invoice_id: number;
    invoice_number: string;
    contact_id: number;
    contact_name: string;
    type: string;
    due_date: string;
    days_overdue: number;
    total: number;
    balance_due: number;
  }>;
}

export interface AgingResponse {
  type: "receivable" | "payable";
  as_of: string;
  buckets: {
    current: AgingBucket;
    "1_30": AgingBucket;
    "31_60": AgingBucket;
    "61_90": AgingBucket;
    "90_plus": AgingBucket;
  };
  total_outstanding: number;
}

export interface ReportTotals {
  from: string;
  to: string;
  base_currency: string;
  total_quantity: number;
  total_revenue: number;
}

export interface SalesByProductResponse extends ReportTotals {
  items: Array<{
    product_id: number;
    product_name: string;
    quantity: number;
    subtotal: number;
    tax: number;
    total: number;
  }>;
}

export interface SalesByCategoryResponse extends ReportTotals {
  categories: Array<{
    category_id: number;
    category_name: string;
    quantity: number;
    subtotal: number;
    tax: number;
    total: number;
  }>;
}

export interface VatSummaryResponse {
  from: string;
  to: string;
  base_currency: string;
  rates: Array<{
    tax_rate: number;
    net_amount: number;
    tax_amount: number;
    gross_amount: number;
    documents: number;
  }>;
  total_net: number;
  total_tax: number;
  total_gross: number;
}

// --- Business advisor ----------------------------------

export interface AdvisorCategory {
  label: string;
  score: number | null;
  weight: number;
  signals: Record<string, number>;
  note: string | null;
}

export interface AdvisorReport {
  company_id: number;
  score_date: string;
  generated_by: string;
  overall_score: number | null;
  categories: Record<string, AdvisorCategory>;
  coverage: { score: number; scored: number; total: number };
  signals: Record<string, unknown>;
  trend: {
    points: Array<{
      score_date: string;
      overall_score: number;
      financial_score: number;
      sales_score: number;
      operations_score: number;
      team_score: number;
    }>;
    delta: number | null;
  };
  alerts: Array<{
    key: string;
    severity: string;
    domain: string;
    title: string;
    detail: string;
  }>;
  insights: Array<{
    key: string;
    severity: string;
    domain: string;
    title: string;
    text: string;
  }>;
  recommendations: Array<{
    key: string;
    severity: string;
    domain: string;
    title: string;
    text: string;
  }>;
}

export interface AdvisorSnapshot {
  id: number;
  company_id: number;
  score_date: string;
  overall_score: number;
  financial_score: number;
  sales_score: number;
  operations_score: number;
  team_score: number;
  projects_score: number;
}

// --- HRM / workload ------------------------------------

export interface HrmSummaryResponse {
  company_id: number;
  as_of: string;
  total_employees: number;
  working_employees: number;
  by_status: Array<{ status: string; count: number }>;
  by_department: Array<{ department_id: number | null; name: string; count: number }>;
  attendance_today: {
    present: number;
    absent: number;
    late: number;
    half_day: number;
    leave: number;
    work_from_home: number;
    missing: number;
  };
  attendance_rate: number;
  on_leave_today: number;
  pending_leaves: number;
  approved_leaves_this_month: number;
}

export interface TeamWorkloadRow {
  employee_id: number;
  employee_name: string;
  open_tasks: number;
  open_tickets: number;
  pending_timesheets: number;
  total_load: number;
}

export interface TeamWorkloadSummary {
  total_employees: number;
  total_open_tasks: number;
  total_open_tickets: number;
  total_pending_timesheets: number;
  average_load: number;
}

// --- Vertical add-on summaries -------------------------

export interface CoworkingBookingSummary {
  summary: {
    today_total: number;
    today_by_status: {
      requested: number;
      confirmed: number;
      checked_in: number;
      checked_out: number;
      cancelled: number;
    };
    in_progress: number;
    upcoming: number;
    active_units: number;
    checked_in_units: number;
  };
}

export interface LaundryOrderSummary {
  summary: {
    total_orders: number;
    by_status: {
      received: number;
      washing: number;
      drying: number;
      folding: number;
      ready_for_pickup: number;
      delivered: number;
      cancelled: number;
    };
    open_value: number;
    delivered_value: number;
  };
}

export interface GymMemberSummary {
  summary: {
    active: number;
    suspended: number;
    cancelled: number;
    expired: number;
    visits_today: number;
    currently_in: number;
  };
}

export interface RentalAgreementSummary {
  summary: {
    total_agreements: number;
    by_status: {
      draft: number;
      active: number;
      returned: number;
      cancelled: number;
      overdue: number;
    };
    overdue: number;
    deposit_held: number;
  };
}

export interface BookingSummary {
  summary: {
    today_total: number;
    today_by_status: {
      pending: number;
      confirmed: number;
      checked_in: number;
      completed: number;
      cancelled: number;
      no_show: number;
    };
    in_progress: number;
    upcoming: number;
    active_resources: number;
  };
}

// --- Notifications -------------------------------------

export interface AppNotification {
  id: number;
  company_id: number | null;
  user_id: number | null;
  type: string;
  title: string;
  body: string | null;
  payload: unknown;
  read_at: string | null;
  is_read: boolean;
  created_at: string;
  updated_at: string;
}

export interface NotificationListResponse {
  data: AppNotification[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface InvoiceItem {
  id: number;
  product_id: number | null;
  product_name: string;
  product_sku: string | null;
  description: string | null;
  quantity: number;
  unit_price: number;
  tax_rate: number;
  tax_amount: number;
  discount_amount: number;
  subtotal: number;
  total: number;
}

export interface Invoice {
  id: number;
  company_id: number;
  contact_id: number | null;
  contact: Contact | null;
  sales_order_id: number | null;
  invoice_number: string;
  type: "invoice" | "bill" | "credit_note" | "debit_note";
  status: "draft" | "sent" | "partial" | "paid" | "overdue" | "cancelled";
  issue_date: string;
  due_date: string | null;
  currency: string;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  notes: string | null;
  terms: string | null;
  items: InvoiceItem[];
  created_at: string;
  updated_at: string;
}

export interface Payment {
  id: number;
  company_id: number;
  invoice_id: number | null;
  contact_id: number | null;
  contact: Contact | null;
  invoice: Invoice | null;
  payment_number: string;
  type: "receipt" | "payment";
  method: string | null;
  amount: number;
  currency: string;
  payment_date: string;
  reference: string | null;
  notes: string | null;
  status: "pending" | "completed" | "cancelled" | "refunded";
  created_at: string;
  updated_at: string;
}

export interface QuotationItem {
  id: number;
  product_id: number | null;
  product_name: string;
  product_sku: string | null;
  description: string | null;
  quantity: number;
  unit_price: number;
  tax_rate: number;
  tax_amount: number;
  discount_amount: number;
  subtotal: number;
  total: number;
}

export interface Quotation {
  id: number;
  company_id: number;
  contact_id: number | null;
  contact: Contact | null;
  quotation_number: string;
  status: "draft" | "sent" | "accepted" | "rejected" | "expired" | "converted";
  issue_date: string;
  expiry_date: string | null;
  currency: string;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  notes: string | null;
  terms: string | null;
  items: QuotationItem[];
  created_at: string;
  updated_at: string;
}

export interface CreditNote {
  id: number;
  company_id: number;
  invoice_id: number | null;
  invoice?: Invoice | null;
  contact_id: number | null;
  contact?: Contact | null;
  credit_note_number: string;
  status: "draft" | "pending" | "approved" | "applied" | "cancelled";
  issue_date: string;
  currency: string;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface DebitNote {
  id: number;
  company_id: number;
  invoice_id: number | null;
  invoice?: Invoice | null;
  contact_id: number | null;
  contact?: Contact | null;
  debit_note_number: string;
  status: "draft" | "pending" | "approved" | "applied" | "cancelled";
  issue_date: string;
  currency: string;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface PurchaseReceipt {
  id: number;
  company_id: number;
  purchase_order_id: number | null;
  purchase_order?: PurchaseOrder | null;
  receipt_number: string;
  status: string;
  receipt_date: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface ExpenseCategory {
  id: number;
  company_id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  created_at: string;
}

export interface Expense {
  id: number;
  company_id: number;
  expense_category_id: number | null;
  expense_category?: ExpenseCategory | null;
  expense_number: string;
  title: string;
  amount: number;
  currency: string;
  expense_date: string;
  status: "draft" | "pending" | "approved" | "paid" | "rejected";
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface IncomeCategory {
  id: number;
  company_id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  created_at: string;
}

export interface Income {
  id: number;
  company_id: number;
  income_category_id: number | null;
  income_category?: IncomeCategory | null;
  income_number: string;
  title: string;
  amount: number;
  currency: string;
  income_date: string;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface ChartOfAccount {
  id: number;
  company_id: number;
  code: string;
  name: string;
  type: "asset" | "liability" | "equity" | "revenue" | "expense";
  parent_id: number | null;
  is_active: boolean;
  description: string | null;
  created_at: string;
}

export interface JournalEntryLine {
  id: number;
  chart_of_account_id: number | null;
  chart_of_account?: ChartOfAccount | null;
  debit: number;
  credit: number;
  description: string | null;
}

export interface JournalEntry {
  id: number;
  company_id: number;
  entry_number: string;
  entry_date: string;
  reference: string | null;
  description: string | null;
  status: "draft" | "posted" | "void";
  total_debit: number;
  total_credit: number;
  lines?: JournalEntryLine[];
  created_at: string;
  updated_at: string;
}

export interface BankAccount {
  id: number;
  company_id: number;
  account_name: string;
  account_number: string | null;
  bank_name: string | null;
  currency: string;
  opening_balance: number;
  current_balance: number;
  is_active: boolean;
  created_at: string;
}

export interface BankTransaction {
  id: number;
  company_id: number;
  bank_account_id: number;
  bank_account?: BankAccount | null;
  transaction_date: string;
  type: "credit" | "debit";
  amount: number;
  description: string | null;
  reference: string | null;
  status: "unmatched" | "matched" | "reconciled";
  created_at: string;
}

export interface Reconciliation {
  id: number;
  company_id: number;
  bank_account_id: number;
  bank_account?: BankAccount | null;
  reconciliation_number: string;
  statement_date: string;
  starting_balance: number;
  ending_balance: number;
  status: "draft" | "in_progress" | "completed" | "cancelled";
  created_at: string;
}

export interface Lead {
  id: number;
  company_id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  company_name: string | null;
  source: string | null;
  status: "new" | "contacted" | "qualified" | "unqualified" | "converted";
  assigned_to_id: number | null;
  notes: string | null;
  created_at: string;
}

export interface CrmStage {
  id: number;
  company_id: number;
  name: string;
  sort_order: number;
  color: string | null;
  is_default: boolean;
  created_at: string;
}

export interface Deal {
  id: number;
  company_id: number;
  lead_id: number | null;
  contact_id: number | null;
  contact?: Contact | null;
  title: string;
  stage_id: number | null;
  stage?: CrmStage | null;
  value: number;
  currency: string;
  probability: number;
  expected_close_date: string | null;
  status: "open" | "won" | "lost" | "abandoned";
  assigned_to_id: number | null;
  notes: string | null;
  created_at: string;
}

export interface HrmDepartment {
  id: number;
  company_id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  created_at: string;
}

export interface HrmDesignation {
  id: number;
  company_id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  created_at: string;
}

export interface HrmEmployee {
  id: number;
  company_id: number;
  employee_id: string | null;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  department_id: number | null;
  department?: HrmDepartment | null;
  designation_id: number | null;
  designation?: HrmDesignation | null;
  status: "active" | "inactive" | "terminated";
  hire_date: string | null;
  created_at: string;
}

export interface HrmAttendance {
  id: number;
  company_id: number;
  employee_id: number;
  employee?: HrmEmployee | null;
  date: string;
  check_in: string | null;
  check_out: string | null;
  status: "present" | "absent" | "late" | "half_day" | "leave";
  notes: string | null;
  created_at: string;
}

export interface HrmLeave {
  id: number;
  company_id: number;
  employee_id: number;
  employee?: HrmEmployee | null;
  leave_type: string;
  start_date: string;
  end_date: string;
  days: number;
  reason: string | null;
  status: "pending" | "approved" | "rejected" | "cancelled";
  created_at: string;
}

export interface Project {
  id: number;
  company_id: number;
  name: string;
  code: string | null;
  description: string | null;
  start_date: string | null;
  end_date: string | null;
  status: "planning" | "in_progress" | "on_hold" | "completed" | "cancelled";
  priority: string | null;
  budget: number | null;
  created_at: string;
}

export interface ProjectTask {
  id: number;
  project_id: number;
  project?: Project | null;
  title: string;
  description: string | null;
  assigned_to_id: number | null;
  status: "todo" | "in_progress" | "review" | "done" | "cancelled";
  priority: string | null;
  due_date: string | null;
  created_at: string;
}

export interface ProjectMilestone {
  id: number;
  project_id: number;
  project?: Project | null;
  title: string;
  due_date: string | null;
  status: "pending" | "in_progress" | "completed" | "overdue";
  created_at: string;
}

export interface StockAdjustment {
  id: number;
  company_id: number;
  warehouse_id: number | null;
  warehouse?: Warehouse | null;
  adjustment_number: string;
  adjustment_date: string;
  type: "increase" | "decrease";
  reason: string | null;
  status: "draft" | "approved" | "rejected";
  notes: string | null;
  created_at: string;
}

export interface StockTransfer {
  id: number;
  company_id: number;
  from_warehouse_id: number | null;
  from_warehouse?: Warehouse | null;
  to_warehouse_id: number | null;
  to_warehouse?: Warehouse | null;
  transfer_number: string;
  transfer_date: string;
  status: "draft" | "pending" | "in_transit" | "completed" | "cancelled";
  notes: string | null;
  created_at: string;
}

export interface Role {
  id: number;
  company_id: number | null;
  name: string;
  guard_name: string;
  permissions?: string[];
  created_at: string;
}

export interface Ticket {
  id: number;
  company_id: number;
  ticket_number: string;
  subject: string;
  description: string | null;
  priority: "low" | "medium" | "high" | "urgent";
  status: "open" | "in_progress" | "resolved" | "closed" | "on_hold";
  assigned_to_id: number | null;
  requester_contact_id: number | null;
  requester_contact?: Contact | null;
  created_at: string;
  updated_at: string;
}

export interface Asset {
  id: number;
  company_id: number;
  asset_code: string;
  name: string;
  category_id: number | null;
  status: "available" | "in_use" | "under_maintenance" | "retired" | "lost";
  purchase_date: string | null;
  purchase_cost: number | null;
  location: string | null;
  notes: string | null;
  created_at: string;
}

export interface Budget {
  id: number;
  company_id: number;
  name: string;
  fiscal_year: string;
  start_date: string | null;
  end_date: string | null;
  status: "draft" | "pending" | "approved" | "closed" | "rejected";
  total_amount: number;
  notes: string | null;
  created_at: string;
}

export interface Goal {
  id: number;
  company_id: number;
  title: string;
  description: string | null;
  start_date: string | null;
  end_date: string | null;
  status: "not_started" | "in_progress" | "completed" | "on_hold" | "cancelled";
  priority: string | null;
  progress: number;
  created_at: string;
}

export interface Training {
  id: number;
  company_id: number;
  title: string;
  description: string | null;
  trainer: string | null;
  start_date: string | null;
  end_date: string | null;
  location: string | null;
  status: "planned" | "in_progress" | "completed" | "cancelled";
  created_at: string;
}

export interface Notice {
  id: number;
  company_id: number;
  title: string;
  content: string | null;
  publish_date: string | null;
  status: "draft" | "published" | "archived";
  created_at: string;
}

export interface Announcement {
  id: number;
  company_id: number;
  title: string;
  content: string | null;
  publish_date: string | null;
  status: "draft" | "published" | "archived";
  created_at: string;
}

export interface Todo {
  id: number;
  company_id: number | null;
  title: string;
  description: string | null;
  due_date: string | null;
  priority: "low" | "medium" | "high";
  status: "pending" | "in_progress" | "completed" | "cancelled";
  created_at: string;
}

export interface Event {
  id: number;
  company_id: number;
  title: string;
  description: string | null;
  start_date: string;
  end_date: string | null;
  location: string | null;
  status: "scheduled" | "ongoing" | "completed" | "cancelled";
  created_at: string;
}

export interface Document {
  id: number;
  company_id: number;
  name: string;
  file_name: string | null;
  mime_type: string | null;
  size: number | null;
  folder_id: number | null;
  created_at: string;
}

export interface Form {
  id: number;
  company_id: number;
  title: string;
  description: string | null;
  status: "draft" | "published" | "archived";
  created_at: string;
}

export interface Proposal {
  id: number;
  company_id: number;
  proposal_number: string;
  contact_id: number | null;
  contact?: Contact | null;
  status: "draft" | "sent" | "accepted" | "rejected" | "expired" | "converted";
  issue_date: string;
  expiry_date: string | null;
  total: number;
  currency: string;
  created_at: string;
}

export interface Award {
  id: number;
  company_id: number;
  title: string;
  description: string | null;
  award_date: string | null;
  recipient_name: string | null;
  created_at: string;
}

export interface CoworkingUnit {
  id: number;
  company_id: number;
  name: string;
  type: string | null;
  capacity: number | null;
  price_per_hour: number | null;
  price_per_day: number | null;
  status: string;
  created_at: string;
}

export interface CoworkingBooking {
  id: number;
  company_id: number;
  unit_id: number;
  unit?: CoworkingUnit | null;
  contact_id: number | null;
  contact?: Contact | null;
  start_time: string;
  end_time: string;
  status: "pending" | "confirmed" | "checked_in" | "checked_out" | "cancelled" | "no_show";
  total_amount: number | null;
  created_at: string;
}

export interface LaundryOrder {
  id: number;
  company_id: number;
  order_number: string;
  contact_id: number | null;
  contact?: Contact | null;
  status: "received" | "washing" | "ironing" | "ready" | "delivered" | "cancelled";
  total_amount: number | null;
  due_date: string | null;
  created_at: string;
}

export interface GymMember {
  id: number;
  company_id: number;
  member_number: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  status: "active" | "inactive" | "suspended";
  join_date: string | null;
  expiry_date: string | null;
  created_at: string;
}

export interface RentalAgreement {
  id: number;
  company_id: number;
  agreement_number: string;
  contact_id: number | null;
  contact?: Contact | null;
  start_date: string;
  end_date: string | null;
  status: "draft" | "active" | "completed" | "cancelled";
  total_amount: number | null;
  created_at: string;
}

export interface SalesTarget {
  id: number;
  company_id: number;
  name: string;
  target_amount: number;
  achieved_amount: number;
  period_start: string | null;
  period_end: string | null;
  status: string;
  created_at: string;
}

export interface SalesReturn {
  id: number;
  company_id: number;
  return_number: string;
  sales_order_id: number | null;
  status: "pending" | "approved" | "completed" | "cancelled";
  total_amount: number | null;
  return_date: string | null;
  created_at: string;
}

export interface PerformanceReview {
  id: number;
  company_id: number;
  employee_id: number;
  employee?: HrmEmployee | null;
  review_period: string | null;
  status: "draft" | "in_progress" | "completed" | "approved";
  overall_rating: number | null;
  review_date: string | null;
  created_at: string;
}

export interface JobPosting {
  id: number;
  company_id: number;
  title: string;
  description: string | null;
  status: "draft" | "published" | "closed";
  publish_date: string | null;
  closing_date: string | null;
  created_at: string;
}

export interface JobCandidate {
  id: number;
  company_id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  status: "applied" | "screening" | "interview" | "offered" | "hired" | "rejected";
  applied_date: string | null;
  created_at: string;
}

export interface Conversation {
  id: number;
  company_id: number;
  subject: string | null;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface Message {
  id: number;
  conversation_id: number;
  sender_id: number | null;
  body: string | null;
  is_read: boolean;
  created_at: string;
}

export interface KbCategory {
  id: number;
  company_id: number;
  name: string;
  description: string | null;
  created_at: string;
}

export interface KbArticle {
  id: number;
  company_id: number;
  category_id: number | null;
  category?: KbCategory | null;
  title: string;
  content: string | null;
  status: "draft" | "published" | "archived";
  created_at: string;
}

export interface CompanyPolicy {
  id: number;
  company_id: number;
  title: string;
  content: string | null;
  status: "draft" | "published" | "archived";
  created_at: string;
}

export interface Timesheet {
  id: number;
  company_id: number;
  employee_id: number | null;
  employee?: HrmEmployee | null;
  period_start: string | null;
  period_end: string | null;
  status: "draft" | "submitted" | "approved" | "rejected";
  total_hours: number | null;
  created_at: string;
}

export interface TimeEntry {
  id: number;
  company_id: number;
  employee_id: number | null;
  employee?: HrmEmployee | null;
  project_id: number | null;
  project?: Project | null;
  start_time: string | null;
  end_time: string | null;
  duration_minutes: number | null;
  description: string | null;
  created_at: string;
}

export interface Commission {
  id: number;
  company_id: number;
  sales_agent_id: number | null;
  amount: number;
  status: "pending" | "approved" | "paid" | "rejected";
  commission_date: string | null;
  notes: string | null;
  created_at: string;
}

export interface Note {
  id: number;
  company_id: number | null;
  title: string | null;
  content: string | null;
  created_at: string;
}

export interface Reminder {
  id: number;
  company_id: number | null;
  title: string;
  description: string | null;
  remind_at: string | null;
  is_completed: boolean;
  created_at: string;
}

export interface Booking {
  id: number;
  company_id: number;
  booking_number: string;
  contact_id: number | null;
  contact?: Contact | null;
  start_time: string;
  end_time: string | null;
  status: string;
  created_at: string;
}
