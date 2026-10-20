<?php

namespace FriendsOfRedaxo\Fluid\Mail;

use FriendsOfRedaxo\Fluid\Kit;
use FriendsOfRedaxo\Fluid\Settings;
use Redaxo\Core\Core;
use Redaxo\Core\Cronjob\AsCronjob;
use Redaxo\Core\Env;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Mailer\MailArchive;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\MediaPool\Media;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

use function count;
use function is_dir;
use function is_file;

use function Redaxo\Core\View\escape;

/**
 * Baustein „Mail“: alles, was fluid mit dem Mailer des Cores macht (Redaxo\Core\Mailer, Symfony Mailer).
 *
 *  - status()   Einrichtung auf einen Blick (Statusseite): Versand, Absender, Umleitung, Protokoll, Archiv, letzte Fehler
 *  - test()     Testmail an eine Adresse
 *  - failed()/resend()   nicht gesendete Mails aus dem Archiv (not_sent_*.eml) erneut senden – an X-Envelope-To
 *                        (inklusive Bcc, ab redaxo/core#6755), sonst An und Kopie
 *  - layout()   HTML-Mail im Stil der Website (Logo, Akzentfarbe des Kits, Kontakt im Fuß) – für Formular-Mails
 *  - purgeArchive()   Cronjob: archivierte Mails nach „Mails aufbewahren (Tage)“ löschen (Datenschutz)
 *
 * Eigenständig (später als eigenes AddOn denkbar): braucht nur den Core und die Website-Einstellungen von fluid.
 */
final class Mail
{
    /** Standard für „Mails aufbewahren (Tage)“, wenn nichts eingestellt ist */
    public const int KEEP_DAYS = 90;

