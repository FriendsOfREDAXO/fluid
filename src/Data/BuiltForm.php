<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\Routing\Routes;
use Redaxo\Core\Content\Article;
use Yakamara\YForm\Attribute\AsForm;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field;
use Yakamara\YForm\Form\Action\Redirect;
use Yakamara\YForm\Form\Action\SaveRecord;
use Yakamara\YForm\Form\Action\SendMail;
use Yakamara\YForm\Form\Action\Submission;
use Yakamara\YForm\Form\Definition\FormDefinition;
use Yakamara\YForm\Form\Layout\Layout;
use Yakamara\YForm\Form\Layout\Tab;

if (!class_exists(FormDefinition::class)) {
    return;
}

/**
 * Formular, das die Redaktion im Block „Formular“ auf der Website zusammenstellt („Eigenes Formular“).
 *
 * Eine YForm-Formulardefinition wie jede #[AsForm]-Klasse – nur kommen Felder, Schritte und Aktionen aus dem Block
 * statt aus Code. Absenden, Prüfung, CSRF, Honigtopf, Aktionen (SaveRecord, SendMail, Redirect) und der EP
 * YFORM_FORM_SUBMITTED laufen über YForm (FormDefinition::run). Schritte = Reiter des YForm-Layouts; das Kit zeigt sie
 * als Schritte mit „Weiter/Zurück“, Fehler der Server-Prüfung öffnen den richtigen Schritt (Form::activeTab).
 *
 * Feldtypen: text, email, tel, url, textarea, number, date, select, radio, checkboxes, checkbox (Zustimmung),
 * info (Hinweistext), step (neuer Schritt mit Titel).
 */
final class BuiltForm extends FormDefinition
{
    public const array TYPES = [
        'text' => 'Textzeile', 'email' => 'E-Mail', 'tel' => 'Telefon', 'url' => 'Internetadresse', 'textarea' => 'Mehrzeiliger Text',
        'number' => 'Zahl', 'date' => 'Datum', 'select' => 'Auswahlliste', 'radio' => 'Auswahl (eine)', 'checkboxes' => 'Auswahl (mehrere)',
        'checkbox' => 'Häkchen (z. B. Zustimmung)', 'info' => 'Hinweistext (kein Eingabefeld)', 'step' => '— Neuer Schritt —',
    ];

    /** @var array<string, mixed> */
    private array $cfg = [];
    private ?Article $article = null;

    /** @param array<string, mixed> $cfg Daten des Blocks */
    public static function make(string $key, array $cfg, ?Article $article = null): self
    {
        $f = new self(new AsForm('fluid-' . $key, trim((string) ($cfg['form_name'] ?? '')) ?: 'Formular'));
        $f->cfg = $cfg;
        $f->article = $article;

        return $f;
    }

    /** @return list<array{type: string, name: string, label: string, item: array<string, mixed>}> */
    public function items(): array
    {
        $out = [];
        $used = [];
        foreach ((array) ($this->cfg['fields'] ?? []) as $i => $item) {
            if (!\is_array($item) || !isset(self::TYPES[$item['type'] ?? ''])) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $name = trim((string) ($item['name'] ?? '')) ?: str_replace('-', '_', substr(Routes::slugify($label), 0, 40));
            $name = preg_replace('~[^a-z0-9_]~', '', strtolower($name)) ?: 'feld';
            if (preg_match('~^\d~', $name)) {
                $name = 'f_' . $name;
            }
            $base = $name;
            for ($n = 2; isset($used[$name]); ++$n) {
                $name = $base . '_' . $n;
            }
            $used[$name] = true;
            $out[] = ['type' => (string) $item['type'], 'name' => 'step' === $item['type'] ? 'step_' . $i : $name, 'label' => $label, 'item' => $item];
        }

        return $out;
    }

    public function fields(): iterable
    {
        foreach ($this->items() as ['type' => $type, 'name' => $name, 'label' => $label, 'item' => $it]) {
            if ('step' === $type) {
                continue;
            }
            $req = !empty($it['required']);
            $help = trim((string) ($it['help'] ?? ''));
            $ph = trim((string) ($it['placeholder'] ?? ''));
            $options = [];
            foreach (preg_split('~\R~', (string) ($it['options'] ?? '')) ?: [] as $line) {
                if ('' !== ($line = trim($line))) {
                    $options[$line] = $line;
                }
            }
            $field = match ($type) {
                'email' => Field\Email::new($name),
                'tel' => Field\Text::new($name)->attributes(['type' => 'tel', 'autocomplete' => 'tel']),
                'url' => Field\Url::new($name),
                'textarea' => Field\Textarea::new($name),
                'number' => Field\Integer::new($name),
                'date' => Field\Date::new($name),
                'select' => Field\Choice::new($name)->options($options)->placeholder($ph ?: '–'),
                'radio' => Field\Choice::new($name)->options($options)->expanded(),
                'checkboxes' => Field\Choice::new($name)->options($options)->expanded()->multiple(),
                'checkbox' => Field\Checkbox::new($name),
                'info' => Field\Markup::new($name)->html('<p>' . nl2br(htmlspecialchars($label), false) . '</p>'),
                default => Field\Text::new($name)->maxLength(500),
            };
            if ('info' === $type) {
                yield $field;
                continue;
            }
            $field->label($label ?: $name)->required($req);
            if ('' !== $help) {
                $field->notice($help);
            }
            if ('' !== $ph && \in_array($type, ['text', 'tel'], true)) {
                $field->placeholder($ph);
            }
            if ('' !== $ph && \in_array($type, ['email', 'url', 'textarea'], true)) {
                $field->attributes(['placeholder' => $ph]);
            }
            yield $field;
        }
    }

