<?php

namespace Modules\Connector\Http\Controllers\Api;

use App\TransactionVisit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaleVisitShareController extends ApiController
{
    const IMAGE_BASE_URL = 'https://piik-data.sgp1.digitaloceanspaces.com/piik-data/sell-visit/images/';

    /**
     * GET /connector/api/mobile/sale-visits/{uuid}/share
     */
    public function share(Request $request, $uuid)
    {
        $user        = Auth::user();
        $business_id = $user->business_id;

        $visit = TransactionVisit::with([
            'contact',
            'location',
            'sales_person',
            'TransactionSellLineVisit.product',
            'TransactionSellLineVisitImage',
        ])->where('uuid', $uuid)->first();

        if (!$visit) {
            return response()->json(['status' => 'error', 'message' => 'Sale Visit not found.'], 404);
        }

        if ((int) $visit->business_id !== (int) $business_id) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $imageUrls    = $this->buildImageUrls($visit);
        $shareCaption = $this->buildCaption($visit);
        $customerName = optional($visit->contact)->name ?? 'N/A';
        $shareText    = 'Sale Visit ' . $visit->id . ' - ' . $customerName;

        return response()->json([
            'status'           => 'success',
            'share_text'       => $shareText,
            'share_image_urls' => $imageUrls,
            'share_caption'    => $shareCaption,
        ]);
    }

    private function buildImageUrls(TransactionVisit $visit): array
    {
        $urls = [];
        foreach ($visit->TransactionSellLineVisitImage as $img) {
            if (!empty($img->image)) {
                $urls[] = self::IMAGE_BASE_URL . $img->image;
            }
        }
        return $urls;
    }

    private function buildCaption(TransactionVisit $visit): string
    {
        $customerName = optional($visit->contact)->name ?? 'N/A';
        $zone         = optional($visit->location)->name ?? 'N/A';
        $date         = Carbon::parse($visit->transaction_date)->format('d/m/Y');
        $createdBy    = $this->resolveCreatedBy($visit);

        $lines = collect($visit->TransactionSellLineVisit)->filter(fn($l) => $l->product);

        $ownLines   = $lines->where('kind_product', 0)->values();
        $otherLines = $lines->where('kind_product', 1)->values();

        $totalOwnQty   = $ownLines->sum('quantity');
        $totalOtherQty = $otherLines->sum('quantity');
        $totalQty      = $totalOwnQty + $totalOtherQty;

        $ownPercent   = $totalQty > 0 ? round(($totalOwnQty / $totalQty) * 100) : 0;
        $otherPercent = $totalQty > 0 ? 100 - $ownPercent : 0;

        $caption  = "🏁 Sale Visit {$visit->id}\n";
        $caption .= "Date : {$date}\n";
        $caption .= "Created by : {$createdBy}\n";
        $caption .= "Customer : {$customerName}\n";
        $caption .= "Zone : {$zone}";

        if ($totalQty > 0) {
            $sequence = $this->getDailyVisitSequence($visit);
            $caption .= "\n\nម៉ូយទី {$sequence}\n";
            $caption .= "📦 Own vs Other Product\n";
            $caption .= "🟢 Own Product: {$ownPercent}%\n";

            foreach ($ownLines as $i => $line) {
                $caption .= '  ' . ($i + 1) . '. ' . $line->product->name . ' : ' . (int) $line->quantity . "\n";
            }

            $caption .= "\n🔴 Other Product: {$otherPercent}%\n";
            foreach ($otherLines as $i => $line) {
                $caption .= '  ' . ($i + 1) . '. ' . $line->product->name . ' : ' . (int) $line->quantity . "\n";
            }

            if (!empty($visit->sale_latlong)) {
                $caption .= "\n📍 Location: https://maps.google.com/?q=" . $visit->sale_latlong . "\n";
            }

            $caption = rtrim($caption);
        }

        return $caption;
    }

    private function getDailyVisitSequence(TransactionVisit $visit): int
    {
        $visitDate = Carbon::parse($visit->transaction_date)->toDateString();

        $sequence = TransactionVisit::where('business_id', $visit->business_id)
            ->where('create_by', $visit->create_by)
            ->whereDate('transaction_date', $visitDate)
            ->where('id', '<=', $visit->id)
            ->count();

        return max(1, $sequence);
    }

    private function resolveCreatedBy(TransactionVisit $visit): string
    {
        $user = $visit->sales_person;
        if (!$user) {
            return 'N/A';
        }

        $full = trim(($user->surname ?? '') . ' ' . ($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $full ?: ($user->username ?? 'N/A');
    }
}
