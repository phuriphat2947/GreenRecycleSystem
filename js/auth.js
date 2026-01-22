document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const submitBtn = document.querySelector('.btn-auth');

    function validatePassword() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("รหัสผ่านไม่ตรงกัน");
            return false;
        } else {
            confirmPassword.setCustomValidity("");
            return true;
        }
    }

    password.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);

    form.addEventListener('submit', function (event) {
        if (!validatePassword()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
