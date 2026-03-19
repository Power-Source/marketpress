jQuery(document).ready(function ($) {
    let salesChart;

    /**
     * Wandelt ein Array von {month, valueKey}-Objekten in eine Schlüssel→Wert-Map um.
     */
    function buildMonthMap(rows, valueKey) {
        var map = {};
        (rows || []).forEach(function (row) {
            map[row.month] = parseFloat(row[valueKey]) || 0;
        });
        return map;
    }

    function fetchSalesData(period, month1, month2) {
        month1 = month1 || null;
        month2 = month2 || null;

        $.ajax({
            url: mpStatsAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'mp_get_sales_data',
                nonce:  mpStatsAjax.nonce,
                period: period,
                month1: month1,
                month2: month2,
            },
            success: function (response) {
                if (salesChart) {
                    salesChart.destroy();
                }

                // Monate aus beiden Datensätzen zusammenführen und sortieren
                var revenueMap = buildMonthMap(response.data,           'total');
                var freeDlMap  = buildMonthMap(response.free_downloads, 'downloads');

                var allMonths = Array.from(
                    new Set(
                        Object.keys(revenueMap).concat(Object.keys(freeDlMap))
                    )
                ).sort();

                var revenueData = allMonths.map(function (m) { return revenueMap[m] || 0; });
                var freeDlData  = allMonths.map(function (m) { return freeDlMap[m]  || 0; });

                var ctx = document.getElementById('salesChart').getContext('2d');
                salesChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: allMonths,
                        datasets: [
                            {
                                label:           'Umsatz (€)',
                                data:            revenueData,
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                borderColor:     'rgba(75, 192, 192, 1)',
                                borderWidth:     1,
                                yAxisID:         'y',
                                order:           2,
                            },
                            {
                                label:           'Gratis-Downloads',
                                data:            freeDlData,
                                backgroundColor: 'rgba(255, 159, 64, 0.3)',
                                borderColor:     'rgba(255, 159, 64, 1)',
                                borderWidth:     1,
                                yAxisID:         'y1',
                                order:           1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode:      'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                position:    'left',
                                title: {
                                    display: true,
                                    text:    'Umsatz (€)',
                                },
                            },
                            y1: {
                                beginAtZero: true,
                                position:    'right',
                                grid: {
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    stepSize:  1,
                                    precision: 0,
                                },
                                title: {
                                    display: true,
                                    text:    'Gratis-Downloads',
                                },
                            },
                        },
                    },
                });

                // KPI-Werte aktualisieren
                $('#mp-stats-total-value').text(
                    (parseFloat(response.total) || 0).toFixed(2)
                );
                $('#mp-stats-freedl-value').text(
                    parseInt(response.free_dl_total, 10) || 0
                );
            },
        });
    }

    // Benutzerdefinierter Zeitraum ein-/ausblenden
    $('#mp-stats-period').on('change', function () {
        if ($(this).val() === 'custom') {
            $('#mp-stats-custom-filters').css('display', 'flex');
        } else {
            $('#mp-stats-custom-filters').hide();
        }
    });

    // Filter anwenden
    $('#mp-stats-apply-filters').on('click', function () {
        var period = $('#mp-stats-period').val();
        var month1 = $('#mp-stats-month1').val();
        var month2 = $('#mp-stats-month2').val();
        fetchSalesData(period, month1, month2);
    });

    // Initiale Daten laden (letzte 3 Monate)
    fetchSalesData('3_months');
});