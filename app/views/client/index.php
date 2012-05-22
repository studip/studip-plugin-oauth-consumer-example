<h1><?= _('Testclient für OAuth') ?></h1>

<form class="settings" action="<?= $controller->url_for('client') ?>" method="get">
    <fieldset>
        <legend><?= _('Request durchführen') ?></legend>

        <div class="type-text">
            <label for="resource"><?= _('Angeforderte Resource') ?></label>
            <input type="text" id="resource" name="resource"
                   value="<?= htmlReady(Request::get('resource', 'discovery')) ?>">
        </div>

        <div class="type-select">
            <label for="method"><?= _('Methode') ?></label>
            <select id="method" name="method">
            <? foreach (words('GET POST PUT DELETE HEAD OPTIONS') as $method): ?>
                <option value="<?= $method ?>" <?= Request::option('method', 'GET') === $method ? 'selected' : '' ?>>
                    <?= $method ?>
                </option>
            <? endforeach; ?>
            </select>
        </div>

        <div class="type-checkbox">
        <? $signed = Request::optionArray('signed'); ?>
            <label for><?= _('Autorisierung') ?></label>
            <label>
                <input type="checkbox" name="signed[]" value="oauth" <?= in_array('oauth', $signed) ? 'checked' : ''?>>
                OAuth
            </label>
            <label>
                <input type="checkbox" name="signed[]" value="studip" <?= in_array('studip', $signed) ? 'checked' : ''?>>
                Stud.IP
            </label>
            <label>
                <input type="checkbox" name="signed[]" value="http" <?= in_array('http', $signed) ? 'checked' : ''?>>
                HTTP
            </label>
        </div>
        
        <div class="type-text auth-http">
            <label for="http-username">HTTP</label>
            <input class="small" type="text" name="http-username" id="http-username" value="<?= Request::get('http-username') ?>" placeholder="<?= _('Nutzername') ?>">
            <input class="small" type="password" name="http-password" value="<?= Request::get('http-password') ?>" placeholder="<?= _('Passwort') ?>">
        </div>

        <div class="type-select">
            <label for="content_type">Content-Type</label>
            <select id="content_type" name="content_type">
                <option value="">- keine Angabe -</option>
            <? foreach ($controller->content_types as $content_type): ?>
                <option <?= Request::get('content_type', 'application/json') === $content_type ? 'selected' : '' ?>>
                    <?= $content_type ?>
                </option>
            <? endforeach; ?>
            </select>
        </div>
        
        <div id="parameters" class="type-text" style="display:none;">
            <label for>
                <?= _('Parameter') ?>
                <?= Assets::img('icons/16/blue/plus', array('class' => 'text-top')) ?>
            </label>
            <ul>
            <? foreach ($parameters as $key => $value): ?>
                <li>
                    <input class="small" type="text" name="parameters[name][]" value="<?= htmlReady($key) ?>" placeholder="<?= _('Name') ?>">
                    =
                <? if (strpos($value, "\n") !== false): ?>
                    <textarea name="parameters[value][]" class="small" placeholder="<?= _('Wert') ?>"><?= htmlReady($value) ?></textarea>
                <? else: ?>
                    <input class="small" type="text" name="parameters[value][]" value="<?= htmlReady($value) ?>" placeholder="<?= _('Wert') ?>">
                <? endif; ?>
                    <?= Assets::img('icons/16/blue/guestbook', array('class' => 'text-top')) ?>
                    <?= Assets::img('icons/16/blue/trash', array('class' => 'text-top')) ?>
                </li>
            <? endforeach; ?>
            </ul>
        </div>

        <div class="type-checkbox">
            <label for="consume"><?= _('Rückgabe umwandeln') ?></label>
            <input type="checkbox" id="consume" name="consume" value="1" <?= Request::int('consume') ? 'checked' : '' ?>>
        </div>
    </fieldset>

    <div class="type-button">
        <?= Studip\Button::createAccept(_('Absenden'), 'submit') ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->url_for('client/index')) ?>
    </div>
</form>

<? if (isset($result)): ?>
    <h2><?= _('Zurückgeliefertes Ergebnis') ?></h2>
    <pre id="result"><?= htmlReady(is_array($result) ? var_dump($result) : $result) ?></pre>
<? endif; ?>

<script>
$(':checkbox[name="signed[]"][value=http]').change(function () {
    $('.auth-http').stop().toggle(this.checked);
}).change();
</script>