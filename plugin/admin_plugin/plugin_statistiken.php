 <script src="/function/js/chart.js"></script>
 <script src="/function/js/charts-loader.js"></script>
<?php
if (!isset($_SESSION['admin_a'])) {
	header('Location:/index.php');
}
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/security/auth_helpers.php";
requireAdmin();
// besucher ip   Zeile 3 spalte 3
    $db_connector = getDbConnection();
    $stmt = $db_connector->prepare('SELECT * FROM plugin_besucher');
    $stmt->execute();
    $plugin_result = $stmt->get_result();
    $commentar = '';
    while($visitors_rec = $plugin_result->fetch_assoc()) {
        $commentar .= 'IP:'. ' '. 
        $visitors_rec['ip_address'] . '<br>' . 'Datum:' .' '.
        $visitors_rec['besucherdatum'] . "<br>" . 'Browser:' . ' '.
        $visitors_rec['browser'] ."<br>" . 'Betriebstsytem:' . ' '.
        $visitors_rec['betriebssystem'] ."<br>" . "--------------------------------------" . "<br>";
    }
// besucher Anzahl  Zeile 2 spalte 2
    $stmt = $db_connector->prepare('SELECT COUNT(*) AS count FROM plugin_besucher');
    $stmt->execute();
    $plugin_result = $stmt->get_result();
    $counter ='';
    while($count_rec = $plugin_result->fetch_assoc()) {
      $counter .= 'Besucher Anzahl:'. ' '. 
      $count = $count_rec['count'];
    }
// besucher Anzahl  Zeile 2 spalte 2 und 2
    $stmt = $db_connector->prepare('SELECT * FROM plugin_besucher ORDER BY id');
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array(); 
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        echo "Keine Daten gefunden";
    }
    $stmt = $db_connector->prepare('SELECT *,COUNT(id) as anzahl FROM plugin_besucher GROUP BY besucherdatum ');
    $stmt->execute();
    $chart_result = $stmt->get_result();
      $data = array();
      while ($row = $chart_result->fetch_assoc()) {
        $data[] = $row;
      }

      #$stmt->close();
?>
 
 
<div class="container">
  <div class="column "></div>
  <div class="column "><?php echo $counter ?></div>
<div class="column" ></div>
</div>
<br><br>
<div class="container">
  <div class="column visitorChart">zweite zeile spalte 1
<canvas id="myChart" width="400" height="400"></canvas>
<script>
        // Daten aus PHP in JavaScript übergeben
        var data = <?php echo json_encode($data); ?>;
        // Chart erstellen
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(function(item) {
                    return item.besucherdatum;
                }),
                datasets: [{
                    label: 'Anzahl Besucher und Datum',
                    data: data.map(function(item) {
                        return item.id;
                    }),
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
    </script>
    </div>
      <div class="column visitors_container">zweite zeile  spalte 2
        <canvas id="beChart" width="400" height="400"></canvas>
        <script>
        var ctx = document.getElementById('beChart').getContext('2d');
        var data = <?php echo json_encode($data); ?>;
        var dates = [];
        var visitors = [];
        data.forEach(function(item) {
            dates.push(item.ip_address);
            visitors.push(item.id);
        });
        var chartData = {
            labels: dates,
            datasets: [{
                label: 'Besucherzahlen nach IP',
                data: visitors,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };
        var chartOptions = {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        };
        var myChart = new Chart(ctx, {
            type: 'bar', // 3D-Diagramm wird nicht direkt unterstützt, aber Sie können verschiedene Diagrammtypen in Chart.js ausprobieren, um das gewünschte Erscheinungsbild zu erhalten
            data: chartData,
            options: chartOptions
        });
        </script>
        </div> 
    <div class="column visitors_container ">
    <canvas id="besucherChart" width="400" height="600"></canvas>
    <script>
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

<div class="container">
<div class="column visitors_container"> zweite zeile spalte 1</div> 
<div class="column visitors_container"> zweite zeile spalte 2</div>
<div class="column visitors_container"> <?php echo $commentar ?></div>
</div>

<br><br>
<div class="container">
    <div class="column visitors_container">
<script src="/function/js/charts-loader.js"></script> 
<?php 
$dates = array();
$counts = array();
foreach ($data as $row) {
    $dates[] = $row['ip_address'];
    $counts[] = intval($row['anzahl']);
}
// Diagramm erstellen
$chartData = "['Datum', 'Anzahl',  { role: 'style' }],";
for ($i = 0; $i < count($dates); $i++) {
    $chartData .= "['" . $dates[$i] . "', " . $counts[$i] . ", 'color: #76A7FA'],";
}
?>
<script>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
    var data = google.visualization.arrayToDataTable([
        <?php echo $chartData; ?>
    ]);

    var options = {
        title: 'Benutzeranzahl nach Datum',
        chartArea: {width: '50%', height: '70%'},
        is3D: true,
        hAxis: {title: 'IP Address', minValue: 0},
        vAxis: {title: 'Anzahl'},
        bars: 'vertical'
    };

    var chart = new google.visualization.ColumnChart(document.getElementById('chart_div'));
    chart.draw(data, options);
}
</script>
<div id="chart_div"> </div>
</div>
