document.addEventListener('change', function (e) {
    const select = e.target;

    if (!select.matches('select[data-redirect-on-change="1"]')) {
        return;
    }

    const value = select.value;

    if (value === '') {
        window.location.href = window.location.pathname;
    } else {
        window.location.href = window.location.pathname + '?id=' + value;
    }
});
