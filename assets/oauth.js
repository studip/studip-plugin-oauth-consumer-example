jQuery(function($) {
  $('[data-behaviour~=modal]').live('click', function(event) {
    var href, title;
    href = $(this).attr('href');
    title = $(this).attr('title');
    $('<div/>').load(href, function() {
      return $(this).dialog({
        modal: true,
        title: title != null ? title : false,
        width: 500,
        buttons: {
          'Schliessen': function() {
            return $(this).dialog('close');
          }
        }
      });
    });
    return event.preventDefault();
  });
  $('[data-behaviour~=confirm]').live('click', function(event) {
    var message, title;
    title = $(this).attr('title') || $(this).val() || $(this).text();
    message = 'Wollen Sie die folgende Aktion wirklich ausführen?'.toLocaleString();
    message += "\n\n\"" + title + "\"";
    if (!confirm(message)) return event.preventDefault();
  });
  return $('#parameters').show().on('click', 'label img', function() {
    return $('#parameters ul li:last').clone().find('input').val('').text('').end().appendTo('#parameters ul');
  }).on('click', 'ul img[src*=trash]', function() {
    var li;
    li = $(this).closest('li');
    if (li.siblings().length) {
      return li.remove();
    } else {
      return li.find('input').val('').end().find('textarea').text('');
    }
  }).on('click', 'ul img[src*=guestbook]', function() {
    var prev, replacement;
    prev = $(this).prev();
    if (prev.is('input')) {
      replacement = $('<textarea/>').text(prev.val());
    } else {
      replacement = $('<input type="text"/>').val(prev.text());
    }
    replacement.attr('placeholder', 'Wert'.toLocaleString()).attr('name', prev.attr('name')).addClass('small');
    prev.replaceWith(replacement);
    return replacement.focus();
  });
});