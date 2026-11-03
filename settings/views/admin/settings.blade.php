@extends('admin.layouts.layout')
@section('title', 'Stripe beállítások')
{{-- a validációs hibákat ez az oldal maga listázza — az újabb core layout ne ismételje meg --}}
@section('wnSajatHibalista', '1')

@section('content')
{{-- Stripe beállításoldal (commerce-stripe) — a commerce-barion beállításoldalának felépítésében. Az adminban mentett
     érték az elsődleges, hiányában a .env (COMMERCE_STRIPE_*) érvényes. A titkok soha nem kerülnek vissza az oldalra,
     csak maszkolva. Nem függ a webshop csomagtól (saját értesítés-blokk), így webshop nélküli projektben is működik. --}}
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="header-box my-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px">
                    <div>
                        <h1 class="mb-0">Stripe beállítások</h1>
                        <p class="text-muted small mb-0">Stripe bankkártyás fizetés (hosztolt Checkout) konfigurálása</p>
                    </div>
                    <div>
                        <button type="button" id="stripe-test-connection-btn" class="btn btn-warning font-weight-bold" @if(!$mode) disabled title="Előbb ments egy titkos kulcsot." @endif>
                            <i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése
                        </button>
                        <div id="stripe-test-connection-result" class="mt-2 mb-0 d-none"></div>
                    </div>
                </div>
            </div>

            {{-- Az újabb core a mentés visszajelzését értesítés-buborékban adja (AdminSaveFeedback) — csak ennek
                 hiányában (régebbi core) írjuk ki itt. --}}
            @unless(class_exists(\App\Http\Middleware\AdminSaveFeedback::class))
                @if(session('success'))
                    <div class="alert alert-success py-2 px-3"><i class="fa fa-check-circle mr-1"></i> {{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger py-2 px-3"><i class="fa fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
                @endif
            @endunless
            @if($errors->any())
                <div class="alert alert-danger py-2 px-3">
                    <i class="fa fa-exclamation-circle mr-1"></i> A beállítások nem mentődtek:
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $stripeError)
                            <li>{{ $stripeError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @unless($storageReady)
                <div class="alert alert-warning py-2 px-3">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    A beállítások táblája még nincs létrehozva — futtasd a <code>php artisan migrate</code> parancsot. Addig a
                    .env (COMMERCE_STRIPE_*) beállításai érvényesek, és az adminból nem lehet menteni.
                </div>
            @endunless
            @if(!empty($undecryptable))
                <div class="alert alert-danger py-2 px-3">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    Egy mentett titok nem fejthető vissza (megváltozott az APP_KEY, vagy más környezetből jött az adatbázis):
                    {{ implode(', ', array_map(fn ($k) => $k === 'secret_key' ? 'titkos kulcs' : 'webhook-titok', $undecryptable)) }}.
                    Add meg újra.
                </div>
            @endif

            <form action="{{ route('admin.webshop.stripe.settings.update') }}" method="POST" autocomplete="off">
                @csrf

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Modul állapota</div>
                        <div class="content-box bordered mb-3">
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="enabled" name="enabled"
                                       @if(old('enabled', $stripeSettings['enabled'])) checked @endif>
                                <label class="custom-control-label fw-600" for="enabled">Stripe fizetés engedélyezve</label>
                            </div>

                            <div class="form-group mb-2">
                                <label class="fw-600" for="payment_method_label">Megnevezés a pénztárban</label>
                                <input type="text" name="payment_method_label" id="payment_method_label" class="form-control" maxlength="120"
                                       value="{{ old('payment_method_label', $stripeSettings['payment_method_label']) }}"
                                       placeholder="Bankkártyás fizetés (Stripe)">
                                <span class="text-muted fs-14">Ez a szöveg jelenik meg a vásárlónak fizetési módként.</span>
                            </div>

                            @if($registered)
                                <div class="alert alert-success mb-0 py-2 px-3 small">
                                    <i class="fa fa-check-circle mr-1"></i>
                                    A Stripe elérhető fizetési mód. A webshopban a Webshop beállítások › Pénztár fülön kapcsolható be a vásárlóknak.
                                </div>
                            @elseif($stripeSettings['enabled'] && !$mode)
                                <div class="alert alert-warning mb-0 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    A modul be van kapcsolva, de nincs érvényes titkos kulcs — a fizetési módok között addig nem jelenik meg.
                                </div>
                            @else
                                <div class="alert alert-info mb-0 py-2 px-3 small">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    Kikapcsolva a Stripe nem kínálható fizetési módként; a beállítások megmaradnak.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Környezet</div>
                        <div class="content-box bordered mb-3">
                            <label class="fw-600 d-block mb-1">API környezet</label>
                            <p class="mb-2">
                                @if($mode === 'live')
                                    <span class="badge badge-danger fs-14">Éles</span>
                                @elseif($mode === 'test')
                                    <span class="badge badge-info fs-14">Teszt (sandbox)</span>
                                @else
                                    <span class="badge badge-secondary fs-14">Nincs kulcs</span>
                                @endif
                            </p>
                            <span class="text-muted fs-14 d-block mb-3">
                                A környezetet a titkos kulcs előtagja dönti el: sk_test_ / rk_test_ = teszt, sk_live_ / rk_live_ = éles.
                                A teszt és az éles fiókhoz külön kulcsok tartoznak.
                            </span>

                            @if($mode && $publishableMode && $mode !== $publishableMode)
                                <div class="alert alert-warning mb-2 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    A publikus és a titkos kulcs nem ugyanabból a környezetből való (teszt / éles).
                                </div>
                            @endif

                            @if($mode === 'live')
                                <div class="alert alert-danger mb-0 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    <strong>Éles környezet.</strong> A vásárlók valódi pénzzel fizetnek.
                                </div>
                            @else
                                <div class="alert alert-secondary mb-0 py-2 px-3 small">
                                    <i class="fa fa-flask mr-1"></i>
                                    Teszt környezetben nincs valódi terhelés. Tesztkártya: 4242 4242 4242 4242, bármilyen jövőbeli lejárat és CVC.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Hitelesítés (Stripe)</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        @foreach(['secret_key' => 'Titkos kulcs', 'webhook_secret' => 'Webhook aláíró titok'] as $stripeKey => $stripeLabel)
                            @php($stripeSecret = $stripeSecrets[$stripeKey])
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label class="fw-600" for="{{ $stripeKey }}">{{ $stripeLabel }}</label>
                                    <input type="password" name="{{ $stripeKey }}" id="{{ $stripeKey }}" class="form-control" autocomplete="new-password"
                                           placeholder="{{ $stripeSecret['masked'] ?: ($stripeKey === 'secret_key' ? 'rk_test_… vagy sk_test_…' : 'whsec_…') }}">
                                    <span class="text-muted fs-14 d-block">
                                        Jelenleg:
                                        @if($stripeSecret['masked'])
                                            <code>{{ $stripeSecret['masked'] }}</code>
                                            · {{ $stripeSecret['source'] === 'admin' ? 'adminban mentve' : '.env-ből' }}
                                        @else
                                            nincs megadva
                                        @endif
                                    </span>
                                    <span class="text-muted fs-14 d-block">
                                        @if($stripeKey === 'secret_key')
                                            Ajánlott a korlátozott kulcs (rk_…) egyetlen joggal: Checkout Sessions — Write.
                                        @else
                                            A Stripe Dashboard › Fejlesztők › Webhookok végpontjának aláíró titka. Nélküle a webhook minden kérést elutasít.
                                        @endif
                                        Titkosítva tárolódik; üresen hagyva a mostani marad.
                                    </span>
                                    @if($stripeSecret['source'] === 'admin')
                                        <div class="custom-control custom-checkbox mt-1">
                                            <input type="checkbox" class="custom-control-input" id="clear_{{ $stripeKey }}" name="clear_{{ $stripeKey }}" value="1">
                                            <label class="custom-control-label fs-14" for="clear_{{ $stripeKey }}">Adminban mentett érték törlése</label>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="fw-600" for="publishable_key">Publikus kulcs</label>
                                <input type="text" name="publishable_key" id="publishable_key" class="form-control"
                                       value="{{ old('publishable_key', $stripeSettings['publishable_key']) }}" placeholder="pk_test_…">
                                <span class="text-muted fs-14 d-block">
                                    Nem titok. A hosztolt fizetéshez nem kötelező, de a környezet-ellenőrzéshez érdemes megadni.
                                    @if($publishableSource === 'env') Jelenleg a .env-ből. @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Fizetési beállítások</div>
                        <div class="content-box bordered mb-3">
                            <div class="form-group">
                                <label class="fw-600" for="payment_method_types">Fizetési lehetőségek</label>
                                <select name="payment_method_types" id="payment_method_types" class="form-control">
                                    @foreach($paymentMethodModes as $stripeValue => $stripeName)
                                        <option value="{{ $stripeValue }}" @if(old('payment_method_types', $stripeSettings['payment_method_types']) === (string) $stripeValue) selected @endif>{{ $stripeName }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted fs-14">
                                    Az elsőnél a Stripe Dashboard › Beállítások › Fizetési módok listája érvényes (kártya, Apple Pay, Google Pay, Link …).
                                </span>
                            </div>
                            <div class="form-group mb-0">
                                <label class="fw-600" for="session_lifetime">Fizetési ablak</label>
                                <select name="session_lifetime" id="session_lifetime" class="form-control">
                                    @foreach($sessionLifetimes as $stripeValue => $stripeName)
                                        <option value="{{ $stripeValue }}" @if((int) old('session_lifetime', $stripeSettings['session_lifetime']) === (int) $stripeValue) selected @endif>{{ $stripeName }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted fs-14">Ennyi ideig fizethető ki ugyanaz a Stripe-oldal; utána a fizetés megszakítottnak számít.</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Megjelenés</div>
                        <div class="content-box bordered mb-3">
                            <div class="form-group">
                                <label class="fw-600" for="locale">Fizetőoldal nyelve</label>
                                <select name="locale" id="locale" class="form-control">
                                    @foreach($locales as $stripeValue => $stripeName)
                                        <option value="{{ $stripeValue }}" @if(old('locale', $stripeSettings['locale']) === $stripeValue) selected @endif>{{ $stripeName }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted fs-14">A Stripe fizetőoldalának nyelve. Többnyelvű webshopban a nem magyar nyelvű rendelések fizetőoldala a vásárló nyelvén nyílik meg.</span>
                            </div>
                            <label class="fw-600 d-block mb-1">Fizetési mód kódja</label>
                            <code>{{ $code }}</code>
                            <span class="text-muted fs-14 d-block">Ezzel a kóddal szerepel a rendeléseknél és a tranzakcióknál.</span>
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Webhook és visszatérés</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="fw-600" for="stripe-webhook-url">Webhook URL</label>
                                <div class="input-group">
                                    <input type="text" id="stripe-webhook-url" class="form-control" value="{{ $webhookUrl }}" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary js-stripe-copy" data-target="#stripe-webhook-url" title="Másolás"><i class="fa fa-copy"></i></button>
                                    </div>
                                </div>
                                <span class="text-muted fs-14 d-block">
                                    Stripe Dashboard › Fejlesztők › Webhookok › új végpont, élesben https://-sel. Események:
                                    {{ implode(', ', $events) }}.
                                </span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="fw-600" for="stripe-return-url">Visszatérési URL</label>
                                <div class="input-group">
                                    <input type="text" id="stripe-return-url" class="form-control" value="{{ $returnUrl }}" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary js-stripe-copy" data-target="#stripe-return-url" title="Másolás"><i class="fa fa-copy"></i></button>
                                    </div>
                                </div>
                                <span class="text-muted fs-14">Ide tér vissza a vásárló a fizetés után; a csomag a Stripe-tól lekérdezi az eredményt, majd a rendelés eredményoldalára irányít. Nem kell beállítani.</span>
                            </div>
                        </div>
                    </div>
                    <label class="fw-600 d-block mb-1">Helyi fejlesztés</label>
                    <code class="d-block p-2 bg-light rounded text-break">stripe listen --events {{ implode(',', $events) }} --forward-to {{ $webhookUrl }}</code>
                    <span class="text-muted fs-14 d-block mt-1">
                        A gépedre a Stripe nem tud webhookot küldeni: a Stripe CLI továbbítja, és kiírja a helyi aláíró titkot (whsec_…).
                        @if(class_exists(\Weboldalnet\CommerceCore\Console\ReconcilePaymentsCommand::class))
                            Ha egy webhook elmarad, a commerce-core 15 percenként magától lekérdezi a függő fizetéseket
                            (<code>commerce:reconcile-payments</code>, a szerver <code>schedule:run</code> cronjával).
                        @else
                            Ha egy webhook elmarad, a <code>php artisan commerce-stripe:reconcile</code> parancs lekérdezi a függő fizetéseket (érdemes ütemezni).
                        @endif
                    </span>
                </div>

                <div class="text-center mt-3 mb-5">
                    <button type="submit" class="btn btn-primary fs-18 font-weight-bold px-5" @unless($storageReady) disabled @endunless>
                        <i class="fa fa-save mr-1"></i> Beállítások mentése
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('stripe-test-connection-btn');
        var out = document.getElementById('stripe-test-connection-result');

        // beágyazott visszajelzés natív alert() helyett: az blokkolja a lapot
        function show(isSuccess, message) {
            out.className = 'mt-2 mb-0 alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
            out.textContent = message;
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Tesztelés...';
            out.className = 'mt-2 mb-0 d-none';
            out.textContent = '';

            fetch('{{ route("admin.webshop.stripe.test-connection") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) { show(!!data.success, data.message || ''); })
            .catch(function () { show(false, 'Váratlan hiba történt a tesztelés során.'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése';
            });
        });

        // a teszt mindig a mentett kulccsal fut: mentetlen kulcsváltásnál figyelmeztetünk
        document.querySelectorAll('#secret_key, #publishable_key').forEach(function (input) {
            input.addEventListener('input', function () {
                show(false, 'Az új kulcs csak mentés után lép érvénybe — a kapcsolat tesztelése addig a korábbival fut.');
            }, { once: true });
        });

        document.querySelectorAll('.js-stripe-copy').forEach(function (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var field = document.querySelector(copyBtn.getAttribute('data-target'));
                field.select();
                (navigator.clipboard ? navigator.clipboard.writeText(field.value) : Promise.reject())
                    .catch(function () { document.execCommand('copy'); })
                    .finally(function () {
                        copyBtn.innerHTML = '<i class="fa fa-check"></i>';
                        setTimeout(function () { copyBtn.innerHTML = '<i class="fa fa-copy"></i>'; }, 1500);
                    });
            });
        });
    });
</script>
@endsection
