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

    $(document).on('change', '#discovery', function () {
        var data   = $('option:selected', this).data(),
            route  = data.route.replace(/^\//, '') || '',
            method = data.method.toUpperCase() || 'GET';
        $('#method').val(method);
        $('#resource').val(route).data('route', route).focus();
    });
});

// Allow the user to switch between the different parameters in a route by
// using the tab key
(function ($) {
    var param_regexp = /:\w+/g;

    function selectText(element, start, end) {
        var range;
        if (element.createTextRange) {
            console.log('1');
            range = element.createTextRange();
            range.collapse(true);
            range.moveStart('character', start);
            range.moveEnd('character', end);
            range.select();
            element.focus();
        } else if (element.setSelectionRange) {
            console.log('2', start, end);
            element.focus();
            element.setSelectionRange(start, end);
        } else if (element.hasOwnProperty('selectionStart')) {
            console.log('3');
            element.selectionStart = start;
            element.selectionEnd = end;
            element.focus();
        }
    }

    function selectParam(element, needle, backwards) {
        var value  = $(element).val(),
            params = [],
            match,
            param,
            index,
            i;
        while (null !== (match = param_regexp.exec(value))) {
            params.push(match);
        }

        index = backwards ? params.length - 1 : 0;

        if (needle) {
            for (i = 0; i < params.length; i += 1) {
                if (params[i][0] === needle) {
                    index = backwards ? i - 1 : i + 1;
                    break;
                }
            }
        }

        if (params[index] !== undefined) {
            param = params[index || 0];
            selectText(element, param.index, param.index + param[0].length);
            return true;
        }

        return false;
    }

    $(document).on('focus', '#resource', function (event) {
        selectParam(this);
    }).on('keypress', '#resource', function (event) {
        var keycode  = event.keyCode || event.charCode,
            selected = $(this).getSelection();
        if (keycode === 9 && selectParam(this, selected, event.shiftKey)) {
            event.preventDefault();
        } else if (keycode === 13) {
            $(this).closest('form').submit();
            event.preventDefault();
        }
        console.log(event.keyCode || event.charCode, event, selected);
    });
}(jQuery));
