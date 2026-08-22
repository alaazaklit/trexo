<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BroadcastController;
use App\Http\Controllers\Admin\CommissionPaymentController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DriverApplicationController as AdminDriverApplicationController;
use App\Http\Controllers\Admin\DriverManagementController;
use App\Http\Controllers\Admin\DriverPricingController;
use App\Http\Controllers\Admin\DriverSimulatorController;
use App\Http\Controllers\Admin\DriverSubscriptionController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\MapsConfigController;
use App\Http\Controllers\Admin\OrderOpsController;
use App\Http\Controllers\Admin\RequestReportController;
use App\Http\Controllers\Admin\SchoolBusPremiumSubscriptionController;
use App\Http\Controllers\Admin\SchoolBusRouteController;
use App\Http\Controllers\Admin\SchoolBusSubscriptionController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\VehicleCategoryController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DriverApplicationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', fn () => redirect()->to(config('localization.default')));

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Public marketing site — bilingual, URL-prefixed per locale ("/ar/...",
// "/en/..."). Entirely separate from the admin/API surfaces below.
Route::group([
    'prefix' => '{locale}',
    'where' => ['locale' => 'ar|en'],
    'middleware' => ['web', 'setlocale'],
], function () {
    Route::get('/', [LandingController::class, 'index'])->name('landing.home');
    Route::get('/become-a-driver', [DriverApplicationController::class, 'create'])->name('driver-application.create');
    Route::post('/become-a-driver', [DriverApplicationController::class, 'store'])->name('driver-application.store');
    Route::get('/become-a-driver/thank-you', [DriverApplicationController::class, 'success'])->name('driver-application.success');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('pages.privacy');
    Route::get('/terms', [PageController::class, 'terms'])->name('pages.terms');

    // Google Play Data Safety "Account Deletion" requirement: a public
    // URL that lets a user request account deletion without the app.
    Route::get('/delete-account', [AccountDeletionController::class, 'show'])->name('pages.delete-account');
    Route::post('/delete-account/otp', [AccountDeletionController::class, 'requestOtp'])->name('pages.delete-account.otp');
    Route::post('/delete-account/confirm', [AccountDeletionController::class, 'confirm'])->name('pages.delete-account.confirm');
});

Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();

    Route::middleware(['web', 'auth', 'admin.user'])->group(function () {
        Route::get('/driver-simulator', [DriverSimulatorController::class, 'index'])->name('admin.driver-simulator.index');
        Route::get('/driver-simulator/state', [DriverSimulatorController::class, 'state'])->name('admin.driver-simulator.state');
        Route::post('/driver-simulator/drivers', [DriverSimulatorController::class, 'store'])->name('admin.driver-simulator.drivers.store');
        Route::put('/driver-simulator/drivers/{driver}', [DriverSimulatorController::class, 'update'])->name('admin.driver-simulator.drivers.update');
        Route::patch('/driver-simulator/drivers/{driver}', [DriverSimulatorController::class, 'update']);
        Route::delete('/driver-simulator/drivers/{driver}', [DriverSimulatorController::class, 'destroy'])->name('admin.driver-simulator.drivers.destroy');
        Route::post('/driver-simulator/spawn', [DriverSimulatorController::class, 'spawn'])->name('admin.driver-simulator.spawn');
        Route::post('/driver-simulator/drivers/{driver}/toggle', [DriverSimulatorController::class, 'toggle'])->name('admin.driver-simulator.drivers.toggle');
        Route::post('/driver-simulator/drivers/{driver}/move', [DriverSimulatorController::class, 'move'])->name('admin.driver-simulator.drivers.move');
        Route::post('/driver-simulator/tick', [DriverSimulatorController::class, 'tick'])->name('admin.driver-simulator.tick');
        Route::post('/driver-simulator/ride-requests', [DriverSimulatorController::class, 'rideRequest'])->name('admin.driver-simulator.ride-requests.store');
        Route::post('/driver-simulator/ride-requests/{ride}/decision', [DriverSimulatorController::class, 'rideDecision'])->name('admin.driver-simulator.ride-requests.decision');
    });

    // Not gated by admin.user (Voyager's own admin-role check) — these use
    // Spatie's granular roles instead, so Operations/Finance/Support/
    // Marketing staff (who aren't Voyager "admin"-role users) can reach
    // them without needing full Voyager BREAD access.
    Route::middleware(['web', 'auth', 'permission:dashboard.view'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('admin.dashboard.data');
    });

    Route::middleware(['web', 'auth', 'permission:audit-logs.view'])->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    });

    Route::middleware(['web', 'auth', 'permission:customers.view'])->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('admin.customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('admin.customers.show');
    });

    Route::middleware(['web', 'auth', 'permission:customers.manage'])->group(function () {
        Route::post('/customers/{customer}/status', [CustomerController::class, 'updateStatus'])->name('admin.customers.update-status');
        Route::post('/customers/{customer}/toggle-verified', [CustomerController::class, 'toggleVerified'])->name('admin.customers.toggle-verified');
    });

    Route::middleware(['web', 'auth', 'permission:drivers.view'])->group(function () {
        Route::get('/driver-management', [DriverManagementController::class, 'index'])->name('admin.drivers.index');
        Route::get('/driver-management/{driver}', [DriverManagementController::class, 'show'])->name('admin.drivers.show');
    });

    Route::middleware(['web', 'auth', 'permission:drivers.manage'])->group(function () {
        Route::post('/driver-management', [DriverManagementController::class, 'store'])->name('admin.drivers.store');
        Route::post('/driver-management/{driver}/approval', [DriverManagementController::class, 'updateApprovalStatus'])->name('admin.drivers.update-approval');
        Route::post('/driver-management/{driver}/school-bus-status', [DriverManagementController::class, 'updateSchoolBusStatus'])->name('admin.drivers.update-school-bus-status');
        Route::post('/driver-management/{driver}/vehicle', [DriverManagementController::class, 'updateVehicle'])->name('admin.drivers.update-vehicle');
        Route::post('/driver-management/{driver}/note', [DriverManagementController::class, 'updateNote'])->name('admin.drivers.update-note');
        Route::post('/driver-management/{driver}/gender', [DriverManagementController::class, 'updateGender'])->name('admin.drivers.update-gender');
        Route::post('/driver-management/{driver}/documents', [DriverManagementController::class, 'uploadDocument'])->name('admin.drivers.upload-document');
        Route::post('/driver-documents/{document}/review', [DriverManagementController::class, 'reviewDocument'])->name('admin.drivers.documents.review');
        Route::delete('/driver-documents/{document}', [DriverManagementController::class, 'deleteDocument'])->name('admin.drivers.documents.destroy');
        Route::delete('/driver-management/{driver}', [DriverManagementController::class, 'destroy'])->name('admin.drivers.destroy');
    });

    Route::middleware(['web', 'auth', 'permission:pricing.manage'])->group(function () {
        Route::post('/driver-management/{driver}/pricing', [DriverPricingController::class, 'update'])->name('admin.drivers.pricing.update');
        Route::post('/driver-management/{driver}/pricing/intercity', [DriverPricingController::class, 'storeIntercityOverride'])->name('admin.drivers.pricing.intercity.store');
        Route::delete('/driver-pricing-intercity-overrides/{override}', [DriverPricingController::class, 'destroyIntercityOverride'])->name('admin.drivers.pricing.intercity.destroy');
    });

    Route::middleware(['web', 'auth', 'permission:vehicles.manage'])->group(function () {
        Route::get('/vehicle-categories', [VehicleCategoryController::class, 'index'])->name('admin.vehicle-categories.index');
        Route::post('/vehicle-categories', [VehicleCategoryController::class, 'store'])->name('admin.vehicle-categories.store');
        Route::put('/vehicle-categories/{vehicleCategory}', [VehicleCategoryController::class, 'update'])->name('admin.vehicle-categories.update');
        Route::delete('/vehicle-categories/{vehicleCategory}', [VehicleCategoryController::class, 'destroy'])->name('admin.vehicle-categories.destroy');
    });

    Route::middleware(['web', 'auth', 'permission:orders.view'])->group(function () {
        Route::get('/order-ops/orders/{order}/candidate-drivers', [OrderOpsController::class, 'candidateDrivers'])->name('admin.order-ops.candidate-drivers');
        Route::get('/reservations', [OrderOpsController::class, 'reservations'])->name('admin.reservations.index');
    });

    Route::middleware(['web', 'auth', 'permission:orders.manage'])->group(function () {
        Route::post('/order-ops/orders/{order}/reassign', [OrderOpsController::class, 'reassign'])->name('admin.order-ops.reassign');
        Route::post('/order-ops/orders/{order}/cancel', [OrderOpsController::class, 'cancel'])->name('admin.order-ops.cancel');
        Route::post('/reservations/{reservation}/reassign', [OrderOpsController::class, 'reassignReservation'])->name('admin.reservations.reassign');
        Route::post('/reservations/{reservation}/cancel', [OrderOpsController::class, 'cancelReservation'])->name('admin.reservations.cancel');
    });

    Route::middleware(['web', 'auth', 'permission:driver-applications.view'])->group(function () {
        Route::get('/driver-applications', [AdminDriverApplicationController::class, 'index'])->name('admin.driver-applications.index');
        Route::get('/driver-applications/{driverApplication}', [AdminDriverApplicationController::class, 'show'])->name('admin.driver-applications.show');
    });

    Route::middleware(['web', 'auth', 'permission:driver-applications.manage'])->group(function () {
        Route::post('/driver-applications/{driverApplication}/status', [AdminDriverApplicationController::class, 'updateStatus'])->name('admin.driver-applications.update-status');
        Route::post('/driver-applications/{driverApplication}/notes', [AdminDriverApplicationController::class, 'addNote'])->name('admin.driver-applications.notes.store');
        Route::post('/driver-applications/{driverApplication}/convert-to-driver', [AdminDriverApplicationController::class, 'convertToDriver'])->name('admin.driver-applications.convert-to-driver');
    });

    Route::middleware(['web', 'auth', 'permission:contact-messages.view'])->group(function () {
        Route::get('/contact-messages', [ContactMessageController::class, 'index'])->name('admin.contact-messages.index');
    });

    Route::middleware(['web', 'auth', 'permission:contact-messages.manage'])->group(function () {
        Route::delete('/contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])->name('admin.contact-messages.destroy');
    });

    Route::middleware(['web', 'auth', 'permission:subscriptions.view'])->group(function () {
        Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index'])->name('admin.subscription-plans.index');
        Route::get('/driver-subscriptions', [DriverSubscriptionController::class, 'index'])->name('admin.driver-subscriptions.index');
        Route::get('/driver-subscriptions/{driverSubscription}', [DriverSubscriptionController::class, 'show'])->name('admin.driver-subscriptions.show');
    });

    Route::middleware(['web', 'auth', 'permission:subscriptions.manage'])->group(function () {
        Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store'])->name('admin.subscription-plans.store');
        Route::put('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->name('admin.subscription-plans.update');
        Route::post('/driver-subscriptions/{driverSubscription}/approve', [DriverSubscriptionController::class, 'approve'])->name('admin.driver-subscriptions.approve');
        Route::post('/driver-subscriptions/{driverSubscription}/reject', [DriverSubscriptionController::class, 'reject'])->name('admin.driver-subscriptions.reject');
    });

    Route::middleware(['web', 'auth', 'permission:school-bus.view'])->group(function () {
        Route::get('/schools', [SchoolController::class, 'index'])->name('admin.schools.index');
        Route::get('/school-bus-routes', [SchoolBusRouteController::class, 'index'])->name('admin.school-bus-routes.index');
        Route::get('/school-bus-subscriptions', [SchoolBusSubscriptionController::class, 'index'])->name('admin.school-bus-subscriptions.index');
        Route::get('/school-bus-subscriptions/{schoolBusSubscription}', [SchoolBusSubscriptionController::class, 'show'])->name('admin.school-bus-subscriptions.show');
        Route::get('/school-bus-premium-subscriptions', [SchoolBusPremiumSubscriptionController::class, 'index'])->name('admin.school-bus-premium-subscriptions.index');
        Route::get('/school-bus-premium-subscriptions/{schoolBusPremiumSubscription}', [SchoolBusPremiumSubscriptionController::class, 'show'])->name('admin.school-bus-premium-subscriptions.show');
    });

    Route::middleware(['web', 'auth', 'permission:school-bus.manage'])->group(function () {
        Route::post('/schools', [SchoolController::class, 'store'])->name('admin.schools.store');
        Route::put('/schools/{school}', [SchoolController::class, 'update'])->name('admin.schools.update');
        Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])->name('admin.schools.destroy');
        Route::post('/school-bus-routes', [SchoolBusRouteController::class, 'store'])->name('admin.school-bus-routes.store');
        Route::put('/school-bus-routes/{schoolBusRoute}', [SchoolBusRouteController::class, 'update'])->name('admin.school-bus-routes.update');
        Route::delete('/school-bus-routes/{schoolBusRoute}', [SchoolBusRouteController::class, 'destroy'])->name('admin.school-bus-routes.destroy');
        Route::post('/school-bus-premium-subscriptions/{schoolBusPremiumSubscription}/approve', [SchoolBusPremiumSubscriptionController::class, 'approve'])->name('admin.school-bus-premium-subscriptions.approve');
        Route::post('/school-bus-premium-subscriptions/{schoolBusPremiumSubscription}/reject', [SchoolBusPremiumSubscriptionController::class, 'reject'])->name('admin.school-bus-premium-subscriptions.reject');
    });

    Route::middleware(['web', 'auth', 'permission:request-reports.view'])->group(function () {
        Route::get('/request-reports', [RequestReportController::class, 'index'])->name('admin.request-reports.index');
    });

    Route::middleware(['web', 'auth', 'permission:wallet.view'])->group(function () {
        Route::get('/wallet', [WalletController::class, 'index'])->name('admin.wallet.index');
        Route::get('/wallet/{driver}', [WalletController::class, 'show'])->name('admin.wallet.show');
        Route::get('/commission-payments', [CommissionPaymentController::class, 'index'])->name('admin.commission-payments.index');
    });

    Route::middleware(['web', 'auth', 'permission:wallet.manage'])->group(function () {
        Route::post('/commission-payments/{commissionPayment}/approve', [CommissionPaymentController::class, 'approve'])->name('admin.commission-payments.approve');
        Route::post('/commission-payments/{commissionPayment}/reject', [CommissionPaymentController::class, 'reject'])->name('admin.commission-payments.reject');
        Route::post('/commission-payments/{commissionPayment}/notify', [CommissionPaymentController::class, 'notify'])->name('admin.commission-payments.notify');
    });

    Route::middleware(['web', 'auth', 'permission:maps.view'])->group(function () {
        Route::get('/maps-config', [MapsConfigController::class, 'show'])->name('admin.maps-config.show');
    });

    Route::middleware(['web', 'auth', 'permission:maps.manage'])->group(function () {
        Route::post('/maps-config', [MapsConfigController::class, 'update'])->name('admin.maps-config.update');
    });

    Route::middleware(['web', 'auth', 'permission:broadcasts.view'])->group(function () {
        Route::get('/broadcasts', [BroadcastController::class, 'index'])->name('admin.broadcasts.index');
    });

    Route::middleware(['web', 'auth', 'permission:broadcasts.manage'])->group(function () {
        Route::post('/broadcasts', [BroadcastController::class, 'store'])->name('admin.broadcasts.store');
        Route::post('/broadcasts/excel/preview', [BroadcastController::class, 'excelPreview'])->name('admin.broadcasts.excel.preview');
        Route::post('/broadcasts/excel/send', [BroadcastController::class, 'excelSend'])->name('admin.broadcasts.excel.send');
        Route::post('/broadcasts/excel/cancel', [BroadcastController::class, 'excelCancel'])->name('admin.broadcasts.excel.cancel');
    });

    Route::middleware(['web', 'auth', 'permission:finance.view'])->group(function () {
        Route::get('/financial-reports', [FinancialReportController::class, 'index'])->name('admin.financial-reports.index');
    });
});
