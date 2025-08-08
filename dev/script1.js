document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById('visitorChart').getContext('2d');

    // AJAX-Anfrage, um Daten vom Server zu erhalten
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'getData.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            var dates = [];
            var visitorCounts = [];

            // Daten verarbeiten
            data.forEach(function(entry) {
                dates.push(entry.date);
                visitorCounts.push(entry.visitors);
            });

            // Chart.js-Diagramm erstellen
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Besucher',
                        data: visitorCounts,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    };
    xhr.send();
});