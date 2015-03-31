jQuery(document).ready(function ($) {
    $('#parameters').show();
    $(document).on('click', '#parameters label .add', function (event) {
        event.preventDefault();
        
        $('#parameters ul li:last').clone().find('input').val('').text('').end().appendTo('#parameters ul');
    });
    $(document).on('click', '#parameters ul .remove', function (event) {
        event.preventDefault();
        
        var li = $(this).closest('li');
        if (li.siblings().length) {
            li.remove();
        } else {
            li.find('input').val('').end().find('textarea').text('');
        }
    });
    $(document).on('click', '#parameters ul .convert', function (event) {
        event.preventDefault();
        
        var prev = $(this).closest('label').prev();
        if (prev.is('input')) {
            replacement = $('<textarea/>').text(prev.val());
        } else {
            replacement = $('<input type="text"/>').val(prev.text());
        }
        replacement.attr('placeholder', 'Wert'.toLocaleString())
                   .attr('name', prev.attr('name'))
                   .addClass('small');

        prev.replaceWith(replacement);
        replacement.focus();
    });
});
