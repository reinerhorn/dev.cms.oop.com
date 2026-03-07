class AuthActionController {
    state = 'login';

    constructor(form) {
        this.form = form;
        this.button = form.querySelector('.js-auth-action');
        this.messages = form.querySelector('.form-messages');

        form.addEventListener('submit', e => this.handle(e));
    }

    async handle(e) {
        e.preventDefault();

        if (this.state === 'support') {
            this.openSupportChat();
            return;
        }

        const payload = this.buildPayload();
        const res = await fetch(this.form.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        this.processResponse(data);
    }

    buildPayload() {
        const formData = new FormData(this.form);
        const payload = Object.fromEntries(formData.entries());

        payload.intent =
            this.state === 'login'  ? 'login' :
            this.state === 'resend' ? 'resend_verify' :
            null;

        return payload;
    }

    processResponse(data) {
        if (data.success) {
            if (this.state === 'resend') {
                this.setState('support');
                this.show('Mail gesendet. Bitte Support kontaktieren.');
            } else if (data.redirect) {
                window.location.href = data.redirect;
            }
            return;
        }

        if (data.code === 'not_verified') {
            this.setState('resend');
            this.show(data.error);
            return;
        }

        this.show(data.error ?? 'Fehler');
    }

    setState(state) {
        this.state = state;

        this.button.textContent =
            state === 'login'   ? 'Login' :
            state === 'resend'  ? 'Code erneut senden' :
            'Beim Support melden';
    }

    show(msg) {
        this.messages.textContent = msg;
    }

    openSupportChat() {
        SupportChat.open(); // 👈 interner Chat
    }
}
 