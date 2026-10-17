@php
    $selectedAccountType = $selectedAccountType ?? null;
    $selectedAccountId = $selectedAccountId ?? null;
@endphp
<optgroup label="الصناديق النقدية">
    @foreach($cashboxes as $cb)
        @php
            $cbOptions = $cb->currencyAccounts
                ->where('is_active', true)
                ->map(fn ($ca) => [
                    'id' => (int) $ca->currency_id,
                    'label' => trim(($ca->currency?->name_ar ?? '').' ('.($ca->currency?->code ?? '').')'),
                ])
                ->values()
                ->all();
            if ($cbOptions === []) {
                $cbOptions = [[
                    'id' => (int) $cb->currency_id,
                    'label' => trim(($cb->currency?->name_ar ?? '').' ('.($cb->currency?->code ?? '').')'),
                ]];
            }
            $cbCodes = collect($cbOptions)->pluck('label')->implode(', ');
        @endphp
        <option
            value="{{ get_class($cb) }}|{{ $cb->id }}"
            data-account-type="{{ get_class($cb) }}"
            data-account-id="{{ $cb->id }}"
            data-currency="{{ $cb->currency_id }}"
            data-currency-options='@json($cbOptions)'
            @selected($selectedAccountType == get_class($cb) && (string) $selectedAccountId === (string) $cb->id)
        >
            {{ $cb->name_ar }} ({{ $cbCodes }})
        </option>
    @endforeach
</optgroup>
<optgroup label="الحسابات البنكية">
    @foreach($bankAccounts as $ba)
        @php
            $baOptions = $ba->currencyAccounts
                ->where('is_active', true)
                ->map(fn ($ca) => [
                    'id' => (int) $ca->currency_id,
                    'label' => trim(($ca->currency?->name_ar ?? '').' ('.($ca->currency?->code ?? '').')'),
                ])
                ->values()
                ->all();
            if ($baOptions === []) {
                $baOptions = [[
                    'id' => (int) $ba->currency_id,
                    'label' => trim(($ba->currency?->name_ar ?? '').' ('.($ba->currency?->code ?? '').')'),
                ]];
            }
            $baCodes = collect($baOptions)->pluck('label')->implode(', ');
        @endphp
        <option
            value="{{ get_class($ba) }}|{{ $ba->id }}"
            data-account-type="{{ get_class($ba) }}"
            data-account-id="{{ $ba->id }}"
            data-currency="{{ $ba->currency_id }}"
            data-currency-options='@json($baOptions)'
            @selected($selectedAccountType == get_class($ba) && (string) $selectedAccountId === (string) $ba->id)
        >
            {{ $ba->bank_name }} - {{ $ba->account_name }} ({{ $baCodes }})
        </option>
    @endforeach
</optgroup>
<optgroup label="المحافظ الإلكترونية">
    @foreach($wallets as $wa)
        @php
            $waOptions = $wa->currencyAccounts
                ->where('is_active', true)
                ->map(fn ($ca) => [
                    'id' => (int) $ca->currency_id,
                    'label' => trim(($ca->currency?->name_ar ?? '').' ('.($ca->currency?->code ?? '').')'),
                ])
                ->values()
                ->all();
            if ($waOptions === []) {
                $waOptions = [[
                    'id' => (int) $wa->currency_id,
                    'label' => trim(($wa->currency?->name_ar ?? '').' ('.($wa->currency?->code ?? '').')'),
                ]];
            }
            $waCodes = collect($waOptions)->pluck('label')->implode(', ');
        @endphp
        <option
            value="{{ get_class($wa) }}|{{ $wa->id }}"
            data-account-type="{{ get_class($wa) }}"
            data-account-id="{{ $wa->id }}"
            data-currency="{{ $wa->currency_id }}"
            data-currency-options='@json($waOptions)'
            @selected($selectedAccountType == get_class($wa) && (string) $selectedAccountId === (string) $wa->id)
        >
            {{ $wa->provider_name }} - {{ $wa->account_label }} ({{ $waCodes }})
        </option>
    @endforeach
</optgroup>
