<?php

namespace Core\Schema\Grammars;

use Core\ORM\QueryBuilder;
use Core\Schema\Blueprint;
use Core\Schema\ColumnDefinition;

interface Grammar
{
    public function compileCreate(Blueprint $blueprint): array;

    public function compileAlter(Blueprint $blueprint): array;

    public function compileDrop(Blueprint $blueprint): array;

    public function compileColumns(Blueprint $blueprint): array;

    public function quote(string $identifier): string;

    public function typeId(ColumnDefinition $column): string;
    public function typeBigIncrements(ColumnDefinition $column): string;
    public function typeIncrements(ColumnDefinition $column): string;
    public function typeString(ColumnDefinition $column): string;
    public function typeText(ColumnDefinition $column): string;
    public function typeInteger(ColumnDefinition $column): string;
    public function typeBigInteger(ColumnDefinition $column): string;
    public function typeUnsignedBigInteger(ColumnDefinition $column): string;
    public function typeUnsignedInteger(ColumnDefinition $column): string;
    public function typeTinyInteger(ColumnDefinition $column): string;
    public function typeUnsignedTinyInteger(ColumnDefinition $column): string;
    public function typeTimestamp(ColumnDefinition $column): string;
    public function typeBoolean(ColumnDefinition $column): string;
    public function typeDecimal(ColumnDefinition $column): string;
    public function typeDate(ColumnDefinition $column): string;
    public function typeTime(ColumnDefinition $column): string;
    public function typeDatetime(ColumnDefinition $column): string;
    public function typeJson(ColumnDefinition $column): string;
    public function typeEnum(ColumnDefinition $column): string;
    public function typeSet(ColumnDefinition $column): string;
    public function typeUuid(ColumnDefinition $column): string;
    public function typeLongText(ColumnDefinition $column): string;

    public function compileSelect(QueryBuilder $query): string;
    public function compileInsert(QueryBuilder $query, array $values): string;
    public function compileUpdate(QueryBuilder $query, array $values): string;
    public function compileDelete(QueryBuilder $query): string;
    public function compileExists(QueryBuilder $query): string;
    public function compileCount(QueryBuilder $query, string $columns): string;
}
