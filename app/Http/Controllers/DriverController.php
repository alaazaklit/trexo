<?php 
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class DriverController extends Controller
{
    public function index()
    {

        // $user = User::create([
        //     'phone'=>'71111111',
        //     'name'=>'alaa7',
        //     'email' => 'alaa5@gmail.com',
        //     'password' => Hash::make('password'),
        // ]);

        $credentials = ['email' => 'alaa2@gmail.com', 'password' => '123456'];
        $token = JWTAuth::attempt($credentials);

   
   
        $drivers = Driver::all();
        return response()->json($drivers);
    }

    public function show($id)
    {
        $driver = Driver::findOrFail($id);
        return response()->json($driver);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'license_number' => 'required|string|unique:drivers,license_number',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'rating' => 'nullable|numeric|min:0|max:5'
        ]);

        $driver = Driver::create($request->all());
        return response()->json($driver, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'license_number' => 'sometimes|required|string|unique:drivers,license_number,' . $id,
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'rating' => 'nullable|numeric|min:0|max:5'
        ]);

        $driver = Driver::findOrFail($id);
        $driver->update($request->all());
        return response()->json($driver);
    }

    public function destroy($id)
    {
        Driver::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
