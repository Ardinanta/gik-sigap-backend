<?php

use App\Models\Partnership;
use App\Models\Reservation;
use App\Notifications\PartnershipActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('notifies the owning farmer when a buyer submits a recommended partnership', function () {
    Notification::fake();
    $fixture = partnershipFixture();

    $response = $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/matches/{$fixture['match']->id}/partnership")
        ->assertCreated();

    $partnershipId = $response->json('data.id');
    Notification::assertSentTo(
        $fixture['farmer'],
        PartnershipActivityNotification::class,
        fn (PartnershipActivityNotification $notification) => $notification->category === 'partnership_requested'
            && $notification->route === "/app/farmer/kemitraan/{$partnershipId}",
    );
    Notification::assertNotSentTo($fixture['buyer'], PartnershipActivityNotification::class);
});

it('notifies the buyer only after the farmer accepts a recommended partnership', function () {
    Notification::fake();
    $fixture = partnershipFixture();
    $partnership = Partnership::query()->create([
        'match_id' => $fixture['match']->id,
        'initiated_by_user_id' => $fixture['buyer']->id,
        'status' => 'interested',
    ]);

    $this->actingAs($fixture['farmer'])
        ->postJson("/api/v1/farmer/partnerships/{$partnership->id}/confirmation")
        ->assertOk();

    Notification::assertSentTo(
        $fixture['buyer'],
        PartnershipActivityNotification::class,
        fn (PartnershipActivityNotification $notification) => $notification->category === 'partnership_confirmed'
            && $notification->route === "/app/buyer/kemitraan/{$partnership->id}",
    );
});

it('notifies both sides across a reservation request and acceptance without duplicate acceptance notice', function () {
    Notification::fake();
    $fixture = catalogFixture();
    $plan = createCatalogPlan($fixture);

    $response = $this->actingAs($fixture['buyer'])
        ->postJson("/api/v1/harvest-plans/{$plan->id}/reservations", ['volume_kg' => 100])
        ->assertCreated();
    $reservation = Reservation::query()->findOrFail($response->json('data.id'));

    Notification::assertSentTo(
        $fixture['farmer'],
        PartnershipActivityNotification::class,
        fn (PartnershipActivityNotification $notification) => $notification->category === 'reservation_requested',
    );

    Notification::fake();
    $this->actingAs($fixture['farmer'])
        ->postJson("/api/v1/farmer/reservations/{$reservation->id}/confirmation")
        ->assertOk();

    $partnership = Partnership::query()->where('reservation_id', $reservation->id)->sole();
    Notification::assertSentTo(
        $fixture['buyer'],
        PartnershipActivityNotification::class,
        fn (PartnershipActivityNotification $notification) => $notification->category === 'reservation_confirmed'
            && $notification->route === "/app/buyer/kemitraan/{$partnership->id}",
    );

    Notification::fake();
    $this->actingAs($fixture['farmer'])
        ->postJson("/api/v1/farmer/reservations/{$reservation->id}/confirmation")
        ->assertOk();
    Notification::assertNothingSent();
});
