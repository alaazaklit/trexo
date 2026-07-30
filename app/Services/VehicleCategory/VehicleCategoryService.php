<?php

namespace App\Services\VehicleCategory;

use App\Models\VehicleCategory;
use Illuminate\Support\Str;

class VehicleCategoryService
{
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return VehicleCategory::orderBy('name')->get();
    }

    public function create(array $data): VehicleCategory
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return VehicleCategory::create($data);
    }

    public function update(VehicleCategory $category, array $data): void
    {
        $category->update($data);
    }

    public function delete(VehicleCategory $category): void
    {
        $category->delete();
    }
}
