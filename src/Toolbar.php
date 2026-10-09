<?php

namespace FriendsOfRedaxo\Fluid;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleRevision;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;

use function Redaxo\Core\View\escape;

/**
 * Leiste und Editor für die angemeldete Redaktion – wird per OUTPUT_FILTER eingefügt, nur wenn ein Backend-Benutzer
 * mit Recht auf den Artikel angemeldet ist. Besucher bekommen kein zusätzliches Markup.
 * Oberfläche: assets/editor/editor.js (Shadow DOM, keine Kollision mit dem CSS der Website).
 */
final class Toolbar
{
    public static function inject(string $html): string
    {
        $user = Editing::user();
        $article = Article::getCurrent();
        if (!$user || !$article || !str_contains($html, '</body>')) {
            return $html;
        }

        $editing = Editing::requested();
        $addon = FluidAddon::instance();
        $perm = $user->getComplexPerm('modules');
        $modules = [];
        foreach (Module::getAll() as $module) {
            if (!$module instanceof BlockModule || !Template::checkModuleAllowed((string) $article->templateKey, 1, $module->key)) {
                continue;
            }
            if (!$perm instanceof ModulePermission || !$perm->hasPerm($module->key)) {
                continue;
            }
            $def = $module->definition();
            $modules[] = [
                'key' => $module->key,
                'label' => (string) ($def['label'] ?? $module->name),
                'group' => (string) ($def['group'] ?? 'Inhalt'),
                'help' => (string) ($def['help'] ?? ''),
                'nestable' => $module->nestable(),
            ];
        }

        $config = [
            'editing' => $editing,
            'article' => ['id' => $article->id, 'name' => $article->name, 'language' => $article->languageId, 'online' => $article->isOnline(), 'category' => $article->getCategory()?->name ?? ''],
            'api' => Url::article($article->id, $article->languageId, [Api::PARAM => '__action__']),
            'csrf' => CsrfToken::factory(Api::CSRF)->getValue(),
            'editUrl' => Url::article($article->id, $article->languageId, [Editing::PARAM => 1]),
            'viewUrl' => Url::article($article->id, $article->languageId),
            // Ansicht der Arbeitsversion (REDAXO-Core: rex_version=1, nur mit Backend-Anmeldung)
            'draftUrl' => Url::article($article->id, $article->languageId, ['rex_version' => ArticleRevision::WORK]),
            'viewingDraft' => !$editing && ArticleRevision::WORK === Request::get('rex_version', 'int', ArticleRevision::LIVE),
            'backendUrl' => Url::backendPage('content/edit', ['article_id' => $article->id, 'language' => $article->languageId, 'mode' => 'edit']),
            'mediapoolUrl' => Url::backendPage('mediapool/media', ['opener_input_field' => 'blocks_media']),
            'user' => $user->name ?: $user->login,
            'modules' => $modules,
            'state' => Api::state($article),
            'design' => $user->admin ? self::designSchema($article) : null,
            // Addon image_editor (optional): Bilder auf der Website bearbeiten
            'imageEditor' => class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditorAddon::class) ? \FriendsOfRedaxo\ImageEditor\ImageEditorAddon::config() : null,
            'css' => $addon->getAssetsUrl('editor/editor.shadow.css') . '?v=' . self::version('editor/editor.shadow.css'),
            'rteCss' => $addon->getAssetsUrl('rte.css') . '?v=' . self::version('rte.css'),
        ];

        $head = '<link rel="stylesheet" href="' . escape($addon->getAssetsUrl('editor/editor.css') . '?v=' . self::version('editor/editor.css')) . '" data-blocks-editor>';
        $body = '<script type="application/json" id="blocks-editor-config">' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
            . ($editing ? Links::json($article->languageId) : '')
            . '<script src="' . escape($addon->getAssetsUrl('form.js') . '?v=' . self::version('form.js')) . '" defer></script>'
            . '<script src="' . escape($addon->getAssetsUrl('rte.js') . '?v=' . self::version('rte.js')) . '" defer></script>'
            . '<script src="' . escape($addon->getAssetsUrl('editor/editor.js') . '?v=' . self::version('editor/editor.js')) . '" defer></script>';

        $html = preg_replace('~</head>~i', $head . '</head>', $html, 1) ?? $html;
        $pos = strripos($html, '</body>');

        return false === $pos ? $html : substr($html, 0, $pos) . $body . substr($html, $pos);
    }

    /** Style-Editor: Schema des Kits der Seite (nur Administration) */
    private static function designSchema(Article $article): ?array
    {
        foreach (Module::getAll() as $m) {
            if ($m instanceof BlockModule && Template::checkModuleAllowed((string) $article->templateKey, 1, $m->key)) {
                return $m->kit()->design()->schema();
            }
        }

        return null;
    }

    private static function version(string $file): string
    {
        $path = FluidAddon::instance()->getPath('assets/' . $file);

        return is_file($path) ? substr(md5((string) filemtime($path)), 0, 8) : '0';
    }
}
