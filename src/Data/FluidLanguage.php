<?php

namespace FriendsOfRedaxo\Fluid\Data;

use Override;
use Redaxo\Core\Language\Language;
use Yakamara\YForm\Field\Choice;

// Nur mit YForm (fluid läuft auch ohne)
if (!class_exists(Choice::class)) {
    return;
}

/**
 * YForm-Feld „Sprache“ (Modell aus KLXM Studio: Eintrag mit Sprache): die Datenliste von fluid zeigt nur Einträge der
 * aktuellen Sprache – und solche ohne Sprache („Alle Sprachen“, auch alle Einträge von vor dem Feld).
 *
 *     yield FluidLanguage::new('lang');
 *
 * Gespeichert wird der Code der Sprache (de, en …). Übersetzen heißt: den Eintrag in der anderen Sprache anlegen.
 */
final class FluidLanguage extends Choice
{
    #[Override]
    protected function init(): void
    {
        parent::init();
        $this->label('Sprache');
        $this->default('');
        $this->options(static function (): array {
            $out = ['' => 'Alle Sprachen'];
            foreach (Language::getAll() as $l) {
                $out[$l->code] = $l->name;
            }

            return $out;
        });
    }
}
