<?php

namespace FriendsOfRedaxo\Fluid\Routing\Attribute;

use Attribute;

/**
 * Datensätze einer Tabelle als Adressen unter einer Seite ({@see \FriendsOfRedaxo\Fluid\Routing\TableSource}).
 *
 * @see Slug, Title, Description, Image, Modified für die Spalten
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FromTable
{
    /**
     * @param non-empty-string $table Tabelle, z. B. 'rex_news' (auch YForm-Tabellen)
     * @param int $page Seite (Artikel-ID), unter der die Einträge liegen und die sie anzeigt
     * @param string $where Bedingung für sichtbare Einträge (SQL, ohne Werte von Besuchern), z. B. 'status = 1'
     * @param string|null $language Spalte mit der Sprach-ID; null = jeder Eintrag in allen Sprachen
     * @param string $param Parameter, unter dem die Seite die ID des Eintrags bekommt (Request und $_GET)
     * @param string $id Spalte mit der ID
     */
    public function __construct(
        public string $table,
        public int $page,
        public string $where = '',
        public ?string $language = null,
        public string $param = 'id',
        public string $id = 'id',
    ) {}
}
