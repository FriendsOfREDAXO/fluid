<?php
/**
 * Kontakt: Angaben (Adresse, Telefon/E-Mail, Öffnungszeiten) als Karten-Raster, daneben Karte und/oder Formular (aus KLXM
 * Studio). Sidebar-Muster: Formular/Karte nehmen den großen Teil ein und rutschen bei wenig Platz unter die Angaben.
 * Alles aus „Website“; Formular = YForm-Tabelle (FriendsOfRedaxo\Fluid\Data\YFormBridge).
 * @var \FriendsOfRedaxo\Fluid\Block $b  @var array $d
 */
use FriendsOfRedaxo\Fluid\Data\YFormBridge;
use FriendsOfRedaxo\Fluid\Maps\Maps;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;

$address = fluid_address_lines();
$phone = fluid_phone();
$tel = fluid_phone_href();
$email = fluid_email();
$hours = !empty($d['show_hours']) ? fluid_hours() : [];
$hoursNote = trim((string) setting('hours_note', ''));
$map = !empty($d['show_map']) && Maps::siteLocation() ? Maps::renderBlock(['location' => 'site', 'zoom' => 15, 'height' => 'm', 'route' => true]) : '';
$table = YFormBridge::table((string) ($d['form_table'] ?? ''));
$form = $table ? YFormBridge::form($table, [
    'id' => $b->domId() . '-f',
    'action' => Url::article($b->slice->articleId, Language::getCurrentId()),
    'submit' => (string) ($d['submit_label'] ?? ''),
    'success' => (string) ($d['success'] ?? ''),
    'notify' => trim((string) ($d['notify'] ?? '')) ?: $email,
    'subject' => 'Anfrage über die Website',
]) : '';
$c = $b->central('Adresse, Telefon, E-Mail und Zeiten unter „Website“ (Menü ⋯) ändern');
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <div class="contact<?= ('' !== $form || '' !== $map) ? ' contact--aside' : '' ?>">
    <div class="contact__info"<?= $c ?>>
      <?php if ($address || is_editing()): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('map-pin') ?></span>
        <h3 class="contact__h"><?= e(lt('Adresse')) ?></h3>
        <address class="contact__address"><?= e(fluid_name()) ?><?php if ($address): ?><br><?= implode('<br>', array_map('e', $address)) ?><?php else: ?><br>[<?= e('Adresse unter „Website“ eintragen') ?>]<?php endif; ?></address>
      </div>
      <?php endif; ?>
      <?php if (($tel && '' !== $phone) || '' !== $email || is_editing()): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('chat-circle-text') ?></span>
        <h3 class="contact__h"><?= e(lt('Telefon & E-Mail')) ?></h3>
        <ul class="contact__list" role="list">
          <?php if ($tel && '' !== $phone): ?><li><span class="contact__label"><?= e(lt('Telefon')) ?></span><a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ('' !== $email): ?><li><span class="contact__label"><?= e(lt('E-Mail')) ?></span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if (!($tel && '' !== $phone) && '' === $email): ?><li>[<?= e('Telefon und E-Mail unter „Website“ eintragen') ?>]</li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($hours): ?>
      <div class="contact__card card">
        <span class="contact__ico"><?= icon('clock') ?></span>
        <h3 class="contact__h"><?= e(lt('Öffnungszeiten')) ?></h3>
        <dl class="hours"><?php foreach ($hours as $h): ?><div class="hours__row"><dt><?= e($h['days']) ?></dt><dd><?= e($h['time']) ?></dd></div><?php endforeach; ?></dl>
        <?php if ('' !== $hoursNote): ?><p class="contact__note"><?= e($hoursNote) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ('' !== $form || '' !== $map): ?>
    <div class="contact__main">
      <?php if ('' !== $form): ?>
      <div class="contact__form dff-wrap card">
        <?php if ('' !== trim((string) ($d['form_title'] ?? ''))): ?><h3 class="contact__formtitle h3"<?= $b->edit('form_title') ?>><?= fluid_title((string) $d['form_title']) ?></h3><?php endif; ?>
        <?= $form ?>
      </div>
      <?php endif; ?>
      <?php if ('' !== $map): ?><div class="contact__map"><?= $map ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ('' !== (string) ($d['note'] ?? '') || is_editing()): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= fluid_title((string) ($d['note'] ?? '')) ?></p><?php endif; ?>
</div>
