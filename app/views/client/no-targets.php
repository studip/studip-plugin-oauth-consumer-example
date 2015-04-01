<h2><?= _('Fehler') ?></h2>
<p><?= _('Weder die Kern-API noch das Rest.IP-Plugin ist aktiviert.') ?></p>
<p>
    <?= _('Um die Kern-API zu aktivieren, ändern Sie bitte den Wert API_ENABLED in der Konfiguration.') ?><br>
    <?= Studip\LinkButton::createButton(_('Zur Konfiguration'), URLHelper::getLink('dispatch.php/admin/configuration/configuration?needle=API_ENABLED')) ?>
</p>
<p>
    <?= _('Um Rest.IP zu aktivieren, müssen Sie das Plugin installieren bzw. in der Plugin-Konfiguration aktivieren.') ?><br>
    <?= Studip\LinkButton::createButton(_('Zum Plugin-Marktplatz'), 'http://develop.studip.de:8080/studip/plugins.php/pluginmarket/presenting/details/7a1627dd9550167137da81bc5216ed1c') ?>
    <?= Studip\LinkButton::createButton(_('Zum Plugin-Konfiguration'), URLHelper::getLink('dispatch.php/admin/plugin')) ?>
</p>