import { useState, type FormEvent } from "react";
import { useMutation } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { Mail } from "lucide-react";

import { forgotPassword } from "@/lib/api";
import { errorMessage } from "@/lib/errors";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";

export function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const request = useMutation({
    mutationFn: forgotPassword,
    onSuccess: (data) => {
      setError(null);
      setSent(data.message);
    },
    onError: (cause) => {
      setSent(null);
      setError(errorMessage(cause));
    },
  });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    request.mutate(email.trim());
  }

  return (
    <div className="auth auth--single">
      <main className="auth__panel">
        <div className="auth-card">
          <header className="auth-card__header">
            <h1 className="auth-card__title">Reset your password</h1>
            <p className="auth-card__subtitle">
              Enter your work email and we&rsquo;ll send a reset link if the account exists.
            </p>
          </header>

          {sent && (
            <Alert variant="success" title="">
              {sent}
            </Alert>
          )}
          {error && (
            <Alert variant="error" title="">
              {error}
            </Alert>
          )}

          <form className="auth-card__form" onSubmit={submit} noValidate>
            <div className="form-field">
              <label className="form-field__label" htmlFor="forgot-email">
                Email address
              </label>
              <div className="form-field__control">
                <Mail className="form-field__icon" size={16} aria-hidden="true" />
                <input
                  id="forgot-email"
                  type="email"
                  autoComplete="email"
                  required
                  className="form-field__input form-field__input--icon"
                  placeholder="you@company.com"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                />
              </div>
            </div>

            <Button type="submit" variant="primary" size="lg" block disabled={request.isPending}>
              {request.isPending ? "Sending…" : "Send reset link"}
            </Button>
          </form>

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
