import Choices from 'choices.js';
import 'choices.js/public/assets/styles/choices.min.css';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import { Arabic } from 'flatpickr/dist/l10n/ar.js';

export function initChoices(root = document) {
    root.querySelectorAll('[data-choices]:not([data-choice="active"])').forEach((element) => {
        const config = {
            searchEnabled: !element.hasAttribute('data-choices-search-false'),
            removeItemButton: element.hasAttribute('data-choices-removeItem') || element.multiple,
            shouldSort: !element.hasAttribute('data-choices-sorting-false'),
            noResultsText: 'لا توجد نتائج مطابقة',
            noChoicesText: 'لا توجد خيارات متاحة',
            itemSelectText: 'اضغط للاختيار',
            placeholder: true,
            placeholderValue: element.getAttribute('data-placeholder') || 'اختر...'
        };
        new Choices(element, config);
    });
}

export function initFlatpickr(root = document) {
    root.querySelectorAll('[data-provider="flatpickr"]:not(.flatpickr-input)').forEach((element) => {
        const config = {
            locale: Arabic,
            disableMobile: true,
            dateFormat: element.dataset.dateFormat || 'd-m-Y',
            onChange: function() {
                element.dispatchEvent(new Event('change', { bubbles: true }));
                element.dispatchEvent(new Event('input', { bubbles: true }));
            }
        };
        if (element.hasAttribute('data-enable-time')) {
            config.enableTime = true;
            const baseFormat = element.dataset.dateFormat || 'd-m-Y';
            config.dateFormat = baseFormat.includes('H:i') ? baseFormat : baseFormat + ' H:i';
        }
        if (element.hasAttribute('data-range-date') || element.hasAttribute('data-range')) {
            config.mode = 'range';
        }
        if (element.dataset.minDate) config.minDate = element.dataset.minDate;
        if (element.dataset.maxDate) config.maxDate = element.dataset.maxDate;
        flatpickr(element, config);
    });
}

export function setFieldInvalid(input, message) {
    input.classList.add('is-invalid');
    let feedback = input.nextElementSibling;
    while (feedback && !feedback.classList.contains('invalid-feedback')) {
        feedback = feedback.nextElementSibling;
    }
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        input.parentNode.appendChild(feedback);
    }
    feedback.textContent = message;
    feedback.style.display = 'block';
}

export function clearFieldInvalid(input) {
    input.classList.remove('is-invalid');
    let feedback = input.nextElementSibling;
    while (feedback && !feedback.classList.contains('invalid-feedback')) {
        feedback = feedback.nextElementSibling;
    }
    if (feedback) {
        feedback.textContent = '';
        feedback.style.display = '';
    }
}

function parseDateValue(val) {
    if (!val) return null;
    const trimmed = val.trim();
    // Match d-m-Y or d/m/Y (e.g. 16-09-2026 or 16/09/2026)
    const dmyMatch = trimmed.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/);
    if (dmyMatch) {
        return new Date(Number(dmyMatch[3]), Number(dmyMatch[2]) - 1, Number(dmyMatch[1])).getTime();
    }
    // Match Y-m-d or Y/m/d (e.g. 2026-09-16)
    const ymdMatch = trimmed.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/);
    if (ymdMatch) {
        return new Date(Number(ymdMatch[1]), Number(ymdMatch[2]) - 1, Number(ymdMatch[3])).getTime();
    }
    const timestamp = Date.parse(trimmed);
    return isNaN(timestamp) ? null : timestamp;
}

export function initDateFilterValidation(root = document) {
    const pairs = [
        ['date_from', 'date_to'],
        ['start_date', 'end_date'],
        ['from_date', 'to_date'],
        ['from', 'to'],
        ['starts_at', 'ends_at'],
    ];

    root.querySelectorAll('form').forEach((form) => {
        pairs.forEach(([fromName, toName]) => {
            const fromInput = form.querySelector(`input[name="${fromName}"]`);
            const toInput = form.querySelector(`input[name="${toName}"]`);

            if (fromInput && toInput) {
                const validateDateRange = () => {
                    const fromTime = parseDateValue(fromInput.value);
                    const toTime = parseDateValue(toInput.value);

                    if (fromTime !== null && toTime !== null && fromTime > toTime) {
                        setFieldInvalid(toInput, 'تاريخ النهاية يجب أن يكون في أو بعد تاريخ البداية.');
                        return false;
                    } else {
                        clearFieldInvalid(toInput);
                        return true;
                    }
                };

                fromInput.addEventListener('input', validateDateRange);
                fromInput.addEventListener('change', validateDateRange);
                toInput.addEventListener('input', validateDateRange);
                toInput.addEventListener('change', validateDateRange);

                form.addEventListener('submit', (e) => {
                    if (!validateDateRange()) {
                        e.preventDefault();
                        e.stopPropagation();
                        toInput.focus();
                        return false;
                    }
                });
            }
        });
    });
}

