import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/lib/api", () => ({
  getNotifications: vi.fn(),
  getUnreadNotificationCount: vi.fn(),
  markAllNotificationsRead: vi.fn(),
  markNotificationRead: vi.fn(),
}));

import * as api from "@/lib/api";
import { NotificationBell } from "@/components/notification-bell";
import type { AppNotification } from "@/types";

const NOTIFICATIONS: AppNotification[] = [
  {
    id: 11,
    company_id: 1,
    user_id: null,
    type: "invoice",
    title: "Invoice INV-1001 is overdue",
    body: "Acme Corp has not paid the balance yet.",
    payload: null,
    read_at: null,
    is_read: false,
    created_at: new Date(Date.now() - 5 * 60_000).toISOString(),
    updated_at: new Date(Date.now() - 5 * 60_000).toISOString(),
  },
  {
    id: 12,
    company_id: 1,
    user_id: 1,
    type: "system",
    title: "Weekly summary ready",
    body: null,
    payload: null,
    read_at: new Date().toISOString(),
    is_read: true,
    created_at: new Date(Date.now() - 26 * 60 * 60_000).toISOString(),
    updated_at: new Date().toISOString(),
  },
];

function renderBell() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <NotificationBell />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("NotificationBell", () => {
  beforeEach(() => {
    vi.mocked(api.getUnreadNotificationCount).mockResolvedValue({ unread_count: 3 });
    vi.mocked(api.getNotifications).mockResolvedValue({ data: NOTIFICATIONS });
    vi.mocked(api.markAllNotificationsRead).mockResolvedValue({ updated: 3 });
    vi.mocked(api.markNotificationRead).mockResolvedValue({ data: NOTIFICATIONS[0] });
  });

  it("shows the unread badge and lists recent notifications", async () => {
    const user = userEvent.setup();
    renderBell();

    expect(await screen.findByTestId("unread-badge")).toHaveTextContent("3");

    await user.click(screen.getByRole("button", { name: "Notifications, 3 unread" }));

    expect(await screen.findByText("Invoice INV-1001 is overdue")).toBeInTheDocument();
    expect(screen.getByText("Weekly summary ready")).toBeInTheDocument();
    expect(api.getNotifications).toHaveBeenCalledWith({ perPage: 6 });
    expect(screen.getByRole("link", { name: "View all notifications" })).toHaveAttribute(
      "href",
      "/notifications",
    );
  });

  it("marks every notification as read from the panel", async () => {
    const user = userEvent.setup();
    renderBell();

    await screen.findByTestId("unread-badge");
    await user.click(screen.getByRole("button", { name: "Notifications, 3 unread" }));
    await user.click(await screen.findByRole("button", { name: /Mark all read/ }));

    expect(api.markAllNotificationsRead).toHaveBeenCalledTimes(1);
  });

  it("marks a single notification when its row is clicked", async () => {
    const user = userEvent.setup();
    renderBell();

    await screen.findByTestId("unread-badge");
    await user.click(screen.getByRole("button", { name: "Notifications, 3 unread" }));
    await user.click(await screen.findByText("Invoice INV-1001 is overdue"));

    const mock = vi.mocked(api.markNotificationRead);
    expect(mock).toHaveBeenCalledTimes(1);
    expect(mock.mock.calls[0][0]).toBe(11);
  });
});
