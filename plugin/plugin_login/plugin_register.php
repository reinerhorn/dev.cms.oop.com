 
<?php
$userData = CMSLoginSession::handleUserAction($_POST);

if (isset($userData['error'])) {
    $userData['message'] = $userData['error'];
} elseif (isset($userData['success'])) {
    $userData['message'] = $userData['success'];
}
if (isset($userData['message'])) {
    $userData['message'] = htmlspecialchars($userData['message']);
} else {
    $userData['message'] = '';
}
?>


 
<div class="flex_container">
    <div id="login-button login-button-text">
        <button class="button" onclick="location.href='?page=1692886141';">Datenschutzerklärung</button>
        <button class="button" onclick="location.href='?page=1692882619';">AGB</button>
    </div>

    <div class="login-box">
        <div class="login-form">
            <form name="editor" method="post" action="">
                <input type="hidden" name="action" value="store">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($userData['id'] ?? ''); ?>">

                <div class="group">
                    <input type="text" id="email" name="email" maxlength="100" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                    <span class="highlight"></span>
                    <span class="bar"></span>
                    <label for="email">E-Mail-Adresse</label>
                </div>

                <div class="group">
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" required>
                    <span class="highlight"></span>
                    <span class="bar"></span>
                    <label for="username">Username</label>
                </div>

                <div class="group">
                    <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($userData['password'] ?? ''); ?>" required>
                    <span class="highlight"></span>
                    <span class="bar"></span>
                    <label for="password">Password</label>
                </div>

                <div class="group">
                    <input type="password" id="password_repeat" name="password_repeat" required>
                    <span class="highlight"></span>
                    <span class="bar"></span>
                    <label for="password_repeat">Passwort wiederholen</label>
                </div>

                <div class="group">
                    <div class="agb-wrapper">
                        <input class="custom-checkbox" type="checkbox" id="agree" name="agree" value="ich stimme zu" required>
                        <label for="agree">Ich stimme der Datenschutzerklärung / AGB zu</label>
                    </div>
                </div>

                <div class="submit-button-row" style="display: flex; gap: 10px;">
                    <button type="submit">Registrieren</button>
                    <button type="button" onclick="location.href='?page=1692888607';">Bereits registriert? Hier einloggen</button>
                </div>
            </form>
 
          <?php if (!empty($userData['message'])): ?>
            <div class="alert"><?php echo $userData['message']; ?></div>
          <?php endif; ?>
 
           
        </div>
    </div>
</div>