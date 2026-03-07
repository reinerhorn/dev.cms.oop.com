function injectLegalCheckbox() {
    let slot = document.getElementById('legal-checkbox-slot');

    if (!slot) {
        slot = document.createElement('div');
        slot.id = 'legal-checkbox-slot';

      const form = document.querySelector('.page-form-box form[data-form]');
        if (!form) return;

        form.appendChild(slot);
    }

    if (slot.querySelector('input[name="agree"]')) return;

    slot.innerHTML = `
        <div class="checkbox-group">
            <label>
                <input type="checkbox" name="agree" required>
                Ich stimme den AGB und der DSGVO zu
            </label>
        </div>
    `;
}