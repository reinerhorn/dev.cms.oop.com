document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('.js-form');

    // Wenn kein JS-Formular existiert → nichts tun (wichtig!)
    if (!forms.length) return;

    forms.forEach(form => {
        new AuthActionController(form);
    });
});

class AuthActionController {
    constructor(form) {
        this.form = form;
        this.button = form.querySelector('button[type="submit"]');
        this.messages = form.querySelector('[data-form-messages]');
        this.state = 'login'; // login | resend | support

        // WICHTIG: echtes Submit IMMER blockieren
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.handleSubmit();
        });
    }

    async handleSubmit() {
        // Support → kein POST
        if (this.state === 'support') {
            this.openSupport();
            return;
        }

        const payload = this.buildPayload();

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

        } catch (err) {
            this.showMessage('Netzwerkfehler');
        }
    }

    buildPayload() {
        const data = Object.fromEntries(new FormData(this.form).entries());

        data.intent =
            this.state === 'login'  ? 'login' :
            this.state === 'resend' ? 'resend_verify' :
            null;

        return data;
    }

    handleResponse(data) {
        // ✅ ERFOLG
        if (data.success === true) {
            // Redirect vom Backend
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            // Fallback (Admin)
            window.location.href = window.location.pathname;
            return;
        }

        // ❌ NICHT VERIFIZIERT
        if (data.code === 'not_verified') {
            this.setState('resend');
            this.showMessage(data.error);
            return;
        }

        // ❌ FEHLER
        this.showMessage(data.error || 'Login fehlgeschlagen');
    }

    setState(state) {
        this.state = state;

        if (!this.button) return;

        this.button.textContent =
            state === 'login'
                ? 'Login'
                : state === 'resend'
                    ? 'Code erneut senden'
                    : 'Beim Support melden';
    }

    showMessage(text) {
        if (this.messages) {
            this.messages.textContent = text;
        }
    }

    openSupport() {
        alert('Support wird kontaktiert');
    }
}