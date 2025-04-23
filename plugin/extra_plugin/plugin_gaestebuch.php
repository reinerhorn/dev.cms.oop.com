<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";   
$connection = getDbConnection(); 
$id = "";
$username = "";
$email = "";
$text = "";
$connection = getDbConnection();
try { 
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'];
    if ($action == "store") {
        $action = $id == "" ? "add" : "update";
    }
    if ($action == "add" || $action == "update") {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $text = $_POST['text'];
    }  
    if ($action == "add") {
        $prepared_stmt = $connection->prepare(
            "INSERT INTO kommentar (username, email, text) VALUES (?, ?, ?)");
        $prepared_stmt->bind_param("sss", $username, $email, $text);
        $prepared_stmt->execute();
        $plugin_result = $connection->query("SELECT id FROM kommentar ORDER BY id DESC LIMIT 1");
        if ($page = $plugin_result->fetch_assoc()) {
            $id = $page['id'];
        }
    } elseif ($action == "update") {
        $prepared_stmt = $connection->prepare(
            "UPDATE kommentar SET username=?, email=?, text=? WHERE id=?"
        );
        $prepared_stmt->bind_param("ssss", $username, $email, $text, $id);
        $prepared_stmt->execute();
    } elseif ($action == "delete") {
        $prepared_stmt = $connection->prepare(
            "DELETE FROM kommentar WHERE id=?"
        );
        $prepared_stmt->bind_param("s", $id);
        $prepared_stmt->execute();
        $id = "";
        $action = "add";
    } elseif ($action == "edit") {
        $prepared_stmt = $connection->prepare(
            "SELECT * FROM kommentar WHERE id=?"
        );
        $prepared_stmt->bind_param("s", $id);
        $prepared_stmt->execute();
        $plugin_result = $prepared_stmt->get_result();
        if ($page = $plugin_result->fetch_assoc()) {
            $username = $page['username'];
            $email = $page['email'];
            $text = $page['text'];
        }
    }
}
} catch(Error $err){  
    echo PHP_EOL . '<br><b>ERROR: ' . $err . '</b>'; 
}
#$connection->close();
?>
<style>
    .input_color{
    color:black;
    }
    </style>
<form name="editor" action="" method="post">
    <input type="hidden" name="action" value="">
    <input type="hidden" name="id">
    <div class="group">
    <input class="input_color" type="text" id="email" name="email" value="<?php echo $email?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="email">Email</label><br>
    </div>
    <div class="group">
    <input class="input_color type="text" id="username" name="username" value="<?php echo $username?>" required><br>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="username">Username</label>
    </div>
    <div class="group">
    <textarea id="text" cols="50" rows="10"> <?php echo $text?></textarea>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="text">Schreibe deine Nachricht</label>
        </div>
<button onclick="this.form.elements['action'].value='store'" type="submit">Senden</button>
</form>