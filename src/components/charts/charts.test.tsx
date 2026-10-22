import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { DonutChart } from "@/components/charts/donut";
import { FunnelChart } from "@/components/charts/funnel";
import { HorizontalBars } from "@/components/charts/horizontal";
import { RadialGauge } from "@/components/charts/radial";

describe("FunnelChart", () => {
  it("renders each step and the share of the first step", () => {
    render(
      <FunnelChart
        data={[
          { name: "new", value: 10 },
          { name: "won", value: 4 },
        ]}
      />,
    );

    expect(screen.getByTestId("funnel-chart")).toBeInTheDocument();
    expect(screen.getByText("New")).toBeInTheDocument();
    expect(screen.getByText("Won")).toBeInTheDocument();
    expect(screen.getByText("40%")).toBeInTheDocument();
  });

  it("shows an empty message when nothing entered the funnel", () => {
    render(<FunnelChart data={[{ name: "new", value: 0 }]} />);

    expect(screen.getByText("No records yet")).toBeInTheDocument();
  });
});

describe("RadialGauge", () => {
  it("exposes the score to assistive technology", () => {
    render(<RadialGauge value={72} label="Overall score" />);

    expect(screen.getByRole("img", { name: "Overall score: 72" })).toBeInTheDocument();
  });

  it("renders a dash when the area is unscored", () => {
    render(<RadialGauge value={null} label="Score" />);

    expect(screen.getByRole("img", { name: "Score: —" })).toBeInTheDocument();
  });
});

describe("DonutChart", () => {
  it("lists every non-empty slice and drops empty ones", () => {
    render(
      <DonutChart
        data={[
          { name: "cash", value: 5 },
          { name: "card", value: 3 },
          { name: "cheque", value: 0 },
        ]}
      />,
    );

    expect(screen.getByText("Cash")).toBeInTheDocument();
    expect(screen.getByText("Card")).toBeInTheDocument();
    expect(screen.queryByText("Cheque")).not.toBeInTheDocument();
  });

  it("shows an empty state", () => {
    render(<DonutChart data={[]} />);

    expect(screen.getByText("Nothing recorded yet")).toBeInTheDocument();
  });
});

describe("HorizontalBars", () => {
  it("renders rows and falls back to an empty state", () => {
    const { rerender } = render(<HorizontalBars rows={[{ label: "Acme", value: 100 }]} />);

    expect(screen.getByText("Acme")).toBeInTheDocument();

    rerender(<HorizontalBars rows={[]} />);
    expect(screen.getByText("No records yet.")).toBeInTheDocument();
  });
});
