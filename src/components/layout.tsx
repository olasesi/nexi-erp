import { useEffect, useState } from "react";
import type { ReactNode } from "react";
import { NavLink } from "react-router-dom";

import { getAddons } from "@/lib/api";

const navigation = [
  { name: "Dashboard", href: "/dashboard" },
  { name: "Companies", href: "/companies" },
  { name: "Contacts", href: "/contacts" },
  { name: "Products", href: "/products" },
  { name: "Warehouses", href: "/warehouses" },
  { name: "Sales Orders", href: "/sales-orders" },
  { name: "Purchase Orders", href: "/purchase-orders" },
  { name: "Settings", href: "/settings" },
];

const SMART_DASHBOARD = { name: "Smart Dashboard", href: "/smart-dashboard" };

export function Layout({ children }: { children: ReactNode }) {
  const [smartDashboardEnabled, setSmartDashboardEnabled] = useState(true);

  useEffect(() => {
    let cancelled = false;

    getAddons()
      .then(({ data }) => {
        const addon = data.find((item) => item.id === "smart-dashboard");
        if (!cancelled && addon) {
          setSmartDashboardEnabled(addon.enabled || addon.enabled_by_default);
        }
      })
      .catch(() => {
        // Keep the entry visible by default when the catalog cannot be read.
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const items = smartDashboardEnabled ? [SMART_DASHBOARD, ...navigation] : navigation;

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="flex">
        <aside className="flex h-screen w-56 shrink-0 flex-col border-r border-gray-200 bg-white">
          <div className="flex h-16 items-center border-b border-gray-200 px-4">
            <h1 className="text-lg font-bold text-gray-900">Nexi ERP</h1>
          </div>
          <nav className="flex-1 space-y-1 overflow-y-auto p-3">
            {items.map((item) => (
              <NavLink
                key={item.href}
                to={item.href}
                className={({ isActive }) =>
                  `flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? "bg-blue-50 text-blue-700"
                      : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                  }`
                }
              >
                <span>{item.name}</span>
                {item.href === "/smart-dashboard" && (
                  <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                    Free
                  </span>
                )}
              </NavLink>
            ))}
          </nav>
        </aside>
        <main className="flex-1 overflow-auto p-8">{children}</main>
      </div>
    </div>
  );
}
