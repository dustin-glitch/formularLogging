document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('fl-json-modal');
    var closeBtn = document.getElementById('fl-modal-close');
    var content = document.getElementById('fl-modal-content');

    if (!modal || !closeBtn || !content) {
        return;
    }

    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return (unsafe + '')
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    document.querySelectorAll('.fl-view-json').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var rawJson = btn.getAttribute('data-json');
            var formatted = rawJson;
            var summaryHtml = '';
            var summaryContainer = document.getElementById('fl-modal-summary');

            try {
                var parsed = JSON.parse(rawJson);
                formatted = JSON.stringify(parsed, null, 4);

                if (parsed && typeof parsed === 'object') {
                    if (parsed.validation && typeof parsed.validation === 'object') {
                        summaryHtml += '<div style="margin-bottom: 15px; padding: 10px; background: #fff8e5; border-left: 4px solid #f0b849;">';
                        summaryHtml += '<h3 style="margin-top: 0; color: #d63638; font-size: 14px;">Validierungsfehler</h3><ul style="margin-bottom: 0; padding-left: 20px;">';
                        for (var f in parsed.validation) {
                            if (parsed.validation.hasOwnProperty(f)) {
                                var msgs = parsed.validation[f];
                                if (Array.isArray(msgs)) msgs = msgs.join(', ');
                                summaryHtml += '<li><strong>' + escapeHtml(f) + ':</strong> ' + escapeHtml(msgs) + '</li>';
                            }
                        }
                        summaryHtml += '</ul></div>';
                    } else if (parsed.field) {
                        summaryHtml += '<div style="margin-bottom: 15px; padding: 10px; background: #fff8e5; border-left: 4px solid #f0b849;">';
                        summaryHtml += '<h3 style="margin-top: 0; color: #d63638; font-size: 14px;">Validierungsfehler</h3><ul style="margin-bottom: 0; padding-left: 20px;">';
                        summaryHtml += '<li><strong>' + escapeHtml(parsed.field) + ':</strong> ' + escapeHtml(parsed.message || '') + '</li>';
                        summaryHtml += '</ul></div>';
                    } else if (parsed.error || parsed.message) {
                        summaryHtml += '<div style="margin-bottom: 15px; padding: 10px; background: #fcf0f1; border-left: 4px solid #d63638;">';
                        summaryHtml += '<h3 style="margin-top: 0; color: #d63638; font-size: 14px;">System-/Fehlermeldung</h3><ul style="margin-bottom: 0; padding-left: 20px;">';
                        summaryHtml += '<li>' + escapeHtml(parsed.error || parsed.message) + '</li>';
                        summaryHtml += '</ul></div>';
                    }
                }
            } catch (err) { }

            if (summaryContainer) {
                summaryContainer.innerHTML = summaryHtml;
            }
            content.textContent = formatted;
            modal.style.display = 'block';
        });
    });

    closeBtn.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    if (document.getElementById('fl-stats-chart') && typeof flStatsData !== 'undefined' && typeof Chart !== 'undefined') {
        var ctx = document.getElementById('fl-stats-chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: flStatsData.labels,
                datasets: [
                    {
                        label: 'Erfolgreich',
                        data: flStatsData.success,
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Fehler',
                        data: flStatsData.errors,
                        borderColor: '#d63638',
                        backgroundColor: 'rgba(214, 54, 56, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
});
