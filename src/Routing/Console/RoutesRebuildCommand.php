<?php

namespace FriendsOfRedaxo\Fluid\Routing\Console;

use FriendsOfRedaxo\Fluid\Routing\Routes;
use FriendsOfRedaxo\Fluid\Routing\Sources;
use Redaxo\Core\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

#[AsCommand(name: 'routing:rebuild', description: 'Berechnet die Adressen der Seiten und Quellen neu (Cache inklusive)')]
final class RoutesRebuildCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io, #[Option(description: 'Nur diese Quelle (Kennung)')] string $source = ''): int
    {
        if ('' !== $source) {
            if (null === Sources::get($source)) {
                $io->error(sprintf('Quelle „%s“ gibt es nicht. Vorhanden: %s', $source, implode(', ', array_keys(Sources::all())) ?: '–'));

                return Command::FAILURE;
            }
            $io->success(sprintf('%s: %d Adressen.', $source, Routes::rebuildSource($source)));

            return Command::SUCCESS;
        }
        Routes::rebuild(true);
        $rows = [];
        foreach (Sources::all() as $key => $s) {
            $rows[] = [$key, $s->label(), count(iterator_to_array(Routes::entries($key), false))];
        }
        if ($rows) {
            $io->table(['Quelle', 'Name', 'Adressen'], $rows);
        }
        $io->success(sprintf('%d Seiten neu berechnet.', count(Routes::all())));

        return Command::SUCCESS;
    }
}
