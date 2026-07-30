<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Schedule;
use App\Address;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Order;
use App\Http\Controllers\Api\OrderController;
 use App\Services\Firebase\FcmMessagingService;



class ScheduleController extends Controller
{


    
  

        /**
     * Authenticate using the Firebase Service Account
     *
     * @param string $serviceAccountFile
     */
 
    public function createOrder(Request $request,FcmMessagingService $Notification ){


        $user = JWTAuth::parseToken()->authenticate();
        

        $validator = Validator::make($request->all(), [
            'type' => 'integer',
            'start_address' => 'required',
            'destination_address' => 'required',
            
        ]);
       
        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
                'data' => []
            ], 400);
        }
     
        $validated = $validator->validated();
        $orderKind = $request->input('order_kind');
        if (empty($orderKind)) {
            $orderKind = ((int) $request->input('type', 1)) === 0 ? 'taxi' : 'delivery';
        }
        $order = Order::create([
            'user_id'=>$user->id,
            'description'=>$request->input('description', ''),
            'order_kind'=>$orderKind,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            // Not "waiting_driver_response" yet — that specifically means a
            // driver has been assigned and we're counting down for their
            // reply (see OrderController::ChooseDriver, which is what
            // actually sets this status once the seller picks someone).
            // Using the same status before any driver is chosen misleads
            // the seller into thinking a driver is already being waited on.
            'status'=>'pending_driver_selection'
            // If needed, you can also use 'selectedDate' or other fields here
        ]);

        $order->tracking_id = $this->generateTrackingId($order->id);
        $order->route_points = $request->input('route_points', []);
        $order->route_distance_km = $request->input('route_distance_km');
        $order->save();

        $result=$this->store($request, $order);

        // No notification is sent here on purpose: drivers are only notified
        // once the seller actually chooses one, via OrderController::ChooseDriver.

        return $result;

    }



    private function generateTrackingId($orderid)
    {
        
        // Generate a unique tracking ID format
        $timestamp = Carbon::now()->format('Ymd');
    
    
        return  $timestamp.$orderid;
    }
    public function store(Request $request,$order="")
    {
       $user = JWTAuth::parseToken()->authenticate();

      

       if(!empty($order)){

        // schedule_data is optional here (an order isn't always scheduled)
        // — but it must still have a rule, or Validator::validated() drops
        // it silently and the Schedule::create() loop below never runs
        // even when the app did send a schedule.
        $validator = Validator::make($request->all(), [
            'type' => 'required|integer',
            'address_id' => 'required|integer',
            'start_address' => '',
            'destination_address' => '',
            'schedule_data' => 'sometimes|array',
            'schedule_data.*.selectedDate' => 'required_with:schedule_data',
            'schedule_data.*.selectedTimeFrom' => 'required_with:schedule_data|string',
            'schedule_data.*.selectedTimeTo' => 'required_with:schedule_data|string',
        ]);

       }else{
        $validator = Validator::make($request->all(), [
            'type' => 'required|integer',
            'address_id' => 'required|integer',
            'start_address' => '',
            'destination_address' => '',
            'schedule_data' => 'required|array',  // Ensure 'schedule_data' is an array
            'schedule_data.*.selectedDate' => 'required',  // Each item must have a 'selectedDate'
            'schedule_data.*.selectedTimeFrom' => 'required|string',  // Each item must have a 'selectedTimeFrom'
            'schedule_data.*.selectedTimeTo' => 'required|string',  // Each item must have a 'selectedTimeTo'
        ]);
       }

    
        
                // If validation fails, return the error messages
        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
                'data' => []
            ], 400);
        }

    

        // Retrieve the validated data
$validated = $validator->validated();
     // Initialize an array to hold created schedules
