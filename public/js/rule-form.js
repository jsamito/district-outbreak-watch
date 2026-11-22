/* Shows only the fields that the chosen alert method uses. Without JavaScript every field stays visible. */
(function () {
    'use strict';
    var form = document.getElementById('rule-form');
    if (!form) { return; }
    function apply() {
        var chosen = form.querySelector('input[name=method]:checked');
        var m = chosen ? chosen.value : 'outbreak_signal';
        form.querySelectorAll('[data-methods]').forEach(function (el) { el.hidden = el.getAttribute('data-methods').split(' ').indexOf(m) === -1; });
        form.querySelectorAll('[data-only]').forEach(function (el) { el.hidden = el.getAttribute('data-only').split(' ').indexOf(m) === -1; });
        form.querySelectorAll('.ow-method').forEach(function (l) { l.classList.toggle('is-on', l.querySelector('input').checked); });
    }
    form.addEventListener('change', function (e) { if (e.target.name === 'method') { apply(); } });
    apply();
}());
