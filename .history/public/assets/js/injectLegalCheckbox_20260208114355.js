document.addEventListener('DOMContentLoaded', function () {

    const debug = document.createElement('div');
    debug.style.position = 'fixed';
    debug.style.bottom = '10px';
    debug.style.right = '10px';
    debug.style.background = 'red';
    debug.style.color = 'white';
    debug.style.padding = '6px 10px';
    debug.style.zIndex = '99999';
    debug.innerText = 'injectLegalCheckbox.js GELADEN';
    document.body.appendChild(debug);


 

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