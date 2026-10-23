import { useEffect, useState, type ReactNode } from "react";
import { NavLink } from "react-router-dom";
import {
  Activity,
  BarChart3,
  Bell,
  Building2,
  CreditCard,
  LayoutDashboard,
  LogOut,
  MapPin,
  Package,
  Receipt,
  Settings,
  ShoppingCart,
  Sparkles,
  User,
  Users,
  Warehouse,
  Zap,
} from "lucide-react";

import { NotificationBell } from "@/components/notification-bell";
import { getAddons } from "@/lib/api";
import { cn } from "@/lib/cn";
import { useAuth } from "@/lib/use-auth";

interface NavItem {
  name: string;
  href: string;
  icon: typeof LayoutDashboard;
  badge?: string;
}

interface NavSection {
  label: string;
  items: NavItem[];
}

const SECTIONS: NavSection[] = [
  {
    label: "Overview",
    items: [
      { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
      { name: "Smart Dashboard", href: "/smart-dashboard", icon: Sparkles, badge: "Free" },
      { name: "Business Health", href: "/health", icon: Activity },
      { name: "Receivables", href: "/receivables", icon: Receipt },
      { name: "Reports", href: "/reports", icon: BarChart3 },
      { name: "Point of sale", href: "/pos", icon: CreditCard },
      { name: "Verticals", href: "/verticals", icon: Zap },
    ],
  },
  {
    label: "Operations",
    items: [
      { name: "Companies", href: "/companies", icon: Building2 },
      { name: "Contacts", href: "/contacts", icon: Users },
      { name: "Products", href: "/products", icon: Package },
      { name: "Warehouses", href: "/warehouses", icon: Warehouse },
      { name: "Sales Orders", href: "/sales-orders", icon: ShoppingCart },
      { name: "Purchase Orders", href: "/purchase-orders", icon: MapPin },
    ],
  },
  {
    label: "System",
    items: [
      { name: "Notifications", href: "/notifications", icon: Bell },
      { name: "Profile", href: "/profile", icon: User },
      { name: "Settings", href: "/settings", icon: Settings },
    ],
  },
];

/** First letter of the signed-in user's name, for the avatar chip. */
function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("");
}

export function Layout({ children }: { children: ReactNode }) {
  const [smartDashboardEnabled, setSmartDashboardEnabled] = useState(true);
  const { user, logout } = useAuth();

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

  const sections = SECTIONS.map((section) =>
    section.label === "Overview"
      ? {
          ...section,
          items: smartDashboardEnabled
            ? section.items
            : section.items.filter((item) => item.href !== "/smart-dashboard"),
        }
      : section,
  );

  return (
    <div className="app-layout">
      <aside className="app-sidebar">
        <div className="app-sidebar__brand">
          <span className="app-sidebar__logo" aria-hidden="true">
            N
          </span>
          <h1 className="app-sidebar__title">Nexi ERP</h1>
          <NotificationBell />
        </div>

        <nav className="app-sidebar__nav" aria-label="Main">
          {sections.map((section) => (
            <div key={section.label} className="nav-section">
              <p className="nav-section__label">{section.label}</p>
              <ul className="nav-section__list">
                {section.items.map((item) => (
                  <li key={item.href}>
                    <NavLink
                      to={item.href}
                      className={({ isActive }) => cn("nav-item", isActive && "nav-item--active")}
                    >
                      <item.icon className="nav-item__icon" aria-hidden="true" />
                      <span className="nav-item__label">{item.name}</span>
                      {item.badge && <span className="badge badge--emerald">{item.badge}</span>}
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </nav>

        {user && (
          <div className="app-sidebar__footer">
            <div className="user-block">
              <span className="user-block__avatar" aria-hidden="true">
                {initials(user.name)}
              </span>
              <div className="user-block__meta">
                <p className="user-block__name">{user.name}</p>
                <p className="user-block__role">{user.company?.name ?? user.email}</p>
              </div>
              <button
                type="button"
                className="user-block__signout"
                onClick={() => void logout()}
                title="Sign out"
                aria-label="Sign out"
              >
                <LogOut size={16} aria-hidden="true" />
              </button>
            </div>
          </div>
        )}
      </aside>

      <main className="app-main">
        <div className="app-main__inner">{children}</div>
      </main>
    </div>
  );
}
