<?php $title = 'Erro interno'; ?>
<div class="error-card"><span class="error-code">500</span><h1>Não foi possível concluir a solicitação</h1><p>O erro foi registrado sem dados enviados. Informe esta referência ao suporte: <code><?= h($reference ?? '') ?></code></p><a class="button button-primary" href="/">Voltar ao início</a></div>
