<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Admin\DriverSimulatorController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// Route::middleware('auth:api')->group(function () {
//     // Your protected API routes go here
// });
Route::middleware(['auth:api'])->group(function () {
    // Your protected routes, including the profile route
    // Route::get('/profile', [App\Http\Controllers\Api\UsersController::class, 'profile'])->name('profile');
    Route::post('/updateProfile', [App\Http\Controllers\Api\UsersController::class, 'updateProfile'])->name('updateProfile');
    Route::post('/updateAvailability', [App\Http\Controllers\Api\UsersController::class, 'updateAvailability'])->name('updateAvailability');
    Route::get('/validateToken', [App\Http\Controllers\Api\UsersController::class, 'validateToken'])->name('validateToken');
    Route::post('/deleteAccount', [App\Http\Controllers\Api\UsersController::class, 'deleteAccount'])->name('deleteAccount');

    // Driver profile / gallery / work-areas / schedule
    Route::get('/getDriverDetails/{user_id}', [App\Http\Controllers\Api\DriverProfileController::class, 'details'])->name('getDriverDetails');
    Route::get('/getUserServiceLines', [App\Http\Controllers\Api\DriverProfileController::class, 'getServiceLines'])->name('getUserServiceLines');
    Route::post('/saveSchedulePerLine', [App\Http\Controllers\Api\DriverProfileController::class, 'saveServiceLine'])->name('saveSchedulePerLine');
    Route::delete('/deleteServiceLine', [App\Http\Controllers\Api\DriverProfileController::class, 'deleteServiceLine'])->name('deleteServiceLine');
    Route::post('/driver/gallery/upload', [App\Http\Controllers\Api\DriverProfileController::class, 'uploadGalleryImage'])->name('driverGalleryUpload');
    Route::delete('/driver/gallery/{id}', [App\Http\Controllers\Api\DriverProfileController::class, 'deleteGalleryImage'])->name('driverGalleryDelete');
    Route::post('/driver/gallery/reorder', [App\Http\Controllers\Api\DriverProfileController::class, 'reorderGallery'])->name('driverGalleryReorder');
    Route::get('/driver/pricing', [App\Http\Controllers\Api\DriverProfileController::class, 'getPricingOverrides'])->name('getDriverPricing');
    Route::post('/driver/pricing', [App\Http\Controllers\Api\DriverProfileController::class, 'updatePricingOverrides'])->name('updateDriverPricing');
    Route::post('/driver/pricing/test', [App\Http\Controllers\Api\DriverProfileController::class, 'testPrice'])->name('driverTestPrice');
    Route::get('/driver/pricing/brackets', [App\Http\Controllers\Api\DriverProfileController::class, 'getPriceBrackets'])->name('getDriverPriceBrackets');
    Route::post('/driver/pricing/brackets', [App\Http\Controllers\Api\DriverProfileController::class, 'updatePriceBrackets'])->name('updateDriverPriceBrackets');
    Route::get('/driver/intercity-routes', [App\Http\Controllers\Api\DriverProfileController::class, 'getIntercityRouteOverrides'])->name('getDriverIntercityRoutes');
    Route::post('/driver/intercity-routes', [App\Http\Controllers\Api\DriverProfileController::class, 'updateIntercityRouteOverride'])->name('updateDriverIntercityRoute');
    Route::get('/driver/verification-status', [App\Http\Controllers\Api\DriverProfileController::class, 'verificationStatus'])->name('driverVerificationStatus');
    Route::post('/driver/verification-documents', [App\Http\Controllers\Api\DriverProfileController::class, 'uploadVerificationDocument'])->name('driverUploadVerificationDocument');


     // Driver Routes
  
    //  Route::get('/drivers', [DriverController::class, 'index']);
    //  Route::get('/drivers/{id}', [DriverController::class, 'show']);
    //  Route::post('/drivers', [DriverController::class, 'store']);
    //  Route::put('/drivers/{id}', [DriverController::class, 'update']);
     //  Route::delete('/drivers/{id}', [DriverController::class, 'destroy']);


     // Payment Routes
     Route::get('/payments', [PaymentController::class, 'index']);
     Route::get('/payments/{id}', [PaymentController::class, 'show']);
     Route::post('/payments', [PaymentController::class, 'store']);
     Route::put('/payments/{id}', [PaymentController::class, 'update']);
     Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);

});


