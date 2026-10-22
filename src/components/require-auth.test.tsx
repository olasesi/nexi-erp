import { render, screen } from "@testing-library/react";
import { MemoryRouter, Route, Routes, useLocation } from "react-router-dom";
import { describe, expect, it } from "vitest";

import { useAuthStore } from "@/lib/auth";
import { RequireAuth } from "@/components/require-auth";

function Destination() {
  return <p>Private workspace</p>;
}

function Login() {
  const location = useLocation();
  const from = (location.state as { from?: string } | null)?.from;

  return <p>Destination: {from ?? "none"}</p>;
}

function renderGuard(initialEntry = "/") {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route
          path="/"
          element={
            <RequireAuth>
              <Destination />
            </RequireAuth>
          }
        />
        <Route path="/login" element={<Login />} />
        <Route
          path="/receivables"
          element={
            <RequireAuth>
              <Destination />
            </RequireAuth>
          }
        />
      </Routes>
    </MemoryRouter>,
  );
}

describe("RequireAuth", () => {
  it("redirects signed-out visitors to the login screen", () => {
    useAuthStore.setState({ status: "unauthenticated", user: null, error: null, pending: false });

    renderGuard();

    expect(screen.getByText("Destination: /")).toBeInTheDocument();
    expect(screen.queryByText("Private workspace")).not.toBeInTheDocument();
  });

  it("preserves the intended destination for after sign-in", () => {
    useAuthStore.setState({ status: "unauthenticated", user: null, error: null, pending: false });

    renderGuard("/receivables");

    expect(screen.getByText("Destination: /receivables")).toBeInTheDocument();
    expect(screen.queryByText("Private workspace")).not.toBeInTheDocument();
  });

  it("renders the children of a signed-in user", () => {
    useAuthStore.setState({
      status: "authenticated",
      user: { id: 1, name: "Ada", email: "ada@nexi.test", company: null },
      error: null,
      pending: false,
    });

    renderGuard();

    expect(screen.getByText("Private workspace")).toBeInTheDocument();
    expect(screen.queryByText(/^Destination:/)).not.toBeInTheDocument();
  });

  it("holds the router while the session is still being restored", () => {
    useAuthStore.setState({ status: "loading", user: null, error: null, pending: false });

    renderGuard();

    expect(screen.getByRole("status")).toHaveTextContent("Checking your session…");
    expect(screen.queryByText(/^Destination:/)).not.toBeInTheDocument();
    expect(screen.queryByText("Private workspace")).not.toBeInTheDocument();
  });
});
