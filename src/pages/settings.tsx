import { useCallback, useEffect, useState } from "react";

import { ErrorState, LoadingState } from "@/components/table";
import { clearSettingsCache, getSettings, sendTestEmail, updateSettingsGroup } from "@/lib/api";
import { logger } from "@/lib/logger";
import type { AppSettings, SettingsMeta, SettingsValue } from "@/types";

type FieldType = "text" | "number" | "email" | "password" | "url" | "textarea" | "select";

interface FieldSpec {
  key: string;
  label: string;
  type: FieldType;
  placeholder?: string;
  options?: (meta: SettingsMeta) => { value: string; label: string }[];
}

interface GroupSpec {
  key: string;
  label: string;
  description: string;
  fields: FieldSpec[];
}

const byMeta =
  (pick: (meta: SettingsMeta) => readonly string[] | Record<string, string>) =>
  (meta: SettingsMeta) => {
    const source = pick(meta);
    return Array.isArray(source)
      ? source.map((value) => ({ value, label: value }))
      : Object.entries(source).map(([value, label]) => ({ value, label }));
  };

const ON_OFF_OPTIONS = () => [
  { value: "on", label: "On" },
  { value: "off", label: "Off" },
];

const GROUP_SPECS: GroupSpec[] = [
  {
    key: "brand",
    label: "Brand",
    description: "Logo, footer and the visual theme.",
    fields: [
      { key: "titleText", label: "Application title", type: "text" },
      { key: "footerText", label: "Footer text", type: "text" },
      { key: "logo_light", label: "Logo (light)", type: "text", placeholder: "URL" },
      { key: "logo_dark", label: "Logo (dark)", type: "text", placeholder: "URL" },
      { key: "favicon", label: "Favicon", type: "text", placeholder: "URL" },
      {
        key: "sidebarVariant",
        label: "Sidebar variant",
        type: "select",
        options: byMeta((m) => m.sidebar_variants),
      },
      { key: "sidebarStyle", label: "Sidebar style", type: "text" },
      {
        key: "layoutDirection",
        label: "Layout direction",
        type: "select",
        options: byMeta((m) => m.layout_directions),
      },
      { key: "themeMode", label: "Theme mode", type: "select", options: byMeta((m) => m.themes) },
      {
        key: "themeColor",
        label: "Theme color",
        type: "select",
        options: byMeta((m) => m.theme_colors),
      },
      { key: "customColor", label: "Custom color", type: "text", placeholder: "#RRGGBB" },
    ],
  },
  {
    key: "dashboard",
    label: "Dashboard",
    description: "Default layout and density for new users.",
    fields: [
      {
        key: "layout",
        label: "Layout",
        type: "select",
        options: (meta) =>
          Object.entries(meta.dashboard_layouts).map(([value, def]) => ({
            value,
            label: def.label,
          })),
      },
      {
        key: "density",
        label: "Density",
        type: "select",
        options: () => [
          { value: "comfortable", label: "Comfortable" },
          { value: "compact", label: "Compact" },
        ],
      },
    ],
  },
  {
    key: "system",
    label: "System",
    description: "Language, formats and registration behaviour.",
    fields: [
      {
        key: "defaultLanguage",
        label: "Default language",
        type: "select",
        options: byMeta((m) => m.available_languages),
      },
      {
        key: "dateFormat",
        label: "Date format",
        type: "select",
        options: byMeta((m) => m.date_formats),
      },
      {
        key: "timeFormat",
        label: "Time format",
        type: "select",
        options: byMeta((m) => m.time_formats),
      },
      {
        key: "calendarStartDay",
        label: "Week starts on",
        type: "select",
        options: byMeta((m) => m.calendar_start_days),
      },
      {
        key: "enableRegistration",
        label: "Self registration",
        type: "select",
        options: ON_OFF_OPTIONS,
      },
      {
        key: "enableEmailVerification",
        label: "Require email verification",
        type: "select",
        options: ON_OFF_OPTIONS,
      },
      {
        key: "landingPageEnabled",
        label: "Landing page",
        type: "select",
        options: ON_OFF_OPTIONS,
      },
      { key: "termsConditionsUrl", label: "Terms & conditions URL", type: "url" },
    ],
  },
  {
    key: "currency",
    label: "Currency",
    description: "Default currency and number formatting.",
    fields: [
      {
        key: "defaultCurrency",
        label: "Default currency",
        type: "select",
        options: (meta) =>
          meta.currencies.map((c) => ({ value: c.code, label: `${c.code} — ${c.name}` })),
      },
      {
        key: "currency_format",
        label: "Currency format",
        type: "select",
        options: () => ["0", "1", "2", "3"].map((value) => ({ value, label: value })),
      },
      {
        key: "decimalFormat",
        label: "Decimal format",
        type: "select",
        options: () => ["0", "1", "2", "3"].map((value) => ({ value, label: value })),
      },
      { key: "decimalSeparator", label: "Decimal separator", type: "text" },
      { key: "thousandsSeparator", label: "Thousands separator", type: "text" },
      { key: "floatNumber", label: "Float precision", type: "number" },
      { key: "currencySymbolSpace", label: "Symbol spacing", type: "text" },
      {
        key: "currencySymbolPosition",
        label: "Symbol position",
        type: "select",
        options: byMeta((m) => m.currency_symbol_positions),
      },
    ],
  },
  {
    key: "seo",
    label: "SEO",
    description: "Search engine metadata for the dashboard.",
    fields: [
      { key: "metaTitle", label: "Meta title", type: "text" },
      { key: "metaKeywords", label: "Meta keywords", type: "text" },
      { key: "metaDescription", label: "Meta description", type: "textarea" },
      { key: "metaImage", label: "Meta image", type: "url" },
    ],
  },
  {
    key: "cookie",
    label: "Cookie",
    description: "Cookie consent banner copy and behaviour.",
    fields: [
      {
        key: "enableCookiePopup",
        label: "Show consent banner",
        type: "select",
        options: ON_OFF_OPTIONS,
      },
      { key: "enableLogging", label: "Consent logging", type: "select", options: ON_OFF_OPTIONS },
      {
        key: "strictlyNecessaryCookies",
        label: "Strictly necessary cookies",
        type: "select",
        options: ON_OFF_OPTIONS,
      },
      { key: "cookieTitle", label: "Banner title", type: "text" },
      { key: "strictlyCookieTitle", label: "Necessary cookies title", type: "text" },
      { key: "cookieDescription", label: "Banner description", type: "textarea" },
      {
        key: "strictlyCookieDescription",
        label: "Necessary cookies description",
        type: "textarea",
      },
    ],
  },
  {
    key: "storage",
    label: "Storage",
    description: "Where uploaded files live and their limits.",
    fields: [
      {
        key: "storageType",
        label: "Storage backend",
        type: "select",
        options: byMeta((m) => m.storage_types),
      },
      {
        key: "allowedFileTypes",
        label: "Allowed file types",
        type: "text",
        placeholder: "png,jpg,pdf",
      },
      { key: "maxUploadSize", label: "Max upload size (KB)", type: "number" },
    ],
  },
  {
    key: "email",
    label: "Email",
    description: "Outgoing mail transport. The password is encrypted at rest.",
    fields: [
      {
        key: "mailDriver",
        label: "Mail driver",
        type: "select",
        options: (meta) => meta.email_providers.map((p) => ({ value: p.code, label: p.name })),
      },
      { key: "mailHost", label: "Host", type: "text" },
      { key: "mailPort", label: "Port", type: "number" },
      {
        key: "mailEncryption",
        label: "Encryption",
        type: "select",
        options: () => [
          { value: "", label: "None" },
          { value: "tls", label: "TLS" },
          { value: "ssl", label: "SSL" },
        ],
      },
      { key: "mailUsername", label: "Username", type: "text" },
      { key: "mailPassword", label: "Password", type: "password" },
      { key: "mailFromAddress", label: "From address", type: "email" },
      { key: "mailFromName", label: "From name", type: "text" },
    ],
  },
];

