<?php

namespace Core;

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

abstract class SnapshotTestCase extends TestCase
{
    use MatchesSnapshots;
}
