/* District choropleth with Leaflet and no tile layer: no third-party requests, and only the
   polygons the signed-in user is allowed to see. A table next to the map gives the same data in text. */
window.owMap = function (elId, cfg) {
    'use strict';
    var el = document.getElementById(elId);
    if (!el || !window.L) { return; }
    var scale = ['#eff3ff', '#bdd7e7', '#6baed6', '#3182bd', '#08519c'];
    var values = cfg.values; // { districtId: {v: number|null, label: string} }
    var nums = Object.keys(values).map(function (k) { return values[k].v; }).filter(function (v) { return v !== null; }).sort(function (a, b) { return a - b; });
    function q(p) { return nums.length ? nums[Math.min(nums.length - 1, Math.floor(p * nums.length))] : 0; }
    var breaks = [q(0.2), q(0.4), q(0.6), q(0.8)];
    function colour(v) {
        if (v === null || v === undefined) { return '#e9ecef'; }
        for (var i = 0; i < breaks.length; i++) { if (v <= breaks[i]) { return scale[i]; } }
        return scale[4];
    }
    var map = L.map(el, { zoomControl: true, attributionControl: false, scrollWheelZoom: false, keyboard: true, preferCanvas: true });
    var info = L.control({ position: 'topright' });
    info.onAdd = function () { this._div = L.DomUtil.create('div', 'ow-map-info'); this.update(); return this._div; };
    info.update = function (p) { this._div.innerHTML = p ? '<strong>' + p.name + '</strong><br>' + (values[p.id] ? values[p.id].label : 'No data') : 'Hover over or select a district'; };
    info.addTo(map);

    fetch(cfg.geojsonUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (geo) {
        var layer = L.geoJSON(geo, {
            style: function (f) {
                var v = values[f.properties.id] ? values[f.properties.id].v : null;
                var flagged = cfg.flags && cfg.flags[f.properties.id];
                return { fillColor: colour(v), weight: flagged ? 3 : 1, color: flagged ? '#d55e00' : '#5b6773', fillOpacity: 0.92 };
            },
            onEachFeature: function (f, l) {
                l.on({
                    mouseover: function () { l.setStyle({ weight: 3, color: '#1b2430' }); info.update(f.properties); },
                    mouseout: function () { layer.resetStyle(l); info.update(); },
                    click: function () { if (cfg.linkBase) { window.location = cfg.linkBase + f.properties.id; } }
                });
            }
        }).addTo(map);
        map.fitBounds(layer.getBounds());
        var legend = L.control({ position: 'bottomleft' });
        legend.onAdd = function () {
            var d = L.DomUtil.create('div', 'ow-map-legend');
            var lo = nums.length ? nums[0] : 0;
            var bounds = [lo].concat(breaks).concat([nums.length ? nums[nums.length - 1] : 0]);
            var html = '<strong>' + cfg.legendTitle + '</strong><br>';
            for (var i = 0; i < scale.length; i++) {
                html += '<span class="ow-swatch" style="background:' + scale[i] + '"></span>' + owCharts.fmt(bounds[i]) + ' to ' + owCharts.fmt(bounds[i + 1]) + '<br>';
            }
            html += '<span class="ow-swatch" style="background:#e9ecef"></span>No data<br>';
            if (cfg.flags) { html += '<span class="ow-swatch" style="background:#fff;border:3px solid #d55e00"></span>Outbreak signal'; }
            d.innerHTML = html;
            return d;
        };
        legend.addTo(map);
    });
};
