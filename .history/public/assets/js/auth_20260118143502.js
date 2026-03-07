document.addEventListener('DOMContentLoaded', () => {
    document
        .querySelectorAll('.js-form')
        .forEach(form => new AuthActionController(form));
});

class AuthActionController {
    constructor(form) {
        this.form = form;
        this.button = form.querySelector('button[type="submit"]');
        this.messages = form.querySelector('[data-form-messages]');
        this.state = 'login'; // login | resend | support

        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    async handleSubmit(e) {
        e.preventDefault();

        // Support-State → KEIN POST, nur Aktion
        if (this.state === 'support') {
            this.openSupport();
            return;
        }

        const payload = this.buildPayload();

        try {
            const res = await fetch(this.form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
        // ✅ ERFOLG → IMMER Redirect, wenn vorhanden
        if (data.success === true) {
            if (data.redirect) {
                window.location.href = data.redirect;
            }
            return;
        }

        // ❌ NICHT VERIFIZIERT → Button-Zustand ändern
        if (data.code === 'not_verified') {
            this.setState('resend');
            this.showMessage(data.error);
            return;
        }

        // ❌ ANDERE FEHLER
        this.showMessage(data.error || 'Unbekannter Fehler');
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
        // Platzhalter – später echtes Modal / Chat
        alert('Support wird kontaktiert');
    }
}