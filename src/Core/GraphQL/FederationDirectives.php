<?php

declare(strict_types=1);

namespace Core\GraphQL;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\Type;

/**
 * Federation Directives
 *
 * Defines Apollo Federation directives for GraphQL schema.
 */
class FederationDirectives
{
    /**
     * Get all federation directives
     *
     * @return array<Directive>
     */
    public static function getDirectives(): array
    {
        return [
            self::keyDirective(),
            self::extendsDirective(),
            self::externalDirective(),
            self::requiresDirective(),
            self::providesDirective(),
        ];
    }

    /**
     * @key directive - Defines entity key fields
     */
    protected static function keyDirective(): Directive
    {
        return new Directive([
            'name' => 'key',
            'description' => 'Defines the key fields for an entity',
            'locations' => ['OBJECT', 'INTERFACE'],
            'args' => [
                'fields' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Field set that forms the key',
                ],
            ],
        ]);
    }

    /**
     * @extends directive - Extends a type from another subgraph
     */
    protected static function extendsDirective(): Directive
    {
        return new Directive([
            'name' => 'extends',
            'description' => 'Extends a type from another subgraph',
            'locations' => ['OBJECT', 'INTERFACE'],
        ]);
    }

    /**
     * @external directive - Marks field as external (defined in another subgraph)
     */
    protected static function externalDirective(): Directive
    {
        return new Directive([
            'name' => 'external',
            'description' => 'Marks a field as external (defined in another subgraph)',
            'locations' => ['FIELD_DEFINITION'],
        ]);
    }

    /**
     * @requires directive - Specifies required fields from another subgraph
     */
    protected static function requiresDirective(): Directive
    {
        return new Directive([
            'name' => 'requires',
            'description' => 'Specifies required fields from another subgraph',
            'locations' => ['FIELD_DEFINITION'],
            'args' => [
                'fields' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Field set that is required',
                ],
            ],
        ]);
    }

    /**
     * @provides directive - Specifies fields provided to other subgraphs
     */
    protected static function providesDirective(): Directive
    {
        return new Directive([
            'name' => 'provides',
            'description' => 'Specifies fields provided to other subgraphs',
            'locations' => ['FIELD_DEFINITION'],
            'args' => [
                'fields' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Field set that is provided',
                ],
            ],
        ]);
    }
}
