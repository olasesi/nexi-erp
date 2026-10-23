import { useState, type FormEvent } from "react";
import { useMutation } from "@tanstack/react-query";
import { Link, useSearchParams } from "react-router-dom";
import { Lock } from "lucide-react";

import { resetPassword } from "@/lib/api";
import { errorMessage } from "@/lib/errors";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";

export function ResetPasswordPage() {
  const [params] = useSearchParams();
  const token = params.get("token") ?? "";
  const [form, setForm] = useState({
    email: params.get("email") ?? "",
    password: "",
    confirm: "",
  });
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const request = useMutation({
    mutationFn: resetPassword,
    onSuccess: () => {
      setError(null);
      setDone(true);
    },
    onError: (cause) => setError(errorMessage(cause)),
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    if (form.password !== form.confirm) {
      setError("The password confirmation does not match.");
      return;
    }

    request.mutate({
      token,
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.confirm,
    });
  }

  return (
    <div className="auth auth--single">
      <main className="auth__panel">
        <div className="auth-card">
          <header className="auth-card__header">
            <h1 className="auth-card__title">Choose a new password</h1>
            <p className="auth-card__subtitle">
              Use at least 8 characters. You&rsquo;ll sign in again afterwards.
            </p>
          </header>

          {!token && (
            <Alert variant="error">
              This reset link is missing its token. Request a new link from the sign-in page.
            </Alert>
          )}
          {done && (
            <Alert variant="success" title="Password updated">
              <Link className="auth-card__link" to="/login">
                Sign in with your new password
              </Link>
            </Alert>
          )}
          {error && (
            <Alert variant="error" title="">
              {error}
            </Alert>
          )}

          {!done && (
            <form className="auth-card__form" onSubmit={submit} noValidate>
              <div className="form-field">
                <label className="form-field__label" htmlFor="reset-email">
                  Email address
                </label>
                <input
                  id="reset-email"
                  type="email"
                  autoComplete="email"
                  required
                  className="form-field__input"
                  value={form.email}
                  onChange={(event) => setForm({ ...form, email: event.target.value })}
                />
              </div>

              <div className="form-field">
                <label className="form-field__label" htmlFor="reset-password">
                  New password
                </label>
                <div className="form-field__control">
                  <Lock className="form-field__icon" size={16} aria-hidden="true" />
                  <input
                    id="reset-password"
                    type="password"
                    autoComplete="new-password"
                    minLength={8}
                    required
                    className="form-field__input form-field__input--icon"
                    value={form.password}
                    onChange={(event) => setForm({ ...form, password: event.target.value })}
                  />
                </div>
              </div>

              <div className="form-field">
                <label className="form-field__label" htmlFor="reset-confirm">
                  Confirm password
                </label>
                <div className="form-field__control">
                  <Lock className="form-field__icon" size={16} aria-hidden="true" />
                  <input
                    id="reset-confirm"
                    type="password"
                    autoComplete="new-password"
                    minLength={8}
                    required
                    className="form-field__input form-field__input--icon"
                    value={form.confirm}
                    onChange={(event) => setForm({ ...form, confirm: event.target.value })}
                  />
                </div>
              </div>

              <Button
                type="submit"
                variant="primary"
                size="lg"
                block
                disabled={request.isPending || !token}
              >
                {request.isPending ? "Saving…" : "Set new password"}
              </Button>
            </form>
          )}

          <p className="auth-card__footer">
            <Link className="auth-card__link" to="/login">
              Back to sign in
            </Link>
          </p>
        </div>
      </main>
    </div>
  );
}
