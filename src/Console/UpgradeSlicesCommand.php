<?php

namespace FriendsOfRedaxo\Fluid\Console;

use FriendsOfRedaxo\Fluid\BlockModule;
use Redaxo\Core\Console\AbstractCommand;
use Redaxo\Core\Cache;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ArticleSliceReference;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_filter;
use function array_keys;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;
use function str_starts_with;

/**
 * Übernimmt Blöcke einer älteren REDAXO-6-Datenbank in das Slice-Format des Cores ab 9132fc1/fa458f9.
 *
 * Der Core migriert Entwicklungs-Datenbanken nicht selbst: Die festen Spalten value1–20, media1–10 … gibt es nicht mehr,
 * die Werte der Module stehen als JSON in `data`, und `ctype_id` heißt `content_section_id`. `migrate` legt die neuen
 * Spalten leer an. Dieser Befehl benennt um (oder überträgt, wenn `migrate` schon lief), legt `data` an und füllt sie
 * aus den alten Spalten: Block-Module von fluid → {values, tunes}, YForm-Formular → {form}. Mehrfach ausführbar; die
 * alten Spalten bleiben, bis --drop-old sie entfernt.
 */
#[AsCommand(name: 'fluid:upgrade:slices', description: 'Übernimmt Blöcke älterer REDAXO-6-Datenbanken in die Spalte data (vor oder nach migrate)')]
final class UpgradeSlicesCommand extends AbstractCommand
{
    private const array TABLES = ['rex_article_slice', 'rex_article_slice_history'];

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Alte Spalten (ctype_id, value1–20, media…, link…) danach entfernen')] bool $dropOld = false,
    ): int {
        foreach (self::TABLES as $name) {
            $table = Table::get($name);
            if (!$table->exists()) {
                continue;
            }
            $io->section($name);

            // ctype_id → content_section_id (lief migrate schon, gibt es beide: Werte übertragen)
            if ($table->hasColumn('ctype_id')) {
                if ($table->hasColumn('content_section_id')) {
                    Sql::factory()->setQuery('UPDATE ' . $name . ' SET content_section_id = ctype_id WHERE content_section_id = 0');
                    // der Core befüllt ctype_id nicht mehr: als Pflichtfeld ohne Standardwert scheitert sonst jedes neue Slice
                    $table->ensureColumn(Column::smallint('ctype_id', unsigned: true, nullable: true))->alter();
                    $io->text('content_section_id aus ctype_id übernommen, ctype_id optional');
                } else {
                    $table->renameColumn('ctype_id', 'content_section_id')->alter();
                    $io->text('ctype_id → content_section_id');
                }
            }
            if (!$table->hasColumn('data')) {
                $table->ensureColumn(Column::json('data', nullable: true))->alter();
                $io->text('Spalte data angelegt');
            }

            $count = $table->hasColumn('value1') ? self::fill($name) : 0;
            $io->text($count . ' Slices übernommen');

            if ($dropOld) {
                $table = Table::get($name);
                $old = array_filter(array_keys($table->getColumns()), static fn (string $c) => 'ctype_id' === $c || 1 === preg_match('~^(value|media|medialist|link|linklist)\d+$~', $c));
                foreach ($old as $c) {
                    $table->removeColumn($c);
                }
                if ($old) {
                    $table->alter();
                    $io->text(count($old) . ' alte Spalten entfernt');
                }
            }
        }
        Cache::delete();   // Inhalte und Klassen neu aufbauen
        if (Table::get('rex_article_slice_reference')->exists()) {
            $io->text(ArticleSliceReference::rebuild() . ' Verweise (Medien, Seiten) neu aufgebaut');
        }
        $io->success('Fertig. Danach (falls noch nicht geschehen) `bin/console migrate`.');

        return Command::SUCCESS;
    }

    /** Slices ohne data aus value1/value2 füllen */
    private static function fill(string $table): int
    {
        $blocks = array_keys(BlockModule::all());
        $rows = Sql::factory()->getArray('SELECT id, module, value1, value2 FROM ' . $table . ' WHERE data IS NULL');
        $count = 0;
        foreach ($rows as $r) {
            $module = (string) $r['module'];
            $data = match (true) {
                in_array($module, $blocks, true), str_starts_with($module, 'fluid.') => BlockModule::store(self::json($r['value1']), self::json($r['value2'])),
                'yform_form' === $module => ['form' => (string) $r['value1']],
                default => [],
            };
            if ([] === $data) {
                continue;
            }
            Sql::factory()->setQuery('UPDATE ' . $table . ' SET data = ? WHERE id = ?', [ArticleSlice::encodeData($data), (int) $r['id']]);
            ++$count;
        }

        return $count;
    }

    /** @return array<string, mixed> */
    private static function json(mixed $value): array
    {
        $v = is_string($value) && '' !== $value ? json_decode($value, true) : null;

        return is_array($v) ? $v : [];
    }
}
