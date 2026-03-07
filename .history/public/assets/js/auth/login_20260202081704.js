document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#login-form');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        fetch('/ajax/login.php', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success === true) {
                window.location.href = res.redirect;
            } else {
                showError(res.message ?? 'Login fehlgeschlagen');
            }
        })
        .catch(() => {
            showError('Server nicht erreichbar');
        });
    });
});
