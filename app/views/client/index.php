<?php
$MAX_LABEL_LENGTH = 80;
$substr = function ($string) use ($MAX_LABEL_LENGTH) {
    if (strlen($string) <= $MAX_LABEL_LENGTH) {
        return $string;
    }
    return studip_substr($string, 0, $MAX_LABEL_LENGTH) . '...';
};
?>
<h1><?= _('Testclient für OAuth') ?></h1>

<form class="settings" action="<?= $controller->url_for('client/' . $auth) ?>" method="get">
    <input type="hidden" name="target" value="<?= htmlReady($target) ?>">
    
    <fieldset>
        <legend><?= _('Request durchführen') ?></legend>

    <? if ($auth === 'oauth'): ?>
        <div class="type-select">
            <label for="consumer_id"><?= _('Consumer') ?></label>
            <select required name="consumer_id" id="consumer_id">
                <option value=""></option>
            <? foreach ($provider->getConsumers() as $consumer): ?>
                <option value="<?= htmlReady($consumer['id']) ?>" <? if ($consumer['id'] === $consumer_id) echo 'selected'; ?>>
                    <?= htmlReady($consumer['title']) ?>
                </option>
            <? endforeach; ?>
            </select>
        </div>
    <? endif; ?>

    <? if ($auth === 'basic'): ?>
        <div class="type-text auth-http">
            <label for="http-username">HTTP Credentials</label>
            <input class="small" type="text" name="http-username" id="http-username" value="<?= Request::get('http-username') ?>" placeholder="<?= _('Nutzername') ?>">
            <input class="small" type="password" name="http-password" value="<?= Request::get('http-password') ?>" placeholder="<?= _('Passwort') ?>">
        </div>
    <? endif; ?>

    <? if ($discovery): ?>
        <div class="type-select">
            <label for="discovery"><?= _('Resourcen') ?></label>
            <select id="discovery">
                <option value=""></option>
            <? foreach ($discovery as $route => $methods): ?>
                <? foreach ($methods as $method => $label): ?>
                    <option data-method="<?= htmlReady($method) ?>" data-route="<?= htmlReady($route) ?>"
                        <? if (strlen($label) > $MAX_LABEL_LENGTH) printf('title="%s"', htmlReady($label)) ?>
                        <? if (('/' . Request::get('resource', 'discovery')) === $route && Request::option('method', 'GET') === strtoupper($method)) echo 'selected'; ?>>
                        <?= htmlReady($substr($label)) ?>
                    </option>
                <? endforeach; ?>
            <? endforeach; ?>
            </select>
        </div>
    <? endif; ?>

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
                <?= Assets::input('icons/16/blue/add.png', array('class' => 'text-top add')) ?>
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
                    <?= Assets::input('icons/16/blue/guestbook', array('class' => 'text-top', 'class' => 'convert')) ?>
                    <?= Assets::input('icons/16/blue/trash', array('class' => 'text-top', 'class' => 'remove')) ?>
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
        <?= Studip\Button::createAccept(_('Absenden'), 'submit', array('tabindex' => 1)) ?>
        <?= Studip\LinkButton::createCancel(_('Abbrechen'), $controller->url_for('client/' . $auth, compact('target'))) ?>
    </div>
</form>

<? if (isset($result)): ?>
    <h2><?= _('Zurückgeliefertes Ergebnis') ?></h2>
    <pre id="result"><?= htmlReady(is_array($result) ? (defined('JSON_PRETTY_PRINT') ? studip_utf8decode(json_encode(studip_utf8encode($result), JSON_PRETTY_PRINT)) : print_r($result, true)) : $result) ?></pre>
<? endif; ?>
