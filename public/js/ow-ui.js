/* District Outbreak Watch: front-end wiring on top of AdminLTE.
   Select2, DataTables registry, datepickers, toastr and keyboard/ARIA fixes.
   Alpine.js drives page state. Plugins are optional: every helper checks that
   its library is present so pages only load what they use. */
(function ($) {
    'use strict';

    /* ── Toastr ─────────────────────────────────────────── */
    if (window.toastr) {
        toastr.options = {
            closeButton: true, progressBar: true, positionClass: 'toast-top-right',
            timeOut: 4500, extendedTimeOut: 1500, showMethod: 'fadeIn', hideMethod: 'fadeOut'
        };
    }
    window.owToast = function (type, message) { if (window.toastr) { toastr[type || 'info'](message); } };

    /* Bootstrap 5 Modal shim required by the DataTables bs5 bundle */
    window.bootstrap = window.bootstrap || {};
    window.bootstrap.Modal = window.bootstrap.Modal || function () { return { show: function () {}, hide: function () {}, dispose: function () {} }; };

    /* ── Select2 ────────────────────────────────────────── */
    window.owInitSelect2 = function (root) {
        if (!$.fn.select2) { return; }
        $(root || document).find('select.ow-select2').each(function () {
            var $s = $(this);
            if ($s.data('select2')) { return; }
            $s.select2({
                width: '100%',
                dropdownParent: $(document.body),
                placeholder: $s.data('placeholder') || $s.find('option[value=""]').text() || '— Select —',
                allowClear: $s.data('allow-clear') === true && !$s.prop('multiple')
            });
        });
    };

    /* Select2 inside Alpine x-for: x-init="$nextTick(() => owInitSelect2Alpine($el))" */
    window.owInitSelect2Alpine = function (el) {
        if (!$.fn.select2) { return; }
        var $el = $(el);
        if ($el.data('select2')) { $el.select2('destroy'); }
        $el.select2({ width: '100%', dropdownParent: $(document.body), placeholder: $el.find('option[value=""]').text() || '— Select —' });
        $el.off('.owalpine').on('select2:select.owalpine select2:unselect.owalpine', function () {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    /* ── Datepickers ────────────────────────────────────── */
    window.owInitDatepickers = function (root) {
        if (!$.fn.datepicker) { return; }
        $(root || document).find('input.ow-datepicker:not([data-dp-init])').each(function () {
            var $el = $(this);
            $el.attr('data-dp-init', 1);
            $el.datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true, weekStart: 1 })
                .on('changeDate', function () { $el.trigger('input').trigger('change'); });
        });
    };

    /* ── DataTables registry ────────────────────────────── */
    var registry = {};
    var pdfPromise = null;

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src; s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }
    /* pdfmake is large (about 2 MB): load it only when someone asks for a PDF */
    function ensurePdf() {
        if (window.pdfMake && window.pdfMake.vfs) { return Promise.resolve(); }
        var base = (document.querySelector('meta[name="vendor-base"]') || {}).content || '/vendor/datatables/';
        pdfPromise = pdfPromise || loadScript(base + 'pdfmake.min.js').then(function () { return loadScript(base + 'vfs_fonts.js'); });
        return pdfPromise;
    }

    function cleanCell(data) {
        var $n = $('<div>').html(data);
        $n.find('.ow-initial, i.fa, .noExport, script, style, input, button').remove();
        $n.find('small.text-muted').remove();
        var t = $n.text().replace(/\s+/g, ' ').trim();
        return (t === '—' || t === '-') ? '' : t;
    }
    window.owCleanCell = cleanCell;

    var cols = { columns: ':not(.noExport)', format: { body: function (d) { return cleanCell(d); } } };
    var title = function () { return window.owTitle || document.title; };

    /* Export set for AGGREGATED tables only. Tables that list individual cases
       must register with buttons: [] so no line list can be downloaded. */
    window.owDefaultButtons = [
        { extend: 'collection', text: '<i class="fa fa-download"></i> Export', autoClose: true, className: 'dt-button',
          buttons: [
            { extend: 'excelHtml5', text: '<i class="fa fa-file-excel-o"></i> Excel', exportOptions: cols, title: title },
            { extend: 'csvHtml5', text: '<i class="fa fa-file-text-o"></i> CSV', exportOptions: cols,
              customize: function (csv) { return 'Aggregated data only. ' + title() + ' exported ' + new Date().toISOString().slice(0, 10) + '\n' + csv; } },
            { extend: 'pdfHtml5', text: '<i class="fa fa-file-pdf-o"></i> PDF', exportOptions: cols, orientation: 'landscape', title: title,
              action: function (e, dt, node, config) {
                  var self = this;
                  ensurePdf().then(function () { $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, node, config); });
              } },
            { extend: 'print', text: '<i class="fa fa-print"></i> Print', exportOptions: cols, title: title },
            { extend: 'copy', text: '<i class="fa fa-copy"></i> Copy', exportOptions: cols }
          ] },
        { extend: 'colvis', text: '<i class="fa fa-columns"></i> Columns', className: 'dt-button' }
    ];

    var defaults = {
        destroy: true, autoWidth: false, responsive: false, stateSave: false, processing: false,
        dom: '<"ow-dt-toolbar"<"ow-dt-left"l><"ow-dt-filters"f><"ow-dt-actions"B>>t<"box-footer clearfix"<"pull-left"i><"pull-right"p>>',
        buttons: window.owDefaultButtons,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language: {
            searchPlaceholder: 'Search…', lengthMenu: 'Show _MENU_', info: 'Showing _START_–_END_ of _TOTAL_',
            infoEmpty: 'No records', zeroRecords: 'No matching records found.', emptyTable: 'No data available.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    };

    window.owRegisterDT = function (id, opts) {
        var merged = $.extend(true, {}, defaults, opts);
        if (opts && opts.buttons) { merged.buttons = opts.buttons; }
        registry[id] = merged;
        window.owInitDT(id);
    };
    window.owInitDT = function (id) {
        if (!$.fn.DataTable) { return; }
        var $t = $('#' + id);
        if (!$t.length) { return; }
        if ($.fn.DataTable.isDataTable($t)) { $t.DataTable().destroy(); }
        $t.DataTable(registry[id] || defaults);
    };

    /* ── Chart helpers (Okabe-Ito palette, honest defaults) ─ */
    window.owColors = ['#0072b2', '#e69f00', '#009e73', '#d55e00', '#cc79a7', '#56b4e9', '#f0e442', '#6b7682'];

    /* ── Keyboard and ARIA improvements for the AdminLTE shell ─ */
    $(document).on('keydown', 'a[role="button"]', function (e) {
        if (e.key === ' ') { e.preventDefault(); this.click(); }
    });
    $(document).on('shown.bs.dropdown', function (e) { $(e.target).find('[data-toggle="dropdown"]').attr('aria-expanded', 'true'); });
    $(document).on('hidden.bs.dropdown', function (e) { $(e.target).find('[data-toggle="dropdown"]').attr('aria-expanded', 'false'); });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            var $open = $('.dropdown.open');
            if ($open.length) { $open.removeClass('open').find('[data-toggle="dropdown"]').attr('aria-expanded', 'false').trigger('focus'); }
        }
    });
    function syncSidebarState() {
        var $b = $('body');
        var open = $(window).width() <= 767 ? $b.hasClass('sidebar-open') : !$b.hasClass('sidebar-collapse');
        $('.sidebar-toggle').attr('aria-expanded', open ? 'true' : 'false');
    }
    $(document).on('click', '.sidebar-toggle', function () { setTimeout(syncSidebarState, 80); });
    $(window).on('resize', syncSidebarState);
    $(document).on('click', '.ow-skip-link', function () {
        var t = document.querySelector($(this).attr('href'));
        if (t) { t.focus(); }
    });

    /* Filter bars: changing the area clears the facility; selects marked data-autosubmit submit the form. */
    $(document).on('change', 'select[data-autosubmit]', function () {
        if (this.getAttribute('data-autosubmit') === 'area') { $(this.form).find('[name=facility]').val(''); }
        this.form.submit();
    });

    $(function () {
        window.owInitSelect2();
        window.owInitDatepickers();
        syncSidebarState();
        var flash = document.querySelector('meta[name="flash-status"]');
        if (flash && flash.content) { window.owToast('success', flash.content); }
    });
}(window.jQuery));
