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
