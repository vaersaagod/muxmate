<?php

namespace vaersaagod\muxmate\gql;

use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class MuxMateFieldResolver extends ObjectType
{
    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo): mixed
    {
        $fieldName = $resolveInfo->fieldName;

        return $source->$fieldName;
    }
}