window.Choices = Choices;
window.flatpickr = flatpickr;
window.initChoices = initChoices;
window.initFlatpickr = initFlatpickr;
window.initDateFilterValidation = initDateFilterValidation;
window.setFieldInvalid = setFieldInvalid;
window.clearFieldInvalid = clearFieldInvalid;

document.addEventListener('DOMContentLoaded', () => {
    initChoices();
    initFlatpickr();
    initDateFilterValidation();
});

const toggle = document.querySelector('#topnav-hamburger-icon');
const overlay = document.querySelector('.vertical-overlay');
const backToTop = document.querySelector('#back-to-top');
const savedSidebarSize = sessionStorage.getItem('data-sidebar-size');

if (savedSidebarSize === 'sm' || savedSidebarSize === 'lg') {
    document.documentElement.dataset.sidebarSize = savedSidebarSize;
}


function setSidebar(open) {
    document.body.classList.toggle('vertical-sidebar-enable', open);
    toggle?.querySelector('.hamburger-icon')?.classList.toggle('open', open);
    toggle?.setAttribute('aria-expanded', String(open));
}

toggle?.addEventListener('click', () => {
    if (window.innerWidth <= 991) {
        setSidebar(!document.body.classList.contains('vertical-sidebar-enable'));
        return;
    }

    const compact = document.documentElement.dataset.sidebarSize === 'sm';
    document.documentElement.dataset.sidebarSize = compact ? 'lg' : 'sm';
    sessionStorage.setItem('data-sidebar-size', compact ? 'lg' : 'sm');
    toggle.querySelector('.hamburger-icon')?.classList.toggle('open', !compact);
});
overlay?.addEventListener('click', () => setSidebar(false));
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setSidebar(false); });

window.addEventListener('resize', () => { if (window.innerWidth > 991) setSidebar(false); });
window.addEventListener('scroll', () => {
    document.querySelector('#page-topbar')?.classList.toggle('topbar-shadow', window.scrollY >= 50);
    if (backToTop) backToTop.style.display = window.scrollY > 100 ? 'block' : 'none';
});
backToTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => new bootstrap.Tooltip(element));
document.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => new bootstrap.Popover(element));

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.querySelector(button.dataset.passwordToggle);
        if (!input) return;
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.setAttribute('aria-label', visible ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
        button.querySelector('i')?.classList.toggle('ri-eye-line', visible);
        button.querySelector('i')?.classList.toggle('ri-eye-off-line', !visible);
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        if (form.dataset.confirmed === 'true') return;
        if (form.dataset.confirmWhen && !form.querySelector(form.dataset.confirmWhen)?.checked) return;

        event.preventDefault();
        const confirmed = window.Swal
            ? (await Swal.fire({
                title: 'هل أنت متأكد؟',
                text: form.dataset.confirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، متابعة',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger w-xs me-2',
                    cancelButton: 'btn btn-light w-xs',
                },
            })).isConfirmed
            : window.confirm(form.dataset.confirm);

        if (confirmed) {
            form.dataset.confirmed = 'true';
            form.requestSubmit(event.submitter || undefined);
        }
    });
});

document.querySelectorAll('.js-flash-message').forEach((message) => {
    if (!window.Swal) return;
    message.hidden = true;
    Swal.fire({
        toast: true,
        position: 'top-start',
        icon: message.dataset.flashType,
        title: message.dataset.flashMessage,
        showConfirmButton: false,
        showCloseButton: true,
        timer: 3500,
        timerProgressBar: true,
    });
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        const submit = form.querySelector('button[type="submit"]');
        if (!submit || submit.disabled) return;
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
    });
});

