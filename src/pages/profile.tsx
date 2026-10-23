import { useState, type FormEvent } from "react";
import { useMutation } from "@tanstack/react-query";

import { changePassword, updateProfile } from "@/lib/api";
import { errorMessage } from "@/lib/errors";
import { useAuth } from "@/lib/use-auth";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";

export function ProfilePage() {
  const { user, updateUser } = useAuth();

  const [profile, setProfile] = useState(() => ({
    name: user?.name ?? "",
    username: user?.username ?? "",
    phone: user?.phone ?? "",
  }));
  const [profileNotice, setProfileNotice] = useState<string | null>(null);
  const [profileError, setProfileError] = useState<string | null>(null);

  const saveProfile = useMutation({
    mutationFn: updateProfile,
    onSuccess: (updated) => {
      updateUser(updated);
      setProfileError(null);
      setProfileNotice("Profile updated.");
    },
    onError: (error) => {
      setProfileNotice(null);
      setProfileError(errorMessage(error));
    },
  });

  const [passwords, setPasswords] = useState({ current: "", next: "", confirm: "" });
  const [passwordNotice, setPasswordNotice] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);

  const savePassword = useMutation({
    mutationFn: changePassword,
    onSuccess: (data) => {
      setPasswords({ current: "", next: "", confirm: "" });
      setPasswordError(null);
      setPasswordNotice(data.message);
    },
    onError: (error) => {
      setPasswordNotice(null);
      setPasswordError(errorMessage(error));
    },
  });

  function submitProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    saveProfile.mutate({
      name: profile.name.trim(),
      username: profile.username.trim() || null,
      phone: profile.phone.trim() || null,
    });
  }

  function submitPassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPasswordNotice(null);
    setPasswordError(null);

    if (passwords.next !== passwords.confirm) {
      setPasswordError("The new password confirmation does not match.");
      return;
    }

    savePassword.mutate({
      current_password: passwords.current,
      password: passwords.next,
      password_confirmation: passwords.confirm,
    });
  }

  return (
    <div className="page">
      <PageHeader
        title="Profile"
        description="Your account details and sign-in credentials."
        actions={
          user?.company ? <span className="badge badge--gray">{user.company.name}</span> : null
        }
      />

      <div className="grid-fixed grid-fixed--2">
        <Card title="Account details" description="Shown to teammates across the workspace">
          <form className="auth-card__form" onSubmit={submitProfile} noValidate>
            {profileNotice && (
              <Alert variant="success" title="">
                {profileNotice}
              </Alert>
            )}
            {profileError && (
              <Alert variant="error" title="">
                {profileError}
              </Alert>
            )}

            <div className="form-field">
              <label className="form-field__label" htmlFor="profile-name">
                Full name
              </label>
              <input
                id="profile-name"
                className="form-field__input"
                required
                value={profile.name}
                onChange={(event) => setProfile({ ...profile, name: event.target.value })}
              />
            </div>

            <div className="form-field">
              <label className="form-field__label" htmlFor="profile-username">
                Username
              </label>
              <input
                id="profile-username"
                className="form-field__input"
                value={profile.username}
                onChange={(event) => setProfile({ ...profile, username: event.target.value })}
              />
            </div>

            <div className="form-field">
              <label className="form-field__label" htmlFor="profile-phone">
                Phone
              </label>
              <input
                id="profile-phone"
                className="form-field__input"
                value={profile.phone}
                onChange={(event) => setProfile({ ...profile, phone: event.target.value })}
              />
            </div>

            <div className="form-field">
              <label className="form-field__label" htmlFor="profile-email">
                Email
              </label>
              <input
                id="profile-email"
                className="form-field__input"
                value={user?.email ?? ""}
                disabled
                readOnly
              />
              <p className="form-field__hint">Email changes are handled by an administrator.</p>
            </div>

            <div>
              <Button type="submit" variant="primary" disabled={saveProfile.isPending}>
                {saveProfile.isPending ? "Saving…" : "Save profile"}
              </Button>
            </div>
          </form>
        </Card>

        <Card title="Password" description="Changing it signs out your other devices">
          <form className="auth-card__form" onSubmit={submitPassword} noValidate>
            {passwordNotice && (
              <Alert variant="success" title="">
                {passwordNotice}
              </Alert>
            )}
            {passwordError && (
              <Alert variant="error" title="">
                {passwordError}
              </Alert>
            )}

            <div className="form-field">
              <label className="form-field__label" htmlFor="current-password">
                Current password
              </label>
              <input
                id="current-password"
                className="form-field__input"
                type="password"
                autoComplete="current-password"
                required
                value={passwords.current}
                onChange={(event) => setPasswords({ ...passwords, current: event.target.value })}
              />
            </div>

            <div className="form-field">
              <label className="form-field__label" htmlFor="new-password">
                New password
              </label>
              <input
                id="new-password"
                className="form-field__input"
                type="password"
                autoComplete="new-password"
                minLength={8}
                required
                value={passwords.next}
                onChange={(event) => setPasswords({ ...passwords, next: event.target.value })}
              />
              <p className="form-field__hint">At least 8 characters.</p>
            </div>

            <div className="form-field">
              <label className="form-field__label" htmlFor="confirm-password">
                Confirm new password
              </label>
              <input
                id="confirm-password"
                className="form-field__input"
                type="password"
                autoComplete="new-password"
                minLength={8}
                required
                value={passwords.confirm}
                onChange={(event) => setPasswords({ ...passwords, confirm: event.target.value })}
              />
            </div>

            <div>
              <Button type="submit" variant="secondary" disabled={savePassword.isPending}>
                {savePassword.isPending ? "Updating…" : "Change password"}
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  );
}