    /**
     * @return array{configured: bool, transport: string, from: string, detour: list<string>, log: string, archive: bool,
     *     error: string, errors: list<array{time: int, to: string, subject: string, error: string}>}
     */
    public static function status(): array
    {
        $out = ['configured' => false, 'transport' => '', 'from' => (string) Env::get('REX_MAILER_FROM'), 'detour' => [],
            'log' => (string) (Env::get('REX_MAILER_LOG') ?? 'errors'), 'archive' => Env::getBool('REX_MAILER_ARCHIVE'), 'error' => '', 'errors' => []];
        try {
            $mailer = Core::getMailer();
            // ohne Zugangsdaten: SMTP-Transporte geben nur Schema, Host und Port aus
            $out['transport'] = (string) $mailer->transport;
            $out['configured'] = !str_starts_with($out['transport'], 'null://');
            $out['from'] = $mailer->defaultFrom?->toString() ?? '';
            $out['detour'] = array_map(static fn (Address $a) => $a->getAddress(), $mailer->detourRecipients);
        } catch (Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        if (is_file(Mailer::logFile())) {
            foreach (LogFile::factory(Mailer::logFile()) as $entry) {
                $d = $entry->getData();
                if ('ERROR' === ($d[0] ?? '')) {
                    $out['errors'][] = ['time' => $entry->getTimestamp(), 'to' => (string) ($d[2] ?? ''), 'subject' => (string) ($d[3] ?? ''), 'error' => (string) ($d[4] ?? '')];
                    if (count($out['errors']) >= 5) {
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /** Testmail; gibt '' bei Erfolg zurück, sonst die Fehlermeldung */
    public static function test(string $to): string
    {
        try {
            $name = Settings::name();
            Core::getMailer()->send(new Email()->to($to)->subject('Testmail von ' . $name . ' · ' . date('d.m.Y H:i'))
                ->text('Diese Testmail kommt von der Website „' . $name . '“ (fluid, REDAXO 6). Der Mailversand funktioniert.')
                ->html(self::layout('Der Mailversand funktioniert', '<p>Diese Testmail kommt von der Website „' . escape($name) . '“.</p>')), archive: false);

            return '';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    // ------------------------------------------------------------------ nicht gesendete Mails

    /**
     * Nicht gesendete Mails im Archiv (REX_MAILER_ARCHIVE), neueste zuerst.
     *
     * @return list<array{file: string, time: int, to: string, subject: string}>
     */
    public static function failed(int $limit = 20): array
    {
        $dir = MailArchive::folder();
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_starts_with($f->getFilename(), 'not_sent_') && str_ends_with($f->getFilename(), '.eml')) {
                $files[$f->getPathname()] = $f->getMTime();
            }
        }
        arsort($files);
        $out = [];
        foreach (array_slice($files, 0, $limit, true) as $file => $time) {
            $h = self::headers((string) File::get($file));
            $out[] = ['file' => self::relative($file), 'time' => $time, 'to' => $h['to'], 'subject' => $h['subject']];
        }

        return $out;
    }

    /**
     * Nicht gesendete Mail erneut senden. Empfänger aus To, Cc und Bcc; die Bcc-Kopfzeile wird vor dem Senden entfernt
     * (sonst sähen alle Empfänger die Bcc-Adressen). Bei Erfolg verliert die Datei das Präfix „not_sent_“.
     * Gibt '' bei Erfolg zurück, sonst die Fehlermeldung.
     */
    public static function resend(string $relative): string
    {
        $file = self::absolute($relative);
        if (null === $file) {
            return 'Datei nicht gefunden.';
        }
        $raw = (string) File::get($file);
        [$head, $body] = array_pad(preg_split("~\r?\n\r?\n~", $raw, 2) ?: [], 2, '');
        $headers = self::unfold($head);
        // Empfänger: X-Envelope-To (vermerkt der Core seit redaxo/core#6755, inklusive Bcc), sonst An, Kopie, Bcc
        $recipients = [];
        foreach (isset($headers['x-envelope-to']) ? ['x-envelope-to'] : ['to', 'cc', 'bcc'] as $name) {
            foreach (self::addresses($headers[$name] ?? '') as $a) {
                $recipients[$a] = new Address($a);
            }
        }
        $from = self::addresses($headers['from'] ?? '')[0] ?? null;
        if (!$recipients || null === $from) {
            return 'Empfänger oder Absender fehlen in der Mail.';
        }
        // Bcc und X-Envelope-To (samt Folgezeilen) aus dem Kopf entfernen – sonst sähen alle die Bcc-Adressen
        $cleanHead = (string) preg_replace('~^(?:Bcc|X-Envelope-To):.*(?:\r?\n[ \t].*)*\r?\n?~mi', '', $head);
        try {
            Core::getMailer()->send(new RawMessage($cleanHead . "\r\n\r\n" . $body), new Envelope(new Address($from), array_values($recipients)), archive: false);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        File::move($file, \dirname($file) . '/' . substr(basename($file), \strlen('not_sent_')));

        return '';
    }

    /** @return array{to: string, subject: string} */
    private static function headers(string $raw): array
    {
        $h = self::unfold((string) (preg_split("~\r?\n\r?\n~", $raw, 2)[0] ?? ''));
        $subject = (string) (iconv_mime_decode((string) ($h['subject'] ?? ''), ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: ($h['subject'] ?? ''));

        return ['to' => implode(', ', self::addresses($h['x-envelope-to'] ?? (($h['to'] ?? '') . ',' . ($h['cc'] ?? '')))), 'subject' => $subject];
    }

    /** @return array<string, string> Kopfzeilen (Kleinbuchstaben), Folgezeilen zusammengefügt */
    private static function unfold(string $head): array
    {
        $out = [];
        foreach (explode("\n", (string) preg_replace("~\r?\n[ \t]+~", ' ', $head)) as $line) {
            if (preg_match('~^([A-Za-z-]+):\s*(.*)$~', rtrim($line), $m)) {
                $out[strtolower($m[1])] = $m[2];
            }
        }

        return $out;
    }

    /** @return list<string> E-Mail-Adressen aus einer Adress-Kopfzeile */
    private static function addresses(string $value): array
    {
        preg_match_all('~[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}~', $value, $m);

        return array_values(array_unique($m[0]));
    }

    private static function relative(string $file): string
    {
        return ltrim(substr($file, \strlen(MailArchive::folder())), '/');
    }

    /** Pfad im Archiv – nur echte not_sent_*.eml unterhalb des Archivs */
    private static function absolute(string $relative): ?string
    {
        if (!preg_match('~^\d{4}/\d{2}/not_sent_[\w-]+\.eml$~', $relative)) {
            return null;
        }
        $file = MailArchive::folder() . '/' . $relative;

        return is_file($file) ? $file : null;
    }

    // ------------------------------------------------------------------ Layout

    /**
     * HTML-Mail im Stil der Website: Logo bzw. Name, Akzentfarbe aus dem Design des Kits, Inhalt, Fuß mit Anschrift und
     * Impressum. Tabellen und Inline-Stile, damit es auch in Outlook und Webmailern hält.
     */
    public static function layout(string $title, string $contentHtml): string
    {
        $design = Kit::active()->design();
        $accent = self::hex((string) $design->get('accent'), '#4338CA');
        $onAccent = self::hex((string) $design->get('on_accent'), '#FFFFFF');
        $ink = self::hex((string) $design->get('ink'), '#0B0D17');
        $text = self::hex((string) $design->get('text'), '#2B2F3A');
        $surface = self::hex((string) $design->get('surface'), '#F4F5F8');
        $name = Settings::name();
        $logo = (string) Settings::get('logo', '');
        // SVG zeigen Gmail und Outlook in Mails nicht an – dann der Name als Schriftzug
        $logoUrl = '' !== $logo && !str_ends_with(strtolower($logo), '.svg') && ($m = Media::get($logo)) ? \FriendsOfRedaxo\Fluid\Routing\Router::absolute($m->getUrl()) : '';
        $brand = '' !== $logoUrl
            ? '<img src="' . escape($logoUrl) . '" alt="' . escape($name) . '" height="40" style="display:block;height:40px;width:auto;border:0">'
            : '<span style="font:700 20px/1.2 Arial,Helvetica,sans-serif;color:' . $ink . '">' . escape($name) . '</span>';
        $address = implode(' · ', array_filter([trim((string) Settings::get('street', '')), trim(Settings::get('zip', '') . ' ' . Settings::get('city', '')), trim((string) Settings::get('phone', '')), trim((string) Settings::get('email', ''))]));
        $imprint = trim((string) Settings::get('imprint_page', ''));
        $imprintUrl = '' !== $imprint && function_exists('link_href') ? \FriendsOfRedaxo\Fluid\Routing\Router::absolute(link_href($imprint)) : '';
        $font = 'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif';

        // kein <title>: der Textteil entsteht aus dem HTML (strip_tags) und hätte ihn sonst doppelt
        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
            . '<body style="margin:0;padding:0;background:' . $surface . '">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $surface . '"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:10px;overflow:hidden">'
            . '<tr><td style="padding:24px 28px;border-bottom:4px solid ' . $accent . '"><div>' . $brand . '</div></td></tr>'
            . '<tr><td style="padding:28px;' . $font . ';font-size:16px;line-height:1.55;color:' . $text . '">'
            . '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.25;color:' . $ink . '">' . escape($title) . '</h1>' . $contentHtml . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:' . $accent . ';' . $font . ';font-size:13px;line-height:1.5;color:' . $onAccent . '">'
            . escape($name) . ('' !== $address ? '<br>' . escape($address) : '')
            . ('' !== $imprintUrl ? '<br><a href="' . escape($imprintUrl) . '" style="color:' . $onAccent . '">Impressum</a>' : '')
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private static function hex(string $value, string $fallback): string
    {
        return preg_match('~^#[0-9A-Fa-f]{3,8}$~', $value) ? $value : $fallback;
    }

    // ------------------------------------------------------------------ Aufbewahrung

    /** Cronjob: archivierte Mails löschen, die älter sind als „Mails aufbewahren (Tage)“ (0 = nie löschen) */
    #[AsCronjob('fluid_mail_archive_purge', '@daily')]
    public static function purgeArchive(): string
    {
        $raw = trim((string) Settings::get('mail_keep_days', ''));
        $days = '' === $raw ? self::KEEP_DAYS : (int) $raw;
        if ($days <= 0) {
            return 'Aufbewahrung unbegrenzt – nichts gelöscht.';
        }

        return MailArchive::purge(new \DateTimeImmutable('-' . $days . ' days')) . ' archivierte Mails gelöscht (älter als ' . $days . ' Tage).';
    }
}
