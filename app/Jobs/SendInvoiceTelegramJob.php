<?php

namespace App\Jobs;

use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Connector\Http\Controllers\Api\SellController;

class SendInvoiceTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $transactionId;
    public int $businessId;

    public $timeout = 120;
    public $tries   = 1;

    public function __construct(int $transactionId, int $businessId)
    {
        $this->transactionId = $transactionId;
        $this->businessId    = $businessId;
    }

    public function handle(): void
    {
        \Log::info('SendInvoiceTelegramJob: starting tx=' . $this->transactionId . ' biz=' . $this->businessId);

        $transaction = Transaction::with([
            'sell_lines',
            'sell_lines.product',
            'sell_lines.variations',
            'contact',
        ])->find($this->transactionId);

        if (! $transaction) {
            \Log::warning('SendInvoiceTelegramJob: transaction not found tx=' . $this->transactionId);
            return;
        }

        app(SellController::class)->sendInvoiceToTelegram($transaction, $this->businessId);
    }
}
