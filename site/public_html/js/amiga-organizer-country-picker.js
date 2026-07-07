/**
 * Amiga tournament organizer — country select (used archive + optional full registry).
 */
(function () {
    "use strict";

    function parseMoreSpec(select) {
        var raw = select.getAttribute("data-amiga-more-countries");
        if (!raw) {
            return [];
        }
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function wireCountryPicker(selectId, toggleId) {
        var select = document.getElementById(selectId);
        var toggle = document.getElementById(toggleId);
        if (!select || !toggle) {
            return;
        }

        var moreSpec = parseMoreSpec(select);
        if (moreSpec.length === 0) {
            return;
        }

        var baseOptions = [];
        for (var i = 0; i < select.options.length; i++) {
            var baseOpt = select.options[i];
            if (!baseOpt.value) {
                continue;
            }
            baseOptions.push({
                value: baseOpt.value,
                label: baseOpt.textContent
            });
        }

        function rebuildOptions(includeMore) {
            var selected = select.value;
            var merged = [];
            var seen = {};

            function pushOption(value, label) {
                var key = String(value).toLowerCase();
                if (seen[key]) {
                    return;
                }
                seen[key] = true;
                merged.push({ value: value, label: label });
            }

            for (var i = 0; i < baseOptions.length; i++) {
                pushOption(baseOptions[i].value, baseOptions[i].label);
            }
            if (includeMore) {
                for (var j = 0; j < moreSpec.length; j++) {
                    var spec = moreSpec[j];
                    if (!spec || !spec.value) {
                        continue;
                    }
                    pushOption(spec.value, spec.label || spec.value);
                }
            }

            merged.sort(function (a, b) {
                return a.label.localeCompare(b.label, undefined, { sensitivity: "base" });
            });

            while (select.options.length > 1) {
                select.remove(1);
            }

            for (var k = 0; k < merged.length; k++) {
                var item = merged[k];
                var opt = document.createElement("option");
                opt.value = item.value;
                opt.textContent = item.label;
                if (item.value === selected) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            }

            if (selected && select.value !== selected) {
                select.selectedIndex = 0;
            }
        }

        toggle.addEventListener("change", function () {
            rebuildOptions(toggle.checked);
        });
        rebuildOptions(toggle.checked);
    }

    function boot() {
        wireCountryPicker("amiga-organizer-country", "amiga-organizer-country-more");
        wireCountryPicker("amiga-organizer-player-country", "amiga-organizer-player-country-more");
    }

    window.k2OnPageReady(boot);
})();