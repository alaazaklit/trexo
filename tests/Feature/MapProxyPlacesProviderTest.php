<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

/**
 * Covers the maps.places_provider toggle end to end through the real HTTP
 * routes: provider dispatch, automatic failover to the other provider, and
 * placeDetails() resolving a place_id by its own provider prefix rather
 * than by whatever places_provider happens to be configured at lookup time.
 */
class MapProxyPlacesProviderTest extends TestCase
{
    use DatabaseTransactions;

    private function setPlacesProvider(string $provider): void
    {
        Setting::updateOrCreate(['key' => 'maps.places_provider'], ['value' => $provider]);
    }

    private function authHeaders(): array
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    private function fakeMapboxFeature(): array
    {
        return [
            'id' => 'address.999',
            'place_type' => ['address'],
            'text' => 'Main Street',
            'address' => '5',
            'place_name' => '5 Main Street, Saida, Lebanon',
            'center' => [35.37, 33.56],
            'context' => [
                ['id' => 'place.1', 'text' => 'Saida'],
                ['id' => 'country.1', 'text' => 'Lebanon', 'short_code' => 'lb'],
            ],
        ];
    }

    public function test_reverse_geocode_uses_mapbox_when_configured_as_the_provider(): void
    {
        config(['services.mapbox.token' => 'test-token']);
        $this->setPlacesProvider('mapbox');

        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeMapboxFeature()]], 200),
            'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'results' => []], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/maps/reverse-geocode?lat=33.56&lng=35.37');

        $response->assertStatus(200)->assertJson(['status' => 'OK']);
        $locality = collect($response->json('results.0.address_components'))
            ->first(fn ($component) => in_array('locality', $component['types'], true));
        $this->assertSame('Saida', $locality['long_name'] ?? null);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_reverse_geocode_falls_back_to_google_when_mapbox_fails(): void
    {
        config(['services.mapbox.token' => 'test-token']);
        $this->setPlacesProvider('mapbox');

        Http::fake([
            'api.mapbox.com/*' => Http::response(['message' => 'invalid token'], 401),
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [['formatted_address' => 'Fallback Address', 'address_components' => []]],
            ], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/maps/reverse-geocode?lat=33.56&lng=35.37');

        $response->assertStatus(200)->assertJson(['status' => 'OK']);
        $this->assertSame('Fallback Address', $response->json('results.0.formatted_address'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'mapbox.com'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_reverse_geocode_falls_back_to_mapbox_when_google_fails(): void
    {
        config(['services.mapbox.token' => 'test-token', 'services.google_maps.key' => 'test-google-key']);
        $this->setPlacesProvider('google');

        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'REQUEST_DENIED'], 200),
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeMapboxFeature()]], 200),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/maps/reverse-geocode?lat=33.56&lng=35.37');

        $response->assertStatus(200)->assertJson(['status' => 'OK']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'mapbox.com'));
    }

    public function test_place_autocomplete_and_place_details_round_trip_through_mapbox(): void
    {
        config(['services.mapbox.token' => 'test-token']);
        $this->setPlacesProvider('mapbox');

        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeMapboxFeature()]], 200),
        ]);

        $headers = $this->authHeaders();

        $autocomplete = $this->withHeaders($headers)
            ->getJson('/api/maps/place-autocomplete?input=Main+Street');
        $autocomplete->assertStatus(200);
        $placeId = $autocomplete->json('predictions.0.place_id');

        $this->assertNotNull($placeId);
        $this->assertStringStartsWith('mapbox:', $placeId);

        $details = $this->withHeaders($headers)
            ->getJson('/api/maps/place-details?place_id=' . urlencode($placeId));

        $details->assertStatus(200)->assertJson(['status' => 'OK']);
        $this->assertSame(33.56, $details->json('result.geometry.location.lat'));
        $this->assertSame(35.37, $details->json('result.geometry.location.lng'));
    }

    public function test_place_details_resolves_a_mapbox_place_id_even_if_places_provider_was_since_switched_to_google(): void
    {
        config(['services.mapbox.token' => 'test-token', 'services.google_maps.key' => 'test-google-key']);
        $this->setPlacesProvider('mapbox');

        Http::fake(['api.mapbox.com/*' => Http::response(['features' => [$this->fakeMapboxFeature()]], 200)]);

        $headers = $this->authHeaders();
        $placeId = $this->withHeaders($headers)
            ->getJson('/api/maps/place-autocomplete?input=Main+Street')
            ->json('predictions.0.place_id');

        // The provider setting flips AFTER the search — a real scenario if
        // an admin changes it mid-session — but the place_id itself must
        // still resolve via Mapbox, not be sent to Google (which wouldn't
        // recognize it).
        $this->setPlacesProvider('google');
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [$this->fakeMapboxFeature()]], 200),
            'maps.googleapis.com/*' => Http::response(['status' => 'INVALID_REQUEST'], 400),
        ]);

        $details = $this->withHeaders($headers)
            ->getJson('/api/maps/place-details?place_id=' . urlencode($placeId));

        $details->assertStatus(200)->assertJson(['status' => 'OK']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_admin_maps_config_now_accepts_mapbox_for_places_provider(): void
    {
        $this->setPlacesProvider('google');

        // Self-contained rather than assuming RolesAndPermissionsSeeder has
        // already run against whatever database this suite executes
        // against — matches this test's own DatabaseTransactions isolation.
        Permission::firstOrCreate(['name' => 'maps.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'web'],
            ['display_name' => 'Super Admin']
        );
        $role->givePermissionTo('maps.manage');

        $admin = User::factory()->create();
        $admin->assignRole($role);

        $response = $this->actingAs($admin, 'web')
            ->postJson('/admin/maps-config', ['places_provider' => 'mapbox']);

        $response->assertStatus(200)->assertJson([
            'result' => true,
            'data' => ['places_provider' => 'mapbox'],
        ]);
    }
}
