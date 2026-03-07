document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[data-auth="login"]');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const response = await fetch('/ajax/login.php', {
            method: 'POST',
            body: new FormData(form)
        });

        let result;
        try {
            result = await response.json();
        } catch {
            showError('Ungültige Server-Antwort');
            return;
        }

        if (result.success && result.redirect) {
            window.location.href = result.redirect;
        } else {
            showError(result.message || 'Login fehlgeschlagen');
        }
    });
});

function showError(message) {
    const box = document.querySelector('[data-auth-error]');
    if (box) {
        box.textContent = message;
        box.style.display = 'block';
    } else {
        alert(message);
    }
};
