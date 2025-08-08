<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/init.php";
CMSAppFrontend::init();
 
 
// Statt erneutem require_once:
 
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(CMSApp::getLanguage()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CMS DATABASE OOP</title>
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="/css/language_selector.css">
  <link rel="stylesheet" href="/css/navi.css">
  <link rel="stylesheet" href="/css/services.css">
  <link rel="stylesheet" href="/css/admin.css">
  <link rel="stylesheet" href="/css/cards.css">
   <link rel="stylesheet" href="/css/login.css">
    
  <link rel="icon" href="/images/icon/favicon.ico" type="image/x-icon">
 
  <title>HD Staffing Services</title>
  <!-- ... -->
</head>

<body>
 
<header>
<?php echo CMSAppFrontend::getHeaderHtml(); ?> 
<?php 
      echo CMSAppFrontend::getNavigationHtml(  
           CMSAppFrontend::getDb(),
           CMSApp::getLanguage(),
      $_REQUEST['page'] ?? null
);
?>
 </header>
  <main>
  <div class="content">  
    <?php CMSAppFrontend::renderMain(); ?>
</div>
  </main>
 
  <?= CMSAppFrontend::getFooterHtml() ?>
 
</body>
</html>
