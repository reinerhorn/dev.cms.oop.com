<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/security/UserRoleManager.php';
$userData = CMSLoginSession::handleUserAction($_POST);
if (isset($_SESSION['user_id'])) {
    $db = CMSAppFrontend::getDb(); // Oder getDbConnection() je nach Struktur
    $permissionGate = new UserRoleManager($db, $_SESSION['user_id']);
    if (method_exists($permissionGate, 'getAllPermissions')) {
        $_SESSION['permissions'] = $permissionGate->getAllPermissions();
    }
}
    $id = "";
    $password = isset($_REQUEST['password']) ? $_REQUEST['password'] : "";
    $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : "";
    
 if (!empty($_SESSION['user_id'])) {
    $permissionGate = new UserRoleManager($db, $_SESSION['user_id']);
    $_SESSION['permissions'] = $permissionGate->getAllPermissions();
} 

?>
<div class="flex_container">
    <div id="login-button login-button-text">
    <button class="button" onclick="location.href='?page=1692886141';">Datenschutzerklärung</button>
    <button class="button" onclick="location.href='?page=1692882619';">AGB</button>
</div>
<div class="login-box">
  <div class="login-form">
      <h2> LoginSysten</h2>
    <form method="post">
      <div class="group">
        <input type="text" id="email" name="email" value="<?php echo $email ?>" required>
        <label for="email">E-Mail-Adresse</label>
        <span class="bar"></span>
        <span class="highlight"></span>
      </div>
      <div class="group">
        <input type="password" id="password" name="password" value="<?php echo $password ?>" required>
        <label for="password">Passwort</label>
        <span class="bar"></span>
        <span class="highlight"></span>
      </div>
      <div class="agb-wrapper">
        <input class="custom-checkbox" type="checkbox" id="agree" name="agree" value="ich stimme zu" required>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agree']) && $_POST['agree'] === 'ich stimme zu') {
            // Checkbox is checked, process the form
            $agree = true;
        } else {
            // Checkbox is not checked, show an error message
            $agree = false;
        }
        if (!$agree) {
            echo '<p class="error">Bitte stimmen Sie der Datenschutzerklärung / AGB zu.</p>';
        }
            
        ?>
         
      </div>
      <div class="button-wrapper">
  <button class="button" type="submit">🔐 Einloggen</button>
</div>
  <?php if (!empty($userData['message'])): ?>
            <div class="alert"><?php echo $userData['message']; ?></div>
          <?php endif; ?>
   </form> 
  </div>
</div></div>