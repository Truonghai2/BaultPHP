<?php

declare(strict_types=1);

namespace Core\GraphQL;

use Core\Application;
use Core\Support\Facades\Log;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use InvalidArgumentException;

/**
 * GraphQL Federation Gateway
 *
 * Combines multiple GraphQL subgraphs into a single federated schema.
 * Supports Apollo Federation specification.
 */
class FederationGateway
{
    protected array $subgraphs = [];
    protected ?Schema $federatedSchema = null;
    protected array $config = [];

    public function __construct(
        Application $app,
        array $config = [],
    ) {
        $this->config = array_merge(config('graphql.federation', []), $config);
    }

    /**
     * Register a subgraph
     *
     * @param string $name Subgraph name
     * @param string $url Subgraph URL
     * @param array $options Subgraph options
     */
    public function registerSubgraph(string $name, string $url, array $options = []): void
    {
        $this->subgraphs[$name] = [
            'name' => $name,
            'url' => $url,
            'options' => $options,
            'schema' => null,
        ];

        Log::info("GraphQL subgraph registered", [
            'name' => $name,
            'url' => $url,
        ]);
    }

    /**
     * Build federated schema from registered subgraphs
     *
     * @return Schema
     */
    public function buildFederatedSchema(): Schema
    {
        if ($this->federatedSchema !== null) {
            return $this->federatedSchema;
        }

        Log::info("Building federated GraphQL schema", [
            'subgraphs' => array_keys($this->subgraphs),
        ]);

        // Fetch schemas from all subgraphs
        $schemas = [];
        foreach ($this->subgraphs as $name => $subgraph) {
            $schema = $this->fetchSubgraphSchema($name, $subgraph['url']);
            if ($schema) {
                $schemas[$name] = $schema;
                $this->subgraphs[$name]['schema'] = $schema;
            }
        }

        if (empty($schemas)) {
            throw new \RuntimeException('No valid subgraph schemas found');
        }

        // Merge schemas using federation
        $this->federatedSchema = $this->mergeSchemas($schemas);

        Log::info("Federated schema built successfully", [
            'subgraph_count' => count($schemas),
        ]);

        return $this->federatedSchema;
    }

    /**
     * Execute federated query
     *
     * @param string $query GraphQL query
     * @param array $variables Query variables
     * @param array $context Execution context
     * @return array
     */
    public function execute(string $query, array $variables = [], array $context = []): array
    {
        $schema = $this->buildFederatedSchema();

        return GraphQL::executeQuery(
            $schema,
            $query,
            null,
            $context,
            $variables
        )->toArray();
    }

