document.addEventListener('DOMContentLoaded', () => {

    // Nur reagieren, wenn Backend es verlangt
    if (window.requireLegalAcceptance !== true) {
        return;
    }

    const form = document.querySelector('.page-form-box form[data-form]');
    if (!form) {
        return;
    }

    // Checkbox nicht doppelt einfügen
    if (form.querySelector('input[name="agree"]')) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'checkbox-group';

    wrapper.innerHTML = `
        <label>
            <input type="checkbox" name="agree" required>
            Ich stimme den AGB und der DSGVO zu
        </label>
    `;

    // Checkbox VOR den Buttons einfügen
    const buttons = form.querySelector('.form-buttons');
    if (buttons) {
        form.insertBefore(wrapper, buttons);
    } else {
        form.appendChild(wrapper);
    }

});