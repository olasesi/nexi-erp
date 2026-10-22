import { useCallback, useEffect, useState } from "react";

import { ErrorState, LoadingState } from "@/components/table";
import { clearSettingsCache, getSettings, sendTestEmail, updateSettingsGroup } from "@/lib/api";
import { logger } from "@/lib/logger";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
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
        className="form-field__select"
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
        className="form-field__textarea"
        rows={3}
        value={stringValue}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  return (
    <input
      type={field.type}
      className="form-field__input"
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
      <div className="page">
        <PageHeader title="Settings" />
        <LoadingState />
      </div>
    );
  }

  if (!meta || !values) {
    return (
      <div className="page">
        <PageHeader title="Settings" />
        <ErrorState message={message?.text ?? "Settings could not be loaded."} />
      </div>
    );
  }

  return (
    <div className="page">
      <PageHeader
        title="Settings"
        description="Appearance, system, currency and email configuration"
      />

      {message ? (
        <Alert variant={message.kind === "success" ? "success" : "error"} title="">
          {message.text}
        </Alert>
      ) : null}

      <div className="settings-layout">
        <nav className="settings-nav" aria-label="Settings groups">
          {GROUP_SPECS.map((spec) => (
            <button
              key={spec.key}
              type="button"
              onClick={() => setActive(spec.key)}
              aria-current={active === spec.key ? "page" : undefined}
              className={`settings-nav__item ${active === spec.key ? "settings-nav__item--active" : ""}`}
            >
              {spec.label}
            </button>
          ))}
        </nav>

        {activeSpec ? (
          <div className="settings-panels">
            <form
              key={activeSpec.key}
              className="card"
              onSubmit={(e) => {
                e.preventDefault();
                handleSave();
              }}
            >
              <div className="card__body">
                <div className="settings-group">
                  <h2 className="settings-group__title">{activeSpec.label}</h2>
                  <p className="settings-group__description">{activeSpec.description}</p>
                </div>

                <div className="settings-group__fields">
                  {activeSpec.fields.map((field) => (
                    <label key={field.key} className="form-field">
                      <span className="form-field__label">{field.label}</span>
                      <Control
                        field={field}
                        value={values[active]?.[field.key]}
                        meta={meta}
                        onChange={(value) => updateValue(active, field.key, value)}
                      />
                    </label>
                  ))}
                </div>

                <div className="settings-group__footer">
                  <Button type="submit" disabled={saving}>
                    {saving ? "Saving..." : "Save changes"}
                  </Button>
                </div>
              </div>
            </form>

            <aside className="settings-rail">
              <Card title="System" description={`Cache footprint: ${meta.cache_size} MB`}>
                <Button variant="secondary" block onClick={() => void handleClearCache()}>
                  Clear application cache
                </Button>
              </Card>

              <Card
                title="Email"
                description="Send a test message using the configured mail transport"
              >
                <div className="form-field">
                  <input
                    type="email"
                    value={testEmail}
                    placeholder="ops@example.com"
                    className="form-field__input"
                    onChange={(e) => setTestEmail(e.target.value)}
                  />
                </div>
                <Button
                  variant="secondary"
                  block
                  disabled={sending || !testEmail.trim()}
                  onClick={() => void handleSendTestEmail()}
                >
                  {sending ? "Sending..." : "Send test email"}
                </Button>
              </Card>
            </aside>
          </div>
        ) : null}
      </div>
    </div>
  );
}