if (class_exists(\Laravel\Passport\Http\Controllers\AccessTokenController::class)) {
    Route::post('oauth/token', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken');
}
Route::post('/requestOtp', [App\Http\Controllers\Api\UsersController::class, 'requestOtp'])->name('requestOtp');
Route::post('/refresh', [App\Http\Controllers\Api\UsersController::class, 'refresh'])->name('refresh')->middleware('throttle:refresh');
Route::post('/logout', [App\Http\Controllers\Api\UsersController::class, 'logout'])->name('logout');
Route::post('/verifyOtp', [App\Http\Controllers\Api\UsersController::class, 'verifyOtp'])->name('verifyOtp');
Route::post('/forgotPassword', [App\Http\Controllers\Api\UsersController::class, 'forgotPassword'])->name('forgotPassword');
Route::post('/resetPassword', [App\Http\Controllers\Api\UsersController::class, 'resetPassword'])->name('resetPassword');
Route::post('/save-fcm-token', [UsersController::class, 'saveFcmToken']);

////Schedule//////
Route::post('/create-schedule', [App\Http\Controllers\Api\ScheduleController::class, 'store'])->name('store');
Route::get('/getUserSchedules', [App\Http\Controllers\Api\ScheduleController::class, 'getUserSchedules'])->name('getUserSchedules');
Route::delete('/removeSchedule/{scheduleId}', [App\Http\Controllers\Api\ScheduleController::class, 'removeSchedule'])->name('removeSchedule');

////ORDERS////////
Route::post('/create-order', [App\Http\Controllers\Api\ScheduleController::class, 'createOrder'])->name('createOrder');
Route::get('/getOrders', [App\Http\Controllers\Api\OrderController::class, 'getOrders'])->name('getOrders');
Route::get('/getOrder/{order_id}', [App\Http\Controllers\Api\OrderController::class, 'getOrder'])->name('getOrder');
Route::get('/getOrderDrivers/{order_id}', [App\Http\Controllers\Api\OrderController::class, 'getOrderDrivers'])->name('getOrderDrivers');
Route::get('/getOrdersForDriver', [App\Http\Controllers\Api\OrderController::class, 'getOrdersForDriver'])->name('getOrdersForDriver');
Route::post('/ChooseDriver', [App\Http\Controllers\Api\OrderController::class, 'ChooseDriver'])->name('ChooseDriver');
Route::post('/updateOrderStatus', [App\Http\Controllers\Api\OrderController::class, 'updateOrderStatus'])->name('updateOrderStatus');
Route::get('/fare-settings', [App\Http\Controllers\Api\OrderController::class, 'getFareSettings'])->name('fareSettings');
Route::post('/updateDriverLocation', [App\Http\Controllers\Api\OrderController::class, 'updateDriverLocation'])->name('updateDriverLocation');
Route::get('/getOrderTracking/{order_id}', [App\Http\Controllers\Api\OrderController::class, 'getOrderTracking'])->name('getOrderTracking');

////MAP PROXY (Directions/Geocoding/Places routed through our own server-side
////Google Maps key instead of the client calling Google directly)////
Route::get('/maps/directions', [App\Http\Controllers\Api\MapProxyController::class, 'directions'])->name('mapsDirections');
Route::get('/maps/reverse-geocode', [App\Http\Controllers\Api\MapProxyController::class, 'reverseGeocode'])->name('mapsReverseGeocode');
Route::get('/maps/places-nearby', [App\Http\Controllers\Api\MapProxyController::class, 'placesNearby'])->name('mapsPlacesNearby');
Route::get('/maps/place-autocomplete', [App\Http\Controllers\Api\MapProxyController::class, 'placeAutocomplete'])->name('mapsPlaceAutocomplete');
Route::get('/maps/place-details', [App\Http\Controllers\Api\MapProxyController::class, 'placeDetails'])->name('mapsPlaceDetails');

Route::get('/exchange-rate', [App\Http\Controllers\Api\SettingsController::class, 'exchangeRate'])->name('exchangeRate');

////RESERVATIONS////////
Route::post('/create-reservation', [App\Http\Controllers\Api\ReservationController::class, 'createReservation'])->name('createReservation');
Route::get('/getReservations', [App\Http\Controllers\Api\ReservationController::class, 'getReservations'])->name('getReservations');
Route::get('/getReservationsForDriver', [App\Http\Controllers\Api\ReservationController::class, 'getReservationsForDriver'])->name('getReservationsForDriver');
Route::get('/getReservation/{reservation_id}', [App\Http\Controllers\Api\ReservationController::class, 'getReservation'])->name('getReservation');
Route::get('/getReservationDrivers/{reservation_id}', [App\Http\Controllers\Api\ReservationController::class, 'getReservationDrivers'])->name('getReservationDrivers');
Route::post('/ChooseReservationDriver', [App\Http\Controllers\Api\ReservationController::class, 'ChooseReservationDriver'])->name('ChooseReservationDriver');
Route::post('/updateReservationStatus', [App\Http\Controllers\Api\ReservationController::class, 'updateReservationStatus'])->name('updateReservationStatus');
Route::get('/getReservationTracking/{reservation_id}', [App\Http\Controllers\Api\ReservationController::class, 'getReservationTracking'])->name('getReservationTracking');

////RATINGS////////
Route::post('/submitRating', [App\Http\Controllers\Api\RatingController::class, 'submitRating'])->name('submitRating');
Route::get('/getRating', [App\Http\Controllers\Api\RatingController::class, 'getRating'])->name('getRating');

////Notifications////////
Route::post('/updateNotification', [App\Http\Controllers\Api\NotificationsController::class, 'updateNotification'])->name('updateNotification');
Route::post('/updateFCMToken', [App\Http\Controllers\Api\NotificationsController::class, 'updateFCMToken'])->name('updateFCMToken');
Route::get('/getNotifications', [App\Http\Controllers\Api\NotificationsController::class, 'getNotifications'])->name('getNotifications');
Route::post('/markAllNotificationsRead', [App\Http\Controllers\Api\NotificationsController::class, 'markAllNotificationsRead'])->name('markAllNotificationsRead');

////Order chat////////
Route::post('/sendOrderMessage', [App\Http\Controllers\Api\OrderChatController::class, 'sendMessage'])->name('sendOrderMessage');
Route::get('/getOrderMessages/{order_id}', [App\Http\Controllers\Api\OrderChatController::class, 'getMessages'])->name('getOrderMessages');

////Order call (Agora in-app voice)////////
Route::post('/initiateOrderCall', [App\Http\Controllers\Api\OrderCallController::class, 'initiateCall'])->name('initiateOrderCall');

////Reservation chat////////
Route::post('/sendReservationMessage', [App\Http\Controllers\Api\ReservationChatController::class, 'sendMessage'])->name('sendReservationMessage');
Route::get('/getReservationMessages/{reservation_id}', [App\Http\Controllers\Api\ReservationChatController::class, 'getMessages'])->name('getReservationMessages');

////Reservation call (Agora in-app voice)////////
Route::post('/initiateReservationCall', [App\Http\Controllers\Api\ReservationCallController::class, 'initiateCall'])->name('initiateReservationCall');

////Driver subscriptions////////
Route::get('/subscription-plans', [App\Http\Controllers\Api\DriverSubscriptionController::class, 'plans'])->name('subscriptionPlans');
Route::get('/driver/subscription', [App\Http\Controllers\Api\DriverSubscriptionController::class, 'current'])->name('driverSubscriptionCurrent');
Route::post('/driver/subscription/subscribe', [App\Http\Controllers\Api\DriverSubscriptionController::class, 'subscribe'])->name('driverSubscriptionSubscribe');

////Driver wallet & earnings////////
Route::get('/driver/wallet', [App\Http\Controllers\Api\WalletController::class, 'wallet'])->name('driverWallet');
Route::get('/driver/transactions', [App\Http\Controllers\Api\WalletController::class, 'transactions'])->name('driverTransactions');
Route::get('/driver/dashboard', [App\Http\Controllers\Api\WalletController::class, 'dashboard'])->name('driverFinanceDashboard');
Route::get('/driver/financial-report', [App\Http\Controllers\Api\WalletController::class, 'financialReport'])->name('driverFinancialReport');
Route::post('/driver/wallet/pay-commission', [App\Http\Controllers\Api\WalletController::class, 'submitCommissionPayment'])->name('driverWalletPayCommission');
Route::get('/driver/wallet/commission-payments', [App\Http\Controllers\Api\WalletController::class, 'commissionPayments'])->name('driverWalletCommissionPayments');

////School Bus////////
// Driver-side: opting in, managing routes, and handling requests.
Route::get('/driver/school-bus/status', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'status'])->name('driverSchoolBusStatus');
Route::post('/driver/school-bus/enable', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'enable'])->name('driverSchoolBusEnable');
Route::put('/driver/school-bus/child-discount', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'updateChildDiscount'])->name('driverSchoolBusChildDiscount');
Route::post('/driver/school-bus/schools/resolve', [App\Http\Controllers\Api\SchoolController::class, 'resolveFromPlace'])->name('driverSchoolBusSchoolsResolve');
Route::get('/driver/school-bus/routes', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'index'])->name('driverSchoolBusRoutes');
Route::post('/driver/school-bus/routes', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'store'])->name('driverSchoolBusRoutesStore');
Route::put('/driver/school-bus/routes/{route}', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'update'])->name('driverSchoolBusRoutesUpdate');
Route::delete('/driver/school-bus/routes/{route}', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'destroy'])->name('driverSchoolBusRoutesDestroy');
Route::get('/driver/school-bus/subscriptions', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'driverIndex'])->name('driverSchoolBusSubscriptions');
Route::get('/driver/school-bus/subscriptions/counts', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'counts'])->name('driverSchoolBusSubscriptionCounts');
Route::get('/driver/school-bus/subscriptions/{subscription}', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'show'])->name('driverSchoolBusSubscriptionShow');
Route::post('/driver/school-bus/subscriptions/{subscription}/accept', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'accept'])->name('driverSchoolBusSubscriptionAccept');
Route::post('/driver/school-bus/subscriptions/{subscription}/reject', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'reject'])->name('driverSchoolBusSubscriptionReject');
Route::post('/driver/school-bus/subscriptions/{subscription}/event', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'event'])->name('driverSchoolBusSubscriptionEvent');

