<?php

namespace FriendsOfRedaxo\Fluid\Routing;

use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Table;

/**
 * Tabellen des Routings – vom AddOn in install() angelegt (idempotent, läuft bei jedem `console migrate`).
 */
final class Schema
{
    /** Tabellen des Routings (für OwnedSchema des AddOns) */
    public const array TABLES = [Routes::TABLE, Routes::ENTRY_TABLE, Domains::TABLE, Redirects::TABLE, Redirects::TABLE_404];

    public static function install(): void
    {
        Table::get(Routes::TABLE)
            ->ensureColumn(new Column('article_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('language_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('slug', 'varchar(191)'))
            ->ensureColumn(new Column('path', 'varchar(500)'))
            ->setPrimaryKey(['article_id', 'language_id'])
            ->ensureIndex(new Index('lang_path', ['language_id', 'path']))
            ->ensure();
        Table::get(Routes::ENTRY_TABLE)
            ->ensureColumn(new Column('source', 'varchar(64)'))
            ->ensureColumn(new Column('entity', 'varchar(191)'))
            ->ensureColumn(new Column('language_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('article_id', 'int(10) unsigned'))
            ->ensureColumn(new Column('slug', 'varchar(191)'))
            ->ensureColumn(new Column('path', 'varchar(500)'))
            ->ensureColumn(new Column('params', 'text', true))
            ->ensureColumn(new Column('modified', 'int(10) unsigned', true))
            ->ensureColumn(new Column('sitemap', 'tinyint(1)', false, '1'))
            ->ensureColumn(new Column('images', 'text', true))
            ->setPrimaryKey(['source', 'entity', 'language_id'])
            ->ensureIndex(new Index('lang_path', ['language_id', 'path']))
            ->ensure();
        Table::get(Domains::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('host', 'varchar(191)'))
            ->ensureColumn(new Column('role', 'varchar(10)', false, 'alias'))
            ->ensureColumn(new Column('article_id', 'int(10) unsigned', true))
            ->ensureColumn(new Column('subtree', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('canonical', 'varchar(10)', false, 'own'))
            ->ensureColumn(new Column('note', 'varchar(191)', true))
            ->ensure();
        Table::get(Redirects::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('source', 'varchar(500)'))
            ->ensureColumn(new Column('source_hash', 'varchar(40)'))
            ->ensureColumn(new Column('wildcard', 'tinyint(1)', false, '0'))
            ->ensureColumn(new Column('target', 'varchar(500)'))
            ->ensureColumn(new Column('code', 'smallint(3)', false, '301'))
            ->ensureColumn(new Column('note', 'varchar(191)', true))
            ->ensureColumn(new Column('origin', 'varchar(10)', false, 'manual'))
            ->ensureColumn(new Column('hits', 'int(10) unsigned', false, '0'))
            ->ensureColumn(new Column('last_hit', 'datetime', true))
            ->ensureColumn(new Column('active', 'tinyint(1)', false, '1'))
            ->ensureColumn(new Column('created', 'datetime', true))
            ->ensureColumn(new Column('updated', 'datetime', true))
            ->ensureIndex(new Index('source_hash', ['source_hash']))
            ->ensure();
        Table::get(Redirects::TABLE_404)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('path', 'varchar(500)'))
            ->ensureColumn(new Column('path_hash', 'varchar(40)'))
            ->ensureColumn(new Column('hits', 'int(10) unsigned', false, '1'))
            ->ensureColumn(new Column('first_seen', 'datetime', true))
            ->ensureColumn(new Column('last_seen', 'datetime', true))
            ->ensureIndex(new Index('path_hash', ['path_hash']))
            ->ensure();
        Routes::rebuild(true);
    }
}
