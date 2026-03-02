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
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});
