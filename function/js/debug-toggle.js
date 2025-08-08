console.log('Debug-Toggle-Script geladen');

document.addEventListener('DOMContentLoaded', () => {
    const toggles = document.querySelectorAll('input[type="checkbox"][data-toggle-key]');
    toggles.forEach(toggle => {
        toggle.addEventListener('change', () => {
            const key = toggle.getAttribute('data-toggle-key') || toggle.name;
            const value = toggle.checked ? 'on' : 'off';

            console.log(`Umschalten erkannt für ${key}: ${value}`);

            const formData = new FormData();
            formData.append('key', key);
            formData.append('value', value);

            fetch('/function/post_toggle_flag.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`${key} ist jetzt ${data.state ? 'aktiviert' : 'deaktiviert'}`);
                } else {
                    console.warn(`Fehler beim Umschalten für ${key}:`, data.message);
                }
            })
            .catch(error => {
                console.error(`Verbindungsfehler bei ${key}:`, error);
            });
        });
    });
});
