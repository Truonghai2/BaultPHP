<?php

namespace Core\ORM\Concerns;

use Core\Support\Str;
use Core\Support\Collection;
use DateTimeInterface;

trait HasAttributes
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * The model's original attributes.
     *
     * @var array
     */
    protected array $original = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected array $casts = [];

    /**
     * The attributes that have been changed.
     *
     * @var array
     */
    protected array $changes = [];

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute(string $key)
    {
        if (!$key) {
            return null;
        }

        // 1. Fetch from attributes array or accessors
        if (array_key_exists($key, $this->attributes) ||
            $this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }

        // 2. Check for Relations already loaded
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // 3. Check for Relation methods
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue(string $key)
    {
        $value = $this->attributes[$key] ?? null;

        // Call Accessor if exists (getFooAttribute)
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // Apply Casting
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }
        
        // Date handling for timestamps if not in casts
        if (in_array($key, $this->getDates()) && !is_null($value)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Get the relation value for the given key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelationValue(string $key)
    {
        // If the "attribute" exists as a method on the model, we will call the
        // method and assume it is a relationship. Once we have the relation
        // we will load it and cache it on the model instance.
        if (method_exists($this, $key)) {
            $relation = $this->{$key}();
            
            if ($relation instanceof \Core\ORM\Relations\Relation) {
                $this->setRelation($key, $results = $relation->getResults());
                return $results;
            }
        }
        
        return null;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute(string $key, mixed $value): static
    {
        // Call Mutator if exists (setFooAttribute)
        if ($this->hasSetMutator($key)) {
            $this->setMutatedAttributeValue($key, $value);

            return $this;
        }

        // Apply Casting logic for setters
        // For json/array, we might want to json_encode before setting
        // However, usually we set the attribute as is, and cast it when retrieving.
        // BUT for JSON/Array types, we should store it as string in DB.
        
        if ($this->isJsonCastable($key) && !is_null($value)) {
            $value = json_encode($value);
        }
        
        // Boolean check
        if ($this->isBoolCastable($key) && !is_null($value)) {
            $value = (bool) $value ? 1 : 0;
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }

    /**
     * Call the "get" mutator for the given attribute.
     */
    protected function mutateAttribute(string $key, mixed $value)
    {
        return $this->{'get' . Str::studly($key) . 'Attribute'}($value);
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set' . Str::studly($key) . 'Attribute');
    }

    /**
     * Set the value of an attribute using its mutator.
     */
    protected function setMutatedAttributeValue(string $key, mixed $value): void
    {
        $this->{'set' . Str::studly($key) . 'Attribute'}($value);
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param  string  $key
     * @param  array|string|null  $types
     * @return bool
     */
    public function hasCast(string $key, $types = null): bool
    {
        if (array_key_exists($key, $this->casts)) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function getCastType(string $key): string
    {
        return trim(strtolower($this->casts[$key]));
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute(string $key, mixed $value)
    {
        if (is_null($value)) {
            return null;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return json_decode($value, false);
            case 'array':
            case 'json':
                return json_decode($value, true) ?: [];
            case 'collection':
                return new Collection(json_decode($value, true) ?: []);
            case 'date':
                return $this->asDateTime($value)->format('Y-m-d');
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asDateTime($value)->getTimestamp();
        }
        
        // Enum support (PHP 8.1+)
        if (enum_exists($castType = $this->getCastType($key))) {
             return $castType::tryFrom($value);
        }

        return $value;
    }

    /**
     * Determine if the cast type is a custom date time cast.
     *
     * @param  string  $cast
     * @return bool
     */
    protected function isCustomDateTimeCast($cast)
    {
        return str_starts_with($cast, 'date:') || str_starts_with($cast, 'datetime:');
    }
    
    protected function isJsonCastable(string $key): bool
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }
    
    protected function isBoolCastable(string $key): bool
    {
        return $this->hasCast($key, ['bool', 'boolean']);
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \DateTimeInterface
     */
    protected function asDateTime($value): DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (is_numeric($value)) {
            return date_create()->setTimestamp($value);
        }

        return date_create($value);
    }
    
    /**
     * Get the attributes that should be converted to dates.
     *
     * @return array
     */
    public function getDates()
    {
        return $this->timestamps ? [$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()] : [];
    }

    /**
     * Get the attributes that have been changed since the last sync.
     *
     * @return array
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key] &&
                      !$this->originalIsNumericallyEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }
    
    protected function originalIsNumericallyEquivalent($key)
    {
        $current = $this->attributes[$key];
        $original = $this->original[$key];

        return is_numeric($current) && is_numeric($original) && strcmp((string) $current, (string) $original) === 0;
    }

    /**
     * Get all the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array
     */
    protected array $visible = [];

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }
    
    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray(): array
    {
        $attributes = $this->getAttributes();
        $mutatedAttributes = [];
        
        // Attributes & Accessors
        foreach ($attributes as $key => $value) {
            if ($this->hasGetMutator($key)) {
                $mutatedAttributes[$key] = $this->mutateAttribute($key, $value);
            } elseif ($this->hasCast($key)) {
                $mutatedAttributes[$key] = $this->castAttribute($key, $value);
            } else {
                 $mutatedAttributes[$key] = $value;
            }
        }
        
        // Append loaded relations
        foreach ($this->relations as $key => $value) {
            // Check if relation is an object with toArray
            if (is_object($value) && method_exists($value, 'toArray')) {
                $mutatedAttributes[$key] = $value->toArray();
            } elseif (is_iterable($value)) {
                 $mutatedAttributes[$key] = $value;
            } else {
                 $mutatedAttributes[$key] = $value;
            }
        }

        return $this->filterAttributesForSerialization($mutatedAttributes);
    }

    /**
     * Filter the attributes for serialization using hidden/visible properties.
     * 
     * @param array $attributes
     * @return array
     */
    protected function filterAttributesForSerialization(array $attributes): array
    {
        if (count($this->visible) > 0) {
            return array_intersect_key($attributes, array_flip($this->visible));
        }

        if (count($this->hidden) > 0) {
            return array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Get the model's original attribute values.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed|array
     */
    public function getOriginal($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->original;
        }

        return array_key_exists($key, $this->original)
            ? $this->original[$key]
            : $default;
    }
}
