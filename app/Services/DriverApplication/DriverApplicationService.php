<?php

namespace App\Services\DriverApplication;

use App\Models\Driver;
use App\Models\DriverApplication;
use App\Models\DriverApplicationNote;
use App\Models\DriverDocument;
use App\Services\AuditLog\AuditLogger;
use App\Services\Driver\DriverManagementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DriverApplicationService
{
    /**
     * Maps a DriverApplication upload column to the document_type expected
     * by driver_documents. The two optional photos have no equivalent
     * document type there and are intentionally not carried over.
     */
    private const DOCUMENT_TYPE_MAP = [
        'national_id_front_path' => 'id_card',
        'driving_license_path' => 'license',
        'vehicle_registration_path' => 'vehicle_registration',
    ];

    private const DOCUMENT_FIELDS = [
        'national_id_front' => 'national_id_front_path',
        'driving_license_file' => 'driving_license_path',
        'vehicle_registration_file' => 'vehicle_registration_path',
        'personal_photo' => 'personal_photo_path',
        'vehicle_photo' => 'vehicle_photo_path',
    ];

    public function __construct(private readonly DriverManagementService $driverManagementService)
    {
    }

    /**
     * @param array<string, mixed> $data validated non-file fields
     * @param array<string, UploadedFile|null> $files keyed by the input names in self::DOCUMENT_FIELDS
     */
    public function create(array $data, array $files): DriverApplication
    {
        $folder = 'driver-applications/'.Str::uuid();
        $paths = [];

        foreach (self::DOCUMENT_FIELDS as $field => $column) {
            $file = $files[$field] ?? null;

            if ($file instanceof UploadedFile) {
                $fileName = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                Storage::disk('public')->putFileAs($folder, $file, $fileName);
                $paths[$column] = $folder.'/'.$fileName;
            }
        }

        return DriverApplication::create(array_merge($data, $paths, [
            'status' => 'pending',
        ]));
    }

    /**
     * @param array{search?: string, status?: string, service_type?: string} $filters
     */
    public function filtered(array $filters): LengthAwarePaginator
    {
        $query = DriverApplication::query()->latest('id');

        if (!empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', $term)
                    ->orWhere('mobile_number', 'like', $term)
                    ->orWhere('national_id_number', 'like', $term)
                    ->orWhere('plate_number', 'like', $term);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['service_type'])) {
            $query->where('service_type', $filters['service_type']);
        }

        return $query->paginate(25)->withQueryString();
    }

    public function updateStatus(DriverApplication $application, string $status): void
    {
        if (!in_array($status, DriverApplication::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $oldStatus = $application->status;

        $application->status = $status;
        $application->reviewed_by = Auth::id();
        $application->reviewed_at = now();
        $application->save();

        AuditLogger::record('driver_application.status_changed', $application, ['status' => $oldStatus], ['status' => $status]);
    }

    public function addNote(DriverApplication $application, string $note): DriverApplicationNote
    {
        return $application->notes()->create([
            'user_id' => Auth::id(),
            'note' => $note,
        ]);
    }

    /**
     * Turns an application into a real, approved Driver account: finds or
     * creates the User by phone, creates the Driver with the submitted
     * vehicle/license info, carries the three required documents over as
     * already-approved DriverDocument rows (referencing the same uploaded
     * files, no re-upload needed), and marks the application itself as
     * approved and linked to the new driver.
     *
     * @throws \InvalidArgumentException if already converted, or if the
     *         phone number already has a driver record (see
     *         DriverManagementService::createDriver).
     */
    public function convertToDriver(DriverApplication $application): Driver
    {
        if ($application->driver_id) {
            throw new \InvalidArgumentException('This application has already been converted to a driver.');
        }

        return DB::transaction(function () use ($application) {
            $user = $this->driverManagementService->findOrCreateUserByPhone($application->mobile_number);

            if (!$user->name) {
                $user->name = $application->full_name;
            }
            if (!$user->email && $application->email) {
                $user->email = $application->email;
            }
            $user->save();

            $driver = $this->driverManagementService->createDriver($user, [
                'license_number' => $application->driving_license_number,
                'national_id_number' => $application->national_id_number,
                'vehicle_type' => $application->vehicle_type,
                'vehicle_make' => $application->vehicle_brand,
                'vehicle_model' => $application->vehicle_model,
                'vehicle_plate' => $application->plate_number,
                'vehicle_year' => $application->vehicle_year,
            ]);

            $this->driverManagementService->updateApprovalStatus($driver, 'approved');

            foreach (self::DOCUMENT_TYPE_MAP as $column => $documentType) {
                if (!$application->{$column}) {
                    continue;
                }

                DriverDocument::create([
                    'driver_id' => $driver->id,
                    'document_type' => $documentType,
                    'file_path' => $application->{$column},
                    'status' => 'approved',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            }

            $application->driver_id = $driver->id;
            $application->status = 'approved';
            $application->reviewed_by = Auth::id();
            $application->reviewed_at = now();
            $application->save();

            AuditLogger::record('driver_application.converted_to_driver', $application, [], ['driver_id' => $driver->id]);

            return $driver;
        });
    }
}
