import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import {
  downloadCsv,
  formatCurrency,
  formatMonth,
  formatNumber,
  formatPercent,
  formatRelative,
  prettifySlug,
  toCsv,
} from "./format";

describe("formatters", () => {
  it("formats numbers with thousands separators", () => {
    expect(formatNumber(1234.567, 2)).toBe("1,234.57");
    expect(formatNumber(12, 0)).toBe("12");
  });

  it("formats currency", () => {
    expect(formatCurrency(1000.5)).toBe("$1,000.50");
    expect(formatCurrency(-42, "EUR")).toBe("-€42.00");
  });

  it("formats percentages", () => {
    expect(formatPercent(42.536, 1)).toBe("42.5%");
    expect(formatPercent(0)).toBe("0.0%");
  });

  it("formats month slugs", () => {
    expect(formatMonth("2026-10")).toBe("Oct 26");
    expect(formatMonth("not-a-date")).toBe("not-a-date");
  });

  it("formats timestamps relative to now", () => {
    const now = new Date("2026-10-23T12:00:00Z").getTime();
    expect(formatRelative("2026-10-23T11:59:40Z", now)).toBe("just now");
    expect(formatRelative("2026-10-23T11:30:00Z", now)).toBe("30m ago");
    expect(formatRelative("2026-10-23T08:00:00Z", now)).toBe("4h ago");
    expect(formatRelative("2026-10-20T12:00:00Z", now)).toBe("3d ago");
    expect(formatRelative("2026-09-01T12:00:00Z", now)).toMatch(/2026/);
    expect(formatRelative(null, now)).toBe("\u2014");
  });

  it("prettifies snake_case slugs", () => {
    expect(prettifySlug("monthly_net_profit")).toBe("Monthly Net Profit");
    expect(prettifySlug("new")).toBe("New");
    expect(prettifySlug("")).toBe("");
  });
});

describe("csv", () => {
  const columns = [
    { key: "name", label: "Customer" },
    { key: "total", label: "Total" },
  ];

  it("renders a csv with escaping", () => {
    const csv = toCsv(
      [
        { name: "Acme, Inc.", total: 1000 },
        { name: 'Say "Hi"', total: null },
      ],
      columns,
    );
    expect(csv).toBe('Customer,Total\n"Acme, Inc.",1000\n"Say ""Hi""",\n');
  });

  it("downloads a csv through a temporary anchor", () => {
    const createUrl = vi.fn(() => "blob:csv");
    const revokeUrl = vi.fn(() => undefined);
    Object.defineProperty(URL, "createObjectURL", { writable: true, value: createUrl });
    Object.defineProperty(URL, "revokeObjectURL", { writable: true, value: revokeUrl });
    const click = vi
      .spyOn(HTMLAnchorElement.prototype, "click")
      .mockImplementation(() => undefined);

    downloadCsv("report.csv", "a,b\n1,2\n");

    expect(createUrl).toHaveBeenCalledTimes(1);
    expect(click).toHaveBeenCalledTimes(1);
    expect(revokeUrl).toHaveBeenCalledWith("blob:csv");
  });
});

beforeEach(() => {
  document.body.innerHTML = "";
});

afterEach(() => {
  vi.restoreAllMocks();
});