// Product & Variant media reordering
document.addEventListener('click', (event) => {
    const target = event.target.closest('.move-media-btn');
    if (target) {
        const direction = target.dataset.direction;
        const cardCol = target.closest('[data-media-id]');
        if (!cardCol) return;

        const grid = document.getElementById('productMediaGrid');
        if (!grid) return;

        if (direction === 'prev' && cardCol.previousElementSibling) {
            grid.insertBefore(cardCol, cardCol.previousElementSibling);
        } else if (direction === 'next' && cardCol.nextElementSibling) {
            grid.insertBefore(cardCol.nextElementSibling, cardCol);
        }

        const mediaIds = Array.from(grid.querySelectorAll('[data-media-id]')).map((el) => el.dataset.mediaId);
        const container = document.getElementById('productReorderInputs');
        const form = document.getElementById('productReorderForm');
        if (container && form) {
            container.innerHTML = '';
            mediaIds.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'media_ids[]';
                input.value = id;
                container.appendChild(input);
            });
            form.submit();
        }
        return;
    }

    const variantTarget = event.target.closest('.move-variant-media-btn');
    if (variantTarget) {
        const variantId = variantTarget.dataset.variantId;
        const direction = variantTarget.dataset.direction;
        const cardCol = variantTarget.closest('[data-media-id]');
        if (!cardCol) return;

        const grid = document.getElementById(`variantMediaGrid${variantId}`);
        if (!grid) return;

        if (direction === 'prev' && cardCol.previousElementSibling) {
            grid.insertBefore(cardCol, cardCol.previousElementSibling);
        } else if (direction === 'next' && cardCol.nextElementSibling) {
            grid.insertBefore(cardCol.nextElementSibling, cardCol);
        }

        const mediaIds = Array.from(grid.querySelectorAll('[data-media-id]')).map((el) => el.dataset.mediaId);
        const container = document.getElementById(`variantReorderInputs${variantId}`);
        const form = document.getElementById(`variantReorderForm${variantId}`);
        if (container && form) {
            container.innerHTML = '';
            mediaIds.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'media_ids[]';
                input.value = id;
                container.appendChild(input);
            });
            form.submit();
        }
    }
});

// Variant inline validation and modal error handling

document.addEventListener('DOMContentLoaded', () => {
    const errorModalHolder = document.getElementById('active-error-modal');
    if (errorModalHolder && window.bootstrap) {
        const modalEl = document.getElementById(errorModalHolder.dataset.modalId);
        if (modalEl) {
            new bootstrap.Modal(modalEl).show();
        }
    }

    document.querySelectorAll('.needs-validation-variant').forEach((form) => {
        form.querySelectorAll('input, select').forEach((field) => {
            field.addEventListener('input', () => clearFieldInvalid(field));
            field.addEventListener('change', () => clearFieldInvalid(field));
        });

        form.addEventListener('submit', (e) => {
            let isValid = true;
            let firstInvalid = null;

            const markError = (input, msg) => {
                isValid = false;
                setFieldInvalid(input, msg);
                if (!firstInvalid) firstInvalid = input;
            };

            const skuInput = form.querySelector('input[name="sku"]');
            if (skuInput) {
                clearFieldInvalid(skuInput);
                const val = skuInput.value.trim();
                if (!val) {
                    markError(skuInput, 'رمز التخزين (SKU) مطلوب.');
                } else if (val.length > 64) {
                    markError(skuInput, 'رمز التخزين يجب ألا يتجاوز 64 حرفاً.');
                }
            }

            const barcodeInput = form.querySelector('input[name="barcode"]');
            if (barcodeInput) {
                clearFieldInvalid(barcodeInput);
                const val = barcodeInput.value.trim();
                if (val.length > 64) {
                    markError(barcodeInput, 'الباركود يجب ألا يتجاوز 64 حرفاً.');
                }
            }

            const priceInput = form.querySelector('input[name="price"]');
            let priceVal = null;
            if (priceInput) {
                clearFieldInvalid(priceInput);
                const val = priceInput.value.trim();
                if (val === '') {
                    markError(priceInput, 'السعر بالعملة الأساسية مطلوب.');
                } else {
                    priceVal = parseFloat(val);
                    if (isNaN(priceVal) || priceVal < 0) {
                        markError(priceInput, 'يجب أن يكون السعر رقماً صحيحاً أو عشرياً أكبر من أو يساوي 0.');
                    }
                }
            }

            const compareInput = form.querySelector('input[name="compare_at_price"]');
            if (compareInput) {
                clearFieldInvalid(compareInput);
                const val = compareInput.value.trim();
                if (val !== '') {
                    const compareVal = parseFloat(val);
                    if (isNaN(compareVal) || compareVal < 0) {
                        markError(compareInput, 'سعر المقارنة يجب أن يكون رقماً أكبر من أو يساوي 0.');
                    } else if (priceVal !== null && compareVal <= priceVal) {
                        markError(compareInput, `سعر المقارنة (${compareVal}) يجب أن يكون أعلى من السعر الأساسي (${priceVal}).`);
                    }
                }
            }

            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                if (firstInvalid) {
                    firstInvalid.focus();
                }
                return false;
            }
        });
    });
});


