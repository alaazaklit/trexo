<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class TripController extends Controller
{

 
    public function index()
    {
       
        $user = Auth::user();
        if ($user) {
            if(!empty($user->avatar )){
        $trips = Trip::all();
        return response()->json($trips);}
    }
        return response()->json(['error' => 'Unauthorized'], 201);
    }

    public function show($id)
    {
        $trip = Trip::findOrFail($id);
        return response()->json($trip);
    }

    public function store(Request $request)
    {
        echo "fs3f";exit;
        $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            // 'passenger_id' => 'required|exists:users,id',
            // 'pickup_location' => 'required|string',
            // 'dropoff_location' => 'required|string',
            // 'fare' => 'required|numeric|min:0',
            // 'status' => 'required|string|in:pending,completed,canceled'
        ]);

    

        $trip = Trip::create($request->all());
       
        return response()->json($trip, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'driver_id' => 'sometimes|required|exists:drivers,id',
            'passenger_id' => 'sometimes|required|exists:users,id',
            'pickup_location' => 'sometimes|required|string',
            'dropoff_location' => 'sometimes|required|string',
            'fare' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|string|in:pending,completed,canceled'
        ]);

        $trip = Trip::findOrFail($id);
        $trip->update($request->all());
        return response()->json($trip);
    }

    public function destroy($id)
    {
        Trip::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
