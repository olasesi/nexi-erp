export function formatNumber(value: number, maximumFractionDigits = 2): string {
  return new Intl.NumberFormat("en-US", { maximumFractionDigits }).format(value);
}

export function formatCurrency(value: number, currency = "USD"): string {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(value);
}

export function formatPercent(value: number, fractionDigits = 1): string {
  return `${new Intl.NumberFormat("en-US", {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  }).format(value)}%`;
}

export function formatMonth(ym: string): string {
  const date = new Date(`${ym}-01T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return ym;
  }
  return date.toLocaleDateString("en-US", { month: "short", year: "2-digit" });
}

export function prettifySlug(slug: string): string {
  return slug
    .split("_")
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

export interface CsvColumn {
  key: string;
  label: string;
}

export function toCsv(
  rows: ReadonlyArray<Readonly<Record<string, unknown>>>,
  columns: CsvColumn[],
): string {
  const escape = (value: unknown): string => {
    const text = value === null || value === undefined ? "" : String(value);
    return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  };
  const header = columns.map((column) => escape(column.label)).join(",");
  const body = rows
    .map((row) => columns.map((column) => escape(row[column.key])).join(","))
    .join("\n");
  return `${header}\n${body}\n`;
}

export function downloadCsv(filename: string, csv: string): void {
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}
