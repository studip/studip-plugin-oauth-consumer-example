<? use Studip\Button, Studip\LinkButton; ?>

<h1><?= _('Testclient f�r OAuth') ?></h1>

<? if (isset($result)): ?>
    <h2><?= _('Zur�ckgeliefertes Ergebnis') ?></h2>
    <p>MD5: <?= md5(serialize($result)) ?></p>
    <pre style="border: 1px solid #888; background: #ccc; padding: .5em; overflow-x: auto"><?= htmlReady(is_array($result) ? var_dump($result) : $result) ?></pre>
<? endif; ?>

<form class="settings" action="<?= $controller->url_for('client') ?>" method="get">
    <fieldset>
        <legend><?= _('Request durchf�hren') ?></legend>

        <div class="type-text">
            <label for="resource"><?= _('Angeforderte Resource') ?></label>
            <input required type="text" id="resource" name="resource"
                   value="<?= htmlReady(Request::get('resource', 'news/studip')) ?>">
        </div>

        <div class="type-select">
            <label for="method"><?= _('Methode') ?></label>
            <select id="method" name="method">
            <? foreach (words('GET POST PUT DELETE') as $method): ?>
                <option value="<?= $method ?>" <?= Request::option('method', 'GET') === $method ? 'selected' : '' ?>>
                    <?= $method ?>
                </option>
            <? endforeach; ?>
            </select>
        </div>

        <div class="type-checkbox">
            <label for="signed"><?= _('Signiert') ?></label>
            <input type="checkbox" id="signed" name="signed" value="1" <?= Request::int('signed') ? 'checked' : ''?>>
        </div>

        <div class="type-select">
            <label for="format"><?= _('Format') ?></label>
            <select id="format" name="format">
            <? foreach (words('csv json php xml') as $format): ?>
                <option value="<?= $format ?>" <?= Request::option('format', 'json') === $format ? 'selected' : '' ?>>
                    <?= $format ?>
                </option>
            <? endforeach; ?>
            </select>
        </div>

        <div class="type-checkbox">
            <label for="consume"><?= _('R�ckgabe umwandeln') ?></label>
            <input type="checkbox" id="consume" name="consume" value="1" <?= Request::int('consume') ? 'checked' : '' ?>>
        </div>
    </fieldset>

    <div class="type-button">
        <?= Button::createAccept('absenden', 'submit') ?>
    </div>
</form>