import { test, expect } from "@playwright/test";

test("homepage loads and displays heading", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { name: /nexi erp/i })).toBeVisible();
});

test("homepage shows all module cards", async ({ page }) => {
  await page.goto("/");

  const modules = [
    "Companies",
    "Contacts",
    "Products",
    "Warehouses",
    "Sales Orders",
    "Purchase Orders",
  ];

  for (const name of modules) {
    await expect(page.getByText(name)).toBeVisible();
  }
});

test("navigation links work", async ({ page }) => {
  await page.goto("/");

  const link = page.getByRole("link", { name: /companies/i });
  await expect(link).toHaveAttribute("href", "/companies");
});
