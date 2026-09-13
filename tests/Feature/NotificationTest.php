<?php

use App\Models\User;
use App\Notifications\PartnershipActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication to access notifications', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->patchJson('/api/v1/notifications/read-all')->assertUnauthorized();
});

it('lists only the authenticated users notifications with unread count and safe data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $user->notify(new PartnershipActivityNotification(
        'partnership_requested',
        'Pengajuan kemitraan baru',
        'Seorang pembeli mengajukan kemitraan.',
        '/app/farmer/kemitraan/10',
        10,
    ));
    $other->notify(new PartnershipActivityNotification(
        'reservation_confirmed',
        'Pengajuan diterima',
        'Pengajuan pasokan diterima.',
        '/app/buyer/kemitraan/20',
        20,
    ));

    $response = $this->actingAs($user)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.category', 'partnership_requested')
        ->assertJsonPath('data.0.route', '/app/farmer/kemitraan/10');

    expect($response->getContent())
        ->not->toContain($other->email)
        ->not->toContain((string) $other->phone)
        ->not->toContain(PartnershipActivityNotification::class);
});

it('marks an owned notification or all owned notifications as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $user->notify(new PartnershipActivityNotification('first', 'Pertama', 'Pesan pertama.', '/app/buyer/kemitraan'));
    $user->notify(new PartnershipActivityNotification('second', 'Kedua', 'Pesan kedua.', '/app/buyer/kemitraan'));
    $other->notify(new PartnershipActivityNotification('other', 'Lain', 'Pesan pengguna lain.', '/app/buyer/kemitraan'));

    $first = $user->notifications()->oldest()->firstOrFail();
    $otherNotification = $other->notifications()->firstOrFail();

    $this->actingAs($user)
        ->patchJson("/api/v1/notifications/{$first->id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $first->id);

    expect($first->fresh()->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->patchJson("/api/v1/notifications/{$otherNotification->id}/read")
        ->assertNotFound();

    $this->actingAs($user)->patchJson('/api/v1/notifications/read-all')->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});

it('removes unsafe notification routes from the api response', function () {
    $user = User::factory()->create();
    $user->notify(new PartnershipActivityNotification(
        'unsafe',
        'Aktivitas',
        'Route tidak boleh keluar dari aplikasi.',
        'https://example.com/phishing',
    ));

    $this->actingAs($user)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.route', null);
});
