import { logger } from "@/lib/logger";
import type {
  Company,
  Contact,
  PaginatedResponse,
  Product,
  ProductCategory,
  PurchaseOrder,
  SalesOrder,
  SettingsGroupResponse,
  SettingsResponse,
  User,
  Warehouse,
} from "@/types";

const API_BASE = import.meta.env.VITE_API_URL ?? "http://localhost:8000";

class ApiError extends Error {
  status: number;
  data: unknown;

  constructor(status: number, message: string, data?: unknown) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.data = data;
  }
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

export async function register(payload: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<{ user: User; token: string; refreshToken: string }> {
  const data = await request<{
    user: User;
    access_token: string;
    refresh_token: string;
  }>("/api/v1/register", {
    method: "POST",
    body: JSON.stringify(payload),
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
