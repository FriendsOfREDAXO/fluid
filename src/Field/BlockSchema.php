<?php

namespace FriendsOfRedaxo\Fluid\Field;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

/**
 * Definition eines Blocks aus den Attributen seiner Klasse: #[AsBlock] an der Klasse, Feld-Attribute an den
 * Eigenschaften (in der Reihenfolge der Klasse, geerbte Eigenschaften zuerst).
 *
 * @internal
 */
final class BlockSchema
{
    /**
     * @param class-string $class
     * @return array<string, mixed>
     */
    public static function of(string $class, string $name): array
    {
        $ref = new ReflectionClass($class);
        $block = ($ref->getAttributes(AsBlock::class)[0] ?? null)?->newInstance() ?? new AsBlock();
        $def = ['label' => $block->label ?? $name, 'icon' => $block->icon, 'group' => $block->group];
        foreach (['help' => $block->help, 'requires' => $block->requires, 'background' => $block->background, 'jsonld' => $block->jsonld] as $k => $v) {
            if (null !== $v) {
                $def[$k] = $v;
            }
        }
        foreach (['variants' => $block->variants, 'variant_help' => $block->variantHelp, 'drags' => $block->drags] as $k => $v) {
            if ([] !== $v) {
                $def[$k] = $v;
            }
        }
        if (false !== $block->nestable) {
            $def['nestable'] = $block->nestable;
        }
        if ($block->reload) {
            $def['reload'] = true;
        }
        $def['fields'] = self::fields($ref);

        return $def;
    }

    /** @return list<array<string, mixed>> */
    private static function fields(ReflectionClass $ref): array
    {
        // geerbte Felder (z. B. eigene Basisklasse mit Überschrift) vor den eigenen
        $chain = [];
        for ($c = $ref; false !== $c; $c = $c->getParentClass()) {
            array_unshift($chain, $c);
        }
        $out = [];
        foreach ($chain as $c) {
            foreach ($c->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->isStatic() || $prop->getDeclaringClass()->getName() !== $c->getName()) {
                    continue;
                }
                $attr = $prop->getAttributes(Field::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
                if (null === $attr) {
                    continue;
                }
                $out[] = $attr->newInstance()->schema($prop->getName(), $prop->hasDefaultValue() ? $prop->getDefaultValue() : null);
            }
        }

        return $out;
    }
}
