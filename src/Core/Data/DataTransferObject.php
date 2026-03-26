<?php

namespace Core\Data;

use Core\Support\Arr;
use Core\Support\Str;
use Core\Contracts\Support\Arrayable;
use Core\Contracts\Support\Jsonable;
use ReflectionClass;
use ReflectionProperty;

abstract class DataTransferObject implements Arrayable, Jsonable
{
    public function __construct(array $parameters = [])
    {
        $class = new ReflectionClass(static::class);

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if (Arr::exists($parameters, $name)) {
                $this->{$name} = $parameters[$name];
            } elseif (Arr::exists($parameters, Str::snake($name))) {
                 $this->{$name} = $parameters[Str::snake($name)];
            } elseif (Arr::exists($parameters, Str::camel($name))) {
                 $this->{$name} = $parameters[Str::camel($name)];
            }
        }
    }

    public static function fromRequest($request): static
    {
        return new static($request->all());
    }
    
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    public function all(): array
    {
        $data = [];
        $class = new ReflectionClass(static::class);
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            if ($property->isInitialized($this)) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    public function toArray(): array
    {
        return $this->all();
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
    
    public function __toString(): string
    {
        return $this->toJson();
    }
}
