<?php

namespace FriendsOfRedaxo\Fluid\Data;

use FriendsOfRedaxo\Fluid\Activity;
use Redaxo\Core\Log\Logger;
use Throwable;
use Yakamara\YForm\Form\Action\Action;
use Yakamara\YForm\Form\Action\SendMail;
use Yakamara\YForm\Form\Action\Submission;

/**
 * Mail einer Formular-Aktion (YForm `SendMail`), deren Fehlschlag die Eingabe nicht verwirft.
 *
 * Ohne diese Hülle bricht YForm bei einem Mailfehler alle weiteren Aktionen ab und zeigt „Fehler – bitte später noch
 * einmal“ – obwohl der Eintrag schon gespeichert ist. Besucher schicken dann erneut ab (doppelte Einträge), die
 * Bestätigung fällt aus. Ist der Eintrag gespeichert ($saved), wird der Fehler protokolliert (Systemprotokoll,
 * Aktivität) und die nächste Aktion läuft weiter; ohne gespeicherten Eintrag bleibt es beim Fehler (sonst gingen Daten
 * verloren).
 */
final class SafeMail extends Action
{
    public const string TEAM = 'team';
    public const string CONFIRM = 'confirm';

    /** @var list<string> In dieser Anfrage gescheiterte Mails (TEAM, CONFIRM) */
    public static array $failed = [];

    public function __construct(
        private readonly SendMail $mail,
        private readonly string $kind,
        private readonly bool $saved,
    ) {}

    public function execute(Submission $submission): void
    {
        try {
            $this->mail->execute($submission);
        } catch (Throwable $e) {
            if (!$this->saved) {
                throw $e;
            }
            self::$failed[] = $this->kind;
            Logger::logException($e);
            Activity::log('form_mail_failed', null, (self::TEAM === $this->kind ? 'Benachrichtigung' : 'Bestätigung') . ': ' . $e->getMessage());
        }
    }
}
