<style>#parameters img { cursor: pointer; }</style>

<h1><?= _('Testclient für OAuth') ?></h1>

<form class="settings" action="<?= $controller->url_for('client') ?>" method="get">
    <fieldset>
        <legend><?= _('Request durchführen') ?></legend>

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
            <? foreach (words('json xml') as $format): ?>
                <option value="<?= $format ?>" <?= Request::option('format', 'json') === $format ? 'selected' : '' ?>>
                    <?= $format ?>
                </option>
            <? endforeach; ?>
            </select>
        </div>
        
        <div id="parameters" class="type-text" style="display:none;">
            <label>
                <?= _('Parameter') ?>
                <?= Assets::img('icons/16/blue/plus', array('class' => 'text-top')) ?>
            </label>
            <ul style="list-style:none;margin:0;padding:0;">
            <? foreach ($parameters as $key => $value): ?>
                <li>
                    <input class="small" type="text" name="parameters[name][]" value="<?= htmlReady($key) ?>" placeholder="<?= _('Name') ?>">
                    =
                    <input class="small" type="text" name="parameters[value][]" value="<?= htmlReady($value) ?>" placeholder="<?= _('Wert') ?>">
                    <?= Assets::img('icons/16/blue/minus', array('class' => 'text-top')) ?>
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
        <?= Studip\Button::createAccept('absenden', 'submit') ?>
    </div>
</form>

<? if (isset($result)): ?>
    <h2><?= _('Zurückgeliefertes Ergebnis') ?></h2>
    <pre style="border: 1px solid #888; background: #ccc; padding: .5em; height: 20em; overflow: auto"><?= htmlReady(is_array($result) ? var_dump($result) : $result) ?></pre>
<? endif; ?>

<script>
jQuery(function ($) {
    $('#parameters')
        .show()
        .delegate('label img', 'click', function () {
            $('#parameters ul li:first').clone().find('input').val('').end().appendTo('#parameters ul');
        })
        .delegate('ul img', 'click', function () {
            var li = $(this).closest('li');
            if (li.siblings().length) {
                li.remove();
            } else {
                li.find('input').val('');
            }
        });
});
</script>