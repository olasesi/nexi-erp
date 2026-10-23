import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return { ...actual, getAuditLogs: vi.fn() };
});

import * as api from "@/lib/api";
import { AuditLogsPage } from "@/pages/audit-logs";
import type { AuditLogRow } from "@/types";

const ROWS: AuditLogRow[] = [
  {
    id: 7,
    company_id: 1,
    user_id: 3,
    event: "updated",
    auditable_type: "App\\Models\\Invoice",
    auditable_id: 42,
    old_values: { status: "draft" },
    new_values: { status: "posted" },
    ip_address: "203.0.113.5",
    created_at: "2026-10-23T09:15:00.000000Z",
  },
  {
    id: 6,
    company_id: 1,
    user_id: null,
    event: "deleted",
    auditable_type: "App\\Models\\Contact",
    auditable_id: 9,
    old_values: null,
    new_values: null,
    ip_address: null,
    created_at: "2026-10-22T18:00:00.000000Z",
  },
];

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <AuditLogsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("AuditLogsPage", () => {
  beforeEach(() => {
    vi.mocked(api.getAuditLogs).mockResolvedValue({
      data: ROWS,
      meta: { current_page: 1, last_page: 1, total: 2 },
    });
  });

  it("renders recent entries and expands a row into before/after JSON", async () => {
    const user = userEvent.setup();
    renderPage();

    expect(await screen.findByText("Invoice")).toBeInTheDocument();
    expect(screen.getByText("Contact")).toBeInTheDocument();
    expect(screen.getByText("#3")).toBeInTheDocument();
    expect(screen.getByText("System")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Show changes for Invoice" }));

    expect(screen.getByText(/"status": "draft"/)).toBeInTheDocument();
    expect(screen.getByText(/"status": "posted"/)).toBeInTheDocument();
  });

  it("refetches with the selected event filter", async () => {
    const user = userEvent.setup();
    renderPage();

    await screen.findByText("Invoice");
    await user.click(screen.getByRole("button", { name: "Deleted" }));

    await waitFor(() =>
      expect(api.getAuditLogs).toHaveBeenLastCalledWith(
        expect.objectContaining({ event: "deleted", page: 1 }),
      ),
    );
  });
});
