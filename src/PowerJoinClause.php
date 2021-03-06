<?php

namespace KirschbaumDevelopment\EloquentJoins;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Str;

class PowerJoinClause extends JoinClause
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * Table name backup in case an alias is being used.
     *
     * @var string
     */
    public $tableName;

    /**
     * Alias name.
     *
     * @var string
     */
    public $alias;

    /**
     * Create a new join clause instance.
     *
     * @param  \Illuminate\Database\Query\Builder  $parentQuery
     * @param  string  $type
     * @param  string  $table
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function __construct(Builder $parentQuery, $type, $table, Model $model = null)
    {
        parent::__construct($parentQuery, $type, $table);

        $this->model = $model;
        $this->tableName = $table;
    }

    /**
     * Add an alias to the table being joined.
     *
     * @return self
     */
    public function as(string $alias)
    {
        $this->alias = $alias;
        $this->table = sprintf('%s as %s', $this->table, $alias);
        $this->useTableAliasInConditions();

        return $this;
    }

    public function on($first, $operator = null, $second = null, $boolean = 'and')
    {
        parent::on($first, $operator, $second, $boolean);
        $this->useTableAliasInConditions();

        return $this;
    }

    public function getModel()
    {
        return $this->model;
    }

    protected function useTableAliasInConditions()
    {
        if (! $this->alias || ! $this->model) {
            return $this;
        }

        $this->wheres = collect($this->wheres)->filter(function ($where) {
            return in_array($where['type'] ?? '', ['Column']);
        })->map(function ($where) {
            $key = $this->model->getKeyName();
            $table = $this->tableName;

            if (Str::contains($where['first'], $table) && Str::contains($where['second'], $table)) {
                // if joining the same table, only replace the correct table.key pair
                $where['first'] = str_replace($table.'.'.$key, $this->alias.'.'.$key, $where['first']);
                $where['second'] = str_replace($table.'.'.$key, $this->alias.'.'.$key, $where['second']);
            } else {
                $where['first'] = str_replace($table, $this->alias, $where['first']);
                $where['second'] = str_replace($table, $this->alias, $where['second']);
            }

            return $where;
        });

        return $this;
    }

    public function __call($name, $arguments)
    {
        $scope = 'scope'.ucfirst($name);

        if (method_exists($this->getModel(), $scope)) {
            $this->getModel()->{$scope}($this, ...$arguments);
        } else {
            throw new InvalidArgumentException(sprintf('Method %s does not exist in PowerJoinClause class', $name));
        }
    }
}
