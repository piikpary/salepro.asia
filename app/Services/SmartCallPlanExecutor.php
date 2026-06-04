<?php

namespace App\Services;

use InvalidArgumentException;

class SmartCallPlanExecutor
{
    protected SmartCallPlanService $service;

    public function __construct(SmartCallPlanService $service)
    {
        $this->service = $service;
    }

    public function execute(array $intent): array
    {
        $businessId = (int) ($intent['business_id'] ?? 0);
        $locationId = $intent['location_id'] ?? null;
        $customerType = $intent['customer_type'] ?? null;
        $limit = (int) ($intent['limit'] ?? 5);
        $period = (string) ($intent['period'] ?? 'month');
        $action = (string) ($intent['action'] ?? 'show');
        $metric = (string) ($intent['metric'] ?? 'board');
        $scope = $this->formatScope($intent);

        if ($businessId <= 0) {
            throw new InvalidArgumentException('Business scope is required.');
        }

        return match ($action) {
            'chat' => [
                'reply' => 'Hello! Ask me things like: how many customers do I have, how many sales do I have, total sales this month, show pending customers, sync customers, generate today plan, or who is the top seller this month.',
                'plans' => [],
                'should_reload' => false,
            ],

            'stats', 'count' => $this->handleStats(
                $metric,
                $scope,
                $businessId,
                $locationId,
                $customerType,
                $period,
                $limit
            ),

            'show' => $this->handleShow(
                $metric,
                $scope,
                $businessId,
                $locationId,
                $customerType
            ),

            'sync' => [
                'reply' => 'Sync completed.',
                'plans' => [],
                'should_reload' => true,
            ] + $this->service->syncFromMainDatabase($businessId, $locationId, $limit),

            'generate' => [
                'reply' => 'Plans generated.',
                'plans' => [],
                'should_reload' => true,
            ] + $this->service->generateTodayPlans($customerType, $businessId, $locationId),

            'sync_and_generate' => [
                'reply' => 'Sync and generate completed.',
                'plans' => [],
                'should_reload' => true,
            ] + $this->service->regenerateBoard($customerType, $businessId, $locationId, $limit),

            default => $this->handleStats(
                'board',
                $scope,
                $businessId,
                $locationId,
                $customerType,
                $period,
                $limit
            ),
        };
    }

    protected function handleStats(
        string $metric,
        string $scope,
        int $businessId,
        ?int $locationId,
        ?string $customerType,
        string $period,
        int $limit
    ): array {
        if ($metric === 'top_seller') {
            $result = $this->service->getTopSellers($businessId, $locationId, $period, $limit);

            if (!empty($result['top_sellers']) && $limit > 1) {
                $lines = [];
                foreach (array_slice($result['top_sellers'], 0, $limit) as $index => $seller) {
                    $lines[] = ($index + 1) . '. ' . $seller['seller_name']
                        . ' - $' . number_format($seller['total_sales'], 2)
                        . ' (' . $seller['total_orders'] . ' order(s))';
                }

                $reply = 'Top ' . min($limit, count($result['top_sellers'])) . ' sellers for ' . $result['label'] . ":\n" . implode("\n", $lines);
            } else {
                $reply = $result['reply'];
            }

            return [
                'reply' => $reply,
                'plans' => [],
                'should_reload' => false,
            ];
        }

        if ($metric === 'sales' || $metric === 'sales_amount') {
            $stats = $this->service->getSalesStats($businessId, $locationId, $period);

            $reply = $metric === 'sales_amount'
                ? "Total sales amount {$scope} for {$stats['label']} is $" . number_format($stats['sales_amount'], 2) . " from {$stats['sales_count']} sale(s)."
                : "We have {$stats['sales_count']} sale(s) {$scope} for {$stats['label']} with total amount $" . number_format($stats['sales_amount'], 2) . '.';

            return [
                'reply' => $reply,
                'plans' => [],
                'should_reload' => false,
            ];
        }

        if (in_array($metric, ['businesses', 'locations', 'customers'], true)) {
            $stats = $this->service->getStats($businessId, $locationId);

            $reply = match ($metric) {
                'businesses' => "We have {$stats['business_count']} business(es) {$scope}.",
                'locations' => "We have {$stats['location_count']} location(s) {$scope}.",
                default => "We have {$stats['customer_count']} customer(s) {$scope}.",
            };

            return [
                'reply' => $reply,
                'plans' => [],
                'should_reload' => false,
            ];
        }

        $board = $this->service->getBoardData([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'customer_type' => $customerType,
        ]);

        return [
            'reply' => "Current board summary {$scope}: total {$board['summary']['total']}, completed {$board['summary']['completed']}, pending {$board['summary']['pending']}.",
            'plans' => [],
            'should_reload' => false,
        ];
    }

    protected function handleShow(
        string $metric,
        string $scope,
        int $businessId,
        ?int $locationId,
        ?string $customerType
    ): array {
        $board = $this->service->getBoardData([
            'business_id' => $businessId,
            'location_id' => $locationId,
            'customer_type' => $customerType,
        ]);

        return [
            'reply' => "Current board summary {$scope}: total {$board['summary']['total']}, completed {$board['summary']['completed']}, pending {$board['summary']['pending']}.",
            'plans' => [],
            'should_reload' => false,
        ];
    }

    protected function formatScope(array $intent): string
    {
        $businessId = $intent['business_id'] ?? null;
        $locationId = $intent['location_id'] ?? null;
        $businessName = $intent['business_name'] ?? null;
        $locationName = $intent['location_name'] ?? null;

        $parts = [];

        if ($businessId !== null) {
            $parts[] = $businessName ? "in business {$businessName}" : "in business {$businessId}";
        }

        if ($locationId !== null) {
            $parts[] = $locationName ? "for location {$locationName}" : "for location {$locationId}";
        }

        return implode(' ', $parts);
    }
}