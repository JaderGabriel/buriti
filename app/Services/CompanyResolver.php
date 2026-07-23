<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Support\Str;

class CompanyResolver
{
    public function findOrCreateByName(?string $name): ?Company
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $existing = Company::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Company::query()->create([
            'name' => $name,
            'status' => CompanyStatus::Active,
        ]);
    }
}
