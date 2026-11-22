/* DataTables configuration (loaded with jQuery and DataTables only on pages that show a data table). */
(function ($) {
    'use strict';

    window.bootstrap = window.bootstrap || {};
    window.bootstrap.Modal = window.bootstrap.Modal || function () { return { show: function () {}, hide: function () {}, dispose: function () {} }; };

    var pdfPromise = null;
    function loadScript(src) { return new Promise(function (ok, fail) { var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = fail; document.head.appendChild(s); }); }
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
    var cols = { columns: ':not(.noExport)', format: { body: function (d) { return cleanCell(d); } } };
    var title = function () { return window.owTitle || document.title; };

    /* Export set for AGGREGATED tables only. Tables that list individual cases must register with buttons: []. */
    var defaultButtons = [
        { extend: 'collection', text: '<i class="fa fa-download"></i> Export', autoClose: true, className: 'dt-button',
          buttons: [
            { extend: 'excelHtml5', text: '<i class="fa fa-file-excel-o"></i> Excel', exportOptions: cols, title: title },
            { extend: 'csvHtml5', text: '<i class="fa fa-file-text-o"></i> CSV', exportOptions: cols, customize: function (csv) { return 'Aggregated data only. ' + title() + ' exported ' + new Date().toISOString().slice(0, 10) + '\n' + csv; } },
            { extend: 'pdfHtml5', text: '<i class="fa fa-file-pdf-o"></i> PDF', exportOptions: cols, orientation: 'landscape', title: title,
              action: function (e, dt, node, config) { var self = this; ensurePdf().then(function () { $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, node, config); }); } },
            { extend: 'print', text: '<i class="fa fa-print"></i> Print', exportOptions: cols, title: title },
            { extend: 'copy', text: '<i class="fa fa-copy"></i> Copy', exportOptions: cols }
          ] },
        { extend: 'colvis', text: '<i class="fa fa-columns"></i> Columns', className: 'dt-button' }
    ];
    var defaults = {
        destroy: true, autoWidth: false, responsive: false, stateSave: false, processing: false,
        dom: '<"ow-dt-toolbar"<"ow-dt-left"l><"ow-dt-filters"f><"ow-dt-actions"B>>t<"box-footer clearfix"<"pull-left"i><"pull-right"p>>',
        buttons: defaultButtons, pageLength: 25, lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language: { searchPlaceholder: 'Search…', lengthMenu: 'Show _MENU_', info: 'Showing _START_–_END_ of _TOTAL_', infoEmpty: 'No records', zeroRecords: 'No matching records found.', emptyTable: 'No data available.', paginate: { first: '«', last: '»', next: '›', previous: '‹' } }
    };

    window.owInitDT = function (id, opts) {
        var $t = $('#' + id);
        if (!$t.length) { return; }
        var merged = $.extend(true, {}, defaults, opts || {});
        if (opts && opts.buttons) { merged.buttons = opts.buttons; }
        if ($.fn.DataTable.isDataTable($t)) { $t.DataTable().destroy(); }
        $t.DataTable(merged);
    };
}(window.jQuery));
