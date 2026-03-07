document.addEventListener('DOMContentLoaded', function () {

    // Server sagt: Zustimmung erforderlich?
    if (typeof requireLegalAcceptance === 'undefined' || !requireLegalAcceptance) {
        return;
    }

    const form = document.querySelector('form[data-form]');
    if (!form) return;

    // Nicht doppelt einfügen
    if (form.querySelector('input[name="agree"]')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'checkbox-group';

    wrapper.innerHTML = `
        <label>
            <input type="checkbox" name="agree" required>
            Ich stimme den AGB und der DSGVO zu
        </label>
    `;

    // vor Submit-Button einfügen
    const submit = form.querySelector('button[type="submit"]');
    if (submit) {
        submit.parentNode.insertBefore(wrapper, submit);
    } else {
        form.appendChild(wrapper);
    }
});