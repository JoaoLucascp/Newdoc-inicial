<?php foreach ($fields as $field): ?>
    <?php
    $name = $field['name'];
    $id = $prefix . '-' . $name;
    $value = $values[$name] ?? ($field['default'] ?? '');
    $required = !empty($field['required']);
    ?>
    <?php if ($field['type'] === 'secret'): ?>
        <div class="form-field field-wide">
            <label for="<?= h($id) ?>"><span><?= h($field['label']) ?></span></label>
            <input class="form-input" id="<?= h($id) ?>" name="<?= h($name) ?>" type="password" autocomplete="new-password">
            <small class="field-help">
                <?= !empty($secretFields[$name]) ? 'Configurada. Deixe em branco para manter. Use o olho para consultar a senha salva.' : 'Será criptografada antes de ser gravada.' ?>
            </small>
            <?php if (!empty($secretFields[$name])): ?>
                <?php
                if (($section['type'] ?? '') === 'extensions' && $name === 'registration_password' && (int) ($recordId ?? 0) > 0) {
                    $credentialTarget = 'newsic_extension';
                    $credentialId = (int) $recordId;
                    require dirname(__DIR__) . '/partials/credential.php';
                }
                ?>
                <label class="checkbox-label"><input type="checkbox" name="clear_<?= h($name) ?>" value="1"
                    <?= ($values['clear_' . $name] ?? '') === '1' ? 'checked' : '' ?>> Remover a credencial</label>
            <?php endif; ?>
        </div>
    <?php elseif ($field['type'] === 'checkbox'): ?>
        <label class="checkbox-label">
            <input type="checkbox" id="<?= h($id) ?>" name="<?= h($name) ?>" value="1" <?= (int) $value === 1 ? 'checked' : '' ?>>
            <?= h($field['label']) ?>
        </label>
    <?php else: ?>
        <label class="form-field" for="<?= h($id) ?>"><span><?= h($field['label']) ?><?= $required ? ' *' : '' ?></span>
            <?php if ($field['type'] === 'textarea'): ?>
                <textarea class="form-input" id="<?= h($id) ?>" name="<?= h($name) ?>" rows="3"
                    <?= isset($field['max']) ? 'maxlength="' . h($field['max']) . '"' : '' ?> <?= $required ? 'required' : '' ?>><?= h($value) ?></textarea>
            <?php elseif ($field['type'] === 'select'): ?>
                <select class="form-select" id="<?= h($id) ?>" name="<?= h($name) ?>">
                    <option value="">Não informado</option>
                    <?php foreach ($field['options'] as $optionValue => $optionLabel): ?>
                        <option value="<?= h($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input class="form-input" id="<?= h($id) ?>" name="<?= h($name) ?>"
                       type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>"
                       value="<?= h($value) ?>"
                       <?= isset($field['max']) ? 'maxlength="' . h($field['max']) . '"' : '' ?>
                       <?= isset($field['min']) ? 'min="' . h($field['min']) . '"' : '' ?>
                       <?= $required ? 'required' : '' ?>>
            <?php endif; ?>
        </label>
    <?php endif; ?>
<?php endforeach; ?>
