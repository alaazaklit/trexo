<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\School;
use App\Models\SchoolBusRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class SchoolBusRouteController extends Controller
{
    private function authenticatedDriver(): ?Driver
    {
        $user = JWTAuth::parseToken()->authenticate();

        return Driver::where('user_id', $user->id)->first();
    }

    // The backend can't otherwise know a driver's app language when
    // composing a push notification asynchronously (e.g. a parent
    // submitting a school-bus request) — opportunistically refreshed here
    // since this endpoint is hit every time the driver opens their School
    // Bus page. See also SchoolBusSubscriptionController::syncLanguage().
    private function syncLanguage(Driver $driver, ?string $language): void
    {
        if (empty($language) || $driver->user === null || $driver->user->language === $language) {
            return;
        }

        $driver->user->update(['language' => $language]);
    }

    public function status(Request $request)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $this->syncLanguage($driver, $request->query('language'));

        return response()->json([
            'result' => true,
            'message' => 'School bus status loaded',
            'data' => [
                'school_bus_status' => $driver->school_bus_status,
                'child_discount_percent' => (float) ($driver->school_bus_child_discount_percent ?? 0),
            ],
        ]);
    }

    // Driver-level sibling discount — a single rate applied per child across
    // every school this driver serves, per the product decision to keep this
    // simple rather than configurable per school/route.
    public function updateChildDiscount(Request $request)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'percent' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $driver->school_bus_child_discount_percent = $request->input('percent');
        $driver->save();

        return response()->json([
            'result' => true,
            'message' => 'Discount updated',
            'data' => ['child_discount_percent' => (float) $driver->school_bus_child_discount_percent],
        ]);
    }

    public function enable()
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        if ($driver->school_bus_status === null || $driver->school_bus_status === 'rejected') {
            $driver->school_bus_status = 'pending';
            $driver->save();
        }

        return response()->json([
            'result' => true,
            'message' => 'School bus enrollment submitted',
            'data' => ['school_bus_status' => $driver->school_bus_status],
        ]);
    }

    // The driver's own routes, grouped by school for "My Routes".
    public function index()
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        $routes = SchoolBusRoute::with('school')->where('driver_id', $driver->id)->orderByDesc('id')->get();

        return response()->json([
            'result' => true,
            'message' => 'Routes loaded',
            'data' => $routes->map(fn (SchoolBusRoute $route) => $this->formatRoute($route)),
        ]);
    }

    public function store(Request $request)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver) {
            return response()->json(['result' => false, 'message' => 'Driver not found'], 404);
        }

        if ($driver->school_bus_status !== 'approved') {
            return response()->json(['result' => false, 'message' => 'You must be an approved school bus driver to add routes'], 403);
        }

        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'pickup_area' => 'required|string|max:255',
            'monthly_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $route = SchoolBusRoute::create([
            'driver_id' => $driver->id,
            'school_id' => $request->input('school_id'),
            'pickup_area' => $request->input('pickup_area'),
            'monthly_price' => $request->input('monthly_price'),
        ]);

        return response()->json([
            'result' => true,
            'message' => 'Route added',
            'data' => $this->formatRoute($route->load('school')),
        ], 201);
    }

    public function update(Request $request, SchoolBusRoute $route)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver || $route->driver_id !== $driver->id) {
            return response()->json(['result' => false, 'message' => 'Route not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'pickup_area' => 'sometimes|required|string|max:255',
            'monthly_price' => 'sometimes|required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $route->update($request->only(['pickup_area', 'monthly_price', 'is_active']));

        return response()->json([
            'result' => true,
            'message' => 'Route updated',
            'data' => $this->formatRoute($route->load('school')),
        ]);
    }

    public function destroy(SchoolBusRoute $route)
    {
        $driver = $this->authenticatedDriver();
        if (!$driver || $route->driver_id !== $driver->id) {
            return response()->json(['result' => false, 'message' => 'Route not found'], 404);
        }

        $route->delete();

        return response()->json(['result' => true, 'message' => 'Route deleted']);
    }

    // Parent-facing "View Prices" — a given driver's routes, optionally
    // scoped to the school the parent is browsing.
    public function forDriver(Request $request, Driver $driver)
    {
        JWTAuth::parseToken()->authenticate();

        $query = SchoolBusRoute::with('school')
            ->where('driver_id', $driver->id)
            ->where('is_active', true);

        $schoolId = $request->query('school_id');
        if (!empty($schoolId)) {
            $query->where('school_id', $schoolId);
        }

        // Narrows to the exact route once the parent already picked a
        // pickup area in step 2 of the browse flow, instead of showing
        // every area this driver serves at the school all over again.
        $pickupArea = $request->query('pickup_area');
        if (!empty($pickupArea)) {
            $query->where('pickup_area', $pickupArea);
        }

        $routes = $query->orderBy('pickup_area')->get();

        return response()->json([
            'result' => true,
            'message' => 'Routes loaded',
            'data' => $routes->map(fn (SchoolBusRoute $route) => $this->formatRoute($route)),
            // Lets the client compute the sibling-discounted price live as
            // the parent adjusts the number of children, without a round
            // trip per keystroke.
            'child_discount_percent' => (float) ($driver->school_bus_child_discount_percent ?? 0),
        ]);
    }

    private function formatRoute(SchoolBusRoute $route): array
    {
        return [
            'id' => $route->id,
            'school_id' => $route->school_id,
            'school_name' => $route->school?->name,
            'pickup_area' => $route->pickup_area,
            'monthly_price' => (float) $route->monthly_price,
            'is_active' => (bool) $route->is_active,
        ];
    }
}
