# Push Notifications for Cash Advance & Leave Requests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send real OS-level push notifications (via the Web Push API) to admin/hr/overseer users when an employee submits a cash advance or leave request, and back to the employee when their request is approved/rejected.

**Architecture:** Laravel's notification system dispatches synchronously through a new `webpush` channel (`laravel-notification-channels/webpush`) backed by a `push_subscriptions` table. The PWA's service worker is switched from vite-plugin-pwa's `generateSW` strategy to `injectManifest` so a custom `push`/`notificationclick` handler can be added. Users opt in via a new Settings → Notifications toggle that requests browser permission and posts the subscription to the backend.

**Tech Stack:** Laravel 13 / PHP 8.3, `laravel-notification-channels/webpush` (wraps `minishlink/web-push`), React 19 + Inertia.js, `vite-plugin-pwa` (injectManifest strategy), `workbox-precaching` / `workbox-core`.

## Global Constraints

- Push notifications are sent synchronously (no queue) — this app's queue driver is `database` and isn't guaranteed to be running in dev.
- Recipients for "new request submitted": every `User` with role `admin`, `hr`, or `overseer` (matches who can already see `/approvals`).
- Recipient for "request reviewed": the employee's linked `User` (`$model->employee->user`), skipped silently if none exists.
- No queued jobs, no in-app notification center/history — push only.
- Follow existing code style: no doc-comment blocks beyond what's shown below, PSR-4 autoloading already configured (`App\` → `app/`).
- PHP feature tests use `RefreshDatabase` and Laravel's `Notification::fake()` — this codebase has no JS test framework, so frontend tasks are verified via `npm run types:check`, `npm run lint:check`, `npm run build`, and manual browser steps.

---

### Task 1: Push subscription infrastructure (backend)

**Files:**
- Modify: `composer.json` (via `composer require`)
- Create: `config/webpush.php` (via `vendor:publish`)
- Create: `database/migrations/<timestamp>_create_push_subscriptions_table.php` (via `vendor:publish`; exact timestamp is assigned by the publish command at run time)
- Modify: `app/Models/User.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Create: `app/Http/Controllers/PushSubscriptionController.php`
- Modify: `routes/web.php`
- Modify: `.env.example`
- Test: `tests/Feature/PushSubscriptionTest.php`

**Interfaces:**
- Produces: `User` gains `pushSubscriptions()`, `updatePushSubscription(string $endpoint, ?string $key, ?string $token)`, `deletePushSubscription(string $endpoint)` (from the package's `HasPushSubscriptions` trait) — Tasks 2 and 3 don't call these directly, but Task 2 relies on `User::canApproveRequests(): \Illuminate\Support\Collection` (added in Task 2, not here).
- Produces: `POST /push-subscriptions` and `DELETE /push-subscriptions` routes, named `push-subscriptions.store` / `push-subscriptions.destroy` — Task 5 (frontend) consumes these by name via the generated Wayfinder helpers `@/routes/push-subscriptions`.
- Produces: shared Inertia prop `vapidPublicKey: string|null` — Task 5 consumes it via `usePage().props.vapidPublicKey`.

- [ ] **Step 1: Install the webpush package**

Run: `composer require laravel-notification-channels/webpush`
Expected: composer.json's `require` gains `"laravel-notification-channels/webpush": "^..."` and the lock file updates without errors.

- [ ] **Step 2: Publish the package's config and migration**

Run: `php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider"`
Expected output includes two lines similar to:
```
INFO  Publishing [config] assets.
Copied File [...] To [config\webpush.php]
INFO  Publishing [migrations] assets.
Copied File [...] To [database\migrations\2026_07_10_120000_create_push_subscriptions_table.php]
```
Confirm `config/webpush.php` now exists and a new migration file matching `*_create_push_subscriptions_table.php` was created under `database/migrations/`.

- [ ] **Step 3: Add VAPID env placeholders and run the migration**

