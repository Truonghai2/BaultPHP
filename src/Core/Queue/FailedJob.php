<?php

namespace Core\Queue;

use Core\ORM\Model;

class FailedJob extends Model
{
    protected static string $table = 'failed_jobs';

    /**
     * The attributes that are mass assignable.
     * This is a security measure to prevent unwanted data from being saved.
     *
     * @var array
     */
    protected array $fillable = [
        'uuid',
        'connection',
        'queue',
        'payload',
        'exception',
    ];
}
