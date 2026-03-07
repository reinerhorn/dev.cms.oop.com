document.addEventListener('change', function (e) {
    const select = e.target;

    if (!select.matches('select[data-redirect-on-change="1"]')) {
        return;
    }

    const value = select.value;
    const url = new URL(window.location.href);

    if (value === '') {
        url.searchParams.delete('id');
    } else {
        url.searchParams.set('id', value);
    }

    window.location.href = url.toString();
});
