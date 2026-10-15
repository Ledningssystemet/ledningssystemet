<?php

namespace Ledningssystemet\Ledningssystemet\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class NamespaceTolerantMorphToMany extends MorphToMany
{
    /**
     * @param array<int, string> $morphClasses
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false,
        private array $morphClasses = [],
    ) {
        parent::__construct(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse,
        );

        $this->morphClasses = array_values(array_unique([
            $this->morphClass,
            ...$this->morphClasses,
        ]));
    }

    protected function addWhereConstraints()
    {
        BelongsToMany::addWhereConstraints();

        $this->query->whereIn($this->qualifyPivotColumn($this->morphType), $this->morphClasses);

        return $this;
    }

    public function addEagerConstraints(array $models)
    {
        BelongsToMany::addEagerConstraints($models);

        $this->query->whereIn($this->qualifyPivotColumn($this->morphType), $this->morphClasses);
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return BelongsToMany::getRelationExistenceQuery($query, $parentQuery, $columns)
            ->whereIn($this->qualifyPivotColumn($this->morphType), $this->morphClasses);
    }

    public function newPivotQuery()
    {
        return BelongsToMany::newPivotQuery()
            ->whereIn($this->morphType, $this->morphClasses);
    }
}
