import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes, useLocation } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { useAuthStore } from "@/lib/auth";
import { LoginPage } from "@/pages/login";

const fetchMock = vi.fn();

const USER = { id: 1, name: "Ada", email: "ada@nexi.test", company: null };

function Dashboard() {
  const location = useLocation();
  return <p>Landed on {location.pathname}</p>;
}

function renderLogin(initialEntry: string | { pathname: string; state?: unknown } = "/login") {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/receivables" element={<Dashboard />} />
      </Routes>
    </MemoryRouter>,
  );
}

beforeEach(() => {
  vi.stubGlobal("fetch", fetchMock);
  localStorage.clear();
  fetchMock.mockReset();
  useAuthStore.setState({ user: null, status: "unauthenticated", error: null, pending: false });
});

describe("LoginPage", () => {
  it("shows the sign-in form", () => {
    renderLogin();

    expect(screen.getByRole("heading", { name: "Welcome back" })).toBeInTheDocument();
    expect(screen.getByLabelText("Email address")).toBeInTheDocument();
    expect(screen.getByLabelText("Password")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Sign in" })).toBeInTheDocument();
  });

  it("signs in and forwards to the saved destination", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 200,
      ok: true,
      json: async () => ({ user: USER, access_token: "tok", refresh_token: "r" }),
    });

    renderLogin("/login");

    const user = userEvent.setup();
    await user.type(screen.getByLabelText("Email address"), "ada@nexi.test");
    await user.type(screen.getByLabelText("Password"), "secret");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("Landed on /dashboard")).toBeInTheDocument();
    expect(localStorage.getItem("auth_token")).toBe("tok");
  });

  it("lands on the destination the visitor was sent from", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 200,
      ok: true,
      json: async () => ({ user: USER, access_token: "tok", refresh_token: "r" }),
    });

    renderLogin({ pathname: "/login", state: { from: "/receivables" } });

    const user = userEvent.setup();
    await user.type(screen.getByLabelText("Email address"), "ada@nexi.test");
    await user.type(screen.getByLabelText("Password"), "secret");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("Landed on /receivables")).toBeInTheDocument();
  });

  it("surfaces the API error without leaving the page", async () => {
    fetchMock.mockResolvedValueOnce({
      status: 401,
      ok: false,
      json: async () => ({ message: "Invalid credentials" }),
    });

    renderLogin();

    const user = userEvent.setup();
    await user.type(screen.getByLabelText("Email address"), "ada@nexi.test");
    await user.type(screen.getByLabelText("Password"), "wrong");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    const alert = await screen.findByRole("alert");
    expect(alert).toHaveTextContent("Invalid credentials");
    expect(screen.getByRole("heading", { name: "Welcome back" })).toBeInTheDocument();
    expect(localStorage.getItem("auth_token")).toBeNull();
  });

  it("toggles password visibility", async () => {
    renderLogin();

    const field = screen.getByLabelText("Password");
    expect(field).toHaveAttribute("type", "password");

    await userEvent.click(screen.getByRole("button", { name: "Show password" }));
    expect(field).toHaveAttribute("type", "text");

    await userEvent.click(screen.getByRole("button", { name: "Hide password" }));
    expect(field).toHaveAttribute("type", "password");
  });

  it("skips the form when the visitor is already signed in", () => {
    useAuthStore.setState({ user: USER, status: "authenticated", error: null, pending: false });

    renderLogin("/login");

    expect(screen.getByText("Landed on /dashboard")).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Welcome back" })).not.toBeInTheDocument();
  });
});
