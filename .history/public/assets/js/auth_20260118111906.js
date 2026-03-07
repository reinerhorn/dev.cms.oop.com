document.addEventListener('DOMContentLoaded', () => {
    document
        .querySelectorAll('.js-auth-form')
        .forEach(form => new AuthActionController(form));
});

class AuthActionController {
    constructor(form) {
        this.form = form;
        this.button = form.querySelector('button[type="submit"]');
        this.messages = form.querySelector('[data-form-messages]');

        this.state = 'login'; // login | resend | support

        form.addEventListener('submit', e => this.handleSubmit(e));
    }

    async handleSubmit(e) {
        e.preventDefault(); // 🔥 DAS ist der entscheidende Punkt

        if (this.state === 'support') {
            this.openSupport();
            return;
        }

        const payload = this.buildPayload();

        try {
            const res = await fetch(this.form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            this.handleResponse(data);

        } catch {
            this.showMessage('Netzwerkfehler – bitte später erneut versuchen.');
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
        if (data.success === true) {
            if (data.redirect) {
                window.location.href = data.redirect;
            }
            return;
        }

        if (data.code === 'not_verified') {
            this.setState('resend');
            this.showMessage(data.error);
            return;
        }

        this.showMessage(data.error || 'Unbekannter Fehler');
    }

    setState(state) {
        this.state = state;

        this.button.textContent =
            state === 'login'
                ? 'Login'
                : state === 'resend'
                    ? 'Bestätigungs-Mail erneut senden'
                    : 'Beim Support melden';
    }

    showMessage(text) {
        if (this.messages) {
            this.messages.textContent = text;
        } else {
            alert(text);
        }
    }

    openSupport() {
        alert('Support-Chat öffnet sich (Platzhalter)');
        // später: eigenes Modal / Chat
    }
}