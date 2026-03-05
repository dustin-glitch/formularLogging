document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('fl-json-modal');
    var closeBtn = document.getElementById('fl-modal-close');
    var content = document.getElementById('fl-modal-content');

    if (!modal || !closeBtn || !content) {
        return;
    }

    document.querySelectorAll('.fl-view-json').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var rawJson = btn.getAttribute('data-json');
            var formatted = rawJson;
            try {
                formatted = JSON.stringify(JSON.parse(rawJson), null, 4);
            } catch (err) { }
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
