function toggleTheme(){
    let theme = localStorage.getItem("theme");

    if(theme === "dark"){
        document.body.classList.remove("dark");
        localStorage.setItem("theme","light");
    } else {
        document.body.classList.add("dark");
        localStorage.setItem("theme","dark");
    }
}

window.onload = () => {
    if(localStorage.getItem("theme") === "dark"){
        document.body.classList.add("dark");
    }
};
