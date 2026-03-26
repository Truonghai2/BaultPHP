<?php

declare(strict_types=1);

namespace Core\Extension;

/**
 * The three execution models for extension points.
 *
 *  FILTER    – Each handler receives a value and returns (a possibly modified) value.
 *              Handlers run in priority order; the final return value is used by core.
 *              Use case: transform block HTML, rewrite a permission decision, alter nav items.
 *
 *  ACTION    – Handlers are called for side-effects only; return value is ignored.
 *              Use case: warm caches, register assets, log analytics.
 *
 *  COLLECTOR – Each handler returns an array; all arrays are merged and returned together.
 *              Use case: gather global view data, discover nav items, collect middleware.
 */
enum ExtensionPointType: string
{
    case FILTER    = 'filter';
    case ACTION    = 'action';
    case COLLECTOR = 'collector';
}
