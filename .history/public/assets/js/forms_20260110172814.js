document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.classList.contains('js-form')) return;

    const mode = form.dataset.mode || 'post';

    if (mode !== 'ajax') return; // normales POST zulassen

    e.preventDefault();

    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'Accept': 'application/json' }
    });

    const data = await response.json();

    if (data.success && data.redirect) {
        window.location.href = data.redirect;
    } else if (data.error) {
        alert(data.error);
    }
});