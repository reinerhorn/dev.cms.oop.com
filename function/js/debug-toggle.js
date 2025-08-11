console.log('🔌 Debug-Toggle-Script geladen');

document.addEventListener('DOMContentLoaded', () => {
    const toggles = document.querySelectorAll('input[type="checkbox"][data-toggle-key]');

    if (toggles.length === 0) {
        console.warn("⚠️ Kein Debug-Toggle gefunden.");
        return;
    }

    toggles.forEach(toggle => {
        toggle.addEventListener('change', async (e) => {
            const isChecked = e.target.checked;
            const key = e.target.dataset.toggleKey || e.target.name;
            const endpoint = e.target.dataset.endpoint || '/function/post_toggle_flag.php';

            console.log(`🔄 Umschalten erkannt für ${key}: ${isChecked ? 'on' : 'off'}`);

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        key: key,
                        value: isChecked ? 'on' : 'off'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    console.log(`✅ ${key} ist jetzt ${data.state ? 'aktiviert' : 'deaktiviert'}`);
                    location.reload();
                } else {
                    console.warn(`❌ Fehler beim Umschalten für ${key}: ${data.message}`);
                    alert(`Fehler: ${data.message}`);
                    e.target.checked = !isChecked; // zurücksetzen bei Fehler
                }
            } catch (error) {
                console.error(`🚨 Verbindungsfehler bei ${key}:`, error);
                alert("⚠️ Netzwerkfehler beim Speichern des Schalters.");
                e.target.checked = !isChecked; // zurücksetzen bei Netzwerkfehler
            }
        });
    });
});
