(function () {
    var hasToast = document.querySelectorAll("[toast-list]").length > 0;
    var hasChoices = document.querySelectorAll("[data-choices]").length > 0;
    var hasFlatpickr = document.querySelectorAll("[data-provider]").length > 0;

    if (hasToast || hasChoices || hasFlatpickr) {
        var base = '/assets/libs/';
        if (hasToast) {
            document.writeln("<script type='text/javascript' src='" + base + "toastify-js/toastify.js'><\/script>");
        }
        if (hasChoices) {
            document.writeln("<link rel='stylesheet' href='" + base + "choices.js/public/assets/styles/choices.min.css' />");
            document.writeln("<script type='text/javascript' src='" + base + "choices.js/public/assets/scripts/choices.min.js'><\/script>");
        }
        if (hasFlatpickr) {
            document.writeln("<link rel='stylesheet' href='" + base + "flatpickr/flatpickr.min.css' />");
            document.writeln("<script type='text/javascript' src='" + base + "flatpickr/flatpickr.min.js'><\/script>");
        }
    }
})();