# Push Notifications for Cash Advance & Leave Requests

**Date:** 2026-07-10
**Status:** Approved

## Problem

The app is a PWA (installable on mobile and desktop) but has no way to alert users of activity outside the browser tab. Admin/HR only find out about new cash advance/leave requests by visiting `/approvals`, and employees only find out about a decision by visiting `/my-requests`. We want real OS-level push notifications (system notification center on both mobile and desktop) for both directions.

## Recipients & triggers

- **New request submitted** (`CashAdvanceRequestController@store`, `LeaveRequestController@store`): notify every `User` with role `admin`, `hr`, or `overseer` — i.e. everyone who can already see `/approvals`. Click opens `/approvals`.
- **Request reviewed** (`RequestApprovalController@review`, covers approve + reject for both request types): notify the employee's linked `User` (`$model->employee->user`), if one exists. Click opens `/my-requests`.

## Backend

- Add `laravel-notification-channels/webpush` (wraps `minishlink/web-push`; handles VAPID signing and per-subscription delivery).
- Generate VAPID keypair, store as `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` in `.env` / `.env.example`.
- Migration + model: `push_subscriptions` table (via the package's standard schema: `id`, `subscribable_type`, `subscribable_id`, `endpoint`, `public_key`, `auth_token`, `content_encoding`, timestamps). Add `HasPushSubscriptions` trait to `User`.
- Two new routes (`auth` middleware, any role):
  - `POST /push-subscriptions` — store/update the browser's subscription for the current user.
  - `DELETE /push-subscriptions` — remove it (unsubscribe).
  - `GET /push-vapid-key` (or an Inertia shared prop) — expose the VAPID public key to the frontend for `pushManager.subscribe()`.
- Two `Illuminate\Notifications\Notification` classes, `via()` returning `[WebPushChannel::class]`:
  - `App\Notifications\NewRequestSubmitted` — constructed with the `CashAdvanceRequest|LeaveRequest` model. Title/body derived from request type + employee name + key details (amount/needed_date for cash advance; start/end date for leave). `data.url` = `/approvals`.
  - `App\Notifications\RequestReviewed` — constructed with the model (post-update) and decision. Title/body reflect approved/rejected + review note if present. `data.url` = `/my-requests`.
- Dispatch synchronously (no queue dependency):
  - In `CashAdvanceRequestController@store` / `LeaveRequestController@store`, after `::create(...)`, fetch admin/hr/overseer users and call `Notification::send($recipients, new NewRequestSubmitted($model))`.
  - In `RequestApprovalController@review`, after `$model->update(...)`, if `$model->employee->user` exists, call `$model->employee->user->notify(new RequestReviewed($model))`.
- Wrap each dispatch site in a try/catch (or rely on the webpush channel's own per-subscription error handling) so an expired/invalid push subscription can never turn into a failed request/approval. The package auto-prunes subscriptions that the push service reports as gone (410/404).

## Frontend

- Switch `vite-plugin-pwa` from `generateSW` to `injectManifest` strategy in `vite.config.ts`:
  - Add `strategies: 'injectManifest'`, `srcDir: 'resources/js'`, `filename: 'sw.ts'`.
  - Keep existing `manifest`, `includeAssets`, `injectRegister: false`, `outDir: 'public'`, `base: '/'` as-is.
- New `resources/js/sw.ts` (custom service worker):
  - `precacheAndRoute(self.__WB_MANIFEST)` (replaces what `generateSW` did automatically).
  - `self.addEventListener('push', (event) => { ... registration.showNotification(title, { body, icon, data: { url } }) })` — payload parsed from `event.data.json()`.
  - `self.addEventListener('notificationclick', (event) => { ... clients matchAll -> focus existing tab at data.url, or clients.openWindow(data.url) })`.
- `resources/js/app.tsx` — no change needed to the `registerSW({ immediate: true })` call itself; it works the same regardless of underlying strategy.
- New Settings page `resources/js/pages/settings/notifications.tsx`, route `settings/notifications` (any authenticated user) added to `routes/settings.php` under the general `auth` group (alongside `profile.edit`), and added to `sidebarNavItems` in `resources/js/layouts/settings/layout.tsx` (no `roles`/`adminOnly` restriction — every user gets this page).
  - Single toggle: "Push notifications". On enable: check `Notification.permission`, call `Notification.requestPermission()` if needed, `navigator.serviceWorker.ready`, then `registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: <VAPID public key> })`, POST the subscription JSON to `/push-subscriptions`.
  - On disable: `subscription.unsubscribe()` then DELETE to `/push-subscriptions`.
  - Reflect current subscribed/unsubscribed state on load by checking `registration.pushManager.getSubscription()`.

## Data flow

1. Employee submits a cash advance/leave request → controller saves the record → looks up admin/hr/overseer users → `Notification::send(...)` with `NewRequestSubmitted` → webpush channel POSTs to each user's stored subscription endpoint(s) → service worker `push` event fires → OS notification shown → click opens/focuses `/approvals`.
2. Admin/HR approves/rejects via `RequestApprovalController@review` → after the model update, if the employee has a linked user, notify with `RequestReviewed` → same push flow → click opens/focuses `/my-requests`.

## Error handling

- No stored subscription for a recipient → nothing sent, no error (standard `Notification::send` behavior with zero webpush channels found).
- `Employee->user` is null → skip the employee-side notification silently (matches existing nullable-employee patterns elsewhere in the codebase).
- Push delivery failures (expired subscription, network error to push service) are caught so they never surface as a failed request submission or approval action.
- Browser doesn't support Push API / permission denied → the Settings toggle shows a disabled/explanatory state rather than erroring.

## Testing

- Feature test: submitting a cash advance request notifies all admin/hr/overseer users (`Notification::fake()` + `Notification::assertSentTo`), and does not notify plain employees.
- Feature test: same for leave requests.
- Feature test: approving a cash advance/leave request notifies the employee's linked user with `RequestReviewed`; rejecting does the same; no notification (and no error) is sent/thrown when the employee has no linked user.
- Feature test: `POST /push-subscriptions` creates a `PushSubscription` row for the authenticated user; `DELETE /push-subscriptions` removes it.

## Out of scope

- Queued/async delivery (explicitly synchronous per decision above).
- In-app notification center / notification history UI — this is push-only, matching the PWA "notification center" request.
- Notifying on request *edits* (there is currently no edit flow for pending requests) or cancellations.
