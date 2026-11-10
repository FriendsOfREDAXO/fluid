<?php

namespace FriendsOfRedaxo\Fluid\Seo;

use FriendsOfRedaxo\Fluid\Routing\Router;
use FriendsOfRedaxo\Fluid\Routing\ExtensionPoint\PageMetaResolved;
use FriendsOfRedaxo\Fluid\Routing\Seo\PageMeta;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Language\Language;


/**
 * Angaben von fluid für den Seitenkopf des Routings (Routing\Seo\Head): Bild für soziale Netzwerke mit image_editor
 * (1200 × 630 als JPEG um den Fokuspunkt) und schema.org (StructuredData). Bausteine ergänzen eigene Knoten über
 * FLUID_STRUCTURED_DATA, eigene Tags über den Extension Point PageMetaResolved des Routings.
 */
final class Head
{
    /** @internal */
    #[AsExtension]
    public static function resolved(PageMetaResolved $ep): PageMeta
    {
        $m = $ep->subject;
        // Netzwerke: 1200 × 630 als JPEG um den Fokuspunkt (LinkedIn kann kein WebP)
        if (null !== $m->image && '' !== $m->image && !preg_match('~^https?://~i', $m->image) && class_exists(\FriendsOfRedaxo\ImageEditor\ImageEditor::class)
            && ($media = Media::get($m->image)) && null !== ($share = \FriendsOfRedaxo\ImageEditor\ImageEditor::shareUrl($media->fileName))) {
            $m->imageAlt ??= '' !== ($alt = trim((string) ($media->getValue('meta_alt') ?? ''))) ? $alt : null;
            $m->image = Router::absolute($share);
            $m->imageType = 'image/jpeg';
            $m->imageWidth = 1200;
            $m->imageHeight = 630;
        }
        if (!\FriendsOfRedaxo\Fluid\Editing::active()) {
            $m->jsonLd = [...(array) (StructuredData::graph($ep->article, $m)['@graph'] ?? []), ...$m->jsonLd];
        }

        return $m;
    }

    /** schema.org-Knoten der Organisation: Name, Logo, Adresse, Telefon, E-Mail, Geo, Öffnungszeiten, Social Media */
    public static function organizationNode(): array
    {
        $s = static fn (string $k) => trim((string) \FriendsOfRedaxo\Fluid\Settings::get($k, ''));
        $type = $s('schema_type') ?: 'Organization';
        $origin = Router::canonical(Article::getSiteStartArticleId(), Language::getCurrentId());
        $data = ['@type' => $type, '@id' => $origin . '#org', 'name' => \FriendsOfRedaxo\Fluid\Settings::name(), 'url' => $origin];
        if ('' !== $s('logo') && ($m = \Redaxo\Core\MediaPool\Media::get($s('logo')))) {
            $data['logo'] = Router::absolute($m->getUrl());
        }
        if ('' !== $s('street') || '' !== $s('city')) {
            $data['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $s('street'), 'postalCode' => $s('zip'), 'addressLocality' => $s('city'), 'addressCountry' => $s('country')]);
        }
        if ('' !== $s('phone')) {
            $data['telephone'] = $s('phone');
        }
        if (filter_var($s('email'), FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $s('email');
        }
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $spec = [];
        foreach ((array) \FriendsOfRedaxo\Fluid\Settings::get('hours', []) as $r) {
            if (isset($days[(int) ($r['tag'] ?? -1)]) && !empty($r['von']) && !empty($r['bis'])) {
                $slots = !empty($r['pause_von']) && !empty($r['pause_bis']) ? [[$r['von'], $r['pause_von']], [$r['pause_bis'], $r['bis']]] : [[$r['von'], $r['bis']]];
                foreach ($slots as [$o, $c]) {
                    $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days[(int) $r['tag']], 'opens' => $o, 'closes' => $c];
                }
            }
        }
        if ($spec && 'Organization' !== $type && 'Person' !== $type) {
            $data['openingHoursSpecification'] = $spec;
        }
        $same = array_values(array_filter(array_map(static fn ($x) => (string) ($x['url'] ?? ''), (array) \FriendsOfRedaxo\Fluid\Settings::get('social', [])), static fn ($u) => (bool) preg_match('~^https?://~', $u)));
        if ($same) {
            $data['sameAs'] = $same;
        }

        return $data;
    }
}
