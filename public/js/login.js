// Password visibility toggle: vanilla, no dependency.
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('toggle-password');
    var input = document.getElementById('password');
    if (!btn || !input) { return; }
    btn.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
        btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        btn.querySelector('i').className = showing ? 'fa fa-eye' : 'fa fa-eye-slash';
    });
});
