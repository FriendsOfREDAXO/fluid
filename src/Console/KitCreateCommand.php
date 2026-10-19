<?php

namespace FriendsOfRedaxo\Fluid\Console;

use FriendsOfRedaxo\Fluid\Kit;
use Redaxo\Core\Console\Command\AbstractCommand;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_array;
use function is_dir;
use function is_file;

/**
 * Legt ein eigenes Kit im Projekt an (`src/Kit/{Name}/`), das von einem vorhandenen Kit erbt.
 *
 * Das neue Kit bringt zunächst nur ein eigenes Stylesheet mit; alles andere kommt vom Eltern-Kit. Mit --view=hero,cards
 * und --template werden einzelne Dateien des Eltern-Kits zum Anpassen kopiert.
 */
#[AsCommand(name: 'fluid:kit:create', description: 'Legt ein eigenes Kit im Projekt an, das von einem Kit erbt (Standard: fluid)')]
final class KitCreateCommand extends AbstractCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Schlüssel des Kits, z. B. nature (Kleinbuchstaben, Ziffern, Bindestrich)')] string $key,
        #[Option(description: 'Name in der Kit-Auswahl, z. B. „Nature“')] string $label = '',
        #[Option(description: 'Eltern-Kit, von dem das neue Kit erbt')] string $parent = Kit::DEFAULT,
        #[Option(description: 'Views des Eltern-Kits zum Anpassen kopieren, z. B. --view=hero,cards')] string $view = '',
        #[Option(description: 'Seitenrahmen (template.view.php) zum Anpassen kopieren')] bool $template = false,
    ): int {
        if (!preg_match('~^[a-z][a-z0-9-]{1,30}$~', $key)) {
            $io->error('Schlüssel: Kleinbuchstaben, Ziffern und Bindestrich, mit einem Buchstaben am Anfang (2–31 Zeichen).');

            return Command::FAILURE;
        }
        if (null !== Kit::get($key)) {
            $io->error('Ein Kit „' . $key . '“ gibt es schon.');

            return Command::FAILURE;
        }
        $parentKit = Kit::get($parent);
        if (null === $parentKit) {
            $io->error('Eltern-Kit „' . $parent . '“ gibt es nicht (' . implode(', ', array_keys(Kit::all())) . ').');

            return Command::FAILURE;
        }

        [$namespace, $srcDir] = self::projectSource();
        $name = str_replace(' ', '', ucwords(str_replace('-', ' ', $key)));
        $dir = $srcDir . '/Kit/' . $name;
        if (is_dir($dir)) {
            $io->error('Den Ordner ' . Path::relative($dir) . ' gibt es schon.');

            return Command::FAILURE;
        }
        $label = '' !== $label ? $label : $name;
        $class = $name . 'Kit';
        $ns = $namespace . 'Kit\\' . $name;

        File::put($dir . '/' . $class . '.php', <<<PHP
            <?php

            namespace {$ns};

            use FriendsOfRedaxo\\Fluid\\AsKit;
            use FriendsOfRedaxo\\Fluid\\Kit;

            /**
             * Kit „{$label}“ – erbt von „{$parentKit->label()}“. Dateien in diesem Ordner ersetzen die des Eltern-Kits:
             * kit.php (Definition), views/{typ}.php, template.view.php, functions.php, assets/.
             */
            #[AsKit('{$key}', '{$this->quote($label)}', parent: '{$parentKit->key()}')]
            final class {$class} extends Kit {}

            PHP);

        File::put($dir . '/kit.php', <<<PHP
            <?php

            /*
             * Kit „{$label}“: Definition. \$parent ist die Definition des Eltern-Kits „{$parentKit->key()}“ – alles, was hier
             * fehlt, gilt von dort. Blöcke, Hintergründe und 'assets' werden je Schlüssel ergänzt, 'css' wird angehängt.
             *
             * Beispiele:
             *   'blocks' => ['hero' => ['variants' => ['eigene' => 'Eigene Variante'] + \$parent['blocks']['hero']['variants']] + \$parent['blocks']['hero']],
             *   'backgrounds' => ['sand' => 'Sand'],
             *   'design' => \$parent['design'],   // Style-Editor; eigene Vorlagen unter ['presets']
             */

            return [
                'label' => '{$this->quote($label)}',
                // Stylesheets für jede Seite – nach dem Grund- und Block-CSS des Eltern-Kits
                'css' => ['css/kit.css'],
            ];

            PHP);

        File::put($dir . '/assets/css/kit.css', <<<CSS
            /*
             * Kit „{$label}“ – eigenes CSS. Wird nach dem CSS von „{$parentKit->label()}“ (auch dem der Blöcke) geladen und
             * kann es überschreiben. Einzelne Dateien des Eltern-Kits (z. B. css/b-cards.css) ersetzt eine gleichnamige Datei hier.
             * Tipp: Farben und Schriften stellen Redakteure im Style-Editor (Design) ein – hier gehört hin, was das Kit
             * grundsätzlich anders macht. Relative Pfade (url(…)) zeigen auf assets/ dieses Kits.
             */

            CSS);

        $copied = [];
        foreach (array_filter(array_map('trim', explode(',', $view))) as $type) {
            $type = (string) preg_replace('~[^a-z0-9_]~', '', $type);
            $source = $parentKit->file('views/' . $type . '.php');
            if (null === $source) {
                $io->warning('Keine View „' . $type . '“ im Eltern-Kit.');
                continue;
            }
            File::copy($source, $dir . '/views/' . $type . '.php');
            $copied[] = 'views/' . $type . '.php';
        }
        if ($template) {
            $source = $parentKit->file('template.view.php') ?? $parentKit->path('src/FluidTemplate.view.php');
            if (is_file($source)) {
                File::copy($source, $dir . '/template.view.php');
                $copied[] = 'template.view.php';
            }
        }

        // Klassen neu einlesen (ClassDiscovery), damit das Kit sofort in der Auswahl steht
        \Redaxo\Core\Cache::delete();

        $io->success('Kit „' . $label . '“ angelegt: ' . Path::relative($dir));
        $io->listing(array_merge([
            $class . '.php – Anmeldung (#[AsKit])',
            'kit.php – Definition (erbt von „' . $parentKit->key() . '“)',
            'assets/css/kit.css – eigenes CSS',
        ], $copied));
        $io->text([
            'Nächste Schritte:',
            '  1. Website-Einstellungen → Reiter „Kit“ → „' . $label . '“ wählen.',
            '  2. assets/css/kit.css anpassen; weitere Views mit --view=typ,typ kopieren oder von Hand anlegen.',
            '  3. Auf dem Server: Ordner mit ausliefern, dann `bin/console migrate` (veröffentlicht die Assets).',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Namespace und Ordner für `src/` des Projekts (aus dessen composer.json, PSR-4).
     *
     * @return array{string, string}
     */
    private static function projectSource(): array
    {
        $file = Path::base('composer.json');
        $json = is_file($file) ? json_decode((string) File::get($file), true) : null;
        $psr4 = is_array($json) ? ($json['autoload']['psr-4'] ?? []) : [];
        foreach ((array) $psr4 as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                if ('src' === rtrim((string) $path, '/')) {
                    return [(string) $namespace, Path::base('src')];
                }
            }
        }

        return ['Project\\', Path::base('src')];
    }

    private function quote(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
