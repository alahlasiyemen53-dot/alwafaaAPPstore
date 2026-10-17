{{-- Reusable Modal to Add Currency Account for Treasury Entity --}}
<div class="modal fade" id="addCurrencyModal" tabindex="-1" aria-labelledby="addCurrencyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light pb-3">
                <h5 class="modal-title" id="addCurrencyModalLabel">
                    <i class="ri-money-dollar-circle-line text-primary me-1"></i> فتح عملة جديدة لحساب الخزينة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.finance.treasury.currency-accounts.store') }}" id="addCurrencyForm">
                @csrf
                <input type="hidden" name="accountable_type" id="modal_accountable_type" value="">
                <input type="hidden" name="accountable_id" id="modal_accountable_id" value="">

                <div class="modal-body">
                    <div class="alert alert-info py-2 fs-13 mb-3">
                        <i class="ri-information-line me-1"></i>
                        تُفتح العملة على نفس حساب الأستاذ العام المرتبط بهذا الصندوق/البنك/المحفظة. الأرصدة تُتتبع حسب العملة، والقيود تُرحَّل بالعملة الأساسية بعد المصارفة.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">كيان الخزينة</label>
                        <input type="text" id="modal_entity_name" class="form-control bg-light" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">العملة المراد فتحها <span class="text-danger">*</span></label>
                        <select name="currency_id" class="form-select" required id="modal_currency_select">
                            <option value="">-- اختر العملة --</option>
                            @foreach($currencies as $curr)
                                <option value="{{ $curr->id }}" data-code="{{ $curr->code }}">
                                    {{ $curr->name_ar }} ({{ $curr->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات اختيارية..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-line align-bottom me-1"></i> فتح العملة وتأكيد الربط
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const addCurrencyModal = document.getElementById('addCurrencyModal');
    if (addCurrencyModal) {
        addCurrencyModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const entityType = button.getAttribute('data-entity-type');
            const entityId = button.getAttribute('data-entity-id');
            const entityName = button.getAttribute('data-entity-name');
            const openedCurrencies = (button.getAttribute('data-opened-currencies') || '').split(',').map(c => c.trim());

            document.getElementById('modal_accountable_type').value = entityType;
            document.getElementById('modal_accountable_id').value = entityId;
            document.getElementById('modal_entity_name').value = entityName;

            const currencySelect = document.getElementById('modal_currency_select');
            Array.from(currencySelect.options).forEach(opt => {
                if (opt.value === '') return;
                const optCode = opt.getAttribute('data-code');
                if (openedCurrencies.includes(optCode)) {
                    opt.disabled = true;
                    opt.textContent = opt.textContent.replace(' (مفتوح مسبقاً)', '') + ' (مفتوح مسبقاً)';
                } else {
                    opt.disabled = false;
                    opt.textContent = opt.textContent.replace(' (مفتوح مسبقاً)', '');
                }
            });
        });
    }
});
</script>
