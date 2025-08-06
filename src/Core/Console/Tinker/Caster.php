<?php

namespace Core\Console\Tinker;

use Core\ORM\Model;
use Core\Support\Collection;
use Symfony\Component\VarDumper\Caster\ConstStub;

class Caster
{
    /**
     * Caster for BaultPHP Model instances.
     *
     * @param \Core\ORM\Model $model
     * @return array
     */
    public static function castModel(Model $model): array
    {
        $attributes = $model->getAttributes();

        $result = [
            (new ConstStub(get_class($model)))->__toString() => $attributes,
        ];

        return $result;
    }

    /**
     * Caster for BaultPHP Collection instances.
     *
     * @param \Core\Support\Collection $collection
     * @return array
     */
    public static function castCollection(Collection $collection): array
    {
        return [
            (new ConstStub(get_class($collection)))->__toString() => $collection->all(),
        ];
    }
}
