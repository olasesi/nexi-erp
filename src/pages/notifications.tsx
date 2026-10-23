import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCheck } from "lucide-react";

import { getNotifications, markAllNotificationsRead, markNotificationRead } from "@/lib/api";
import { cn } from "@/lib/cn";
import { formatRelative } from "@/lib/format";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { EmptyState, ErrorState, LoadingState } from "@/components/ui/states";

export function NotificationsPage() {
  const [unreadOnly, setUnreadOnly] = useState(false);
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["notifications", "list", unreadOnly],
    queryFn: () => getNotifications({ unread: unreadOnly, perPage: 50 }),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ["notifications"] });
  const markAll = useMutation({ mutationFn: markAllNotificationsRead, onSuccess: invalidate });
  const markOne = useMutation({ mutationFn: markNotificationRead, onSuccess: invalidate });

  const items = query.data?.data ?? [];
  const unread = items.filter((item) => !item.is_read).length;

  return (
    <div className="page">
      <PageHeader
        title="Notifications"
        description="Company-wide announcements and messages addressed to you."
        actions={
          <Button
            icon={<CheckCheck size={16} aria-hidden="true" />}
            onClick={() => markAll.mutate()}
            disabled={markAll.isPending || unread === 0}
          >
            Mark all read
          </Button>
        }
      />

      <div className="tabs" role="tablist" aria-label="Notification filters">
        {[
          { label: "All", value: false },
          { label: "Unread", value: true },
        ].map((tab) => (
          <button
            key={tab.label}
            type="button"
            role="tab"
            aria-selected={unreadOnly === tab.value}
            className={cn("tabs__tab", unreadOnly === tab.value && "tabs__tab--active")}
            onClick={() => setUnreadOnly(tab.value)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <Card flush>
        {query.isLoading && <LoadingState label="Loading notifications…" />}
        {query.isError && <ErrorState error={query.error} onRetry={() => void query.refetch()} />}
        {query.isSuccess && items.length === 0 && (
          <EmptyState
            title="Nothing here"
            description={
              unreadOnly ? "You have read everything." : "Notifications will appear here."
            }
          />
        )}

        {items.length > 0 && (
          <ul className="divided-list">
            {items.map((item) => (
              <li key={item.id} className="divided-list__item">
                <span
                  className={cn("notification-dot", item.is_read && "notification-dot--read")}
                  aria-hidden="true"
                />
                <div className="divided-list__body">
                  <div className="divided-list__head">
                    <p className="divided-list__title">{item.title}</p>
                    <span className="tally">{formatRelative(item.created_at)}</span>
                  </div>
                  {item.body && <p className="divided-list__text">{item.body}</p>}
                </div>
                {!item.is_read && (
                  <Button
                    size="sm"
                    variant="ghost"
                    disabled={markOne.isPending}
                    onClick={() => markOne.mutate(item.id)}
                  >
                    Mark read
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
