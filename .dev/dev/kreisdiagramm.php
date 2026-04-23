 
<?php 
include $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
$conn = getDbConnection();
$sql = "SELECT SUM(ip_address) as gesamt, YEAR(besucherdatum) as jahr  FROM plugin_besucher GROUP BY jahr ORDER BY id";
$result = $conn->query($sql);
$data = array();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Besucherstatistik</title>
    <script src="/function/js/chart.js"></script>
</head>
<body>
    <div style="width: 400px; height: 400px;">
        <canvas id="besucherDiagramm"></canvas>
    </div>

    <script>
        var ctx = document.getElementById('besucherDiagramm').getContext('2d');
        var data = <?php echo json_encode($data); ?>;

        var labels = [];
        var values = [];

        data.forEach(function(item) {
            labels.push(item.jahr);
            values.push(item.gesamt);
        });

        var chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                    ],
                }],
            },
        });
    </script>
</body>
</html>