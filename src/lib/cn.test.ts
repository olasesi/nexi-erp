import { describe, expect, it } from "vitest";

import { cn } from "@/lib/cn";

describe("cn", () => {
  it("joins conditional classes", () => {
    const includeExtra = 1 > 2;

    expect(cn("a", includeExtra && "b", "c")).toBe("a c");
  });

  it("joins BEM block modifiers", () => {
    const compact = 1 > 2;

    expect(cn("btn", "btn--primary", compact && "btn--lg", "btn--sm")).toBe(
      "btn btn--primary btn--sm",
    );
  });

  it("keeps unrelated classes", () => {
    expect(cn("card", "stack--6", "text-sm")).toBe("card stack--6 text-sm");
  });
});
