<?php

namespace Core\ORM\Exceptions;

use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    /**
     * The name of the affected Eloquent model.
     *
     * @var string
     */
    protected $model;

    /**
     * Set the affected Eloquent model.
     *
     * @param  string  $model
     * @return $this
     */
    public function setModel(string $model)
    {
        $this->model = $model;
        $this->message = "No query results for model [{$model}].";
        return $this;
    }
}