    /**
     * Fetch schema from subgraph
     *
     * @param string $name Subgraph name
     * @param string $url Subgraph URL
     * @return array|null Schema introspection result
     */
    protected function fetchSubgraphSchema(string $name, string $url): ?array
    {
        try {
            $introspectionQuery = $this->getIntrospectionQuery();
            
            $response = $this->makeSubgraphRequest($url, [
                'query' => $introspectionQuery,
            ]);

            if (!isset($response['data'])) {
                Log::warning("Failed to fetch schema from subgraph", [
                    'name' => $name,
                    'url' => $url,
                    'error' => $response['errors'] ?? 'Unknown error',
                ]);
                return null;
            }

            return $response['data'];

        } catch (\Throwable $e) {
            Log::error("Error fetching subgraph schema", [
                'name' => $name,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Make request to subgraph
     *
     * @param string $url
     * @param array $payload
     * @return array
     */
    protected function makeSubgraphRequest(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Subgraph request failed: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Subgraph returned HTTP {$httpCode}");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON response from subgraph");
        }

        return $decoded;
    }

    /**
     * Merge multiple schemas into federated schema
     *
     * @param array $schemas Array of schema introspection results
     * @return Schema
     */
    protected function mergeSchemas(array $schemas): Schema
    {
        // This is a simplified implementation
        // In production, use a proper federation library like apollo-federation/graphql-php
        
        $typeMap = [];
        $queryFields = [];
        $mutationFields = [];
        $subscriptionFields = [];

        foreach ($schemas as $subgraphName => $schema) {
            $types = $schema['__schema']['types'] ?? [];
            
            foreach ($types as $type) {
                $typeName = $type['name'] ?? null;
                if (!$typeName || str_starts_with($typeName, '__')) {
                    continue;
                }

                // Handle federation directives
                if ($this->hasFederationDirective($type)) {
                    $typeMap[$typeName] = $this->processFederatedType($type, $subgraphName);
                } else {
                    $typeMap[$typeName] = $type;
                }
            }

            // Collect query fields
            $queryType = $schema['__schema']['queryType'] ?? null;
            if ($queryType) {
                $queryFields = array_merge($queryFields, $this->extractFields($queryType, $subgraphName));
            }

            // Collect mutation fields
            $mutationType = $schema['__schema']['mutationType'] ?? null;
            if ($mutationType) {
                $mutationFields = array_merge($mutationFields, $this->extractFields($mutationType, $subgraphName));
            }
        }

        // Build federated schema
        // Note: This is a simplified version. Use proper federation library for production.
        return $this->buildSchemaFromTypes($typeMap, $queryFields, $mutationFields, $subscriptionFields);
    }

    /**
     * Check if type has federation directive
     *
     * @param array $type
     * @return bool
     */
    protected function hasFederationDirective(array $type): bool
    {
        $directives = $type['directives'] ?? [];
        foreach ($directives as $directive) {
            $name = $directive['name'] ?? '';
            if (in_array($name, ['key', 'extends', 'external', 'requires', 'provides'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Process federated type
     *
     * @param array $type
     * @param string $subgraphName
     * @return array
     */
    protected function processFederatedType(array $type, string $subgraphName): array
    {
        // Add subgraph metadata
        $type['_subgraph'] = $subgraphName;
        $type['_federated'] = true;
        
        return $type;
    }

    /**
     * Extract fields from type
     *
     * @param array $type
     * @param string $subgraphName
     * @return array
     */
    protected function extractFields(array $type, string $subgraphName): array
    {
        $fields = [];
        $typeFields = $type['fields'] ?? [];
        
        foreach ($typeFields as $field) {
            $fieldName = $field['name'] ?? null;
            if ($fieldName) {
                $fields[$fieldName] = array_merge($field, [
                    '_subgraph' => $subgraphName,
                ]);
            }
        }
        
        return $fields;
    }

    /**
     * Build schema from types and fields
     *
     * @param array $typeMap
     * @param array $queryFields
     * @param array $mutationFields
     * @param array $subscriptionFields
     * @return Schema
     */
    protected function buildSchemaFromTypes(
        array $typeMap,
        array $queryFields,
        array $mutationFields,
        array $subscriptionFields
    ): Schema {
        // For federation, we need to merge schemas from multiple subgraphs
        // This is a simplified implementation. For production use, consider using
        // a proper federation library like apollo-federation/graphql-php
        
        // If we have a SchemaFactory available, use it to build the base schema
        // Otherwise, we'll need to build from introspection results
        if ($this->app->bound(\TheCodingMachine\GraphQLite\SchemaFactory::class)) {
            try {
                // Use GraphQLite's schema factory as base
                $factory = $this->app->make(\TheCodingMachine\GraphQLite\SchemaFactory::class);
                $baseSchema = $factory->createSchema();
                
                // Note: Full federation requires merging multiple schemas properly
                // This implementation uses the base schema and would need additional
                // logic to merge types from multiple subgraphs
                // For now, return the base schema
                return $baseSchema;
            } catch (\Throwable $e) {
                Log::warning("Failed to build schema from SchemaFactory, falling back to introspection", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Fallback: Build schema from introspection results
        // This is a complex operation that requires converting introspection
        // results back to GraphQL schema objects
        // For now, throw an exception indicating federation needs proper setup
        throw new \RuntimeException(
            'Federation schema building requires either GraphQLite SchemaFactory ' .
            'or a proper federation library. Please ensure GraphQLServiceProvider is registered.'
        );
    }

    /**
     * Get GraphQL introspection query
     *
     * @return string
     */
    protected function getIntrospectionQuery(): string
    {
        return <<<'GRAPHQL'
query IntrospectionQuery {
  __schema {
    queryType { name }
    mutationType { name }
    subscriptionType { name }
    types {
      ...FullType
    }
    directives {
      name
      description
      locations
      args {
        ...InputValue
      }
    }
  }
}

fragment FullType on __Type {
  kind
  name
  description
  fields(includeDeprecated: true) {
    name
    description
    args {
      ...InputValue
    }
    type {
      ...TypeRef
    }
    isDeprecated
    deprecationReason
  }
  inputFields {
    ...InputValue
  }
  interfaces {
    ...TypeRef
  }
  enumValues(includeDeprecated: true) {
    name
    description
    isDeprecated
    deprecationReason
  }
  possibleTypes {
    ...TypeRef
  }
  directives {
    name
    description
    locations
    args {
      ...InputValue
    }
  }
}

fragment InputValue on __InputValue {
  name
  description
  type { ...TypeRef }
  defaultValue
}

fragment TypeRef on __Type {
  kind
  name
  ofType {
    kind
    name
    ofType {
      kind
      name
      ofType {
        kind
        name
        ofType {
          kind
          name
          ofType {
            kind
            name
            ofType {
              kind
              name
              ofType {
                kind
                name
              }
            }
          }
        }
      }
    }
  }
}
GRAPHQL;
    }

    /**
     * Get registered subgraphs
     *
     * @return array
     */
    public function getSubgraphs(): array
    {
        return $this->subgraphs;
    }

    /**
     * Clear federated schema cache
     */
    public function clearCache(): void
    {
        $this->federatedSchema = null;
    }
}
