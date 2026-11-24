<?php

declare(strict_types=1);

/**
 * m4p_packaging
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The attribute group whose values ("1 pc", "9 pcs"…) build the package combinations.
 */
class M4pPackagingAttributes
{
    public const CONFIG_GROUP = 'M4P_PACKAGING_ATTRIBUTE_GROUP';

    public static function getGroupId(): int
    {
        return (int) Configuration::getGlobalValue(self::CONFIG_GROUP);
    }

    /**
     * Uses the stored group, then a group already named like the module's one, and creates it only when none exists.
     */
    public static function ensureGroup(): int
    {
        $idGroup = self::getGroupId();
        if ($idGroup > 0 && Validate::isLoadedObject(new AttributeGroup($idGroup))) {
            return $idGroup;
        }

        $idGroup = self::findGroupByName();
        if ($idGroup === 0) {
            $idGroup = self::createGroup();
        }

        Configuration::updateGlobalValue(self::CONFIG_GROUP, $idGroup);

        return $idGroup;
    }

    public static function getOrCreateValue(int $piecesPerPackage): int
    {
        $idGroup = self::ensureGroup();

        $idAttribute = self::findValueUsedByPackages($idGroup, $piecesPerPackage)
            ?: self::findValueByName($idGroup, $piecesPerPackage);
        if ($idAttribute > 0) {
            return $idAttribute;
        }

        $attribute = new ProductAttribute();
        $attribute->id_attribute_group = $idGroup;
        $attribute->color = '';
        $attribute->position = ProductAttribute::getHigherPosition($idGroup) + 1;
        foreach (Language::getLanguages(false) as $language) {
            $attribute->name[(int) $language['id_lang']] = self::getValueName($piecesPerPackage, (string) $language['locale']);
        }
        $attribute->add();

        return (int) $attribute->id;
    }

    /**
     * Renames values that still carry the default name of another language, e.g. "9 szt." on an English shop.
     */
    public static function translateValueNames(): void
    {
        $idGroup = self::getGroupId();
        if ($idGroup <= 0) {
            return;
        }

        $languages = Language::getLanguages(false);
        $rows = Db::getInstance()->executeS(
            'SELECT al.id_attribute, al.id_lang, al.name FROM `' . _DB_PREFIX_ . 'attribute_lang` al
             INNER JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.id_attribute = al.id_attribute
             WHERE a.id_attribute_group = ' . $idGroup
        ) ?: [];

        foreach ($rows as $row) {
            if (!preg_match('/^(\d+) (szt\.|pcs?)$/u', (string) $row['name'], $match)) {
                continue;
            }

            $locale = self::getLocale($languages, (int) $row['id_lang']);
            $name = self::getValueName((int) $match[1], $locale);
            if ($name === $row['name']) {
                continue;
            }

            Db::getInstance()->update(
                'attribute_lang',
                ['name' => pSQL($name)],
                'id_attribute = ' . (int) $row['id_attribute'] . ' AND id_lang = ' . (int) $row['id_lang']
            );
        }
    }

    private static function getValueName(int $piecesPerPackage, string $locale): string
    {
        $pattern = Context::getContext()->getTranslator()->trans('{count, plural, one {# pc} other {# pcs}}', [], 'Modules.M4ppackaging.Admin', $locale);

        return M4pPackagingPlural::format($pattern, $piecesPerPackage, $locale);
    }

    private static function createGroup(): int
    {
        $translator = Context::getContext()->getTranslator();
        $group = new AttributeGroup();
        $group->is_color_group = false;
        $group->group_type = 'radio';
        $group->position = AttributeGroup::getHigherPosition() + 1;
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $locale = (string) $language['locale'];
            $group->name[$idLang] = $translator->trans('Package (pieces)', [], 'Modules.M4ppackaging.Admin', $locale);
            $group->public_name[$idLang] = $translator->trans('Package', [], 'Modules.M4ppackaging.Admin', $locale);
        }

        if (!$group->add()) {
            throw new PrestaShopException('Cannot create the packaging attribute group');
        }

        return (int) $group->id;
    }

    private static function findGroupByName(): int
    {
        $translator = Context::getContext()->getTranslator();
        $names = ['Packaging', 'Package (pieces)'];
        foreach (Language::getLanguages(false) as $language) {
            $names[] = $translator->trans('Package (pieces)', [], 'Modules.M4ppackaging.Admin', (string) $language['locale']);
        }

        $quoted = array_map(static fn (string $name): string => "'" . pSQL($name) . "'", array_unique($names));

        return (int) Db::getInstance()->getValue(
            'SELECT id_attribute_group FROM `' . _DB_PREFIX_ . 'attribute_group_lang`
             WHERE name IN (' . implode(',', $quoted) . ') ORDER BY id_attribute_group ASC'
        );
    }

    private static function findValueUsedByPackages(int $idGroup, int $piecesPerPackage): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT p.id_attribute FROM `' . _DB_PREFIX_ . 'm4p_package` p
             INNER JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.id_attribute = p.id_attribute
             WHERE a.id_attribute_group = ' . $idGroup . ' AND p.qty_per_package = ' . $piecesPerPackage
        );
    }

    private static function findValueByName(int $idGroup, int $piecesPerPackage): int
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $language = new Language($idLang);
        $name = self::getValueName($piecesPerPackage, (string) $language->locale);

        return (int) Db::getInstance()->getValue(
            'SELECT a.id_attribute FROM `' . _DB_PREFIX_ . 'attribute` a
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON al.id_attribute = a.id_attribute AND al.id_lang = ' . $idLang . '
             WHERE a.id_attribute_group = ' . $idGroup . " AND al.name = '" . pSQL($name) . "'"
        );
    }

    /**
     * @param array<int, array<string, mixed>> $languages
     */
    private static function getLocale(array $languages, int $idLang): string
    {
        foreach ($languages as $language) {
            if ((int) $language['id_lang'] === $idLang) {
                return (string) $language['locale'];
            }
        }

        return 'en-US';
    }
}