    /** Schritte = Reiter des Layouts (nur, wenn es „Neuer Schritt“ gibt) */
    public function layout(Layout $layout): Layout
    {
        $steps = [];
        $cur = ['label' => trim((string) ($this->cfg['first_step'] ?? '')) ?: null, 'fields' => []];
        foreach ($this->items() as $it) {
            if ('step' === $it['type']) {
                if ($cur['fields']) {
                    $steps[] = $cur;
                }
                $cur = ['label' => $it['label'] ?: null, 'fields' => []];
                continue;
            }
            $cur['fields'][] = $it['name'];
        }
        if ($cur['fields']) {
            $steps[] = $cur;
        }
        if (\count($steps) < 2) {
            return $layout;
        }
        foreach ($steps as $i => $s) {
            $layout->tab($s['label'] ?? ('Schritt ' . ($i + 1)), static function (Tab $t) use ($s): void {
                $t->fields(...$s['fields']);
            });
        }

        return $layout;
    }

    public function actions(): iterable
    {
        $mode = (string) ($this->cfg['mode'] ?? 'save_mail');
        $page = $this->article ? $this->article->name . ' (' . $this->article->getUrl() . ')' : '';
        if (\in_array($mode, ['save', 'save_mail'], true)) {
            $target = trim((string) ($this->cfg['target'] ?? ''));
            yield '' !== $target && YFormBridge::table($target)
                ? new SaveRecord($target)
                : new SaveRecord(FormEntryTable::class, function (Record $r, Submission $s) use ($page): void {
                    $r->set('form', $this->definition->label ?? $this->definition->name);
                    $r->set('page', $page);
                    $r->set('summary', $this->summary($s));
                    $r->set('data', json_encode(array_filter($s->texts(), static fn ($v) => '' !== $v), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                });
        }
        $email = $this->emailField();
        if (\in_array($mode, ['mail', 'save_mail'], true) && '' !== ($to = trim((string) ($this->cfg['mail_to'] ?? '')))) {
            $subject = trim((string) ($this->cfg['mail_subject'] ?? '')) ?: 'Neue Nachricht: ' . ($this->definition->label ?? 'Formular');
            yield new SendMail(to: array_map('trim', explode(',', $to)), subject: $subject, body: fn (Submission $s) => $this->summary($s) . "\n\n– " . $page, replyTo: $email ? '{' . $email . '}' : null);
        }
        if (!empty($this->cfg['confirm']) && $email) {
            $text = trim((string) ($this->cfg['confirm_text'] ?? '')) ?: 'Vielen Dank für Ihre Nachricht. Wir melden uns bald.';
            yield new SendMail(to: '{' . $email . '}', subject: trim((string) ($this->cfg['confirm_subject'] ?? '')) ?: 'Ihre Nachricht ist angekommen',
                body: fn (Submission $s) => $s->interpolate($text) . "\n\n" . ($this->cfg['confirm_copy'] ?? false ? "Ihre Angaben:\n" . $this->summary($s) : ''));
        }
        $redirect = (string) ($this->cfg['redirect'] ?? '');
        if (preg_match('~^redaxo://(\d+)~', $redirect, $m)) {
            yield new Redirect((int) $m[1]);
        }
    }

    public function submitLabel(): string
    {
        return trim((string) ($this->cfg['submit'] ?? '')) ?: 'Absenden';
    }

    public function successMessage(): string
    {
        return nl2br(htmlspecialchars(trim((string) ($this->cfg['success'] ?? '')) ?: 'Vielen Dank! Ihre Nachricht ist angekommen.'), false);
    }

    /** Erstes E-Mail-Feld (Antwortadresse, Bestätigung) */
    private function emailField(): ?string
    {
        foreach ($this->items() as $it) {
            if ('email' === $it['type']) {
                return $it['name'];
            }
        }

        return null;
    }

    /** „Bezeichnung: Wert“ je Zeile – für Mail und Postfach */
    private function summary(Submission $s): string
    {
        $lines = [];
        foreach ($this->items() as $it) {
            if (\in_array($it['type'], ['step', 'info'], true)) {
                continue;
            }
            $v = $s->text($it['name']);
            if ('' !== $v) {
                $lines[] = ($it['label'] ?: $it['name']) . ': ' . (str_contains($v, "\n") ? "\n" . $v : $v);
            }
        }

        return implode("\n", $lines);
    }
}
