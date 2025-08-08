 
// AJAX-Anfrage an die PHP-Skript-Datei senden
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    const userInput = document.getElementById('user_input').value;
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'plugin_chatgpt.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            displayChatResponse(response.choices[0].text);
        }
    };
    xhr.send('user_input=' + encodeURIComponent(userInput));
});

// Anzeige der Chatantworten im Chatfenster
function displayChatResponse(responseText) {
    const chatbox = document.getElementById('chatbox');
    const chatResponse = document.createElement('p');
    chatResponse.textContent = responseText;
    chatbox.appendChild(chatResponse);
}