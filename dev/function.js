function getOpenElement(){
document.getElementById("show-dialog").addEventListener("click", () => {
    document.getElementById("dialog").showModal();
});
}
function getCloseElement(){
document.getElementById("close-dialog").addEventListener("click", () => {
    document.getElementById("dialog").close();
});
}