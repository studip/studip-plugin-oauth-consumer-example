jQuery ($) ->
    $('[data-behaviour~=modal]').live 'click', (event) ->
        href  = $(@).attr 'href'
        title = $(@).attr 'title'
        $('<div/>').load href, ->
            $(@).dialog
                modal: true
                title: title ? false
                width: 500
                buttons:
                    'Schliessen': ->
                        $(@).dialog('close');
        
        event.preventDefault()
        
    $('[data-behaviour~=confirm]').live 'click', (event) ->
        title = $(@).attr('title') || $(@).val() || $(@).text()
        message = 'Wollen Sie die folgende Aktion wirklich ausführen?'.toLocaleString()
        message += "\n\n\"" + title + "\""
        event.preventDefault() unless confirm(message)
        
    $('#parameters')
        .show()
        .on 'click', 'label img', ->
            $('#parameters ul li:last').clone().find('input').val('').text('').end().appendTo('#parameters ul')
        .on 'click', 'ul img[src*=trash]', ->
            li = $(@).closest('li')
            if li.siblings().length
                li.remove();
            else
                li.find('input').val('').end().find('textarea').text('')
        .on 'click', 'ul img[src*=guestbook]', ->
            prev = $(this).prev()
            if prev.is('input')
                replacement = $('<textarea/>').text(prev.val())
            else
                replacement = $('<input type="text"/>').val(prev.text())
            replacement.attr('placeholder', 'Wert'.toLocaleString())
                       .attr('name', prev.attr('name'))
                       .addClass('small')

            prev.replaceWith(replacement)
            replacement.focus()
