/* District Outbreak Watch: page shell behaviour in plain JavaScript (no jQuery at load).
   Sidebar, dropdown menu, keyboard and ARIA, filter forms, and a loader that brings in
   jQuery, select2, datepicker, DataTables and toastr only when something needs them. */
(function () {
    'use strict';

    /* The DataTables bundle expects a Bootstrap 5 Modal at load time; the app does not use modals. */
    window.bootstrap = window.bootstrap || {};
    window.bootstrap.Modal = window.bootstrap.Modal || function () { return { show: function () {}, hide: function () {}, dispose: function () {} }; };

    var doc = document, body = doc.body;
    var q = function (s, r) { return (r || doc).querySelector(s); };
    var qa = function (s, r) { return Array.prototype.slice.call((r || doc).querySelectorAll(s)); };

    /* ── Lazy libraries ───────────────────────────────── */
    var assetMap = {};
    try { assetMap = JSON.parse((q('meta[name="assets"]') || {}).content || '{}'); } catch (e) { assetMap = {}; }
    var needsJquery = { select2: 1, datepicker: 1, datatables: 1, tables: 1 };
    var libs = {};
    function addCss(href) { var l = doc.createElement('link'); l.rel = 'stylesheet'; l.href = href; doc.head.appendChild(l); }
    function addJs(src) { return new Promise(function (ok, fail) { var s = doc.createElement('script'); s.src = src; s.onload = ok; s.onerror = fail; doc.head.appendChild(s); }); }
    window.owLib = function (name) {
        if (!libs[name]) {
            var a = assetMap[name];
            var base = needsJquery[name] ? owLib('jquery') : Promise.resolve();
            libs[name] = !a ? base : base.then(function () { if (a[0]) { addCss(a[0]); } return addJs(a[1]); });
        }
        return libs[name];
    };
    function afterLoad(fn) {
        var run = function () { (window.requestIdleCallback || function (f) { setTimeout(f, 300); })(fn, { timeout: 4000 }); };
        if (doc.readyState === 'complete') { run(); } else { window.addEventListener('load', run); }
    }
    window.owAfterLoad = afterLoad;

    /* ── Toasts ───────────────────────────────────────── */
    window.owToast = function (type, message) {
        owLib('toastr').then(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4500, extendedTimeOut: 1500, showMethod: 'fadeIn', hideMethod: 'fadeOut' };
            toastr[type || 'info'](message);
        });
    };

    /* ── Sidebar (replaces AdminLTE's push menu) ──────── */
    function sidebarOpen() { return window.innerWidth <= 767 ? body.classList.contains('sidebar-open') : !body.classList.contains('sidebar-collapse'); }
    function syncSidebar() { qa('.sidebar-toggle').forEach(function (t) { t.setAttribute('aria-expanded', sidebarOpen() ? 'true' : 'false'); }); }
    doc.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-toggle="offcanvas"]') : null;
        if (t) {
            e.preventDefault();
            body.classList.toggle(window.innerWidth <= 767 ? 'sidebar-open' : 'sidebar-collapse');
            syncSidebar();
            return;
        }
        if (window.innerWidth <= 767 && body.classList.contains('sidebar-open') && e.target.closest && e.target.closest('.content-wrapper')) {
            body.classList.remove('sidebar-open');
            syncSidebar();
        }
    });
    window.addEventListener('resize', syncSidebar);

    /* ── Dropdown menu (user menu) ────────────────────── */
    function closeMenus(except) {
        qa('.dropdown.open').forEach(function (d) {
            if (d === except) { return; }
            d.classList.remove('open');
            var t = q('[data-toggle="dropdown"]', d);
            if (t) { t.setAttribute('aria-expanded', 'false'); }
        });
    }
    doc.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-toggle="dropdown"]') : null;
        if (t) {
            e.preventDefault();
            var d = t.closest('.dropdown');
            var open = !d.classList.contains('open');
            closeMenus(open ? d : null);
            d.classList.toggle('open', open);
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
        } else if (!(e.target.closest && e.target.closest('.dropdown-menu'))) {
            closeMenus(null);
        }
    });

    /* ── Keyboard ─────────────────────────────────────── */
    doc.addEventListener('keydown', function (e) {
        if (e.key === ' ' && e.target.matches && e.target.matches('a[role="button"]')) { e.preventDefault(); e.target.click(); }
        if (e.key === 'Escape') {
            var open = q('.dropdown.open');
            if (open) { var t = q('[data-toggle="dropdown"]', open); closeMenus(null); if (t) { t.focus(); } }
        }
    });
    doc.addEventListener('click', function (e) {
        var s = e.target.closest ? e.target.closest('.ow-skip-link') : null;
        if (s) { var target = q(s.getAttribute('href')); if (target) { target.focus(); } }
    });

    /* ── Filter forms: selects marked data-autosubmit submit on change ── */
    doc.addEventListener('change', function (e) {
        var s = e.target;
        if (s.matches && s.matches('select[data-autosubmit]') && !s.__owUpgraded) {
            if (s.getAttribute('data-autosubmit') === 'area' && s.form) { var f = s.form.elements.facility; if (f) { f.value = ''; } }
            s.form.submit();
        }
    });

    /* ── select2 and datepicker: native until first used, then upgraded ── */
    window.owInitSelect2 = function (root) {
        var $ = window.jQuery;
        if (!$ || !$.fn.select2) { return; }
        $(root || doc).find('select.ow-select2').each(function () {
            var $s = $(this);
            if ($s.data('select2')) { return; }
            $s.select2({ width: '100%', dropdownParent: $(doc.body), placeholder: $s.data('placeholder') || $s.find('option[value=""]').text() || '— Select —', allowClear: $s.data('allow-clear') === true && !$s.prop('multiple') });
            this.__owUpgraded = true;
            if (this.hasAttribute('data-autosubmit')) {
                var el = this;
                $s.on('change.owsubmit', function () {
                    if (el.getAttribute('data-autosubmit') === 'area' && el.form && el.form.elements.facility) { el.form.elements.facility.value = ''; }
                    el.form.submit();
                });
            }
        });
    };
    window.owInitDatepickers = function (root) {
        var $ = window.jQuery;
        if (!$ || !$.fn.datepicker) { return; }
        $(root || doc).find('input.ow-datepicker:not([data-dp-init])').each(function () {
            var $el = $(this);
            $el.attr('data-dp-init', 1);
            $el.datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true, weekStart: 1 }).on('changeDate', function () { $el.trigger('input').trigger('change'); });
        });
    };
    function upgrade(el) {
        if (el.matches('select.ow-select2')) { owLib('select2').then(function () { owInitSelect2(el.parentNode); }); }
        else if (el.matches('input.ow-datepicker')) { owLib('datepicker').then(function () { owInitDatepickers(el.parentNode); }); }
    }
    ['pointerenter', 'focusin', 'touchstart'].forEach(function (ev) {
        doc.addEventListener(ev, function (e) {
            var t = e.target;
            if (t && t.matches && t.matches('select.ow-select2, input.ow-datepicker')) { upgrade(t); }
        }, { capture: true, passive: true });
    });

    /* ── DataTables: loaded and initialised when a table is about to be seen ── */
    var registry = {};
    window.owRegisterDT = function (id, opts) {
        registry[id] = opts || {};
        var el = doc.getElementById(id);
        if (!el) { return; }
        var go = function () { owLib('datatables').then(function () { return owLib('tables'); }).then(function () { window.owInitDT(id, registry[id]); }); };
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) { if (entries.some(function (x) { return x.isIntersecting; })) { io.disconnect(); setTimeout(go, 0); } }, { rootMargin: '300px' });
            io.observe(el);
        } else { go(); }
    };

    window.owColors = ['#0072b2', '#e69f00', '#009e73', '#d55e00', '#cc79a7', '#56b4e9', '#f0e442', '#6b7682'];

    /* ── Start-up ─────────────────────────────────────── */
    syncSidebar();
    var flash = q('meta[name="flash-status"]');
    if (flash && flash.content) { afterLoad(function () { window.owToast('success', flash.content); }); }
}());
