/* ==============================
   Water Bill Management System
   Main JavaScript File
============================== */

// Show alerts (success/error)
function showAlert(message, type = "success") {
    const alertBox = document.createElement("div");
    alertBox.className = "alert " + (type === "success" ? "alert-success" : "alert-error");
    alertBox.innerText = message;

    document.body.prepend(alertBox);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        alertBox.remove();
    }, 3000);
}

// Simple form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener("submit", function (event) {
        let valid = true;
        const inputs = form.querySelectorAll("input[required], select[required]");

        inputs.forEach(input => {
            if (input.value.trim() === "") {
                input.style.border = "2px solid red";
                valid = false;
            } else {
                input.style.border = "1px solid #ccc";
            }
        });

        if (!valid) {
            event.preventDefault();
            showAlert("Please fill in all required fields.", "error");
        }
    });
}

// Toggle password visibility
function togglePassword(inputId, toggleBtnId) {
    const passwordInput = document.getElementById(inputId);
    const toggleBtn = document.getElementById(toggleBtnId);

    if (passwordInput && toggleBtn) {
        toggleBtn.addEventListener("click", function () {
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleBtn.innerText = "🙈 Hide";
            } else {
                passwordInput.type = "password";
                toggleBtn.innerText = "👁 Show";
            }
        });
    }
}

// Run scripts on page load
document.addEventListener("DOMContentLoaded", function () {
    console.log("✅ Water Bill Management System JS Loaded");

    // Example: enable validation for login form
    validateForm("loginForm");

    // Example: enable validation for registration form
    validateForm("registerForm");

    // Example: enable password toggle (if element exists)
    togglePassword("password", "togglePassword");
});