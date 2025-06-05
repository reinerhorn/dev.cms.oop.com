<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_a'])) {
    header('Location:/index.php');
} 

$id = "";
$vorname = "";
$nachname = "";
$strasse = "";
$ort ="";
$plz ="";
$telefon ="";
$beruf ="";
$gehalt ="";
$date ="";
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'];
    if ($action == "store") {
        $action = $id == "" ? "add" : "update";
    }
    if ($action == "add" || $action == "update") {
        $vorname = $_POST['vorname'];
        $nachname = $_POST['nachname'];
        $strasse = $_POST['strasse'];
        $ort = $_POST['ort'];
        $plz = $_POST['plz'];
        $telefon = $_POST['telefon'];
        $beruf = $_POST['beruf'];
        $gehalt = $_POST['gehalt'];
        $date = $_POST['geburstag'];
    }

    $connection = getDbConnection();

    try {
        if ($action == "add") {
            $prepared_stmt = $connection->prepare(
                "INSERT INTO plugin_bewerbung (vorname, nachname, strasse, ort, plz, telefon, beruf, gehalt, geburstag) VALUES (?, ?, ?, ? ,? ,?, ?, ?, ?)"
            );
            $prepared_stmt->bind_param("sssssssss", $vorname, $nachname, $strasse, $ort, $plz, $telefon, $beruf, $gehalt, $date);
            $prepared_stmt->execute();
            $result = $connection->query("SELECT id FROM plugin_bewerbung ORDER BY id DESC LIMIT 1");
            if ($page = $result->fetch_assoc()) {
                $id = $page['id'];
            }
        } elseif ($action == "update") {
            $prepared_stmt = $connection->prepare(
                "UPDATE plugin_bewerbung SET vorname=?, nachname=?, strasse=?, ort=?, plz=?, beruf=?, gehalt=?, telefon=?, geburstag=? WHERE id=?"
            );
            $prepared_stmt->bind_param("ssssssssss", $vorname, $nachname, $strasse, $ort, $plz, $beruf, $gehalt, $telefon, $date , $id);
            $prepared_stmt->execute();
        } elseif ($action == "delete") {
            $prepared_stmt = $connection->prepare(
                "DELETE FROM plugin_bewerbung WHERE id=?"
            );
            $prepared_stmt->bind_param("s", $id);
            $prepared_stmt->execute();
            $id="";
            $action = "add";
        } elseif ($action == "edit") {
            $prepared_stmt = $connection->prepare(
                "SELECT * FROM plugin_bewerbung WHERE id=?"
            );
            $prepared_stmt->bind_param("s", $id);
            $prepared_stmt->execute();
            $result = $prepared_stmt->get_result();
            if ($page = $result->fetch_assoc()) {
                $vorname = $page['vorname'];
                $nachname = $page['nachname'];
                $strasse = $page['strasse'];
                $ort = $page['ort'];
                $plz = $page['plz'];
                $telefon = $page['telefon'];
                $beruf = $page['beruf'];
                $gehalt = $page['gehalt'];
                $date = $page['geburstag'];
            }
        }
    } catch (mysqli_sql_exception $e) {
        echo "Fehler bei der Datenbankoperation: " . $e->getMessage();
    }
}
?>
       <div class="flex_container">
        <form id="editor" method="post" action="#">
            <input type="hidden" name="action" value="">
            <input type="hidden" name="id" value="<?php echo $id ?>">
            <label class="label-text" for="vorname">Vorname:</label><br>
            <input type="text" name= "vorname" maxlength="100" value="<?php echo $vorname?>"><br>
            <label class="label-text" for="nachname">Name</label><br>
            <input type="text" name="nachname" maxlength="100" value="<?php echo $nachname?>"><br>
            <label class="label-text" for="strasse">Strasse</label><br> 
            <input type="text" name="strasse" maxlength="100" value="<?php echo $strasse?>"><br>
            <label class="label-text" for="plz">PLZ:</label><br>
            <input type="text" name="plz" maxlength="8" value="<?php echo $plz?>"><br>
            <label class="label-text" for="ort">Ort</label><br>
            <input type="text" name="ort" maxlength="100" value="<?php echo $ort ?>"><br>
            <label class="label-text" for="beruf">Beruf</label><br>
            <input type="text" name="beruf" maxlength="100" value="<?php echo $beruf ?>"><br>
            <label class="label-text" for="gehalt">Gehaltsvorstellung</label><br>
            <input type="text" name="gehalt" maxlength="20" value="<?php echo $gehalt ?>"><br>
            <label class="label-text" for="telefon">Telefon</label><br>
            <input type="text" name="telefon" maxlength="40" value="<?php echo $telefon ?>"><br>
            <label for="geburstag">Geburtsdatum:</label><br>
            <input type="date" id="id_date" name="geburstag" value="<?php echo $date ?>"><br>
            <button onclick="return isSubmitForm('store')">speichern</button>
    </form>
</div>