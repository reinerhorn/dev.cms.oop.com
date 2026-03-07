document.addEventListener('change', function (e) {

    const select = e.target;
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }

    // =========================
    // 1️⃣ Feld automatisch füllen
    // =========================
    const fillTarget = select.dataset.fillTarget;
    if (fillTarget) {
        const targetField = document.getElementById(fillTarget);
        if (targetField) {
            targetField.value = select.value || '';
        }
    }

    // =========================
    // 2️⃣ Redirect wenn gewünscht
    // =========================
    if (select.dataset.redirectOnChange === "1") {

        const value = select.value;
        const url = new URL(window.location.href);

        if (value === '') {
            url.searchParams.delete(select.name);
        } else {
            url.searchParams.set(select.name, value);
        }

        window.location.href = url.toString();
    }

});