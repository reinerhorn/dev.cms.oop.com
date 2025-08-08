function submitForm(selector) {
   
    let index = selector.selectedIndex;
    if (index != 0) {        
        let lang = selector.options[index].value;        
        localStorage.setItem('site_language', lang);        
        selector.form.submit();
    }
}

/*
    Verwendet in Sprachwahlmenue
*/
function setSiteLanguage(lang) {
    localStorage.setItem('site_language', lang);
}

window.addEventListener('load', function(evt){
    // wenn noch keine Sprache gewaehlt wurde
    if(!localStorage.getItem('site_language')) {
        // browser sprache ermitteln
        let browser_language = navigator.language.split('-')[0];
        // in local storage setzen
        this.localStorage.setItem('site_language', browser_language);
    }
    let anchors = document.getElementsByTagName('a');
    for(let anchor of anchors) {        
        let href = anchor.getAttribute("href");
        if(href) {
            let has_parameters = href.indexOf('?') != -1;
            let lang = localStorage.getItem('site_language');
            href = href + (has_parameters ? "&" : "?") + "lang=" + lang;
            anchor.setAttribute('href', href);           
        }
    }
    let forms = document.getElementsByTagName('form');
    for(let form of forms) {
        let lang = document.createElement('input');
        lang.type = 'hidden';
        lang.name = 'lang';
        lang.value = localStorage.getItem('site_language');
        form.appendChild(lang);
    }
});