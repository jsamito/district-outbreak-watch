/* Shell behaviour: keyboard and ARIA improvements on top of AdminLTE. */
(function ($) {
    'use strict';

    // The sidebar toggle is an anchor with role="button": make Space activate it like a button.
    $(document).on('keydown', 'a[role="button"]', function (e) {
        if (e.key === ' ' || e.which === 32) {
            e.preventDefault();
            this.click();
        }
    });

    // Reflect dropdown state for assistive technology.
    $(document).on('shown.bs.dropdown', function (e) {
        $(e.target).find('[data-toggle="dropdown"]').attr('aria-expanded', 'true');
    });
    $(document).on('hidden.bs.dropdown', function (e) {
        $(e.target).find('[data-toggle="dropdown"]').attr('aria-expanded', 'false');
    });

    // Close an open dropdown with Escape and return focus to its toggle.
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            var $open = $('.dropdown.open');
            if ($open.length) {
                $open.removeClass('open').find('[data-toggle="dropdown"]').attr('aria-expanded', 'false').trigger('focus');
            }
        }
    });

    // Keep the toggle's expanded state in sync with the sidebar (mobile off-canvas and desktop collapse).
    function syncSidebarState() {
        var $body = $('body');
        var open = $(window).width() <= 767 ? $body.hasClass('sidebar-open') : !$body.hasClass('sidebar-collapse');
        $('.sidebar-toggle').attr('aria-expanded', open ? 'true' : 'false');
    }
    $(document).on('click', '.sidebar-toggle', function () { setTimeout(syncSidebarState, 50); });
    $(window).on('resize', syncSidebarState);
    $(syncSidebarState);

    // Move focus to the main region after in-page skip-link navigation.
    $(document).on('click', '.ow-skip-link', function () {
        var target = document.querySelector($(this).attr('href'));
        if (target) { target.focus(); }
    });
})(jQuery);
