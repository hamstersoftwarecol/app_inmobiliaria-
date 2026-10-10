<?php

namespace Tests\Feature\Search;

use App\Domain\Identity\Enums\RoleName;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Notifications\Search\SavedSearchMatchesNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FavoritesAndSavedSearchesTest extends TestCase
{
    public function test_buyer_manages_favorites_of_public_listings_only(): void
    {
        $this->actingAsRole(RoleName::Buyer);
        $listing = Property::factory()->published()->create(['title' => 'Favorite colonial']);
        $draft = Property::factory()->create();

        $this->putJson("/api/v1/me/favorites/{$listing->slug}")->assertNoContent();
        $this->putJson("/api/v1/me/favorites/{$listing->slug}")->assertNoContent(); // idempotent
        $this->putJson("/api/v1/me/favorites/{$draft->slug}")->assertNotFound();

        $this->getJson('/api/v1/me/favorites')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Favorite colonial');
        $this->getJson('/api/v1/me/favorites/slugs')->assertOk()->assertExactJson(['data' => [$listing->slug]]);

        // Unpublished favorites are hidden but kept, and can still be removed.
        $listing->forceFill(['is_published' => false])->save();
        $this->getJson('/api/v1/me/favorites')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/me/favorites/{$listing->slug}")->assertNoContent();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_favorites_and_saved_searches_require_their_permissions(): void
    {
        $listing = Property::factory()->published()->create();

        $this->putJson("/api/v1/me/favorites/{$listing->slug}")->assertUnauthorized();

        $this->actingAsRole(RoleName::Agent); // agents have neither permission by default
        $this->putJson("/api/v1/me/favorites/{$listing->slug}")->assertForbidden();
        $this->getJson('/api/v1/me/saved-searches')->assertForbidden();

        $this->actingAsRole(RoleName::Seller); // sellers keep favorites but not saved searches
        $this->putJson("/api/v1/me/favorites/{$listing->slug}")->assertNoContent();
        $this->postJson('/api/v1/me/saved-searches', ['name' => 'Hoboken', 'filters' => ['city' => 'Hoboken']])->assertForbidden();
    }

    public function test_saved_searches_validate_filters_and_are_private(): void
    {
        $owner = $this->actingAsRole(RoleName::Buyer);

        $this->postJson('/api/v1/me/saved-searches', ['name' => 'Anything', 'filters' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['filters'], 'error.details.fields');
        $this->postJson('/api/v1/me/saved-searches', ['name' => 'Bad', 'filters' => ['min_price' => 500000, 'max_price' => 100000]])
            ->assertUnprocessable()->assertJsonValidationErrors(['filters.max_price'], 'error.details.fields');
        $this->postJson('/api/v1/me/saved-searches', ['name' => 'Bad', 'filters' => ['bbox' => 'nope']])
            ->assertUnprocessable()->assertJsonValidationErrors(['filters.bbox'], 'error.details.fields');

        $id = $this->postJson('/api/v1/me/saved-searches', [
            'name' => 'Hoboken 3 beds',
            'filters' => ['city' => 'Hoboken', 'min_beds' => 3, 'max_price' => '', 'amenities' => ['garage'], 'page' => 4],
            'sort' => 'price_asc',
        ])->assertCreated()
            ->assertJsonPath('data.filters', ['city' => 'Hoboken', 'min_beds' => 3, 'amenities' => ['garage']])
            ->assertJsonPath('data.alert_frequency', 'daily')
            ->json('data.id');

        $this->patchJson("/api/v1/me/saved-searches/{$id}", ['alert_frequency' => 'weekly'])->assertOk()->assertJsonPath('data.alert_frequency', 'weekly');

        $this->actingAsRole(RoleName::Buyer);
        $this->getJson('/api/v1/me/saved-searches')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/me/saved-searches/{$id}", ['name' => 'Mine now'])->assertNotFound();
        $this->deleteJson("/api/v1/me/saved-searches/{$id}")->assertNotFound();
        $this->assertSame('Hoboken 3 beds', SavedSearch::find($id)->name);
        $this->assertSame($owner->id, SavedSearch::find($id)->user_id);
    }

    public function test_saved_search_limit(): void
    {
        $user = $this->actingAsRole(RoleName::Buyer);
        for ($i = 0; $i < 25; $i++) {
            $user->savedSearches()->create(['name' => "Search {$i}", 'filters' => ['city' => 'Hoboken']]);
        }

        $this->postJson('/api/v1/me/saved-searches', ['name' => 'One more', 'filters' => ['city' => 'Newark']])
            ->assertUnprocessable()->assertJsonPath('error.code', 'saved_search_limit_reached');
    }

    public function test_alerts_include_only_new_public_matches_once(): void
    {
        Notification::fake();
        $buyer = $this->userWithRole(RoleName::Buyer);
        $search = $buyer->savedSearches()->create([
            'name' => 'Hoboken under 1M', 'filters' => ['city' => 'Hoboken', 'max_price' => 1000000], 'last_alerted_at' => now()->subDay(),
        ]);
        $muted = $buyer->savedSearches()->create(['name' => 'Muted', 'filters' => ['city' => 'Hoboken'], 'alert_frequency' => 'off', 'last_alerted_at' => now()->subDay()]);

        Property::factory()->published()->create(['title' => 'Old match', 'published_at' => now()->subDays(3)]);
        Property::factory()->published()->create(['title' => 'New match', 'published_at' => now()->subHour()]);
        Property::factory()->published()->create(['title' => 'Too expensive', 'list_price' => 2000000, 'published_at' => now()->subHour()]);
        Property::factory()->published()->create(['title' => 'Elsewhere', 'city' => 'Newark', 'published_at' => now()->subHour()]);
        Property::factory()->create(['title' => 'Draft', 'published_at' => now()->subHour()]);

        $this->artisan('hitlease:saved-searches:alert --frequency=daily')->expectsOutputToContain('notified 1')->assertSuccessful();

        Notification::assertSentTo($buyer, SavedSearchMatchesNotification::class, function ($n, $channels) use ($search) {
            return $n->search->is($search) && $n->total === 1 && $n->listings[0]['title'] === 'New match' && $channels === ['mail', 'database'];
        });
        Notification::assertSentTimes(SavedSearchMatchesNotification::class, 1);
        $this->assertNotNull($search->fresh()->last_alerted_at);
        $this->assertTrue($muted->fresh()->last_alerted_at->lt(now()->subHours(12)));

        // Second run: nothing new since the last alert.
        $this->artisan('hitlease:saved-searches:alert --frequency=daily')->expectsOutputToContain('notified 0');
        Notification::assertSentTimes(SavedSearchMatchesNotification::class, 1);

        $this->artisan('hitlease:saved-searches:alert --frequency=off')->assertExitCode(2);
    }

    public function test_alerts_skip_unverified_mail_and_deactivated_users(): void
    {
        Notification::fake();
        $unverified = $this->userWithRole(RoleName::Buyer, ['email_verified_at' => null]);
        $inactive = $this->userWithRole(RoleName::Buyer, ['status' => 'deactivated']);
        foreach ([$unverified, $inactive] as $user) {
            $user->savedSearches()->create(['name' => 'Hoboken', 'filters' => ['city' => 'Hoboken'], 'last_alerted_at' => now()->subDay()]);
        }
        Property::factory()->published()->create(['published_at' => now()->subHour()]);

        $this->artisan('hitlease:saved-searches:alert');

        Notification::assertSentTo($unverified, SavedSearchMatchesNotification::class, fn ($n, $channels) => $channels === ['database']);
        Notification::assertNotSentTo($inactive, SavedSearchMatchesNotification::class);
    }
}
