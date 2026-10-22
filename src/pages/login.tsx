import { useState, type FormEvent } from "react";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { BarChart3, Lock, Mail, ShieldCheck, Eye, EyeOff } from "lucide-react";

import { useAuth } from "@/lib/use-auth";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";

/**
 * Full-screen sign-in. On success the visitor is sent to wherever they were
 * originally headed (captured by RequireAuth) or to the dashboard.
 */
export function LoginPage() {
  const { isAuthenticated, pending, error, login, clearError } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [reveal, setReveal] = useState(false);

  const from = (location.state as { from?: string } | null)?.from ?? "/dashboard";

  // Already signed in (e.g. opened /login directly) — skip the form.
  if (isAuthenticated) {
    return <Navigate to={from} replace />;
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const ok = await login(email.trim(), password);
    if (ok) {
      navigate(from, { replace: true });
    }
  }

  return (
    <div className="auth">
      {/* Brand panel */}
      <aside className="auth__brand">
        <div className="auth__logo">
          <span className="auth__logo-mark">N</span>
          <span className="auth__logo-text">Nexi ERP</span>
        </div>

        <div>
          <h1 className="auth__headline">Run your whole business from one calm screen.</h1>
          <p className="auth__subline">
            Sales, stock, finance and the people side of the business — connected, live, and ready
            when you are.
          </p>

          <ul className="auth__features">
            <li className="auth__feature">
              <span className="auth__feature-icon">
                <BarChart3 size={14} aria-hidden="true" />
              </span>
              <span className="auth__feature-text">
                Dashboards that surface what actually needs a decision today.
              </span>
            </li>
            <li className="auth__feature">
              <span className="auth__feature-icon">
                <ShieldCheck size={14} aria-hidden="true" />
              </span>
              <span className="auth__feature-text">
                Role-scoped access and a full audit trail on every change.
              </span>
            </li>
            <li className="auth__feature">
              <span className="auth__feature-icon">
                <Lock size={14} aria-hidden="true" />
              </span>
              <span className="auth__feature-text">
                Tokens are short-lived and every request is authenticated.
              </span>
            </li>
          </ul>
        </div>

        <p className="auth__footnote">
          © {new Date().getFullYear()} Nexi ERP. All rights reserved.
        </p>
      </aside>

      {/* Form panel */}
      <main className="auth__panel">
        <div className="auth-card">
          <header className="auth-card__header">
            <h2 className="auth-card__title">Welcome back</h2>
            <p className="auth-card__subtitle">Sign in with your work email to continue.</p>
          </header>

          {error && (
            <Alert variant="error" className="auth-card__alert">
              {error}
            </Alert>
          )}

          <form className="auth-card__form" onSubmit={handleSubmit} noValidate>
            <div className="form-field">
              <label className="form-field__label" htmlFor="login-email">
                Email address
              </label>
              <div className="form-field__control">
                <Mail className="form-field__icon" size={16} aria-hidden="true" />
                <input
                  id="login-email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  className="form-field__input form-field__input--icon"
                  placeholder="you@company.com"
                  value={email}
                  onChange={(event) => {
                    setEmail(event.target.value);
                    if (error) clearError();
                  }}
                />
              </div>
            </div>

            <div className="form-field">
              <label className="form-field__label" htmlFor="login-password">
                Password
              </label>
              <div className="form-field__control">
                <Lock className="form-field__icon" size={16} aria-hidden="true" />
                <input
                  id="login-password"
                  name="password"
                  type={reveal ? "text" : "password"}
                  autoComplete="current-password"
                  required
                  className="form-field__input form-field__input--icon form-field__input--action"
                  placeholder="••••••••"
                  value={password}
                  onChange={(event) => {
                    setPassword(event.target.value);
                    if (error) clearError();
                  }}
                />
                <button
                  type="button"
                  className="form-field__action"
                  onClick={() => setReveal((value) => !value)}
                  aria-label={reveal ? "Hide password" : "Show password"}
                >
                  {reveal ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            <Button type="submit" variant="primary" size="lg" block disabled={pending}>
              {pending ? "Signing in…" : "Sign in"}
            </Button>
          </form>

          <p className="auth-card__footer">
            Trouble signing in?{" "}
            <a className="auth-card__link" href="mailto:support@nexi-erp.test">
              Contact your administrator
            </a>
          </p>
        </div>
      </main>
    </div>
  );
}
