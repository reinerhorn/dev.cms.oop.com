fetch('/ajax/login.php', {
    method: 'POST',
    body: new FormData(form)
})
.then(r => r.json())
.then(res => {
    if (res.success) {
        window.location.href = res.redirect;
    } else {
        showError(res.message);
    }
});
