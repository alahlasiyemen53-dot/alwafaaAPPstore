<script>
(function () {
    const preferredCurrencyId = @json(isset($preferredCurrencyId) ? (string) $preferredCurrencyId : null);

    function rebuildCurrencyOptions(selectedOption, preferredId) {
        const currencySelect = document.getElementById('currencyId');
        if (!currencySelect) return;

        currencySelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';

        if (!selectedOption || !selectedOption.value) {
            placeholder.textContent = 'اختر حساب الخزينة أولاً...';
            currencySelect.appendChild(placeholder);
            return;
        }

        placeholder.textContent = 'اختر العملة...';
        currencySelect.appendChild(placeholder);

        let options = [];
        try {
            options = JSON.parse(selectedOption.getAttribute('data-currency-options') || '[]');
        } catch (e) {
            options = [];
        }

        if (!Array.isArray(options) || options.length === 0) {
            const fallbackId = selectedOption.getAttribute('data-currency');
            if (fallbackId) {
                options = [{ id: parseInt(fallbackId, 10), label: 'العملة الأساسية' }];
            }
        }

        const preferred = preferredId || selectedOption.getAttribute('data-currency') || '';
        let matched = false;

        options.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = String(item.id);
            opt.textContent = item.label || String(item.id);
            if (String(item.id) === String(preferred)) {
                opt.selected = true;
                matched = true;
            }
            currencySelect.appendChild(opt);
        });

        if (!matched && options.length > 0) {
            currencySelect.value = String(options[0].id);
        }
    }

    window.handleAccountChange = function (select) {
        const selectedOption = select.options[select.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            document.getElementById('accountType').value = '';
            document.getElementById('accountId').value = '';
            rebuildCurrencyOptions(null, null);
            return;
        }

        document.getElementById('accountType').value = selectedOption.getAttribute('data-account-type') || '';
        document.getElementById('accountId').value = selectedOption.getAttribute('data-account-id') || '';

        const currencySelect = document.getElementById('currencyId');
        const currentCurrency = currencySelect ? currencySelect.value : '';
        let options = [];
        try {
            options = JSON.parse(selectedOption.getAttribute('data-currency-options') || '[]');
        } catch (e) {
            options = [];
        }
        const allowedIds = options.map(function (item) { return String(item.id); });
        const keepCurrent = currentCurrency && allowedIds.includes(String(currentCurrency));
        rebuildCurrencyOptions(selectedOption, keepCurrent ? currentCurrency : preferredCurrencyId);
    };

    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('accountSelection');
        if (select && select.value) {
            handleAccountChange(select);
        } else {
            rebuildCurrencyOptions(null, null);
        }
    });
})();
</script>
