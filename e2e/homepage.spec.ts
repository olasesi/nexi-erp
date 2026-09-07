import { expect, test } from "@playwright/test";

test("homepage loads and redirects to dashboard", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { name: /dashboard/i })).toBeVisible();
});

test("sidebar navigation is visible with all modules", async ({ page }) => {
  await page.goto("/");

  const modules = [
    "Dashboard",
    "Companies",
    "Contacts",
    "Products",
    "Warehouses",
    "Sales Orders",
    "Purchase Orders",
  ];

  for (const name of modules) {
    await expect(page.getByRole("link", { name })).toBeVisible();
  }
});

test("navigating to a module loads its page", async ({ page }) => {
  await page.goto("/");
  await page.getByRole("link", { name: "Companies" }).click();
  await expect(page.getByRole("heading", { name: /companies/i })).toBeVisible();
});