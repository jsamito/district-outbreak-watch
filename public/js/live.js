/* Live dashboard updates. Subscribes to the private channel of the area being viewed through Laravel
   Reverb (Pusher protocol). If the WebSocket cannot connect, the page polls a small JSON summary instead. */
(function () {
    'use strict';
    var strip = document.querySelector('[data-live-area]');
    if (!strip) { return; }
    var area = strip.getAttribute('data-live-area');
    var url = strip.getAttribute('data-live-url') + '?area=' + encodeURIComponent(area);
    var pollTimer = null;

    function apply(data) {
        (data.diseases || []).forEach(function (d) {
            var cell = document.querySelector('[data-live-disease="' + d.code + '"] [data-live-count]');
            if (cell) { cell.textContent = Number(d.live).toLocaleString(); }
        });
        var badge = document.querySelector('.sidebar-badge, .navbar-custom-menu .label-danger');
        if (data.open_alerts !== undefined && badge) { badge.textContent = data.open_alerts; }
    }
    function refresh() {
        return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(apply).catch(function () {});
    }
    function startPolling() {
        if (pollTimer) { return; }
        pollTimer = setInterval(refresh, 60000);
    }

    var meta = document.querySelector('meta[name="reverb"]');
    if (!meta || !window.owLib) { startPolling(); return; }
    owLib('pusher').then(connect, startPolling);

    function connect() {
        var cfg;
        try { cfg = JSON.parse(meta.content); } catch (e) { startPolling(); return; }
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
        var pusher = new Pusher(cfg.key, {
            wsHost: cfg.host, wsPort: cfg.port, wssPort: cfg.port, forceTLS: cfg.scheme === 'https', enabledTransports: cfg.scheme === 'https' ? ['wss'] : ['ws'], cluster: 'mt1', disableStats: true,
            channelAuthorization: { endpoint: '/broadcasting/auth', headers: { 'X-CSRF-TOKEN': csrf } }
        });
        pusher.connection.bind('connected', function () { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } });
        // If the socket server cannot be reached, stop retrying and poll instead (it reconnects on the next page load).
        function giveUp() { startPolling(); try { pusher.disconnect(); } catch (e) {} }
        pusher.connection.bind('unavailable', giveUp);
        pusher.connection.bind('failed', giveUp);

        var channel = pusher.subscribe('private-area.' + area);
        channel.bind('case.notified', function (e) {
            if (window.owToast) { owToast('info', 'New ' + e.disease + ' notification in ' + e.area + ' (' + e.district_cases + ' cases this week there).'); }
            refresh();
        });
    }
}());
