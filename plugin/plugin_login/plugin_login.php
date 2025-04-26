<?php 
    $id = "";
    $password = isset($_REQUEST['password']) ? $_REQUEST['password'] : "";
    $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : "";;
?>
<div class="flex_container">
    <div id="login-button login-button-text">
    <button class="button" onclick="location.href='?page=1692886141';">Datenschutzerklärung</button>
    <button class="button" onclick="location.href='?page=1692882619';">AGB</button>
</div>
    <div class="box">
    <form name="editor" method="post" action="">
    <div class="group">
    <input type="text" id="email" name="email" value="<?php echo $email?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="email">E-Mail-Adresse</label>
    </div>
    <div class="group">
    <input type="password" id="password" name="password" value="<?php echo $password?>" required>
        <span class="highlight" value="true"></span>
        <span class="bar" value="true"></span>
        <label type="text" for="password">Password</label>
    </div>
    <div class="group">
        <label type="text" for="checkbox">Ich stimme der Datenschutzerklärung / AGB zu</label>  
    </div>
        <input class="custom-checkbox"  type="checkbox" name="agree" value="ich stimme zu" required>
    <br>
    <button class="button" >Senden</button>
    </form>
    </div> 
<div>