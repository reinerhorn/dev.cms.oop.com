document.addEventListener('click', async (e) => {

    const btn = e.target.closest('[data-resend-verify]');
    if (!btn) return; // ❗ nur dieser Button

    const form = btn.closest('form');
    if (!form) return;

    const emailInput = form.querySelector('input[name="email"]');
    if (!emailInput || !emailInput.value) {
        alert('Bitte zuerst deine E-Mail-Adresse eingeben.');
        return;
    }

    btn.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                intent: 'resend_verify',
                email: emailInput.value
            })
        });

        const data = await response.json();

        alert(
            data.success
                ? (data.message ?? 'E-Mail wurde erneut gesendet.')
                : (data.error ?? 'Fehler beim Senden')
        );

    } catch (err) {
        alert('Netzwerkfehler – bitte später erneut versuchen.');
    } finally {
        btn.disabled = false;
    }
});