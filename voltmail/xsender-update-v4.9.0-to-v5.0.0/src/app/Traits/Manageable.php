<?php

namespace App\Traits;

use App\Enums\SettingKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

trait Manageable
{
     /**
      * Summary of getSpecificLogByColumn
      * @param \Illuminate\Database\Eloquent\Model $model
      * @param string $column
      * @param mixed $value
      * @param array|null $attributes
      * @return Builder|Model|object|null
      */
     public function getSpecificLogByColumn(Model $model, string $column, mixed $value, array|null $attributes = null): Model|null {

          return $model::when($attributes, fn(Builder $q): Builder =>
                                   $q->where($attributes))
                              ->where($column, $value)
                              ->first();
     }

     /**
      * Summary of getPaginatedLogs
      * @param \Illuminate\Database\Eloquent\Model $model
      * @param string|null $specificDateColumn
      * @param array|null $counters
      * @param array|null $relations
      * @param array|null $select
      * @param array|null $search
      * @param array|null $filter
      * @param array|null $attributes
      * @return \Illuminate\Pagination\LengthAwarePaginator
      */
     public function getPaginatedLogs(Model $model, string|null $specificDateColumn = null, array|null $counters = null, array|null $relations = null, array|null $select = null, array|null $search = null, array|null $filter = null, array|null $attributes = null): LengthAwarePaginator {

          return $model::when($select, fn(Builder $q): Builder =>
                                   $q->select($select))
                              ->when($specificDateColumn, fn(Builder $q): Builder =>
                                   $q->date($specificDateColumn), 
                                        fn(Builder $q): Builder => 
                                             $q->date())
                              ->when($search, fn(Builder $q): Builder =>
                                   $q->search($search))
                              ->when($filter, fn(Builder $q): Builder =>
                                   $q->filter($filter))
                              ->when($attributes, fn(Builder $q): Builder =>
                                   $q->where($attributes))
                              ->when($relations, fn(Builder $q): Builder =>
                                   $q->with($relations))
                              ->when($counters, fn(Builder $q): Builder =>
                                   $q->withCount($counters))
                              ->paginate(paginateNumber(site_settings(SettingKey::PAGINATE_NUMBER->value, 10)))
                                        ->onEachSide(1)
                                        ->appends(request()->all());
     }

     /**
      * Summary of getCollectionLogs
      * @param \Illuminate\Database\Eloquent\Model $model
      * @param string|null $specificDateColumn
      * @param array|null $counters
      * @param array|null $relations
      * @param array|null $select
      * @param array|null $search
      * @param array|null $filter
      * @param array|null $attributes
      * @return \Illuminate\Database\Eloquent\Collection
      */
     public function getCollectionLogs(Model $model, string|null $specificDateColumn = null, array|null $counters = null, array|null $relations = null, array|null $select = null, array|null $search = null, array|null $filter = null, array|null $attributes = null): Collection {

          return $model::when($select, fn(Builder $q): Builder =>
                                   $q->select($select))
                              ->when($specificDateColumn, fn(Builder $q): Builder =>
                                   $q->date($specificDateColumn), 
                                        fn(Builder $q): Builder => 
                                             $q->date())
                              ->when($search, fn(Builder $q): Builder =>
                                   $q->search($search))
                              ->when($filter, fn(Builder $q): Builder =>
                                   $q->filter($filter))
                              ->when($attributes, fn(Builder $q): Builder =>
                                   $q->where($attributes))
                              ->when($relations, fn(Builder $q): Builder =>
                                   $q->with($relations))
                              ->when($counters, fn(Builder $q): Builder =>
                                   $q->withCount($counters))
                              ->get();
     }
}