Edit `.env.example`, adding after the `MAIL_*` block:
```
VAPID_SUBJECT="${APP_URL}"
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
```

Run: `php artisan migrate`
Expected: `Migrating: ..._create_push_subscriptions_table` then `Migrated: ...` with no errors. A `push_subscriptions` table now exists in the local sqlite database.

Then generate real local keys (this only touches your local `.env`, not `.env.example`):
Run: `php artisan webpush:vapid`
Expected: `VAPID keys set successfully.` and `.env` now contains non-empty `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` values.

- [ ] **Step 4: Add push-subscription support to the User model**

Edit `app/Models/User.php` — add the trait import and `use` it:

```php
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'username', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
```

(Leave the rest of the class body untouched for now — `canApproveRequests()` is added in Task 2.)

- [ ] **Step 5: Share the VAPID public key with the frontend**

Edit `app/Http/Middleware/HandleInertiaRequests.php`, inside `share()`:

```php
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'pendingRequests' => $canManage ? [
```

- [ ] **Step 6: Create the subscribe/unsubscribe controller**

Create `app/Http/Controllers/PushSubscriptionController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * Store (or update) the current user's push subscription for this browser.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
        );

        return response()->noContent();
    }

    /**
     * Remove the current user's push subscription for this browser.
     */
    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->noContent();
    }
}
```

- [ ] **Step 7: Add the routes**

Edit `routes/web.php` — add the `use` statement and the two routes inside the existing `Route::middleware(['auth', 'verified'])->group(...)` block, right after the employee request portal routes:

```php
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RequestApprovalController;
```

```php
    Route::post('my-requests/cash-advance', [CashAdvanceRequestController::class, 'store'])->name('my-requests.cash-advance.store');
    Route::post('my-requests/leave', [LeaveRequestController::class, 'store'])->name('my-requests.leave.store');

    // --- Push notification subscriptions (any authenticated user) ---
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
```

- [ ] **Step 8: Write the feature test**

Create `tests/Feature/PushSubscriptionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_and_unsubscribe_to_push_notifications(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->postJson('/push-subscriptions', [
            'endpoint' => 'https://push.example.com/abc123',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-token',
            ],
        ])->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc123',
        ]);

        $this->actingAs($user)->deleteJson('/push-subscriptions', [
            'endpoint' => 'https://push.example.com/abc123',
        ])->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://push.example.com/abc123',
        ]);
    }

    public function test_subscribing_requires_endpoint_and_keys(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->postJson('/push-subscriptions', [])
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }
}
```

- [ ] **Step 9: Run the test**

Run: `php artisan test tests/Feature/PushSubscriptionTest.php`
Expected: `PASS` for both tests.

- [ ] **Step 10: Commit**

```bash
git add composer.json composer.lock config/webpush.php database/migrations app/Models/User.php app/Http/Middleware/HandleInertiaRequests.php app/Http/Controllers/PushSubscriptionController.php routes/web.php .env.example tests/Feature/PushSubscriptionTest.php
git commit -m "feat: add push subscription storage and endpoints"
```

---

### Task 2: "New request submitted" notification (backend)

**Files:**
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Create: `app/Notifications/NewRequestSubmitted.php`
- Modify: `app/Http/Controllers/CashAdvanceRequestController.php`
- Modify: `app/Http/Controllers/LeaveRequestController.php`
- Test: `tests/Feature/NewRequestSubmittedTest.php`

**Interfaces:**
- Consumes: `HasPushSubscriptions` trait already applied to `User` in Task 1 (needed so `Notification::send()` has somewhere to route to; the notification still fires without it, it simply has no subscriptions to deliver to).
- Produces: `User::canApproveRequests(): \Illuminate\Support\Collection` — a static helper other code can reuse to fetch the admin/hr/overseer recipient list.
- Produces: `App\Notifications\NewRequestSubmitted` — constructed as `new NewRequestSubmitted(CashAdvanceRequest|LeaveRequest $requestModel)`.

