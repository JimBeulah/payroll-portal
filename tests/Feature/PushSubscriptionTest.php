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

    public function test_notification_settings_page_reports_no_subscription_for_user_without_one(): void
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->get('/settings/notifications')
            ->assertInertia(fn ($page) => $page->where('hasPushSubscription', false));
    }

    public function test_notification_settings_page_reports_subscription_for_user_with_one(): void
    {
        $user = User::factory()->employee()->create();

        $user->updatePushSubscription(
            endpoint: 'https://push.example.com/xyz789',
            key: 'test-p256dh-key',
            token: 'test-auth-token',
            contentEncoding: 'aesgcm',
        );

        $this->actingAs($user)->get('/settings/notifications')
            ->assertInertia(fn ($page) => $page->where('hasPushSubscription', true));
    }
}