const INPUT_CLASS =
  "w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500";

function Control({
  field,
  value,
  meta,
  onChange,
}: {
  field: FieldSpec;
  value: SettingsValue | undefined;
  meta: SettingsMeta;
  onChange: (value: SettingsValue) => void;
}) {
  const stringValue = value === null || value === undefined ? "" : String(value);

  if (field.type === "select") {
    return (
      <select
        className={INPUT_CLASS}
        value={stringValue}
        onChange={(e) => onChange(e.target.value)}
      >
        {field.options?.(meta).map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    );
  }

  if (field.type === "textarea") {
    return (
      <textarea
        className={INPUT_CLASS}
        rows={3}
        value={stringValue}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  return (
    <input
      type={field.type}
      className={INPUT_CLASS}
      value={stringValue}
      placeholder={field.placeholder}
      autoComplete={field.type === "password" ? "new-password" : undefined}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

export function SettingsPage() {
  const [meta, setMeta] = useState<SettingsMeta | null>(null);
  const [values, setValues] = useState<AppSettings | null>(null);
  const [active, setActive] = useState(GROUP_SPECS[0].key);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);
  const [testEmail, setTestEmail] = useState("");
  const [message, setMessage] = useState<{ kind: "success" | "error"; text: string } | null>(null);

  const load = useCallback(() => {
    getSettings()
      .then((res) => {
        setMeta(res.meta);
        setValues(res.data);
        setMessage(null);
      })
      .catch((error) => {
        logger.error("Failed to load settings", { error });
        setMessage({ kind: "error", text: (error as Error).message });
        setValues(null);
        setMeta(null);
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const activeSpec = GROUP_SPECS.find((spec) => spec.key === active);

  function updateValue(group: string, key: string, value: SettingsValue) {
    setValues((prev) => {
      if (!prev) return prev;
      return { ...prev, [group]: { ...prev[group], [key]: value } };
    });
  }

  async function handleSave() {
    if (!values) return;
    const group = values[active];
    if (!group) return;

    setSaving(true);
    setMessage(null);
    try {
      const res = await updateSettingsGroup(active, group);
      setValues((prev) => (prev ? { ...prev, [active]: res.data } : prev));
      setMessage({ kind: "success", text: res.message ?? "Settings saved." });
    } catch (error) {
      setMessage({ kind: "error", text: (error as Error).message });
    } finally {
      setSaving(false);
    }
  }

  async function handleClearCache() {
    setMessage(null);
    try {
      const res = await clearSettingsCache();
      setMessage({ kind: "success", text: `Cache cleared (${res.cache_size} MB).` });
    } catch (error) {
      setMessage({ kind: "error", text: (error as Error).message });
    }
  }

  async function handleSendTestEmail() {
    if (!testEmail.trim()) return;

    setSending(true);
    setMessage(null);
    try {
      const res = await sendTestEmail(testEmail.trim());
      setMessage({ kind: "success", text: res.message });
    } catch (error) {
      setMessage({ kind: "error", text: (error as Error).message });
    } finally {
      setSending(false);
    }
  }

  if (loading) {
    return (
      <div>
        <header className="mb-8">
          <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
        </header>
        <LoadingState />
      </div>
    );
  }

  if (!meta || !values) {
    return (
      <div>
        <header className="mb-8">
          <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
        </header>
        <ErrorState message={message?.text ?? "Settings could not be loaded."} />
      </div>
    );
  }

  return (
    <div>
      <header className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
        <p className="mt-1 text-gray-600">Appearance, system, currency and email configuration</p>
      </header>

      {message ? (
        <div
          className={`mb-6 rounded-xl border p-4 text-sm ${
            message.kind === "success"
              ? "border-green-200 bg-green-50 text-green-700"
              : "border-red-200 bg-red-50 text-red-700"
          }`}
        >
          {message.text}
        </div>
      ) : null}

      <div className="mb-6 flex flex-wrap gap-1 border-b border-gray-200">
        {GROUP_SPECS.map((spec) => (
          <button
            key={spec.key}
            type="button"
            onClick={() => setActive(spec.key)}
            className={`rounded-t-lg px-4 py-2 text-sm font-medium transition-colors ${
              active === spec.key
                ? "border-b-2 border-blue-600 text-blue-700"
                : "text-gray-500 hover:text-gray-700"
            }`}
          >
            {spec.label}
          </button>
        ))}
      </div>

      {activeSpec ? (
        <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
          <form
            key={activeSpec.key}
            className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm"
            onSubmit={(e) => {
              e.preventDefault();
              handleSave();
            }}
          >
            <h2 className="text-sm font-semibold text-gray-900">{activeSpec.label}</h2>
            <p className="mt-1 text-xs text-gray-500">{activeSpec.description}</p>

            <div className="mt-6 grid gap-4 sm:grid-cols-2">
              {activeSpec.fields.map((field) => (
                <label key={field.key} className="block">
                  <span className="mb-1 block text-xs font-medium text-gray-600">
                    {field.label}
                  </span>
                  <Control
                    field={field}
                    value={values[active]?.[field.key]}
                    meta={meta}
                    onChange={(value) => updateValue(active, field.key, value)}
                  />
                </label>
              ))}
            </div>

            <div className="mt-6 flex justify-end">
              <button
                type="submit"
                disabled={saving}
                className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {saving ? "Saving..." : "Save changes"}
              </button>
            </div>
          </form>

          <aside className="space-y-6">
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
              <h2 className="text-sm font-semibold text-gray-900">System</h2>
              <p className="mt-1 text-xs text-gray-500">Cache footprint: {meta.cache_size} MB</p>
              <button
                type="button"
                onClick={handleClearCache}
                className="mt-4 w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
              >
                Clear application cache
              </button>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
              <h2 className="text-sm font-semibold text-gray-900">Email</h2>
              <p className="mt-1 text-xs text-gray-500">
                Send a test message using the configured mail transport
              </p>
              <input
                type="email"
                value={testEmail}
                placeholder="ops@example.com"
                className={`${INPUT_CLASS} mt-4`}
                onChange={(e) => setTestEmail(e.target.value)}
              />
              <button
                type="button"
                disabled={sending || !testEmail.trim()}
                onClick={handleSendTestEmail}
                className="mt-3 w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {sending ? "Sending..." : "Send test email"}
              </button>
            </div>
          </aside>
        </div>
      ) : null}
    </div>
  );
}
