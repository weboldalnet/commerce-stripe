<?php

namespace Weboldalnet\CommerceStripe\Console;

use Illuminate\Console\Command;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceCore\Models\PaymentTransaction;
use Weboldalnet\CommerceCore\Services\PaymentCallbackProcessor;
use Weboldalnet\CommerceCore\Status\PaymentStatus;

/**
 * Egyeztetés: a függőben lévő Stripe-tranzakciók állapota a Stripe API-tól (ha a webhook nem érkezett meg vagy a
 * feldolgozása elszállt). Ütemezve pl. 10 percenként; kézzel: php artisan commerce-stripe:reconcile
 */
class StripeReconcileCommand extends Command
{
    protected $signature = 'commerce-stripe:reconcile {--days= : ennyi napnál nem régebbi függő tranzakciók}';

    protected $description = 'A függő Stripe-fizetések állapotának lekérdezése és feldolgozása';

    public function handle(PaymentManager $payments, PaymentCallbackProcessor $processor): int
    {
        $code = config('commerce-stripe.provider_code', 'stripe');
        if (!$payments->hasProvider($code)) {
            $this->info('A Stripe nincs bekapcsolva vagy nincs kulcs — nincs teendő.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?: config('commerce-stripe.reconcile_days', 2));
        $pending = PaymentTransaction::where('provider', $code)
            ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::UNPAID])
            ->whereNotNull('provider_transaction_id')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('id')
            ->get();

        $changed = 0;
        foreach ($pending as $transaction) {
            try {
                $result = $payments->getProvider($code)->queryStatus($transaction);
                if ($result && $result->status !== PaymentStatus::PENDING) {
                    $processor->processResult($code, $result, ['source' => 'reconcile'] + (array) $result->rawPayload);
                    $changed++;
                }
            } catch (\Throwable $e) {
                $this->warn('#' . $transaction->id . ': ' . $e->getMessage());
            }
        }

        $this->info($pending->count() . ' függő tranzakció ellenőrizve, ' . $changed . ' állapota változott.');

        return self::SUCCESS;
    }
}
