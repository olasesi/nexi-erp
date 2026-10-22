import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { Stat } from "@/components/ui/stat";

describe("Stat", () => {
  it("renders the label and a formatted currency value", () => {
    render(<Stat label="Revenue" value={1234.5} currency />);

    expect(screen.getByText("Revenue")).toBeInTheDocument();
    expect(screen.getByTestId("stat-value")).toHaveTextContent("$1,234.50");
  });

  it("renders a dash when the value is missing", () => {
    render(<Stat label="Profit" value={null} />);

    expect(screen.getByTestId("stat-value")).toHaveTextContent("—");
  });

  it("shows the change percentage and hint", () => {
    render(<Stat label="Revenue" value={10} change={12.5} hint="vs last month" />);

    expect(screen.getByText(/12\.5%/)).toBeInTheDocument();
    expect(screen.getByText("vs last month")).toBeInTheDocument();
  });

  it("keeps a unit suffix separate from the number", () => {
    render(<Stat label="Conversion" value={42} unit="%" />);

    expect(screen.getByTestId("stat-value")).toHaveTextContent("42%");
  });
});
