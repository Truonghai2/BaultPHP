<?php

namespace Modules\User\Domain\Attributes;

use Attribute;

/**
 * Marks a method on a Model as the one that defines its parent
 * in the Access Control List (ACL) context hierarchy.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ParentContext
{
}