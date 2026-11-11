// Logic for login.html and register.html

const form = document.getElementById("auth-form");
const errorEl = document.getElementById("error");
const submitBtn = form.querySelector("button[type='submit']");
const isRegister = form.dataset.mode === "register";
const submitText = submitBtn.textContent;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Already logged in? Go straight to the app.
if (Auth.getToken()) {
    window.location.replace("index.html");
}

function showError(message) {
    errorEl.textContent = message;
    errorEl.hidden = !message;
}

function value(id) {
    const field = document.getElementById(id);
    return field ? field.value : "";
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    showError("");

    const name = value("name").trim();
    const email = value("email").trim();
    const password = value("password");

    if (isRegister && !name) return showError("Please enter your name.");
    if (!EMAIL_PATTERN.test(email)) return showError("Please enter a valid email address.");
    if (isRegister) {
        if (password.length < 8) return showError("Password must be at least 8 characters.");
        if (password !== value("confirm")) return showError("Passwords do not match.");
    } else if (!password) {
        return showError("Please enter your password.");
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Please wait…";

    try {
        if (isRegister) {
            await Api.register(name, email, password);
        }
        const data = await Api.login(email, password);
        Auth.save(data.token, data.user);
        window.location.replace("index.html");
    } catch (err) {
        showError(err.message);
        submitBtn.disabled = false;
        submitBtn.textContent = submitText;
    }
});
