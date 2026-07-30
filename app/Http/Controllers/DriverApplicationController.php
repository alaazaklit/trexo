<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDriverApplicationRequest;
use App\Services\DriverApplication\DriverApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DriverApplicationController extends Controller
{
    public function __construct(private readonly DriverApplicationService $service)
    {
    }

    public function create(): View
    {
        return view('driver-application.create');
    }

    public function store(StoreDriverApplicationRequest $request): RedirectResponse
    {
        $data = $request->safe()->except([
            'national_id_front',
            'driving_license_file',
            'vehicle_registration_file',
            'personal_photo',
            'vehicle_photo',
        ]);

        $files = [
            'national_id_front' => $request->file('national_id_front'),
            'driving_license_file' => $request->file('driving_license_file'),
            'vehicle_registration_file' => $request->file('vehicle_registration_file'),
            'personal_photo' => $request->file('personal_photo'),
            'vehicle_photo' => $request->file('vehicle_photo'),
        ];

        $this->service->create($data, $files);

        return redirect(localized_route('driver-application.success'));
    }

    public function success(): View
    {
        return view('driver-application.success');
    }
}
