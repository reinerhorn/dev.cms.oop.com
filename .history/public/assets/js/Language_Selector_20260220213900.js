document.addEventListener("DOMContentLoaded", function () {
  const selector = document.getElementById("LanguageSelector");
  if (!selector) return;

  const button = selector.querySelector(".language-button");
  const list = selector.querySelector(".language-list");
  const items = selector.querySelectorAll(".language-option");

  // mousedown innerhalb des Selectors stoppen (verhindert Aufblitzen)
  selector.addEventListener("mousedown", function (e) {
    e.stopPropagation();
  });

  if (!button || !list || items.length === 0) return;

  // Dropdown öffnen / schließen (nur bei echtem Button-Klick)
  button.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    selector.classList.toggle("open");
  });

  // Sprache auswählen
  items.forEach(function (item) {
    item.addEventListener("click", function () {
      const lang = item.getAttribute("data-lang");
      if (!lang) return;

      fetch("/ajax/set_language.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({ language: lang })
      })
        .then(function (res) {
          if (!res.ok) {
            throw new Error("Language not set");
          }
        })
        .then(function () {
          const path = window.location.pathname.split("/").filter(Boolean);

          // Wenn erstes Segment wie ein Sprachcode aussieht → ersetzen
          if (path[0] && /^[a-zA-Z-]{2,5}$/.test(path[0])) {
            path[0] = lang;
          } else {
            path.unshift(lang);
          }

          window.location.href = "/" + path.join("/");
        })
        .catch(function (err) {
          console.error("Language selector error:", err);
        });
    });
  });

  // Klick außerhalb → Dropdown schließen (auf mousedown, stabiler)
  document.addEventListener("mousedown", function (e) {
    if (!selector.contains(e.target)) {
      selector.classList.remove("open");
    }
  });
});
