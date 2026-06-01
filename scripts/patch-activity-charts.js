/* eslint-disable no-console */
const fs = require('fs');
const path = require('path');

const jsDir = path.join(__dirname, '..', 'site', 'public_html', 'js');

const chartFiles = [
    ['server-games-day-chart.js', '.server-games-day-chart'],
    ['server-games-month-chart.js', '.server-games-month-chart'],
    ['server-games-year-chart.js', '.server-games-year-chart'],
    ['server-active-players-month-chart.js', '.server-active-players-month-chart'],
    ['server-daily-active-players-chart.js', '.server-daily-active-players-chart'],
    ['server-matchup-breadth-chart.js', '.server-matchup-breadth-chart'],
    ['server-established-players-year-chart.js', '.server-established-players-year-chart'],
    ['server-cumulative-established-month-chart.js', '.server-cumulative-established-month-chart'],
    ['server-established-rating-distribution-chart.js', '.server-established-rating-distribution-chart'],
    ['server-top-activity-eras-chart.js', '.server-top-activity-eras-chart'],
    ['server-play-texture-chart.js', '.server-play-texture-chart']
];

function patchChartModule(file, selector) {
    const filePath = path.join(jsDir, file);
    let src = fs.readFileSync(filePath, 'utf8');

    src = src.replace(/^\(function \(\) \{\s*/m, '(function (global) {\n');
    src = src.replace(/\}\)\(\);\s*$/m, '');

    src = src.replace(/T\.createChart\(/g, 'new Chart(');
    src = src.replace(/T\.mergeChartOptions\(/g, 'T.activityChartOptions(');
    src = src.replace(/,\s*'(?:bar|line)'\s*\)/g, ')');

    src = src.replace(
        /function boot\(\) \{[\s\S]*?if \(document\.readyState === 'loading'\) \{[\s\S]*?\} else \{\s*boot\(\);\s*\}\s*\}\)\(\);/m,
        ''
    );

    if (!src.includes('return fetch(') && src.includes('fetch(')) {
        src = src.replace(
            /(function initRoot\(root\) \{[\s\S]*?)(fetch\()/m,
            (match, head, fetchKw) => {
                if (head.includes('return fetch(')) {
                    return match;
                }
                return head + 'return ' + fetchKw;
            }
        );
    }

    if (file === 'server-top-activity-eras-chart.js') {
        src = src.replace(
            /onHover: function \(event, elements\) \{[\s\S]*?\},\s*\n\s*scales:/m,
            `onHover: T.isCoarsePointer() ? undefined : function (event, elements) {
                            var activeIdx = elements.length ? elements[0].datasetIndex : -1;
                            applyDatasetHighlight(chartInstance, activeIdx);
                            var target = event && event.native ? event.native.target : canvas;
                            if (target) {
                                target.style.cursor = activeIdx === -1 ? 'default' : 'pointer';
                            }
                        },
                        scales:`
        );
        src = src.replace(
            /chartInstance\._k2HighlightIdx = -1;\s*canvas\.addEventListener\('mouseleave'/m,
            `if (!T.isCoarsePointer()) {
                    chartInstance._k2HighlightIdx = -1;
                    canvas.addEventListener('mouseleave'`
        );
        src = src.replace(
            /canvas\.style\.cursor = 'default';\s*\}\);\s*\}\)/m,
            `canvas.style.cursor = 'default';
                    });
                }`
        );
    }

    src = src.trimEnd() + '\n\n    if (global.K2ActivityCharts) {\n';
    src += `        global.K2ActivityCharts.register('${selector}', initRoot);\n`;
    src += '    }\n})(typeof window !== \'undefined\' ? window : this);\n';

    fs.writeFileSync(filePath, src, 'utf8');
    console.log('patched', file);
}

function patchHeatmap() {
    const file = 'server-activity-heatmap.js';
    const filePath = path.join(jsDir, file);
    let src = fs.readFileSync(filePath, 'utf8');

    src = src.replace(/^\(function \(\) \{\s*/m, '(function (global) {\n');
    src = src.replace(
        /function boot\(\) \{[\s\S]*?if \(document\.readyState === 'loading'\) \{[\s\S]*?\} else \{\s*boot\(\);\s*\}\s*\}\)\(\);/m,
        ''
    );

    if (!src.includes('return fetch(')) {
        src = src.replace(/(function initRoot\(root\) \{[\s\S]*?)(fetch\()/m, '$1return $2');
    }

    src = src.trimEnd() + '\n\n    if (global.K2ActivityCharts) {\n';
    src += "        global.K2ActivityCharts.register('.server-activity-heatmap', initRoot);\n";
    src += '    }\n})(typeof window !== \'undefined\' ? window : this);\n';

    fs.writeFileSync(filePath, src, 'utf8');
    console.log('patched', file);
}

chartFiles.forEach(([file, sel]) => patchChartModule(file, sel));
patchHeatmap();
