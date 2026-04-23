<!--  ===================================================================
	  Urheberrechtshinweis / Copyright

	  Die Gestaltung, Inhalte und Programmierung dieser Seiten
	  unterliegen dem Urheberrecht. Urheber ist Reiner Horn
	  Eine Verwendung der Inhalte außerhalb der vom Urheber betriebenen
	  Domains ist nicht gestattet. Ein Verstoß gegen diese Bestimmungen
	  wird als Urheberrechtsverletzung betrachtet und bei Bekanntwerdung 
	  unter Einsatz von Rechtsmitteln geahndet.
      Verwndung von der leeren datenbank und code muss eine genehmigung
      des Urhebers eingeholt werden.
      Die Datenbank und der Code sind urheberrechtlich geschützt.
      Die Verwendung der Datenbank und des Codes ist nur mit
      ausdrücklicher Genehmigung des Urhebers gestattet.
      Die Datenbank und der Code dürfen nicht ohne Genehmigung
      des Urhebers kopiert, verbreitet oder veröffentlicht werden.

	 Reiner Horn
	 Huaptstr. 8
	 40597 Düsseldorf
     horm.it@t-online.de
===================================================================  -->
<style>
 .flex-box {
	display:flex; 
}
.container {
  display: flex;
}
.column {
  flex: 1;
  padding: 10px;
  text-align: center;
}
div.visitors_container { 
    bottom: 0;
    text-align: center;
    height: 400px;
    overflow: scroll;
    border: 1px solid #053beb; 
    padding: 10px;
  }
  .visitorChart { 
    font-family: Arial, sans-serif;
    text-align: center;
  
    height: 400px;
    color:black;
    overflow: scroll;
    padding: 10px;
   }
</style>
<script src="/function/js/chart.js"></script>   

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>3D Besucherstatistik</title>
    
</head>

<body>
<div class="container">
  <div class="column visitorChart">  
    
    <canvas id="besucherChart" width="400" height="400"></canvas>

    <script>
        // Daten von getData.php abrufen
        fetch('/function/php/getData.php')
            .then(response => response.json())
            .then(data => {
                var dates = [];
                var visitors = [];

                data.forEach(record => {
                    dates.push(record.datum);
                    visitors.push(record.besucherzahl);
                });

                // 3D-Kreisdiagramm erstellen
                var ctx = document.getElementById('besucherChart').getContext('2d');
                var myChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Besucherstatistik',
                            data: visitors,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.2)',
                                'rgba(54, 162, 235, 0.2)',
                                'rgba(255, 206, 86, 0.2)',
                                'rgba(75, 192, 192, 0.2)',
                                'rgba(153, 102, 255, 0.2)',
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)',
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                            },
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'xy',
                            intersect: false,
                        },
                        elements: {
                            arc: {
                                borderWidth: 0
                            }
                        },
                        scales: {
                            x: {
                                display: false,
                            },
                            y: {
                                display: false,
                            }
                        }
                    }
                });
            });
    </script>
    </div>
</div>
</body>
</html>
