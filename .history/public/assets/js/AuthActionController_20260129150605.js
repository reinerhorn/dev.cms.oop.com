class AuthActionController {
    constructor(form) {
        this.form = form;

        this.button =
            form.querySelector('.js-auth-action') ||
            form.querySelector('button[type="submit"]');

        this.messages =
            form.querySelector('[data-form-messages]') ||
            form.querySelector('.form-messages');

        this.form.addEventListener('submit', e => this.handle(e));
    }

    async handle(e) {
        e.preventDefault();

        const formData = new FormData(this.form);

        let response;
        try {
            response = await fetch(this.form.action, {
                method: 'POST',
                body: formData
            });
        } catch (err) {
            this.show('Netzwerkfehler');
            return;
        }

        let data;
        try {
            data = await response.json();
        } catch (err) {
            this.show('Ungültige Server-Antwort');
            return;
        }

        this.processResponse(data);
    }

    processResponse(data) {
        if (data.success) {
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            this.show('Erfolgreich');
            return;
        }

        this.show(data.message || data.error || 'Login fehlgeschlagen');
    }

    show(msg) {
        if (!this.messages) return;
        this.messages.textContent = msg;
    }
}
 