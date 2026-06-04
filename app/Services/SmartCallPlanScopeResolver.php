<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SmartCallPlanScopeResolver
{
    public function resolve(array $intent): array
    {
        $prompt = trim((string) ($intent['raw_prompt'] ?? ''));
        $action = (string) ($intent['action'] ?? 'show');

        $loginBusinessId = isset($intent['login_business_id'])
            ? (int) $intent['login_business_id']
            : null;

        $businessId = isset($intent['business_id']) && $intent['business_id'] !== null
            ? (int) $intent['business_id']
            : $loginBusinessId;

        $locationId = isset($intent['location_id']) && $intent['location_id'] !== null
            ? (int) $intent['location_id']
            : null;

        $businessName = null;
        $locationName = null;

        if ($locationId !== null) {
            $location = DB::connection('mysql')
                ->table('business_locations')
                ->select('id', 'business_id', 'name')
                ->where('id', $locationId)
                ->first();

            if (!$location) {
                throw new InvalidArgumentException("Location {$locationId} not found.");
            }

            if ($businessId !== null && (int) $location->business_id !== $businessId) {
                throw new InvalidArgumentException('Location does not belong to your business.');
            }

            $businessId = (int) $location->business_id;
            $locationName = $location->name ?? null;
        }

        if ($locationId === null) {
            $matchedLocation = $this->findLocationByNameInPrompt($prompt, $businessId);

            if ($matchedLocation) {
                if ($businessId !== null && (int) $matchedLocation->business_id !== $businessId) {
                    throw new InvalidArgumentException('Location does not belong to your business.');
                }

                $locationId = (int) $matchedLocation->id;
                $locationName = $matchedLocation->name ?? null;
                $businessId = (int) $matchedLocation->business_id;
            }
        }

        if ($businessId === null) {
            $matchedBusiness = $this->findBusinessByNameInPrompt($prompt, $loginBusinessId);

            if ($matchedBusiness) {
                $businessId = (int) $matchedBusiness->id;
                $businessName = $matchedBusiness->name ?? null;
            }
        }

        if ($loginBusinessId !== null && $businessId !== null && $businessId !== $loginBusinessId) {
            throw new InvalidArgumentException('You are not allowed to access another business.');
        }

        if ($businessId !== null) {
            $business = DB::connection('mysql')
                ->table('business')
                ->select('id', 'name')
                ->where('id', $businessId)
                ->first();

            if (!$business) {
                throw new InvalidArgumentException("Business {$businessId} not found.");
            }

            $businessName = $business->name ?? $businessName;
        }

        if (in_array($action, ['sync', 'generate', 'sync_and_generate'], true) && $businessId === null) {
            throw new InvalidArgumentException('Business scope is required.');
        }

        return array_merge($intent, [
            'business_id' => $businessId,
            'location_id' => $locationId,
            'business_name' => $businessName,
            'location_name' => $locationName,
        ]);
    }

    private function findLocationByNameInPrompt(string $prompt, ?int $businessId = null): ?object
    {
        if ($prompt === '') {
            return null;
        }

        $query = DB::connection('mysql')
            ->table('business_locations')
            ->select('id', 'business_id', 'name')
            ->whereNotNull('name');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $locations = $query->orderByRaw('LENGTH(name) DESC')->get();

        foreach ($locations as $location) {
            $name = trim((string) ($location->name ?? ''));

            if ($name !== '' && stripos($prompt, $name) !== false) {
                return $location;
            }
        }

        return null;
    }

    private function findBusinessByNameInPrompt(string $prompt, ?int $loginBusinessId = null): ?object
    {
        if ($prompt === '') {
            return null;
        }

        $query = DB::connection('mysql')
            ->table('business')
            ->select('id', 'name')
            ->whereNotNull('name');

        if ($loginBusinessId !== null) {
            $query->where('id', $loginBusinessId);
        }

        $businesses = $query->orderByRaw('LENGTH(name) DESC')->get();

        foreach ($businesses as $business) {
            $name = trim((string) ($business->name ?? ''));

            if ($name !== '' && stripos($prompt, $name) !== false) {
                return $business;
            }
        }

        return null;
    }
}