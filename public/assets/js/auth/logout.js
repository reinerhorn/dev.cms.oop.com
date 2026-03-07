document.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('[data-auth="logout"]');
    if (!btn) return;

    btn.addEventListener('click', async (e) => {
        e.preventDefault();

        const response = await fetch('/ajax/logout.php', {
            method: 'POST'
        });

        let result;
        try {
            result = await response.json();
        } catch {
            alert('Ungültige Server-Antwort');
            return;
        }

        if (result.success && result.redirect) {
            window.location.href = result.redirect;
        } else {
            alert(result.message || 'Logout fehlgeschlagen');
        }
    });
});
