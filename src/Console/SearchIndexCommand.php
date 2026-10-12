<?php

namespace FriendsOfRedaxo\Fluid\Console;

use FriendsOfRedaxo\Fluid\Search\Search;
use Redaxo\Core\Console\Command\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fluid:search-index', description: 'Baut den Suchindex der Website neu auf (Loupe, alle Sprachen)')]
final class SearchIndexCommand extends AbstractCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(Search::rebuild() . ' Seiten im Suchindex.');

        return Command::SUCCESS;
    }
}
