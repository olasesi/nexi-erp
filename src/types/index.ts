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

export interface SalesData {
  kpis: SalesKpis;
  lead_funnel: Record<string, number>;
  leads_by_source: Record<string, { count: number; converted: number }>;
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
