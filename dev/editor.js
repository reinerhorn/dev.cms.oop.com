function getTinyMceBody() {
    let iframe = document.getElementsByTagName('iframe')[0];
    let doc = iframe.document || iframe.contentDocument || iframe.contentWindow.document;
    let body = doc.body;
    return body;
}
function resetTinyMce() {
    getTinyMceBody().innerHTML = "";
}

function submitEditor() {
    //let content = getTinyMceBody().innerHTML;
    let form = document.forms['editor'];
    form.text.value = content;
    form.action.value = form.action.id != '' ? 'add' : 'update';
    form.submit();
}



function resetForm(form) {
    let elements = form.elements;
    for(let element of elements) {
        //console.log(element.nodeName);
        let nodename = element.nodeName.toUpperCase();
        let type = element.type.toUpperCase();
        switch(nodename) {
            case 'SELECT' : element.selectedIndex = 0; break;
            case 'TEXTAREA':;
            case 'INPUT' : 
                if(type == ('CHECKBOX' || 'RADIO')) {
                    element.checked = false;
                } else {
                    let _default = element.getAttribute('data-default');
                    element.value = _default ? _default : '';
                }
                break;
            default: break;
        }
    }
}

// submitForm();
// submitForm('add');
// submitForm('update', ['feld1', 'feld2'])
function isSubmitForm(action, message_fields) {
    // weise der Variable 'form' das HTMLFormElement zu, das getForm() zurückgibt
    let form = getForm();
    // wenn variable action 'delete' ist
    if(action == "delete") {  
        // erstelle Variable message mit folgendem Inhalt
        let message = 'Soll dieser Eintrag gelöscht werden?';
        // wenn die Variable 'message_fields' existiert/einen Wert hat
        if(message_fields) {
            // fuege an den Inhalt der Variable message zwei Leerzeilen an
            message += '\n\n';
            // gehe durch alle Array-Inhalte von message_fields
            for(let field of message_fields) {
                // lese das Formularfeld[variable->field] aus
                // und haenge den Wert an den Inhalt der Variable
                // message an
                message += form[field].value + ' ';
            }
            // entferne voranstehende und anhaengende Leerzeichen
            // aus dem Inhalt der Variable 'message'
            message = message.trim();
        }
        // weise der Variable 'is_delete' den Rückgabewert der
        // Abfragefunktion 'confirm' zu (BOOLscher Wert true/false)
        let is_delete = confirm(message);
        // wenn also NICHT bestaetigt wurde (false)
        if(!is_delete) {
            // dann steige aus der Funktion aus und tu nichts
            // verbiete den Versand des Formulars
            return false;
        }
    }
    // ansonsten schreibe die festgelegte Aktion (action)
    // in das Formularfeld 'action'
    form.action.value = action;
    // erlaube den Versand des Formulars
    return true;
}

function getForm() {
    return document.forms['editor'];
}

function selectRecord() {
    let form = getForm();
    let id = form.record_selection.options[form.record_selection.selectedIndex].value;
    if(id == "") {
        return false;
    } else if(id == "neu") {
        let fields = form.getElementsByTagName('input'); 
           
        for(let field of fields) {
            if(field.type == "text" ||                 
                field.type == "password" ||                 
                field.type == "hidden") {
                    field.value = "";
            }
            if(field.name == "language") {
                field.removeAttribute("readonly");
                field.removeAttribute("style");
            }
        }
        return false;
    } else {
        form.id.value = id;
        if(isSubmitForm("edit")) {
            form.submit();
        };
    }
}