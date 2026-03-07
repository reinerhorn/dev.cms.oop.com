document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('.js-auth-form');

    if (!forms.length) {
        console.warn('[auth.js] Kein Auth-Formular gefunden');
        return;
    }

    forms.forEach(form => {
        new AuthActionController(form);
    });
});

class AuthActionController {
    constructor(form) {
        this.form = form;
        this.button = form.querySelector('button[type="submit"]');
        this.messages = form.querySelector('[data-form-messages]');

        // ⬅️ INTENT KOMMT IMMER AUS DEM FORMULAR (NICHT AUS STATE!)
        this.intentInput = form.querySelector('input[name="intent"]');
        this.intent = this.intentInput ? this.intentInput.value : null;

        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });
    }

    async handleSubmit() {
        if (!this.intent) {
            this.showMessage('Formular-Intent fehlt');
            return;
        }

        const payload = Object.fromEntries(new FormData(this.form).entries());
        payload.intent = this.intent;

        try {
            const res = await fetch(this.form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'fetch'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            this.handleResponse(data);

        } catch (e) {
            this.showMessage('Netzwerkfehler');
        }
    }

    handleResponse(data) {
        if (data.success === true) {
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            return;
        }

        // Sonderfall: nicht verifiziert → Button auf "Code erneut senden"
        if (data.code === 'not_verified') {
            this.intent = 'resend_verify';
            if (this.button) {
                this.button.textContent = 'Code erneut senden';
            }
            this.showMessage(data.error);
            return;
        }

        this.showMessage(data.error || 'Aktion fehlgeschlagen');
    }

    showMessage(text) {
        if (this.messages) {
            this.messages.textContent = text;
        }
    }
}