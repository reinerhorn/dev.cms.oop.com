function go(url){
  window.location.href = '/index.php?page=' + url;
}

function initSessionPopup(evt) {
  if(!localStorage.getItem('accept_session') && !sessionStorage.getItem('accept_session')) {
    setCookieBannerVisible(true);
  } else {
    setCookieBannerVisible(false);
  }
}

function setCookieBannerVisible(isVisible) {
  let popup = document.getElementById("cookie-popup");
  popup.style.display = isVisible ? "block" : "none";
}

function acceptSession(isAcceptSession) {
  if(isAcceptSession) {
    localStorage.setItem("accept_session", "true");
    let url = '/function/init_session.php';
    let callbackAction = function(responseText, status, responseUrl) {
      console.log(responseText);
    };
    performXmlHttpGetRequest(url, callbackAction);
  } else {
    sessionStorage.setItem("accept_session", "false");
  }
  setCookieBannerVisible(false);
}

function performXmlHttpGetRequest(url, callbackAction) {
  var httpRequest = new XMLHttpRequest();
  httpRequest.onreadystatechange = function() {
    if (this.readyState == 4) {
      if(this.status == 200) {
          callbackAction(this.responseText, this.status, this.responseURL);
      } else if(this.status == 404) {
        console.log('WARN HTTP error 404');
      }
    }
  };
  httpRequest.open("GET", url, true);
  httpRequest.send();
}

window.addEventListener("load", initSessionPopup); 