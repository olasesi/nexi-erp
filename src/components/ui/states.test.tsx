import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { EmptyState, ErrorState } from "@/components/ui/states";

describe("ErrorState", () => {
  it("surfaces the error message", () => {
    render(<ErrorState error={new Error("boom")} />);

    expect(screen.getByTestId("error-state")).toHaveTextContent("boom");
  });

  it("explains a disabled add-on differently", () => {
    const error = Object.assign(new Error("disabled"), { data: { code: "addon_disabled" } });
    render(<ErrorState error={error} />);

    expect(screen.getByTestId("error-state")).toHaveTextContent("add-on is disabled");
  });

  it("invokes the retry handler", async () => {
    const onRetry = vi.fn();
    const { userEvent } = await import("@testing-library/user-event");

    render(<ErrorState error={new Error("boom")} onRetry={onRetry} />);
    await userEvent.click(screen.getByRole("button", { name: "Try again" }));

    expect(onRetry).toHaveBeenCalledTimes(1);
  });
});

describe("EmptyState", () => {
  it("renders a default title", () => {
    render(<EmptyState />);

    expect(screen.getByText("No data yet")).toBeInTheDocument();
  });
});
