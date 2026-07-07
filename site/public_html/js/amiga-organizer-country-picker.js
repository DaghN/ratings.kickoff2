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

        var builtOptions = [];

        function clearMoreOptions() {
            for (var i = 0; i < builtOptions.length; i++) {
                var opt = builtOptions[i];
                if (opt.parentNode) {
                    if (opt.selected) {
                        select.selectedIndex = 0;
                    }
                    opt.remove();
                }
            }
            builtOptions = [];
        }

        function addMoreOptions() {
            for (var i = 0; i < moreSpec.length; i++) {
                var spec = moreSpec[i];
                if (!spec || !spec.value) {
                    continue;
                }
                var opt = document.createElement("option");
                opt.value = spec.value;
                opt.textContent = spec.label || spec.value;
                opt.setAttribute("data-amiga-country-more", "1");
                if (spec.selected) {
                    opt.selected = true;
                }
                select.appendChild(opt);
                builtOptions.push(opt);
            }
        }

        function setMoreVisible(show) {
            clearMoreOptions();
            if (show) {
                addMoreOptions();
            }
        }

        toggle.addEventListener("change", function () {
            setMoreVisible(toggle.checked);
        });
        setMoreVisible(toggle.checked);
    }

    function boot() {
        wireCountryPicker("amiga-organizer-country", "amiga-organizer-country-more");
        wireCountryPicker("amiga-organizer-player-country", "amiga-organizer-player-country-more");
    }

    window.k2OnPageReady(boot);
})();