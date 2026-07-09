jQuery(function ($) {
    var $rows = $('#alt-mapping-rows');
    var counter = $rows.find('tr').length;

    $('#alt-add-mapping-row').on('click', function () {
        var template = document.getElementById('alt-mapping-row-template').innerHTML;
        template = template.split('__INDEX__').join(counter);
        $rows.append(template);
        counter++;
    });

    $rows.on('click', '.alt-remove-row', function () {
        $(this).closest('tr').remove();
    });
});
