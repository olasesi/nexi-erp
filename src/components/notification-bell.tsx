import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell, CheckCheck } from "lucide-react";
import { Link } from "react-router-dom";

import {
  getNotifications,
  getUnreadNotificationCount,
  markAllNotificationsRead,
  markNotificationRead,
} from "@/lib/api";
import { cn } from "@/lib/cn";
import { formatRelative } from "@/lib/format";

export function NotificationBell() {
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const queryClient = useQueryClient();

  const unreadQuery = useQuery({
    queryKey: ["notifications", "unread-count"],
    queryFn: getUnreadNotificationCount,
    refetchInterval: 60_000,
  });

  const recentQuery = useQuery({
    queryKey: ["notifications", "recent"],
    queryFn: () => getNotifications({ perPage: 6 }),
    enabled: open,
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["notifications"] });

  const markAll = useMutation({ mutationFn: markAllNotificationsRead, onSuccess: invalidate });
  const markOne = useMutation({ mutationFn: markNotificationRead, onSuccess: invalidate });

  useEffect(() => {
    if (!open) return;

    const closeOnOutside = (event: PointerEvent) => {
      if (root.current && !root.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };

    document.addEventListener("pointerdown", closeOnOutside);
    document.addEventListener("keydown", closeOnEscape);
    return () => {
      document.removeEventListener("pointerdown", closeOnOutside);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, [open]);

  const unread = unreadQuery.data?.unread_count ?? 0;
  const recent = recentQuery.data?.data ?? [];
  const pending = markAll.isPending || markOne.isPending;

  return (
    <div className="notification-bell" ref={root}>
      <button
        type="button"
        className="notification-bell__trigger"
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
      >
        <Bell size={18} aria-hidden="true" />
        {unread > 0 && (
          <span className="notification-bell__badge" data-testid="unread-badge">
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="notification-panel" role="dialog" aria-label="Notifications">
          <div className="notification-panel__header">
            <p className="notification-panel__title">Notifications</p>
            <button
              type="button"
              className="notification-panel__action"
              onClick={() => markAll.mutate()}
              disabled={pending || unread === 0}
            >
              <CheckCheck size={14} aria-hidden="true" />
              Mark all read
            </button>
          </div>

          {recentQuery.isLoading && <p className="state__empty">Loading notifications…</p>}
          {recentQuery.isError && (
            <p className="state__empty">Notifications could not be loaded.</p>
          )}
          {recentQuery.isSuccess && recent.length === 0 && (
            <p className="state__empty">You&rsquo;re all caught up.</p>
          )}

          {recent.length > 0 && (
            <ul className="notification-panel__list">
              {recent.map((item) => (
                <li
                  key={item.id}
                  className={cn("notification-item", !item.is_read && "notification-item--unread")}
                >
                  <button
                    type="button"
                    className="notification-item__button"
                    onClick={() => {
                      if (!item.is_read) markOne.mutate(item.id);
                    }}
                  >
                    <span className="notification-item__title">{item.title}</span>
                    {item.body && <span className="notification-item__body">{item.body}</span>}
                    <span className="notification-item__time">
                      {formatRelative(item.created_at)}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}

          <Link
            to="/notifications"
            className="notification-panel__footer"
            onClick={() => setOpen(false)}
          >
            View all notifications
          </Link>
        </div>
      )}
    </div>
  );
}
