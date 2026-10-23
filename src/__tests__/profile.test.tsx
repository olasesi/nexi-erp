import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();
  return {
    ...actual,
    updateProfile: vi.fn(),
    changePassword: vi.fn(),
  };
});

import * as api from "@/lib/api";
import { useAuthStore } from "@/lib/auth";
import { ProfilePage } from "@/pages/profile";
import type { User } from "@/types";

const USER = {
  id: 1,
  name: "Ada Lovelace",
  email: "ada@nexi.test",
  username: "ada",
  phone: null,
  company: { id: 1, name: "Acme" },
} as User;

function renderProfile() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ProfilePage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("ProfilePage", () => {
  beforeEach(() => {
    useAuthStore.setState({
      user: USER,
      status: "authenticated",
      error: null,
      pending: false,
    });
    vi.mocked(api.updateProfile).mockResolvedValue({ ...USER, name: "Ada King" });
    vi.mocked(api.changePassword).mockResolvedValue({
      message: "Password changed successfully.",
    });
  });

  it("saves profile changes and updates the session user", async () => {
    const user = userEvent.setup();
    renderProfile();

    const name = screen.getByLabelText("Full name");
    await user.clear(name);
    await user.type(name, "Ada King");
    await user.click(screen.getByRole("button", { name: "Save profile" }));

    expect(vi.mocked(api.updateProfile).mock.calls[0][0]).toEqual({
      name: "Ada King",
      username: "ada",
      phone: null,
    });
    expect(await screen.findByText("Profile updated.")).toBeInTheDocument();
    expect(useAuthStore.getState().user?.name).toBe("Ada King");
  });

  it("blocks a password change when the confirmation does not match", async () => {
    const user = userEvent.setup();
    renderProfile();

    await user.type(screen.getByLabelText("Current password"), "old-password");
    await user.type(screen.getByLabelText("New password"), "new-password");
    await user.type(screen.getByLabelText("Confirm new password"), "different-password");
    await user.click(screen.getByRole("button", { name: "Change password" }));

    expect(screen.getByText("The new password confirmation does not match.")).toBeInTheDocument();
    expect(api.changePassword).not.toHaveBeenCalled();
  });

  it("submits a matching password change", async () => {
    const user = userEvent.setup();
    renderProfile();

    await user.type(screen.getByLabelText("Current password"), "old-password");
    await user.type(screen.getByLabelText("New password"), "new-password");
    await user.type(screen.getByLabelText("Confirm new password"), "new-password");
    await user.click(screen.getByRole("button", { name: "Change password" }));

    expect(vi.mocked(api.changePassword).mock.calls[0][0]).toEqual({
      current_password: "old-password",
      password: "new-password",
      password_confirmation: "new-password",
    });
    expect(await screen.findByText("Password changed successfully.")).toBeInTheDocument();
  });
});
