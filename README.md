
## 📌 Lizenz

**Proprietär / Open Source?** 
 
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
 

## 📞 Support / Kontakt

Für Fragen, Anregungen oder individuelle Anpassungen:  
✉️ horn.it@t-online.de

---

# CMS-OOP

Ein leichtgewichtiges, objektorientiertes CMS-Projekt auf Basis von **PHP 8.4**, entwickelt speziell für kleine und mittelständische Unternehmen. Ziel ist es, eine vollständig einsatzbereite Lösung zu bieten – **ohne Baukastensysteme** wie WordPress oder umfangreiche Programmierkenntnisse.

---

## ✨ Zielsetzung

Dieses Projekt wurde mit dem Anspruch entwickelt, Unternehmen eine professionelle Weblösung zu bieten, **ohne dass PHP-Kenntnisse erforderlich sind**. Inhalte und Struktur lassen sich vollständig über ein benutzerfreundliches **Admin-Interface** verwalten.

---

## 🔧 Technologien

- PHP 8.4 (OOP-Basis)
- Kein Framework, keine CMS-Abhängigkeiten
- Eigenes Admin-Panel
- Modul- und Plugin-Architektur

---

## 🎯 Features

- **Adminbereich** für die gesamte Konfiguration
- **Button-Generator** für wiederverwendbare UI-Komponenten
- **Formular-Generator** zum schnellen Erstellen individueller Formulare
- **Editoren** für Header, Footer und Seiteninhalte
- **Seitenkonfiguration** mit Mehrsprachigkeit
- **PluginValidator** zur Prüfung aktiver Plugins
- **RolePermissionManager** für Benutzerrollen und Rechteverwaltung
- **Dynamische Navigation** mit Haupt- und Untermenüs
- **Kein PHP-Wissen notwendig** – Anpassung erfolgt primär über CSS

---

## 🎨 Gestaltung & Anpassung

Die **gesamte Optik basiert auf CSS** – keine komplizierten Themes oder Templates notwendig. Jeder Seitenbetreiber kann die Darstellung nach den eigenen Bedürfnissen gestalten. Die Struktur ist bewusst einfach gehalten, damit auch Einsteiger ihr Layout anpassen können.

---

## 🚀 Einsatzbereit

Das CMS ist sofort einsetzbar. Hochladen, konfigurieren, loslegen.

> **Hinweis:** Es wird empfohlen, grundlegende CSS-Kenntnisse mitzubringen, um die Seite individuell zu gestalten.

---

## 📂 Projektstruktur (Beispiel)

 dev.cms-oop.com/
    ├──  Index.php        
    ├──  init.php 
	├──  CMSApp.php
	├── .htaccess
	├── .gitignore
	├── robots.txt
	├── README.md
	├── README
	├── /.env/ ← geschützt, unterordner eingebunden 
	│	 	└── /conf/
	│	 			├──	 h-d_config.php    Für den PHPMAULER conf	│	 			    
                    └── cms_oop-config   Für die config.onc.php den ein binden
	├── .vscode/    
    │     └── settings.json.      
	│ 	 
	├── /ajax/   
    │     └── ajax_header_upload.php
	│ 	 
	├── /assets/ 
    │   └── css/
    │        └── ui-components.css
    │
	├── /config/ 
    │   	└── config.inc.php
 	│
	├── /css/ 
	│	 	├──	 admin.css
	│ 	    ├──	 card.css
	│ 	 	├──	 language_selector.css
	│ 	 	├──	 navi.css
	│ 	 	├──	 services.css
	│ 		└── style.css
    │         
	├── /cache/
    │    └── twig/
    │   		 └──  leer
	├── /class
    │   ├── session.php   
    │   ├── web_besucher.php 
	│ 	 │  
    │    ├── /helper/ 
	│	 │		├──	 ButtonGenerator.php
	│ 	 │      ├──	 IdGenerator.php
	│ 	 │		├──	 PluginValidator.php
	│ 	 │		└── SelectGenerator.php
  	│    ├── /admin/ 
	│	 │		├──	 admin_editor_handler.inc.php
	│ 	 │      ├──	 CMSAdminSession.php.
	│ 	 │		├──	 HeaderFooterManager.php
	│ 	 │		└── HeaderUploadHandler.php│
	│    │
	│ 	 ├── /menber/ 
	│	 │		├──	 MemberProfile
	│ 	 │  	└── UserProfile.php	
	│ 	 │  	│ 
	│ 	 ├── /navi/ 
	│ 	 │		└── navi.inc.php
	│ 	 │	
	│ 	 ├── /repository/
	│ 	 │		└── ToggleFlagRepository.php
	│	 │
	│ 	 └── /security/ 
	│	 		├──	 PageIntegrityChecker.php
	│ 	    	└── UserRoleManager.php
	│ 
	├── /fonts/ 
	│	 	├── greatvibes/
	│		│		├──	 GreatVibes-Regular.ttf
	│		│		└── GreatVibes-Regular.woff2
	│		└── robotocondensed/	
 	│						├──	 RobotoCondensed-Bold.ttf
	│		      			├──	 RobotoCondensed-Bold.woff2
	│						└──  usw
	├── /function/
    │	 	├── /js/
	│		│		├──	 chart.js
	│		│		├──	 charts-loader.js
	│		│		├──	 debug-toggle.js
	│		│		└── language_selector.js
	│		│
	│		├── formular_generator.php	
	│		├──	 getData.php
	│		│──	 handle_page_editor_function.inc.php
	│		├──	 handle_plaintext_editor_function.inc.php
	│		├──	 language_selector.inc.php
	│		└── post_toggle_flag.php
	│ 
	├── /images/
	│		│ 	├── hd-logo.svg
	│ 		│ 	└── hd-logo.webp
	│ 		│ 
	│   	├── /flaggen/
	│		├── /icon/
	│		├── /socialmedia/
	│		├── /uploads/
	│		└── usw.
	│	 	 	
	├── /inc/
    │   ├── logout.log  
    │   ├── session.php   
    │   ├── web_besucher.php     
    │   └── plugin_admin_roles.php		
    │ 	   
	├── /plugin/
	│ 	 ├── admin_plugin/ 
	│ 	 │         ├── plugin_admin_card_editor.php 
	│ 	 │         ├── plugin_admin_footer_editor.php
	│ 	 │         ├── plugin_admin_footer_images.php
	│ 	 │         ├── plugin_admin_header_footer_editor.php 
	│ 	 │         ├── plugin_admin_formular_editor.php
	│ 	 │         ├── plugin_admin_page_editor.php 
	│ 	 │         └── 	plugin_admin_roles.php
	│ 	 │
    │ 	 ├── /extra_plugin/
	│ 	 ├── /plugin_cards  
    │ 	 ├── /plugin_login
    │ 	 ├──  /plugin_member 
    │ 	 ├──  /plugin_shop 
 	│ 
    ├── /templates/
  	│    	├── debug_mode.tpl.php
	│ 		│ 
  	│    	├── /layout/
	│    	│   ├──  base.twig        ← Grundlayout
	│		│ 	├── header.twig      ← Header-Partial
	│    	│   ├── footer.twig      ← Footer-Partial
	│		│   └──  navigation.twig  ← Navigation
	│    	└── /page/
	│				├── start.twig       ← Startseite.   
	│    			└── plugin_plaintext.twig
	│ 
	└──  /protected/  
 				 └── verify.php

# dev.cms.oop.com