$createdSchedules = [];
$start_address=self::getOrInsertAddress($user->id,$request->input('start_address'),'start_address',$order);
$destination_address=self::getOrInsertAddress($user->id,$request->input('destination_address'),'destination_address',$order);
// return response()->json([
//     'result' => true,
//     'message' => 'newAddressnewAddressnewAddress created successfully',
//     'data' => $start_address
// ], 201);
if(!empty($validated['schedule_data'])){
// Loop through the schedule_data array and create schedules
foreach ($validated['schedule_data'] as $scheduleItem) {
    if($validated['type']==1) {
        $validated['day']=$scheduleItem['selectedDate'];
        $scheduleItem['selectedDate']=null;
    }

    $order_id=0;
if(!empty($order)) $order_id=$order->id;

    $schedule = Schedule::create([
        'user_id'=>$user->id,
        'order_id'=>$order_id,
        'type' => $validated['type'],
        'day' => $validated['day'] ?? '', // If 'day' is not provided, set it to null or a default value
        'date' => $scheduleItem['selectedDate'] ?? null, // If 'day' is not provided, set it to null or a default value
        'time_from' => $scheduleItem['selectedTimeFrom'],
        'time_to' => $scheduleItem['selectedTimeTo'],
        'destination_address' => $destination_address,
        'start_address' => $start_address,
        'route_points' => $request->input('route_points', []),
        'route_distance_km' => $request->input('route_distance_km'),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
        // If needed, you can also use 'selectedDate' or other fields here
    ]);

    // Add the created schedule to the array
    $createdSchedules[] = $schedule;
}}
        // Optional: Return success response with the created schedule
return response()->json([
    'result' => true,
    'order_id' => !empty($order) ? $order->id : null,
    'message' => 'Schedules created successfully'.$request->input('start_address')['region'].' city:'.$request->input('start_address')['city'],
    'data' => $request->all()
], 201);
 }

function getOrInsertAddress($user_id, $address_data, $direction, $order = "")
{
    if(empty($order)){
    // Check if the address exists based on latitude and longitude
    $address = Address::where('latitude', $address_data['lat'])
                      ->where('longitude', $address_data['lng'])
                      ->where('order_id', 0)
                      ->first();
 
    // If the address exists, return the existing address id
    if ($address) {
        return $address->id;
    }}
    $order_id=0;
    if(!empty($order)) $order_id=$order->id;

  
    // If the address does not exist, insert a new address
    $newAddress = Address::create([
        'user_id'=>$user_id,
        'order_id'=>$order_id,
        "address_line1"=> $address_data['address'],
        "address_line2"=> "",
        "region"=> $address_data['region'],
        "city"=> $address_data['city'],
        "state"=> "",
        "postal_code"=> "",
        // App only operates in Lebanon, so there's no country input to take.
        "country"=> "Lebanon",
        "latitude"=> $address_data['lat'],
        "longitude"=> $address_data['lng'],
        "direction"=> $direction,
        "location_note"=> $address_data['note'] ?? null,
    ]);
  
    // Return the newly created address id
    return $newAddress->id;
}

function getUserSchedules()
{  $user = JWTAuth::parseToken()->authenticate();
 
  $schedules = Schedule::where('schedules.user_id', $user->id)
    ->join('addresses as address_from', 'address_from.id', '=', 'schedules.start_address')
    ->join('addresses as address_to', 'address_to.id', '=', 'schedules.destination_address')
    ->select('schedules.*', 'address_from.address_line1 as start_address', 'address_to.address_line1 as destination_address')
    ->get();
    // echo "<pre>";
    // print($schedules);exit;
  return response()->json([
    'result' => true,
    'message' => 'All Records',
    'data' =>$schedules 
], 201);
  
}

public function removeSchedule( $scheduleId)
{
    // Get the authenticated user
    $user = JWTAuth::parseToken()->authenticate();

    // Find the schedule by user_id and schedule_id
    $schedule = Schedule::where('user_id', $user->id)
        ->where('id', $scheduleId)
        ->first();

    // Check if the schedule exists
    if (!$schedule) {
        return response()->json([
            'result' => false,
            'message' => 'Schedule not found or you do not have permission to delete this schedule.',
        ], 404);
    }

    // Delete the schedule
    $schedule->delete();

    // Return success response
    return response()->json([
        'result' => true,
        'message' => 'Schedule deleted successfully.',
    ], 200);
}
}
