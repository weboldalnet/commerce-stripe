@extends('admin.layouts.layout')
@section('title', 'Stripe beállítások')

@section('content')
    {{-- Stripe beállításoldal (commerce-stripe): csak kijelzés — a kulcsok a .env-ben élnek, itt maszkolva látszanak.
         A kapcsolatteszt a fiók egyenlegét kéri le (csak olvasás). --}}
    <div class="container mw-1100 mt-lg-4 mt-3 mb-150">
        <div class="row">
            <div class="col-lg-12">
                <h2 class="header-box d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px">
                    Stripe beállítások
                    <button type="button" id="stripeTestBtn" class="btn btn-warning font-weight-bold btn-sm" @if(!$mode) disabled @endif>
                        <i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése
                    </button>
                </h2>
                <div id="stripeTestResult" class="alert d-none"></div>
            </div>
        </div>

        {{-- Telefonon a címke és az érték egymás alá kerül: a 260 px-es címkeoszlop mellett az érték kilógna. --}}
        <style>
            .stripe-settings-table th { width: 260px; }
            @media (max-width: 767.98px) {
                .stripe-settings-table, .stripe-settings-table tbody, .stripe-settings-table tr,
                .stripe-settings-table th, .stripe-settings-table td { display: block; width: auto; }
                .stripe-settings-table td { overflow-wrap: anywhere; }
                .stripe-settings-table td { border-top: 0; padding-top: 0; }
            }
        </style>
        <div class="content-box mb-4">
            <table class="table mb-0 stripe-settings-table">
                <tbody>
                <tr>
                    <th class="text-left">Állapot</th>
                    <td class="text-left">
                        @if($registered)
                            <span class="badge badge-success fs-14">Bekapcsolva</span>
                        @elseif(!$enabled)
                            <span class="badge badge-secondary fs-14">Kikapcsolva</span> <span class="text-muted fs-14">COMMERCE_STRIPE_ENABLED=false</span>
                        @else
                            <span class="badge badge-danger fs-14">Nincs kulcs</span> <span class="text-muted fs-14">A fizetési módok között addig nem jelenik meg.</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th class="text-left">Mód</th>
                    <td class="text-left">
                        @if($mode === 'live')
                            <span class="badge badge-danger fs-14">ÉLES</span> <span class="text-muted fs-14">Valódi terhelések!</span>
                        @elseif($mode === 'test')
                            <span class="badge badge-info fs-14">Teszt</span> <span class="text-muted fs-14">Tesztkártya: 4242 4242 4242 4242, bármilyen jövőbeli lejárat és CVC.</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <th class="text-left">Titkos kulcs</th>
                    <td class="text-left"><code>{{ $maskedSecret ?: 'nincs megadva' }}</code> <span class="d-block text-muted fs-14">.env: COMMERCE_STRIPE_SECRET_KEY</span></td>
                </tr>
                <tr>
                    <th class="text-left">Webhook-titok</th>
                    <td class="text-left"><code>{{ $maskedWebhook ?: 'nincs megadva' }}</code> <span class="d-block text-muted fs-14">.env: COMMERCE_STRIPE_WEBHOOK_SECRET — nélküle a webhook minden kérést elutasít.</span></td>
                </tr>
                <tr>
                    <th class="text-left">Webhook-cím</th>
                    <td class="text-left">
                        <code>{{ $webhookUrl }}</code>
                        <span class="d-block text-muted fs-14">Stripe Dashboard › Developers › Webhooks › Add endpoint. Események: {{ implode(', ', $events) }}.</span>
                    </td>
                </tr>
                <tr>
                    <th class="text-left">Felirat / nyelv</th>
                    <td class="text-left">{{ $label }} · {{ $locale }}</td>
                </tr>
                <tr>
                    <th class="text-left">Provider kód</th>
                    <td class="text-left"><code>{{ $code }}</code></td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info mr-1"></i>
            A függő fizetések állapotát a <code>php artisan commerce-stripe:reconcile</code> parancs kérdezi le újra a Stripe-tól
            (ha a webhook nem érkezett meg) — érdemes ütemezni.
        </div>
    </div>

    <script>
        document.getElementById('stripeTestBtn').addEventListener('click', function () {
            const box = document.getElementById('stripeTestResult');
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            this.disabled = true;
            fetch('{{ route('admin.webshop.stripe.test-connection') }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                credentials: 'same-origin'
            }).then(r => r.json()).then(json => {
                box.className = 'alert ' + (json.success ? 'alert-success' : 'alert-danger');
                box.textContent = json.message;
            }).catch(() => {
                box.className = 'alert alert-danger';
                box.textContent = 'A teszt nem futott le.';
            }).finally(() => { this.disabled = false; });
        });
    </script>
@endsection
