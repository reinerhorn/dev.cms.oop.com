document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('.js-auth-form');
    forms.forEach(form => new AuthActionController(form));
});

class AuthActionController {
    constructor(form) {
        this.form = form;
        this.button = form.querySelector('button[type="submit"]');
        this.messages = form.querySelector('[data-form-messages]');
        this.intentInput = form.querySelector('input[name="intent"]');
        this.intent = this.intentInput ? this.intentInput.value : null;

        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });
    }

    async handleSubmit() {
        if (!this.intent) {
            this.showMessage('Interner Fehler: Formular-Intent fehlt');
            return;
        }

        this.clearMessage();

        const payload = Object.fromEntries(new FormData(this.form).entries());
        payload.intent = this.intent;

        try {
            const response = await fetch(this.form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            let data;
            try {
                data = await response.json();
            } catch {
                this.showMessage('Ungültige Server-Antwort');
                return;
            }

            this.handleResponse(data);

        } catch (err) {
            this.showMessage('Server nicht erreichbar');
        }
    }

    handleResponse(data) {
        // ❌ Fehler → IMMER im Formular anzeigen
        if (!data || data.success !== true) {

            // Sonderfall: nicht verifiziert
            if (data?.code === 'not_verified') {
                this.intent = 'resend_verify';
                if (this.button) {
                    this.button.textContent = 'Bestätigungs-Mail erneut senden';
                }
            }

            this.showMessage(data?.error || 'Anmeldung fehlgeschlagen');
            return;
        }

        // ✅ Erfolg → Redirect nur bei success
        if (data.redirect) {
            window.location.href = data.redirect;
        }
    }

    clearMessage() {
        if (this.messages) {
            this.messages.textContent = '';
            this.messages.classList.remove('error', 'success');
        }
    }

    showMessage(text) {
        if (this.messages) {
            this.messages.textContent = text;
            this.messages.classList.add('error');
        }
    }
}