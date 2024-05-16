<?php

namespace vaersaagod\muxmate\gql;

use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use GraphQL\Type\Definition\Type;
use vaersaagod\muxmate\fields\MuxMateField;

class MuxMateFieldTypeGenerator implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes($context = null): array
    {
        /** @var MuxMateField $context */
        $typeName = self::getName($context);

        $properties = [
            "id" => Type::string(),
            "created_at" => Type::string(),
            "status" => Type::string(),
            "duration" => Type::float(),
            "max_stored_resolution" => Type::string(),
            "resolution_tier" => Type::string(),
            "max_resolution_tier" => Type::string(),
            "encoding_tier" => Type::string(),
            "max_stored_frame_rate" => Type::int(),
            "aspect_ratio" => Type::string(),
            "playback_id" => [
                'name' => 'playback_id',
                'description' => 'Returns a Mux playback ID for the given policy.',
                'args' => [
                    'policy' => [
                        'name' => 'policy',
                        'type' => Type::string(),
                        'description' => 'Given policy to return',
                        'default' => 'public'
                    ],
                ],
                'type' => Type::string(),
            ],
            "master_access" => Type::string(),
            "mp4_support" => Type::string(),
            "normalize_audio" => Type::boolean(),
            "test" => Type::boolean(),
            "ingest_type" => Type::string(),
            "error" => Type::string(),
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
