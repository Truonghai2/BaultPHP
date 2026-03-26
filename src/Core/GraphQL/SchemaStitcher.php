<?php

declare(strict_types=1);

namespace Core\GraphQL;

use Core\Support\Facades\Log;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use InvalidArgumentException;

/**
 * GraphQL Schema Stitcher
 *
 * Merges multiple GraphQL schemas into a single schema.
 * Handles type conflicts and field merging.
 */
class SchemaStitcher
{
    protected array $schemas = [];
    protected array $typeMap = [];
    protected array $directiveMap = [];

    /**
     * Add schema to be stitched
     *
     * @param Schema $schema
     * @param string $namespace Optional namespace prefix
     */
    public function addSchema(Schema $schema, string $namespace = ''): void
    {
        $this->schemas[] = [
            'schema' => $schema,
            'namespace' => $namespace,
        ];

        Log::debug("Schema added for stitching", ['namespace' => $namespace]);
    }

    /**
     * Stitch all schemas together
     *
     * @return Schema
     */
    public function stitch(): Schema
    {
        $this->typeMap = [];
        $this->directiveMap = [];

        // Collect all types from all schemas
        foreach ($this->schemas as $schemaData) {
            $schema = $schemaData['schema'];
            $namespace = $schemaData['namespace'];
            
            $this->collectTypes($schema, $namespace);
        }

        // Merge types
        $mergedTypes = $this->mergeTypes();

        // Build stitched schema
        return $this->buildStitchedSchema($mergedTypes);
    }

    /**
     * Collect types from schema
     *
     * @param Schema $schema
     * @param string $namespace
     */
    protected function collectTypes(Schema $schema, string $namespace): void
    {
        $typeMap = $schema->getTypeMap();

        foreach ($typeMap as $typeName => $type) {
            // Skip internal types
            if (str_starts_with($typeName, '__')) {
                continue;
            }

            $fullName = $namespace ? "{$namespace}_{$typeName}" : $typeName;

            if (!isset($this->typeMap[$fullName])) {
                $this->typeMap[$fullName] = [];
            }

            $this->typeMap[$fullName][] = [
                'type' => $type,
                'namespace' => $namespace,
                'original_name' => $typeName,
            ];
        }
    }

    /**
     * Merge types with same name
     *
     * @return array
     */
    protected function mergeTypes(): array
    {
        $merged = [];

        foreach ($this->typeMap as $typeName => $typeDefinitions) {
            if (count($typeDefinitions) === 1) {
                // Single definition, no merge needed
                $merged[$typeName] = $typeDefinitions[0]['type'];
            } else {
                // Multiple definitions, merge them
                $merged[$typeName] = $this->mergeTypeDefinitions($typeName, $typeDefinitions);
            }
        }

        return $merged;
    }

    /**
     * Merge multiple type definitions
     *
     * @param string $typeName
     * @param array $definitions
     * @return Type
     */
    protected function mergeTypeDefinitions(string $typeName, array $definitions): Type
    {
        $firstType = $definitions[0]['type'];

        // If it's an ObjectType, merge fields
        if ($firstType instanceof ObjectType) {
            return $this->mergeObjectTypes($typeName, $definitions);
        }

        // For other types, use first definition
        return $firstType;
    }

    /**
     * Merge ObjectType definitions
     *
     * @param string $typeName
     * @param array $definitions
     * @return ObjectType
     */
    protected function mergeObjectTypes(string $typeName, array $definitions): ObjectType
    {
        $fields = [];
        $interfaces = [];
        $description = null;

        foreach ($definitions as $def) {
            /** @var ObjectType $type */
            $type = $def['type'];
            
            // Merge fields
            $typeFields = $type->getFields();
            foreach ($typeFields as $fieldName => $field) {
                if (!isset($fields[$fieldName])) {
                    $fields[$fieldName] = $field;
                } else {
                    // Field conflict - use first definition or merge
                    Log::warning("Field conflict in type", [
                        'type' => $typeName,
                        'field' => $fieldName,
                    ]);
                }
            }

            // Merge interfaces
            $typeInterfaces = $type->getInterfaces();
            foreach ($typeInterfaces as $interface) {
                $interfaces[] = $interface;
            }

            // Use first description
            if ($description === null) {
                $description = $type->description;
            }
        }

        return new ObjectType([
            'name' => $typeName,
            'description' => $description,
            'fields' => $fields,
            'interfaces' => array_unique($interfaces),
        ]);
    }

    /**
     * Build stitched schema from merged types
     *
     * @param array $types
     * @return Schema
     */
    protected function buildStitchedSchema(array $types): Schema
    {
        // Find Query type
        $queryType = $types['Query'] ?? null;
        if (!$queryType instanceof ObjectType) {
            throw new InvalidArgumentException('Query type not found in stitched schemas');
        }

        // Find Mutation type (optional)
        $mutationType = $types['Mutation'] ?? null;

        // Find Subscription type (optional)
        $subscriptionType = $types['Subscription'] ?? null;

        return new Schema([
            'query' => $queryType,
            'mutation' => $mutationType,
            'subscription' => $subscriptionType,
            'types' => array_values($types),
        ]);
    }

    /**
     * Clear all schemas
     */
    public function clear(): void
    {
        $this->schemas = [];
        $this->typeMap = [];
        $this->directiveMap = [];
    }
}
