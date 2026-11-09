<?php
/**
 * Code – eine oder mehrere Dateien als Codebeispiel, maskiert ausgegeben (kein HTML aus dem Feld), mit Kopier-Knopf (js/code.js).
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
$langs = ['php' => 'PHP', 'bash' => 'Terminal', 'env' => '.env', 'json' => 'JSON', 'yaml' => 'YAML', 'html' => 'HTML', 'css' => 'CSS', 'js' => 'JavaScript', 'text' => 'Text'];
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['code'] ?? '')) !== ''));
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <div class="code-files">
    <?php foreach ($items as $i => $it): $lang = (string) ($it['lang'] ?? 'text'); $file = trim((string) ($it['file'] ?? '')); ?>
    <figure class="code">
      <figcaption class="code__head">
        <span class="code__file"<?= $b->edit("items.$i.file") ?>><?= e($file !== '' ? $file : ($langs[$lang] ?? $lang)) ?></span>
        <?php if ($file !== ''): ?><span class="code__lang"><?= e($langs[$lang] ?? $lang) ?></span><?php endif; ?>
        <button type="button" class="code__copy" data-code-copy hidden><?= icon('copy') ?><span data-code-label><?= e(lt('Kopieren')) ?></span></button>
      </figcaption>
      <pre class="code__pre" tabindex="0"><code class="lang-<?= e($lang) ?>"><?= e(rtrim((string) $it['code'])) ?></code></pre>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php if (trim((string) ($d['note'] ?? '')) !== ''): ?><p class="code__note"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
</div>
