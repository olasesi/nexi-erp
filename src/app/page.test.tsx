import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import Home from "./page";

describe("Home page", () => {
  it("renders the ERP title", () => {
    render(<Home />);
    expect(screen.getByRole("heading", { name: /Nexi ERP/i })).toBeDefined();
  });

  it("renders all module links", () => {
    render(<Home />);
    for (const name of [
      "Companies",
      "Contacts",
      "Products",
      "Warehouses",
      "Sales Orders",
      "Purchase Orders",
    ]) {
      expect(screen.getByRole("link", { name: new RegExp(name, "i") })).toBeDefined();
    }
  });
});
