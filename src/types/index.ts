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
  company: Company | null;
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
