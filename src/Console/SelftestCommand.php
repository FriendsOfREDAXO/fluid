<?php

namespace FriendsOfRedaxo\Fluid\Console;

use FriendsOfRedaxo\Fluid\Live;
use FriendsOfRedaxo\Fluid\Presence;
use Redaxo\Core\Console\Command\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fluid:selftest', description: 'Prüft Live-Aktualisierung (Versionen, signierte Abos) und Präsenz der Redaktion')]
final class SelftestCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $fails = 0;
        foreach (['Live' => Live::selftest(...), 'Präsenz' => Presence::selftest(...)] as $name => $test) {
            $r = $test();
            $fails += \count($r['fails']);
            $io->writeln(sprintf('%s: %d in Ordnung%s', $name, $r['ok'], $r['fails'] ? ', ' . \count($r['fails']) . ' Fehler' : ''));
            foreach ($r['fails'] as $f) {
                $io->writeln('  ✗ ' . $f);
            }
        }
        if ($fails) {
            $io->error('Selbsttest fehlgeschlagen.');

            return Command::FAILURE;
        }
        $io->success('Alles in Ordnung.');

        return Command::SUCCESS;
    }
}
