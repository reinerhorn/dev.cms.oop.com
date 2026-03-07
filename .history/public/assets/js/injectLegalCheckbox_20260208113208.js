document.addEventListener('DOMContentLoaded', function () {

    // Debug (can be removed later)
    console.log('injectLegalCheckbox.js loaded');

    // Server flag must exist and be true
    if (typeof window.requireLegalAcceptance === 'undefined' || window.requireLegalAcceptance !== true) {
        return;
    }

    // Only for login form
    const form = document.querySelector('form[data-form="login"]');
    if (!form) return;

    // Do not inject twice
    if (form.querySelector('input[name="agree"]')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'checkbox-group';

    wrapper.innerHTML = `
        <label>
            <input type="checkbox" name="agree" required>
            Ich stimme den AGB und der DSGVO zu
        </label>
    `;

    // Insert before submit button if possible
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton && submitButton.parentNode) {
        submitButton.parentNode.insertBefore(wrapper, submitButton);
    } else {
        form.appendChild(wrapper);
    }
});