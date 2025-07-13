<?php

namespace Core\Queue;

use Core\ORM\Model;

class FailedJob extends Model
{
    protected static string $table = 'failed_jobs';
    public $timestamps = false; // We have a manual `failed_at` column.
}