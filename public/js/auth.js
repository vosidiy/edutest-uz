(function (global) {
    'use strict';

    function looksLikeGibberish(value) {
        const lower = value.toLocaleLowerCase();

        if (/^([a-z]{2,4})\1+$/i.test(lower)) return true;
        if ([
            'asd', 'asdf', 'qwe', 'qwer', 'qwerty', 'zxc', 'zcx',
            'sadasd', 'zxczxc'
        ].some((sequence) => lower.includes(sequence))) return true;
        if (/(.)\1\1/u.test(lower)) return true;

        return false;
    }

    function validateProfile(values) {
        const name = values.name.trim();
        const email = values.email.trim();
        const phone = values.phone.trim();

        if (name.length < 2) {
            return {field: 'name', message: 'Name must be at least 2 characters.'};
        }

        if (looksLikeGibberish(name)) {
            return {field: 'name', message: 'Please enter a real name, not random characters.'};
        }

        const emailLocal = email.split('@')[0];
        if (looksLikeGibberish(emailLocal)) {
            return {field: 'email', message: 'Email is not real. Please enter a real email.'};
        }

        if (phone !== '') {
            if (!/^\+998[0-9]{9}$/.test(phone)) {
                return {field: 'phone', message: 'Phone must start with +998 and contain 9 digits after it.'};
            }

            const subscriber = phone.slice(4);
            if (/^(\d)\1{8}$/.test(subscriber)) {
                return {field: 'phone', message: 'Please enter a real phone number, not repeating digits.'};
            }
        }

        return null;
    }

    function validatePassword(password, passwordConfirm, fields = {password: 'password', passwordConfirm: 'passwordConfirm'}) {
        if (password.length < 6) {
            return {field: fields.password, message: 'Password must be at least 6 characters.'};
        }

        if (password !== passwordConfirm) {
            return {field: fields.passwordConfirm, message: 'Passwords do not match. Repeat the same password twice.'};
        }

        return null;
    }

    function validateRegistration(values) {
        const profileResult = validateProfile(values);
        if (profileResult) return profileResult;

        return validatePassword(values.password, values.passwordConfirm);
    }

    function validatePasswordChange(values) {
        return validatePassword(values.newPassword, values.newPasswordConfirm, {
            password: 'newPassword',
            passwordConfirm: 'newPasswordConfirm',
        });
    }

    function rejectSubmission(event, result, fields) {
        if (!result) return;

        event.preventDefault();
        alert(result.message);
        fields[result.field].focus();
    }

    function attachRegistrationValidation() {
        const form = document.getElementById('registerForm');
        if (!form) return;

        const fields = {
            name: document.getElementById('display_name'),
            email: document.getElementById('email'),
            phone: document.getElementById('phone'),
            password: document.getElementById('password'),
            passwordConfirm: document.getElementById('password_confirm'),
        };

        form.addEventListener('submit', function (event) {
            rejectSubmission(event, validateRegistration({
                name: fields.name.value,
                email: fields.email.value,
                phone: fields.phone.value,
                password: fields.password.value,
                passwordConfirm: fields.passwordConfirm.value,
            }), fields);
        });
    }

    function attachProfileValidation() {
        const form = document.getElementById('profileForm');
        if (!form) return;

        const fields = {
            name: document.getElementById('profile_display_name'),
            email: document.getElementById('profile_email'),
            phone: document.getElementById('profile_phone'),
        };

        form.addEventListener('submit', function (event) {
            rejectSubmission(event, validateProfile({
                name: fields.name.value,
                email: fields.email.value,
                phone: fields.phone.value,
            }), fields);
        });
    }

    function attachPasswordValidation() {
        const form = document.getElementById('passwordForm');
        if (!form) return;

        const fields = {
            newPassword: document.getElementById('new_password'),
            newPasswordConfirm: document.getElementById('new_password_confirm'),
        };

        form.addEventListener('submit', function (event) {
            rejectSubmission(event, validatePasswordChange({
                newPassword: fields.newPassword.value,
                newPasswordConfirm: fields.newPasswordConfirm.value,
            }), fields);
        });
    }

    function attachValidation() {
        attachRegistrationValidation();
        attachProfileValidation();
        attachPasswordValidation();
    }

    global.EduTestAuthValidation = {
        looksLikeGibberish,
        validateProfile,
        validateRegistration,
        validatePasswordChange,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachValidation);
    } else {
        attachValidation();
    }
}(window));