- [ ] **Step 1: Add the `overseer()` factory state**

Edit `database/factories/UserFactory.php`, add after the `employee()` method:

```php
    public function employee(): static
    {
        return $this->role(User::ROLE_EMPLOYEE);
    }

    public function overseer(): static
    {
        return $this->role(User::ROLE_OVERSEER);
    }
```

- [ ] **Step 2: Add the recipient-lookup helper to User**

Edit `app/Models/User.php` — add the import and method:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
```

```php
    /**
     * Users who can view and act on cash advance/leave requests.
     *
     * @return Collection<int, self>
     */
    public static function canApproveRequests(): Collection
    {
        return static::whereIn('role', [self::ROLE_ADMIN, self::ROLE_HR, self::ROLE_OVERSEER])->get();
    }

    /**
     * Admins and HR can manage payroll, employees, and approve requests.
     */
    public function canManagePayroll(): bool
```

- [ ] **Step 3: Write the failing test**

Create `tests/Feature/NewRequestSubmittedTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CashAdvanceRequest;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewRequestSubmittedTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_submitting_cash_advance_request_notifies_admin_hr_and_overseer(): void
    {
        Notification::fake();

        $employeeUser = $this->employeeUser();
        $admin = User::factory()->admin()->create();
        $hr = User::factory()->hr()->create();
        $overseer = User::factory()->overseer()->create();

        $this->actingAs($employeeUser)->post('/my-requests/cash-advance', [
            'amount' => 1500,
            'needed_date' => '2026-05-10',
            'reason' => 'Medical',
        ])->assertRedirect('/my-requests');

        Notification::assertSentTo($admin, NewRequestSubmitted::class);
        Notification::assertSentTo($hr, NewRequestSubmitted::class);
        Notification::assertSentTo($overseer, NewRequestSubmitted::class);
        Notification::assertNotSentTo($employeeUser, NewRequestSubmitted::class);
    }

    public function test_submitting_leave_request_notifies_admin_hr_and_overseer(): void
    {
        Notification::fake();

        $employeeUser = $this->employeeUser();
        $admin = User::factory()->admin()->create();

        $this->actingAs($employeeUser)->post('/my-requests/leave', [
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
            'reason' => 'Family matter',
        ])->assertRedirect('/my-requests');

        Notification::assertSentTo($admin, NewRequestSubmitted::class);
        Notification::assertNotSentTo($employeeUser, NewRequestSubmitted::class);
    }
}
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test tests/Feature/NewRequestSubmittedTest.php`
Expected: FAIL — `Class "App\Notifications\NewRequestSubmitted" not found`.

- [ ] **Step 5: Create the notification class**

Create `app/Notifications/NewRequestSubmitted.php`:

```php
<?php

namespace App\Notifications;