// Parent-side: browsing schools/drivers/prices and submitting requests.
Route::get('/schools', [App\Http\Controllers\Api\SchoolController::class, 'index'])->name('schoolsIndex');
Route::get('/schools/{school}/pickup-areas', [App\Http\Controllers\Api\SchoolController::class, 'pickupAreasForSchool'])->name('schoolPickupAreas');
Route::get('/schools/{school}/drivers', [App\Http\Controllers\Api\SchoolController::class, 'driversForSchool'])->name('schoolDrivers');
Route::get('/drivers/{driver}/school-bus-routes', [App\Http\Controllers\Api\SchoolBusRouteController::class, 'forDriver'])->name('driverSchoolBusRoutesForDriver');
Route::post('/school-bus/subscriptions', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'submit'])->name('schoolBusSubscriptionSubmit');
Route::get('/school-bus/subscriptions', [App\Http\Controllers\Api\SchoolBusSubscriptionController::class, 'mine'])->name('schoolBusSubscriptionsMine');

// Parent-side: School Bus Premium — paid add-on gating the proximity alert.
Route::get('/school-bus/subscriptions/{subscription}/premium', [App\Http\Controllers\Api\SchoolBusPremiumController::class, 'status'])->name('schoolBusPremiumStatus');
Route::post('/school-bus/subscriptions/{subscription}/premium/subscribe', [App\Http\Controllers\Api\SchoolBusPremiumController::class, 'subscribe'])->name('schoolBusPremiumSubscribe');


Route::group(['middleware' => ['jwt.verify']], function() {
  Route::get('/profile', [App\Http\Controllers\Api\UsersController::class, 'profile'])->name('profile');
  Route::post('/updateBusinessType', [App\Http\Controllers\Api\UsersController::class, 'updateBusinessType'])->name('updateBusinessType');
  
 // Trip Routes
 Route::get('/trips', [App\Http\Controllers\Api\TripController::class, 'index']);
 Route::get('/trips/{id}', [App\Http\Controllers\Api\TripController::class, 'show']);
 Route::post('/trips', [App\Http\Controllers\Api\TripController::class, 'store']);
 Route::put('/trips/{id}', [App\Http\Controllers\Api\TripController::class, 'update']);
 Route::delete('/trips/{id}', [App\Http\Controllers\Api\TripController::class, 'destroy']);
  Route::get('/tripss', [App\Http\Controllers\Api\TripController::class, 'index']);
});
Route::get('/drivers', [App\Http\Controllers\DriverController::class, 'index']);
Route::get('/driver-simulator/drivers', [DriverSimulatorController::class, 'drivers'])
    ->name('api.driver-simulator.drivers');

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });






