// Vanilla JS (no jQuery dependency) so this works even on pages that don't load it.
// Event delegation supports multiple [aerosol_branches] tables on the same page.
document.addEventListener('input', function (e) {
    if (!e.target.classList.contains('alt-branch-search')) {
        return;
    }

    var wrap = e.target.closest('.alt-branch-list-wrap');
    if (!wrap) {
        return;
    }

    var value = e.target.value.toLowerCase().trim();
    var rows = wrap.querySelectorAll('.alt-branch-table tbody tr');

    rows.forEach(function (row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(value) > -1 ? '' : 'none';
    });
});