use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewRequestSubmitted extends Notification
{
    public function __construct(private readonly CashAdvanceRequest|LeaveRequest $requestModel)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification): WebPushMessage
    {
        $employeeName = $this->requestModel->employee->name;

        if ($this->requestModel instanceof CashAdvanceRequest) {
            $title = 'New cash advance request';
            $body = sprintf('%s requested ₱%s', $employeeName, number_format((float) $this->requestModel->amount, 2));
        } else {
            $title = 'New leave request';
            $body = sprintf(
                '%s requested leave from %s to %s',
                $employeeName,
                $this->requestModel->start_date->format('M j'),
                $this->requestModel->end_date->format('M j'),
            );
        }

        return (new WebPushMessage)
            ->title($title)
            ->icon('/pwa-192x192.png')
            ->body($body)
            ->data(['url' => '/approvals']);
    }
}
```

- [ ] **Step 6: Wire it into CashAdvanceRequestController@store**

Edit `app/Http/Controllers/CashAdvanceRequestController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashAdvanceRequest;
use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class CashAdvanceRequestController extends Controller
{
    /**
     * The employee's own request portal — lists both cash advance and leave requests.
     */
    public function index()
    {
        $employee = request()->user()->employee;

        abort_if($employee === null, 403, 'No employee record is linked to your account.');

        return Inertia::render('requests/index', [
            'employee' => $employee->only(['id', 'name', 'employee_number', 'department']),
            'cashAdvances' => CashAdvanceRequest::where('employee_id', $employee->id)
                ->latest()
                ->get(),
            'leaveRequests' => LeaveRequest::where('employee_id', $employee->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreCashAdvanceRequest $request)
    {
        $employee = $request->user()->employee;

        $cashAdvanceRequest = CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => $request->validated('amount'),
            'needed_date' => $request->validated('needed_date'),
            'reason' => $request->validated('reason'),
            'status' => CashAdvanceRequest::STATUS_PENDING,
        ]);

        Notification::send(User::canApproveRequests(), new NewRequestSubmitted($cashAdvanceRequest));

        return redirect()->route('my-requests.index')->with('success', 'Cash advance request submitted.');
    }
}
```

- [ ] **Step 7: Wire it into LeaveRequestController@store**

Edit `app/Http/Controllers/LeaveRequestController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use Illuminate\Support\Facades\Notification;

class LeaveRequestController extends Controller
{
    public function store(StoreLeaveRequest $request)
    {
        $employee = $request->user()->employee;

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
            'reason' => $request->validated('reason'),
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        Notification::send(User::canApproveRequests(), new NewRequestSubmitted($leaveRequest));

        return redirect()->route('my-requests.index')->with('success', 'Leave/absent request submitted.');
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test tests/Feature/NewRequestSubmittedTest.php`
Expected: `PASS` for both tests.

- [ ] **Step 9: Run the full existing suite to check for regressions**

Run: `php artisan test tests/Feature/RoleAccessTest.php`
Expected: `PASS` (this test posts cash advance/leave requests as an employee with no admin/hr/overseer users seeded — `User::canApproveRequests()` simply returns an empty collection and `Notification::send()` is a no-op).

- [ ] **Step 10: Commit**

```bash
git add app/Models/User.php database/factories/UserFactory.php app/Notifications/NewRequestSubmitted.php app/Http/Controllers/CashAdvanceRequestController.php app/Http/Controllers/LeaveRequestController.php tests/Feature/NewRequestSubmittedTest.php
git commit -m "feat: notify admin/hr/overseer when a request is submitted"
```

---

### Task 3: "Request reviewed" notification (backend)

**Files:**
- Create: `app/Notifications/RequestReviewed.php`
- Modify: `app/Http/Controllers/RequestApprovalController.php`
- Test: `tests/Feature/RequestReviewedTest.php`

**Interfaces:**
- Consumes: nothing from Tasks 1–2 beyond the package/channel already installed.
- Produces: `App\Notifications\RequestReviewed`, constructed as `new RequestReviewed(CashAdvanceRequest|LeaveRequest $requestModel)` (referenced nowhere else in this plan).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RequestReviewedTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\CashAdvanceRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\RequestReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestReviewedTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_approving_cash_advance_notifies_the_employees_linked_user(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $employeeUser = $this->employeeUser();
        $request = CashAdvanceRequest::create([
            'employee_id' => $employeeUser->employee->id,
            'amount' => 500,
            'needed_date' => '2026-05-10',
            'reason' => 'Emergency expense',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)
            ->post("/approvals/cash-advance/{$request->id}/approve", ['review_note' => 'OK'])
            ->assertRedirect();

        Notification::assertSentTo($employeeUser, RequestReviewed::class);
    }

    public function test_rejecting_leave_notifies_the_employees_linked_user(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $employeeUser = $this->employeeUser();
        $request = LeaveRequest::create([
            'employee_id' => $employeeUser->employee->id,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
            'reason' => 'Family matter',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)
            ->post("/approvals/leave/{$request->id}/reject", ['review_note' => 'Denied'])
            ->assertRedirect();

        Notification::assertSentTo($employeeUser, RequestReviewed::class);
    }

    public function test_reviewing_request_does_not_notify_when_employee_has_no_linked_user(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $employee = Employee::factory()->create();
        $request = CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => 500,
            'needed_date' => '2026-05-10',
            'reason' => 'Emergency expense',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)
            ->post("/approvals/cash-advance/{$request->id}/approve", ['review_note' => 'OK'])
            ->assertRedirect();

        Notification::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/RequestReviewedTest.php`
Expected: FAIL — `Class "App\Notifications\RequestReviewed" not found`.

- [ ] **Step 3: Create the notification class**

Create `app/Notifications/RequestReviewed.php`:

```php
<?php

namespace App\Notifications;

use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class RequestReviewed extends Notification
{
    public function __construct(private readonly CashAdvanceRequest|LeaveRequest $requestModel)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification): WebPushMessage
    {
        $type = $this->requestModel instanceof CashAdvanceRequest ? 'Cash advance request' : 'Leave request';
        $decision = $this->requestModel->status === $this->requestModel::STATUS_APPROVED ? 'approved' : 'rejected';

        $title = "{$type} {$decision}";
        $body = $this->requestModel->review_note
            ? "Note: {$this->requestModel->review_note}"
            : "Your {$type} was {$decision}.";

        return (new WebPushMessage)
            ->title($title)
            ->icon('/pwa-192x192.png')
            ->body($body)
            ->data(['url' => '/my-requests']);
    }
}
```

- [ ] **Step 4: Wire it into RequestApprovalController@review**

Edit `app/Http/Controllers/RequestApprovalController.php`:

```php
use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use App\Notifications\RequestReviewed;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
```

```php
    /**
     * Apply a reviewer decision to a request model.
     */
    private function review(Request $request, CashAdvanceRequest|LeaveRequest $model, string $status): void
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $model->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        $model->employee->user?->notify(new RequestReviewed($model));

        $type = $model instanceof CashAdvanceRequest ? 'cash_advance' : 'leave';
        $decision = $status === CashAdvanceRequest::STATUS_APPROVED ? 'approved' : 'rejected';

        AuditLogger::record(
            "{$type}.{$decision}",
            $model,
            $model->employee->name,
            ['review_note' => $validated['review_note'] ?? null]
        );
    }
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/RequestReviewedTest.php`
Expected: `PASS` for all three tests.

- [ ] **Step 6: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: all tests pass (this repo has some pre-existing unrelated baseline failures noted in prior sessions — confirm no *new* failures were introduced by this change).

- [ ] **Step 7: Commit**

```bash
git add app/Notifications/RequestReviewed.php app/Http/Controllers/RequestApprovalController.php tests/Feature/RequestReviewedTest.php
git commit -m "feat: notify the employee when their request is reviewed"
```

---

### Task 4: Custom service worker with push handling (frontend)

**Files:**
- Modify: `package.json`
- Modify: `vite.config.ts`
- Create: `resources/js/sw.ts`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: a service worker that listens for `push` events and shows an OS notification using the `title`/`body`/`icon`/`data.url` fields sent by `WebPushMessage::toArray()` (Tasks 2–3). Also handles `notificationclick` to focus/open `data.url`. Task 5 does not call into this file directly — it only registers/subscribes via the browser `PushManager` API, which this service worker responds to.

- [ ] **Step 1: Install workbox dependencies**

Run: `npm install --save-dev workbox-precaching workbox-core`
Expected: `package.json`'s `devDependencies` gains `"workbox-precaching"` and `"workbox-core"` entries, `npm install` completes without errors.

- [ ] **Step 2: Switch the VitePWA plugin to the injectManifest strategy**

Edit `vite.config.ts` — replace the `VitePWA({...})` block:

```ts
        VitePWA({
            // No index.html for VitePWA to inject into (Laravel/Blade renders the shell),
            // so the manifest link + SW registration are added manually in app.blade.php / app.tsx.
            injectRegister: false,
            // laravel-vite-plugin builds hashed assets into public/build/ (served at /build/*),
            // but the manifest + service worker must live at the site root so the SW's default
            // scope covers the whole app, not just /build/. Pointing outDir at the real public
            // root (which already contains build/) makes the plugin write sw.js/manifest there
            // while still finding the hashed assets under build/ for precaching.
            outDir: 'public',
            base: '/',
            manifestFilename: 'manifest.webmanifest',
            registerType: 'autoUpdate',
            // Custom service worker (resources/js/sw.ts) handles push notifications;
            // vite-plugin-pwa injects the Workbox precache manifest into it at build time.
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            includeAssets: ['favicon.ico', 'apple-touch-icon-180x180.png'],
            manifest: {
                name: 'Payroll Portal',
                short_name: 'Payroll',
                description: 'Payroll, attendance, and employee self-service portal',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                theme_color: '#4B5563',
                background_color: '#ffffff',
                icons: [
                    { src: '/pwa-64x64.png', sizes: '64x64', type: 'image/png' },
                    { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            injectManifest: {
                globPatterns: ['**/*.{js,css,woff2,png,svg}'],
            },
        }),
```

- [ ] **Step 3: Write the custom service worker**

Create `resources/js/sw.ts`:

```ts
// @ts-nocheck
// Service worker globals (self, ServiceWorkerGlobalScope, PushEvent, NotificationEvent) conflict
// with the DOM lib used by the rest of this project's tsconfig, so this file is intentionally
// excluded from type checking. Vite/esbuild still transpiles it normally at build time.
import { clientsClaim } from 'workbox-core';
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

self.skipWaiting();
clientsClaim();

cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : { title: 'Payroll Portal' };

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: payload.icon || '/pwa-192x192.png',
            data: payload.data,
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            const existing = clientList.find((client) => new URL(client.url).pathname === url);

            if (existing) {
                return existing.focus();
            }

            return self.clients.openWindow(url);
        }),
    );
});
```

- [ ] **Step 4: Verify the build**

Run: `npm run types:check`
Expected: no errors (sw.ts is skipped via `@ts-nocheck`).

Run: `npm run build`
Expected: build succeeds; `public/sw.js` is generated (replacing the previous Workbox-autogenerated one) and contains the precache manifest plus `push`/`notificationclick` listeners. Confirm by opening `public/sw.js` and checking it references `addEventListener("push"` and `addEventListener("notificationclick"`.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json vite.config.ts resources/js/sw.ts
git commit -m "feat: add push notification handling to the service worker"
```

---

### Task 5: Settings → Notifications opt-in page (frontend)

**Files:**
- Create: `app/Http/Controllers/Settings/NotificationController.php`
- Modify: `routes/settings.php`
- Modify: `resources/js/layouts/settings/layout.tsx`
- Create: `resources/js/lib/csrf.ts`
- Create: `resources/js/pages/settings/notifications.tsx`

**Interfaces:**
- Consumes: `POST /push-subscriptions` / `DELETE /push-subscriptions` (Task 1, named `push-subscriptions.store` / `push-subscriptions.destroy`) and the shared `vapidPublicKey` Inertia prop (Task 1).
- Produces: the `/settings/notifications` page reachable from the settings sidebar — no other task depends on this.

- [ ] **Step 1: Add the backend page controller and route**

Create `app/Http/Controllers/Settings/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show the user's push notification settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/notifications');
    }
}
```

Edit `routes/settings.php`:

```php
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
});
```

- [ ] **Step 2: Add the CSRF helper**

Create `resources/js/lib/csrf.ts`:

```ts
/**
 * Reads the XSRF-TOKEN cookie Laravel sets on every response, for use in
 * plain `fetch()` calls that bypass Inertia's request handling (which
 * doesn't apply here since these calls don't navigate to a new page).
 */
