<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers SchoolController::resolveFromPlace()'s 'mapbox:'-prefixed place_id
 * path — before this, a place_id issued by Mapbox autocomplete (see
 * MapProxyPlacesProviderTest) had no branch here and fell straight into the
 * Google Place Details call, which doesn't recognize a Mapbox id and always
 * failed with "Could not find this school".
 */
class SchoolResolveFromPlaceTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(): array
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_resolves_a_mapbox_place_id_without_calling_google(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'INVALID_REQUEST'], 400),
        ]);

        School::query()->delete();

        $response = $this->withHeaders($this->authHeaders())->postJson(
            '/api/driver/school-bus/schools/resolve',
            ['place_id' => 'mapbox:address.999']
        );

        // No cached Mapbox feature exists for this id in this test, so
        // resolution legitimately fails — the point here is only that it's
        // routed to the Mapbox branch (never reaches Google), not that a
        // bare place_id with no prior search resolves successfully.
        $response->assertStatus(422);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }

    public function test_resolves_a_mapbox_place_id_previously_cached_by_autocomplete(): void
    {
        config(['services.mapbox.token' => 'test-token']);
        \TCG\Voyager\Models\Setting::updateOrCreate(['key' => 'maps.places_provider'], ['value' => 'mapbox']);

        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => [[
                'id' => 'address.999',
                'place_type' => ['poi'],
                'text' => 'Saida Public School',
                'place_name' => 'Saida Public School, Saida, Lebanon',
                'center' => [35.37, 33.56],
            ]]], 200),
            'maps.googleapis.com/*' => Http::response(['status' => 'INVALID_REQUEST'], 400),
        ]);

        $headers = $this->authHeaders();

        $placeId = $this->withHeaders($headers)
            ->getJson('/api/maps/place-autocomplete?input=Saida+Public+School')
            ->json('predictions.0.place_id');

        $this->assertStringStartsWith('mapbox:', $placeId);

        School::query()->delete();

        $response = $this->withHeaders($headers)->postJson(
            '/api/driver/school-bus/schools/resolve',
            ['place_id' => $placeId]
        );

        $response->assertStatus(201)->assertJson(['result' => true]);
        $this->assertDatabaseHas('schools', [
            'place_id' => $placeId,
            'name' => 'Saida Public School',
        ]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis.com'));
    }
}
