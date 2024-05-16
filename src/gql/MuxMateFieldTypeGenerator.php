<?php

namespace vaersaagod\muxmate\gql;

use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use GraphQL\Type\Definition\Type;
use vaersaagod\muxmate\fields\MuxMateField;

class OembedFieldTypeGenerator implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes($context = null): array
    {
        /** @var MuxMateField $context */
        $typeName = self::getName($context);

        $properties = [
            'muxAssetId' => Type::string(),
            'muxMetaData' => Type::array(),
        ];

        $property = GqlEntityRegistry::getEntity($typeName)
            ?: GqlEntityRegistry::createEntity($typeName, new MuxMateFieldResolver([
                'name' => $typeName,
                'description' => 'This entity has all the MuxMate Field properties',
                'fields' => function () use ($properties) {
                    return $properties;
                },
            ]));

        TypeLoader::registerType($typeName, function () use ($property) {
            return $property;
        });

        return [$property];
    }

    /**
     * @inheritdoc
     */
    public static function getName($context = null): string
    {
        /** @var OembedField $context */
        return $context->handle . '_MuxMateField';
    }
}