export function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
```

- [ ] **Step 3: Add the Notifications tab to the settings sidebar**

Edit `resources/js/layouts/settings/layout.tsx`:

```ts
import { edit as editAppearance } from '@/routes/appearance';
import { index as indexAuditLogs } from '@/routes/audit-logs';
import { edit as editCompany } from '@/routes/company';
import { edit as editNotifications } from '@/routes/notifications';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as indexUsers } from '@/routes/users';
```

```ts
const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Notifications',
        href: editNotifications(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
```

- [ ] **Step 4: Build the Notifications settings page**

Create `resources/js/pages/settings/notifications.tsx`:

```tsx
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { getCsrfToken } from '@/lib/csrf';
import { edit as editNotifications } from '@/routes/notifications';
import { destroy as destroySubscription, store as storeSubscription } from '@/routes/push-subscriptions';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

export default function Notifications() {
    const { props } = usePage<{ vapidPublicKey: string | null }>();
    const [supported, setSupported] = useState(true);
    const [subscribed, setSubscribed] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setSupported(false);
            return;
        }

        navigator.serviceWorker.ready
            .then((registration) => registration.pushManager.getSubscription())
            .then((subscription) => setSubscribed(subscription !== null));
    }, []);

    async function enable() {
        if (!props.vapidPublicKey) {
            toast.error('Push notifications are not configured for this server.');
            return;
        }

        setBusy(true);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                toast.error('Notification permission was denied.');
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(props.vapidPublicKey),
            });

            await fetch(storeSubscription().url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(subscription.toJSON()),
            });

            setSubscribed(true);
            toast.success('Push notifications enabled.');
        } finally {
            setBusy(false);
        }
    }

    async function disable() {
        setBusy(true);

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await fetch(destroySubscription().url, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });

                await subscription.unsubscribe();
            }

            setSubscribed(false);
            toast.success('Push notifications disabled.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notification settings"
                    description="Get a push notification on this device for new requests and review decisions"
                />

                {!supported && (
                    <p className="text-muted-foreground text-sm">
                        Push notifications aren't supported in this browser.
                    </p>
                )}

                {supported && (
                    <Button
                        disabled={busy}
                        onClick={subscribed ? disable : enable}
                    >
                        {subscribed ? 'Disable push notifications' : 'Enable push notifications'}
                    </Button>
                )}
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [
        {
            title: 'Notification settings',
            href: editNotifications(),
        },
    ],
};
```

- [ ] **Step 5: Regenerate Wayfinder routes and verify the build**

Run: `npm run build`
Expected: succeeds and regenerates `resources/js/routes/notifications.ts` (exporting `edit`) and `resources/js/routes/push-subscriptions.ts` (exporting `store`/`destroy`) from the new route names in `routes/settings.php` / `routes/web.php`.

Run: `npm run types:check`
Expected: no errors.

Run: `npm run lint:check`
Expected: no errors.

- [ ] **Step 6: Manually verify in the browser**

Run: `composer run dev` (starts the Laravel server, queue listener, and Vite).

In a browser (Chrome/Edge, since Web Push requires a supported browser and a secure context — `http://localhost` counts as secure for this purpose):
1. Log in, go to `/settings/notifications`, click "Enable push notifications", accept the browser permission prompt.
2. In a second browser session logged in as an admin/hr user, log in as an employee in the first session's account and submit a cash advance or leave request from `/my-requests`.
3. Confirm the admin/hr browser shows an OS notification, and clicking it opens/focuses `/approvals`.
4. As admin/hr, approve or reject the request from `/approvals`.
5. Confirm the employee's browser shows an OS notification, and clicking it opens/focuses `/my-requests`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Settings/NotificationController.php routes/settings.php resources/js/layouts/settings/layout.tsx resources/js/lib/csrf.ts resources/js/pages/settings/notifications.tsx
git commit -m "feat: add Settings > Notifications opt-in page"
```
