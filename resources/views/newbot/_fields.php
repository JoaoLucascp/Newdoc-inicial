<?php foreach ($fields as $field): ?>
    <?php
    $name = $field['name'];
    $id = $prefix . '-' . $name;
    $value = $values[$name] ?? ($field['default'] ?? '');
    $required = !empty($field['required']);
    ?>
    <?php if ($field['type'] === 'secret'): ?>
        <div class="form-field field-wide"><label for="<?= h($id) ?>"><span><?= h($field['label']) ?></span></label>
            <input class="form-input" id="<?= h($id) ?>" name="<?= h($name) ?>" type="password" autocomplete="new-password">
            <small class="field-help"><?= !empty($secretFields[$name]) ? 'Configurada. Deixe em branco para manter. Use o olho para consultar a senha salva.' : 'Será criptografada antes de ser gravada.' ?></small>
            <?php if (!empty($secretFields[$name])): ?>
                <?php
                $credentialTarget = match ($section['type']) {
                    'administrators' => 'newbot_administrator',
                    'integrations' => 'newbot_integration',
                    default => null,
                };
                if ($credentialTarget !== null) {
                    $credentialId = $recordId;
                    require dirname(__DIR__) . '/partials/credential.php';
                }
                ?>
                <label class="checkbox-label"><input type="checkbox" name="clear_<?= h($name) ?>" value="1" <?= ($values['clear_' . $name] ?? '') === '1' ? 'checked' : '' ?>> Remover a credencial</label>
            <?php endif; ?>
        </div>
    <?php elseif ($field['type'] === 'textarea'): ?>
        <label class="form-field field-wide" for="<?= h($id) ?>"><span><?= h($field['label']) ?><?= $required ? ' *' : '' ?></span>
            <textarea class="form-input" id="<?= h($id) ?>" name="<?= h($name) ?>" rows="3" <?= isset($field['max']) ? 'maxlength="' . h($field['max']) . '"' : '' ?> <?= $required ? 'required' : '' ?>><?= h($value) ?></textarea>
        </label>
    <?php elseif ($field['type'] === 'select'): ?>
        <label class="form-field" for="<?= h($id) ?>"><span><?= h($field['label']) ?><?= $required ? ' *' : '' ?></span><select class="form-select" id="<?= h($id) ?>" name="<?= h($name) ?>" <?= $required ? 'required' : '' ?>>
            <option value="">Não informado</option><?php foreach ($field['options'] as $option): ?><option value="<?= h($option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>><?= h(ucfirst(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?>
        </select></label>
    <?php else: ?>
        <?php $inputType = in_array($field['type'], ['date', 'email', 'number'], true) ? $field['type'] : 'text'; ?>
        <label class="form-field" for="<?= h($id) ?>"><span><?= h($field['label']) ?><?= $required ? ' *' : '' ?></span>
            <input class="form-input" id="<?= h($id) ?>" name="<?= h($name) ?>" type="<?= h($inputType) ?>" value="<?= h($value) ?>"
                   <?= isset($field['max']) ? 'maxlength="' . h($field['max']) . '"' : '' ?> <?= isset($field['min']) ? 'min="' . h($field['min']) . '"' : '' ?> <?= $required ? 'required' : '' ?>>
        </label>
    <?php endif; ?>
<?php endforeach; ?>
