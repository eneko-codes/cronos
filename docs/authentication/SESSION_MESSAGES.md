# Authentication Session Messages Reference

This document lists all session messages displayed in authentication views and when they appear.

## 1. Login View (`auth/login.blade.php`)

### Success Messages

| Message Key              | When Displayed                  | Trigger                                  | Status       |
| ------------------------ | ------------------------------- | ---------------------------------------- | ------------ |
| `password_reset_success` | After successful password reset | User redirected from reset password page | ✅ Displayed |

### Error Messages

| Message Key             | When Displayed            | Trigger                  | Status       |
| ----------------------- | ------------------------- | ------------------------ | ------------ |
| `rate_limit`            | Too many login attempts   | Rate limiting middleware | ✅ Displayed |
| `credentials`           | Invalid login credentials | Failed authentication    | ✅ Displayed |
| `email` (validation)    | Email validation fails    | Form validation          | ✅ Displayed |
| `password` (validation) | Password validation fails | Form validation          | ✅ Displayed |

---

## 2. Forgot Password View (`auth/forgot-password.blade.php`)

### Success Messages

| Message Key              | When Displayed                    | Trigger                                   | Status       |
| ------------------------ | --------------------------------- | ----------------------------------------- | ------------ |
| `password_reset_success` | After password reset link is sent | ForgotPasswordController sends reset link | ✅ Displayed |

### Error Messages

| Message Key  | When Displayed                           | Trigger                                                    | Status       |
| ------------ | ---------------------------------------- | ---------------------------------------------------------- | ------------ |
| `email`      | Email validation fails or user not found | Form validation or Password::sendResetLink() returns error | ✅ Displayed |
| `rate_limit` | Too many password reset requests         | Password broker throttling                                 | ✅ Displayed |

**Note:** Laravel Password broker can return these statuses:

- `RESET_LINK_SENT` ✅ Handled (success)
- `INVALID_USER` ✅ Handled (error on email field)
- `RESET_THROTTLED` ✅ Handled (error on rate_limit field)

---

## 3. Reset Password View (`auth/reset-password.blade.php`)

### Success Messages

| Message Key | When Displayed                  | Trigger                       | Status                                               |
| ----------- | ------------------------------- | ----------------------------- | ---------------------------------------------------- |
| None        | After successful password reset | User redirected to login page | ✅ Handled (redirects to login with success message) |

### Error Messages

| Message Key             | When Displayed               | Trigger                                 | Status       |
| ----------------------- | ---------------------------- | --------------------------------------- | ------------ |
| `email`                 | Email/token validation fails | Password::reset() returns error         | ✅ Displayed |
| `token`                 | Token validation fails       | Password::reset() returns INVALID_TOKEN | ✅ Displayed |
| `password`              | Password validation fails    | Form validation                         | ✅ Displayed |
| `password_confirmation` | Password confirmation fails  | Form validation                         | ✅ Displayed |
| `rate_limit`            | Too many reset attempts      | Rate limiting middleware                | ✅ Displayed |

**Note:** Laravel Password broker can return these statuses:

- `PASSWORD_RESET` ✅ Handled (redirects to login)
- `INVALID_TOKEN` ✅ Handled (error on email field via \_\_($status))
- `INVALID_USER` ✅ Handled (error on email field via \_\_($status))
- `RESET_THROTTLED` ✅ Handled (error on rate_limit field)

---

## 4. First-Time Password Setup View (`auth/first-time-password-setup.blade.php`)

### Success Messages

| Message Key              | When Displayed                  | Trigger                                        | Status       |
| ------------------------ | ------------------------------- | ---------------------------------------------- | ------------ |
| `password_setup_success` | After successful password setup | FirstTimePasswordSetupController sets password | ✅ Displayed |

### Error Messages

| Message Key             | When Displayed               | Trigger                                 | Status       |
| ----------------------- | ---------------------------- | --------------------------------------- | ------------ |
| `email`                 | Email/token validation fails | Password::reset() returns error         | ✅ Displayed |
| `token`                 | Token validation fails       | Password::reset() returns INVALID_TOKEN | ✅ Displayed |
| `password`              | Password validation fails    | Form validation                         | ✅ Displayed |
| `password_confirmation` | Password confirmation fails  | Form validation                         | ✅ Displayed |
| `rate_limit`            | Too many setup attempts      | Rate limiting middleware                | ✅ Displayed |

**Note:** Laravel Password broker can return these statuses:

- `PASSWORD_RESET` ✅ Handled (redirects to dashboard)
- `INVALID_TOKEN` ✅ Handled (error on email field via \_\_($status))
- `INVALID_USER` ✅ Handled (error on email field via \_\_($status))
- `RESET_THROTTLED` ✅ Handled (error on rate_limit field)

---

## Missing Messages Summary

### ✅ All Issues Fixed

1. **Login View**
   - ✅ Removed unused `welcome_email_sent` check (intentionally not set for security)

2. **Forgot Password View**
   - ✅ Added `RESET_THROTTLED` handling in controller
   - ✅ Added `rate_limit` error display in view

3. **Reset Password View**
   - ✅ Added `RESET_THROTTLED` handling in controller
   - ✅ Already has `rate_limit` error display

4. **First-Time Password Setup View**
   - ✅ Added `RESET_THROTTLED` handling in controller
   - ✅ Already has `rate_limit` error display

---

## Implementation Notes

1. **`welcome_email_sent`**: Removed from login view as it was intentionally not set by the controller to prevent user enumeration attacks. The email is sent silently without revealing user existence.

2. **`RESET_THROTTLED`**: Now specifically handled in all password-related controllers. When Laravel's Password broker returns this status, it's displayed as a `rate_limit` error which is already handled in all views.

3. **Rate Limiting**: Two types of rate limiting exist:
   - Route middleware throttling (handled by middleware)
   - Password broker throttling (handled by controllers)
     Both now display proper error messages to users.
