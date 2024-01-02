<?php

namespace Bones\Skeletons\Database;

use Bones\Database;
use Bones\DatabaseException;
use Bones\Str;
use Bones\Traits\Database\Process;
use PDO;
use stdClass;

class Builder
{
    use Process;

    protected $CONFIG;
    protected $TABLE;
    protected $PARAMS = [];
    protected $ACTION = "select";
    protected $SOURCE_VALUE = [];

    protected $PRIMARY_KEY = "id";
    protected $TIMESTAMPS = false;
    protected $CREATED_AT = "created_at";
    protected $UPDATED_AT = "updated_at";

    protected $HAS_TRASH_MASK = false;
    protected $TRASH_MASK_COLUMN = "deleted_at";

    public $model = null;
    public $where_exists = [];
    
    protected static $PDO_CONN = false;

    public function __construct($config = null)
    {
        $this->setConfig($config);
    }

    public function setTable($name)
    {
        $this->TABLE = $name;
    }

    public function setConfig($config)
    {
        if (!empty($config))
            $this->CONFIG = $config;
    }

    public function setAction($action)
    {
        $this->ACTION = $action;
    }

    public function getAction()
    {
        return $this->ACTION;
    }

    public function setPrimaryKey(string $value)
    {
        $this->PRIMARY_KEY = $value;
    }

    public function setTimestampsStatus(
        bool $value,
        $set_created_at_name = false,
        $set_updated_at_name = false
    ) {
        $this->TIMESTAMPS = $value;

        if ($set_created_at_name) {
            $this->CREATED_AT = $set_created_at_name;
        }

        if ($set_updated_at_name) {
            $this->UPDATED_AT = $set_updated_at_name;
        }
    }

    public function setTrashMaskStatus(bool $value, $trash_mask_column = false)
    {
        $this->HAS_TRASH_MASK = $value;

        if ($trash_mask_column) {
            $this->TRASH_MASK_COLUMN = $trash_mask_column;
        }
    }

    protected function execute($query, $params = [], $return = false)
    {
        if (!self::$PDO_CONN)
            self::$PDO_CONN = $this->CONFIG->connect();
            
        $this->PARAMS = $params;

        if ($this->PARAMS == null) {
            $stmt = self::$PDO_CONN->query($query);
        } else {
            $stmt = self::$PDO_CONN->prepare($query);
            $stmt->execute($this->PARAMS);
        }

        $last_executed_query = $this->mapParams($query, $this->PARAMS);
        Database::setQueryLog($last_executed_query);
        Database::setLastExecutedQuery($last_executed_query);

        if (!$stmt) {
            $db_error = self::$PDO_CONN->errorInfo();
            $db_error_message = $db_error[0] . '[#' . $db_error[1] . ']: ' . $db_error[2];
            throw new DatabaseException($db_error_message);
        }

        if ($return)
            $result = $stmt->fetchAll($this->CONFIG->getFetch());
        else
            $result = $stmt->rowCount();

        return $result;
    }

    protected function addOperator($oprator)
    {
        $array = $this->getSourceValueItem("WHERE");

        if (count($array) > 0) {
            $end = $array[count($array) - 1];

            if (in_array($end, ["AND", "OR", "("]) == false) {
                $this->addToSourceArray("WHERE", $oprator);
            }
        } else {
            $this->addToSourceArray("WHERE", "WHERE");
        }
    }

    protected function addOperatorHaving($oprator)
    {
        $array = $this->getSourceValueItem("HAVING");

        if (count($array) > 0) {
            $end = $array[count($array) - 1];

            if (in_array($end, ["AND", "OR", "("]) == false) {
                $this->addToSourceArray("HAVING", $oprator);
            }
        }
    }

    protected function addStartParentheses()
    {
        $this->addToSourceArray("WHERE", "(");
    }

    protected function addEndParentheses()
    {
        $this->addToSourceArray("WHERE", ")");
    }

    protected function addComma()
    {
        $this->addToSourceArray("DISTINCT", ",");
    }

    public function select(...$args)
    {
        if (
            count($args) == 1 &&
            !is_string($args[0]) &&
            !$args[0] instanceof Raw
        ) {
            if (is_array($args[0])) {
                foreach ($args[0] as $key => $arg) {
                    $args[$key] = $this->fixColumnName($arg)["name"];
                }

                $this->addToSourceArray("DISTINCT", implode(",", $args));
            } elseif (is_callable($args[0])) {
                $select = new Select($this);
                $args[0]($select);
                $this->addToSourceArray("DISTINCT", $select->getString());
            }
        } else {
            foreach ($args as $key => $arg) {
                if ($arg instanceof Raw) {
                    $args[$key] = $this->rawMaker(
                        $arg->getRawQuery(),
                        $arg->getRawValues()
                    );
                }
            }

            $this->addToSourceArray("DISTINCT", implode(",", $args));
        }

        return $this;
    }

    public function appendSelect(...$args)
    {
        $this->addComma();

        return $this->select(...$args);
    }

    public function selectRaw($query, $values = [])
    {
        $raw = new Raw();
        $raw->setRawData($query, $values);
        $this->select($raw);
        return $this;
    }

    public function whereIn($name, array $list)
    {
        $query = $this->queryMakerIn($name, $list, "");
        $this->addOperator("AND");
        $this->addToSourceArray("WHERE", $query);
        return $this;
    }

    public function whereNotIn($name, array $list)
    {
        $query = $this->queryMakerIn($name, $list, "NOT");
        $this->addOperator("AND");
        $this->addToSourceArray("WHERE", $query);
        return $this;
    }

    public function orWhereIn($name, array $list)
    {
        $query = $this->queryMakerIn($name, $list, "");
        $this->addOperator("OR");
        $this->addToSourceArray("WHERE", $query);
        return $this;
    }

    public function orWhereNotIn($name, array $list)
    {
        $query = $this->queryMakerIn($name, $list, "NOT");
        $this->addOperator("OR");
        $this->addToSourceArray("WHERE", $query);
        return $this;
    }

    public function whereColumn($first, $operator, $second = false)
    {
        $this->addOperator("AND");
        $this->fixOperatorAndValue($operator, $second);
        $this->addToSourceArray("WHERE", "`$first` $operator `$second`");

        return $this;
    }

    private function queryMakerIn($name, array $list, $extra_opration = "")
    {
        $name = $this->fixColumnName($name)["name"];

        $values = [];

        $this->methodInMaker($list, function ($get_param_name) use (
            &$values
        ) {
            $values[] = $get_param_name;
        });

        $string_query_name = $name;

        if (!empty($extra_opration)) {
            $string_query_name .= " " . $extra_opration;
        }

        $string_query_value = "IN(" . implode(",", $values) . ")";

        $string_query = "$string_query_name $string_query_value";

        return $string_query;
    }

    public function where(...$args)
    {
        $this->addOperator("AND");
        $this->queryMakerWhere($args);
        return $this;
    }

    public function orWhere(...$args)
    {
        $this->addOperator("OR");
        $this->queryMakerWhere($args);
        return $this;
    }

    public function whereNot(...$args)
    {
        $this->addOperator("AND");
        $this->queryMakerWhere($args, "NOT");
        return $this;
    }

    public function orWhereNot(...$args)
    {
        $this->addOperator("OR");
        $this->queryMakerWhere($args, "NOT");
        return $this;
    }

    public function whereNull($name)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereStaticValue($name, "IS NULL");
        return $this;
    }

    public function orWhereNull($name)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereStaticValue($name, "IS NULL");
        return $this;
    }

    public function whereNotNull($name)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereStaticValue($name, "IS NOT NULL");
        return $this;
    }

    public function orWhereNotNull($name)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereStaticValue($name, "IS NOT NULL");
        return $this;
    }

    public function whereBetween($name, array $values)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereBetween($name, $values);
        return $this;
    }

    public function orWhereBetween($name, array $values)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereBetween($name, $values);
        return $this;
    }

    public function whereNotBetween($name, array $values)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereBetween($name, $values, "NOT");
        return $this;
    }

    public function orWhereNotBetween($name, array $values)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereBetween($name, $values, "NOT");
        return $this;
    }

    public function whereRaw($query, array $values, $boolean = "AND")
    {
        $this->addOperator($boolean);
        $this->addToSourceArray("WHERE", $this->rawMaker($query, $values));
        return $this;
    }

    public function orWhereRaw($query, array $values)
    {
        return $this->whereRaw($query, $values, "OR");
    }

    public function whereDate(...$args)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereLikeDate("DATE", $args);
        return $this;
    }

    public function orWhereDate(...$args)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereLikeDate("DATE", $args);
        return $this;
    }

    public function whereYear(...$args)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereLikeDate("YEAR", $args);
        return $this;
    }

    public function orWhereYear(...$args)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereLikeDate("YEAR", $args);
        return $this;
    }

    public function whereMonth(...$args)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereLikeDate("MONTH", $args);
        return $this;
    }

    public function orWhereMonth(...$args)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereLikeDate("MONTH", $args);
        return $this;
    }

    public function whereDay(...$args)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereLikeDate("DAY", $args);
        return $this;
    }

    public function orWhereDay(...$args)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereLikeDate("DAY", $args);
        return $this;
    }

    public function whereTime(...$args)
    {
        $this->addOperator("AND");
        $this->queryMakerWhereLikeDate("TIME", $args);
        return $this;
    }

    public function orWhereTime(...$args)
    {
        $this->addOperator("OR");
        $this->queryMakerWhereLikeDate("TIME", $args);
        return $this;
    }

    public function whereLike(...$args)
    {
        $this->where($args[0], "LIKE", $args[1]);
        return $this;
    }

    public function orWhereLike(...$args)
    {
        $this->orWhere($args[0], "LIKE", $args[1]);
        return $this;
    }

    public function whereNotLike(...$args)
    {
        $this->where($args[0], "NOT LIKE", $args[1]);
        return $this;
    }

    public function orWhereNotLike(...$args)
    {
        $this->orWhere($args[0], "NOT LIKE", $args[1]);
        return $this;
    }

    public function and(...$args)
    {
        return $this->where(...$args);
    }

    public function or(...$args)
    {
        return $this->orWhere(...$args);
    }

    public function not(...$args)
    {
        return $this->whereNot(...$args);
    }

    public function orNot(...$args)
    {
        return $this->orWhereNot(...$args);
    }

    public function null($column)
    {
        return $this->whereNull($column);
    }

    public function orNull($column)
    {
        return $this->orWhereNull($column);
    }

    public function notNull($column)
    {
        return $this->whereNotNull($column);
    }

    public function orNotNull($column)
    {
        return $this->orWhereNotNull($column);
    }

    public function is($column, $boolean = true)
    {
        return $this->where($column, $boolean);
    }

    public function true($column)
    {
        return $this->is($column, false);
    }

    public function false($column)
    {
        return $this->is($column, false);
    }

    public function date(...$args)
    {
        return $this->whereDate(...$args);
    }

    public function orDate(...$args)
    {
        return $this->orWhereDate(...$args);
    }

    public function year(...$args)
    {
        return $this->whereYear(...$args);
    }

    public function orYear(...$args)
    {
        return $this->orWhereYear(...$args);
    }

    public function month(...$args)
    {
        return $this->whereMonth(...$args);
    }

    public function orMonth(...$args)
    {
        return $this->orWhereMonth(...$args);
    }

    public function day(...$args)
    {
        return $this->whereDay(...$args);
    }

    public function orDay(...$args)
    {
        return $this->orWhereDay(...$args);
    }

    public function time(...$args)
    {
        return $this->whereTime(...$args);
    }

    public function orTime(...$args)
    {
        return $this->orWhereTime(...$args);
    }

    public function in($name, array $list)
    {
        return $this->whereIn($name, $list);
    }

    public function notIn($name, array $list)
    {
        return $this->whereNotIn($name, $list);
    }

    public function orIn($name, array $list)
    {
        return $this->orWhereIn($name, $list);
    }

    public function orNotIn($name, array $list)
    {
        return $this->orwhereNotIn($name, $list);
    }

    public function join(...$args)
    {
        $query = $this->queryMakerJoin("INNER", $args);
        $this->addToSourceArray("JOIN", $query);
        return $this;
    }

    public function leftJoin(...$args)
    {
        $query = $this->queryMakerJoin("LEFT", $args);
        $this->addToSourceArray("JOIN", $query);
        return $this;
    }

    public function rightJoin(...$args)
    {
        $query = $this->queryMakerJoin("RIGHT", $args);
        $this->addToSourceArray("JOIN", $query);
        return $this;
    }

    public function fullJoin(...$args)
    {
        $query = $this->queryMakerJoin("FULL", $args);
        $this->addToSourceArray("JOIN", $query);
        return $this;
    }

    public function crossJoin($column)
    {
        $this->addToSourceArray("JOIN", "CROSS JOIN `$column`");
        return $this;
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($column = "*")
    {
        $this->select(function ($query) use ($column) {
            $query->count($column)->as("count");
        });
        return $this->getValue($this->first([], true), "count");
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $columns
     * @return int
     */
    public function sum($column = "*")
    {
        $this->select(function ($query) use ($column) {
            $query->sum($column)->as("sum");
        });

        return $this->getValue($this->first([], true), "sum");
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column = "*")
    {
        $this->select(function ($query) use ($column) {
            $query->avg($column)->as("avg");
        });

        return $this->getValue($this->first([], true), "avg");
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column = "*")
    {
        $this->select(function ($query) use ($column) {
            $query->min($column)->as("min");
        });

        return $this->getValue($this->first([], true), "min");
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column = "*")
    {
        $this->select(function ($query) use ($column) {
            $query->max($column)->as("max");
        });

        return $this->getValue($this->first([], true), "max");
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function value($column = "*")
    {
        return $this->getValue($this->first([], true), $column);
    }

    /**
     * Add a "having" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function having(
        $column,
        $operator,
        $value = null,
        $boolean = "and",
        $fn = ""
    ) {
        $this->addOperatorHaving($boolean);
        $this->fixOperatorAndValue($operator, $value);
        $column = $this->fixColumnName($column)["name"];

        $array = $this->getSourceValueItem("HAVING");
        $beginning = "HAVING";

        if (count($array) > 0) {
            $beginning = "";
        }

        if (empty($fn)) {
            $this->addToSourceArray(
                "HAVING",
                "$beginning $column $operator $value"
            );
        } else {
            $this->addToSourceArray(
                "HAVING",
                "$beginning $fn($column) $operator $value"
            );
        }

        return $this;
    }

    /**
     * Add a "or having" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function orHaving($column, $operator, $value = null)
    {
        return $this->having($column, $operator, $value, "OR");
    }

    /**
     * Add a "having count()" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @return $this
     */
    public function havingCount($column, $operator, $value = null)
    {
        return $this->having($column, $operator, $value, "AND", "COUNT");
    }

    /**
     * Add a "having sum()" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @return $this
     */
    public function havingSum($column, $operator, $value = null)
    {
        return $this->having($column, $operator, $value, "AND", "SUM");
    }

    /**
     * Add a "having avg()" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @return $this
     */
    public function havingAvg($column, $operator, $value = null)
    {
        return $this->having($column, $operator, $value, "AND", "AVG");
    }

    /**
     * Add a "or having count()" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @return $this
     */
    public function orHavingCount($column, $operator, $value = null)
    {
        return $this->having($column, $operator, $value, "OR", "COUNT");
    }

    /**
     * Add a "or having sum()" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @return $this
     */
    public function orHavingSum($column, $operator, $value = null)
    {
        return $this->having($column, $operator, $value, "OR", "SUM");
    }

    /**
     * Add a "or having avg()" clause to the query.
     *
     * @param  string  $column
     * @param  string|null  $operator
     * @param  string|null  $value
     * @return $this
     */
    public function orHavingAvg($column, $operator, $value = null)
    {
        return $this->having($column, $operator, $value, "OR", "AVG");
    }

    public function havingRaw($sql, array $bindings = [], $boolean = "AND")
    {
        $this->addOperatorHaving($boolean);

        $array = $this->getSourceValueItem("HAVING");
        $beginning = "HAVING";

        if (count($array) > 0) {
            $beginning = "";
        }
        $raw = Database::raw($sql, $bindings);
        $raw = $this->rawMaker($raw->getRawQuery(), $raw->getRawValues());
        $this->addToSourceArray("HAVING", "$beginning " . $raw);

        return $this;
    }

    public function orHavingRaw($sql, array $bindings = [])
    {
        return $this->havingRaw($sql, $bindings, "OR");
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  array  ...$groups
     * @return $this
     */
    public function groupBy(...$groups)
    {
        $arr = [];
        foreach ($groups as $group) {
            $arr[] = $this->fixColumnName($group)["name"];
        }
        $this->addToSourceArray("GROUP_BY", "GROUP BY " . implode(",", $arr));
        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = "DESC")
    {
        $column = $this->fixColumnName($column)["name"];
        $this->addToSourceArray("ORDER_BY", "ORDER BY $column $direction");
        return $this;
    }

    /**
     * Add an "order by" clause with raw syntax to the query.
     *
     * @param  string  $raw
     * @return $this
     */
    public function orderByRaw($raw)
    {
        $this->addToSourceArray("ORDER_BY", "ORDER BY $raw");
        return $this;
    }

    /**
     * Add an "order by count(`column`)" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderByCount($column, $direction = "asc")
    {
        $column = $this->fixColumnName($column)["name"];
        $this->addToSourceArray(
            "ORDER_BY",
            "ORDER BY COUNT($column) $direction"
        );
        return $this;
    }

    public function latest()
    {
        $this->orderBy($this->PRIMARY_KEY, "DESC");
        return $this;
    }

    public function oldest()
    {
        $this->orderBy($this->PRIMARY_KEY, "ASC");
        return $this;
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit(int $value)
    {
        $this->addToSourceArray("LIMIT", "LIMIT $value");
        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int  $value
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function take(int $value)
    {
        return $this->limit($value);
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset(int $offset)
    {
        $this->addToSourceArray("OFFSET", "OFFSET $offset");
        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param  int  $value
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function skip(int $skip)
    {
        return $this->offset($skip);
    }

    public function page(int $value, int $take)
    {
        $offset = $value * $take;
        return $this->take($take)
            ->offset($offset)
            ->get(true);
    }

    public function paginate(int $take = 15, $page = 1, $query_param = 'page')
    {
        $page = (!empty(request()->get($query_param))) ? request()->get($query_param) : $page;

        $records = $this->page($page - 1, $take);
        $count = count(Database::rawQuery(preg_replace("/LIMIT (\d+) OFFSET (\d+)/", '', (string) Database::getLastExecutedQuery())));

        $total_pages = ceil($count / $take);

        $records['__pagination'] = [
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $take,
            'has_next_page' => $page < $total_pages,
        ];

        if ($this->hasModel()) {
            return $this->model->wrapPaginated($records, $query_param);
        }

        return $records;
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     * @param  callable  $callback
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        $list = $this->get()->all();

        do {
            $return = $callback(array_splice($list, 0, $count));
            if ($return === false) {
                break;
            }
        } while (count($list));
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     * @param  callable  $callback
     * @return bool
     */
    public function each(callable $callback)
    {
        $list = $this->get()->all();

        do {
            $callback(array_splice($list, 0, 1)[0]);
        } while (count($list));
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        $result = $this->first([], true);
        return $result ? true : false;
    }

    /**
     * Determine if no rows exist for the current query.
     *
     * @return bool
     */
    public function doesntExist()
    {
        return !$this->exists();
    }

    private function queryMakerJoin($type, $args)
    {
        $join_table = $args[0];
        $join_table_column = $args[1];
        $operator = $args[2] ?? false;
        $main_column = $args[3] ?? false;

        if (!$operator && !$main_column) {
            $table_second = $this->fixColumnName($join_table);
            $table_main = $this->fixColumnName($join_table_column);

            $join_table = $table_second["table"];

            $join_table_column = $table_second["name"];

            $operator = "=";

            $main_column = $table_main["name"];
        } elseif ($operator && !$main_column) {
            $table_second = $this->fixColumnName($join_table);
            $table_main = $this->fixColumnName($operator);

            $operator = $join_table_column;

            $join_table = $table_second["table"];
            $join_table_column = $table_second["name"];

            $main_column = $table_main["name"];
        } elseif ($main_column) {
            $join_table = "`$join_table`";

            $join_table_column = $this->fixColumnName($join_table_column)["name"];
            $main_column = $this->fixColumnName($main_column)["name"];
        }

        return "$type JOIN $join_table ON $join_table_column $operator $main_column";
    }

    private function queryMakerWhereLikeDate($action, $args)
    {
        $column = $args[0];
        $operator = $args[1];
        $value = $args[2] ?? false;

        $this->fixOperatorAndValue($operator, $value);

        $column = $this->fixColumnName($column)["name"];

        $value_name = $this->addToParamAutoName($value);

        $query = "$action($column) $operator $value_name";

        /*
        | Add finally string to Source
        */
        $this->addToSourceArray("WHERE", $query);
    }

    private function queryMakerWhereStaticValue($name, $value)
    {
        $name = $this->fixColumnName($name)["name"];

        $query = "$name $value";

        /*
        | Add NOT to query
        */
        if (!empty($extra_operation)) {
            $query = "NOT " . $query;
        }

        $this->addToSourceArray("WHERE", $query);
    }

    private function queryMakerWhereBetween(
        $name,
        array $values,
        $extra_operation = ""
    ) {
        $name = $this->fixColumnName($name)["name"];

        $v1 = $this->addToParamAutoName($values[0]);
        $v2 = $this->addToParamAutoName($values[1]);

        $query = "$name BETWEEN $v1 AND $v2";

        /*
        | Add NOT to query
        */
        if (!empty($extra_operation)) {
            $query = "NOT " . $query;
        }

        $this->addToSourceArray("WHERE", $query);
    }

    private function queryMakerWhere($args, $extra_operation = "")
    {
        if (is_string($args[0])) {
            $column = $args[0];
            $operator = $args[1];
            $value = $args[2] ?? false;

            $this->fixOperatorAndValue($operator, $value);

            $column = $this->fixColumnName($column)["name"];

            $value_name = $this->addToParamAutoName($value);

            $query = "$column $operator $value_name";

            /*
            | Add NOT to query
            */
            if (!empty($extra_operation)) {
                $query = "NOT " . $query;
            }

            /*
            | Add finally string to Source
            */
            $this->addToSourceArray("WHERE", $query);
        } elseif (is_callable($args[0])) {
            $this->addStartParentheses();
            $args[0]($this);
            $this->addEndParentheses();
        }
    }

    protected function makeSelectQueryString()
    {
        $this->addToSourceArray("SELECT", "SELECT");
        $this->addToSourceArray("FROM", "FROM `$this->TABLE`");

        if (count($this->getSourceValueItem("DISTINCT")) == 0) {
            $this->select("*");
        }

        return $this->makeSourceValueStrign();
    }

    public function getSelectSQL()
    {
        return $this->makeSelectQueryString();
    }

    public function setTimestamps(array &$values, $just_update = false)
    {
        if ($this->TIMESTAMPS) {
            $now = date("Y-m-d H:i:s");
            if (!$just_update) {
                $values[$this->CREATED_AT] = $now;
            }
            $values[$this->UPDATED_AT] = $now;
        }
    }

    protected function makeInsertQueryString(array $values)
    {
        $param_name = [];
        $param_value_name_list = [];

        $this->setTimestamps($values);

        foreach ($values as $name => $value) {
            $param_name[] = $this->fixColumnName($name)["name"];
            $param_value_name_list[] = $this->addToParamAutoName($value);
        }

        return "INSERT INTO `$this->TABLE` (" .
            implode(",", $param_name) .
            ") VALUES (" .
            implode(",", $param_value_name_list) .
            ")";
    }

    protected function makeUpdateQueryString(array $values)
    {
        $this->setTimestamps($values, true);

        $params = [];
        foreach ($values as $name => $value) {
            $params[] =
                $this->fixColumnName($name)["name"] .
                " = " .
                $this->addToParamAutoName($value);
        }

        $extra = $this->makeSourceValueStrign();

        return "UPDATE `$this->TABLE` SET " . implode(",", $params) . " $extra";
    }

    protected function makeUpdateQueryIncrement(
        string $column,
        $value = 1,
        $action = "+"
    ) {
        $values = [];
        $this->setTimestamps($values, true);

        $column = $this->fixColumnName($column)["name"];

        $params = [];
        $params[] = "$column = $column $action $value";

        foreach ($values as $name => $value) {
            $params[] =
                $this->fixColumnName($name)["name"] .
                " = " .
                $this->addToParamAutoName($value);
        }

        $extra = $this->makeSourceValueStrign();

        return "UPDATE `$this->TABLE` SET " . implode(",", $params) . " $extra";
    }

    protected function makeSourceValueStrign()
    {
        ksort($this->SOURCE_VALUE);

        $array = [];
        foreach ($this->SOURCE_VALUE as $value) {
            if (is_array($value)) {
                $array[] = implode(" ", $value);
            }
        }

        return implode(" ", $array);
    }

    protected function makeDeleteQueryString()
    {
        $extra = $this->makeSourceValueStrign();
        return "DELETE FROM `$this->TABLE`  $extra";
    }

    public function insertMulti(array $values)
    {
        return $this->insert($values);
    }

    public function insert(array $values, $get_last_insert_id = false)
    {
        $this->PARAMS = null;
        
        if (count($values) != count($values, COUNT_RECURSIVE)) {
            $multi_insert_query_set = "";
            foreach ($values as $row) {
                $this->setAction("insert");
                $multi_insert_query_set .=
                    $this->makeInsertQueryString($row) . ";";
            }

            if (!empty($multi_insert_query_set)) {
                $result = $this->execute(
                    $multi_insert_query_set,
                    $this->PARAMS
                );
            }
        } else {
            $this->setAction("insert");
            $query = $this->makeInsertQueryString($values);
            $result = $this->execute($query, $this->PARAMS);
        }

        if (!$get_last_insert_id) {
            return $result;
        } else {
            return $this->CONFIG->pdo()->lastInsertId();
        }
    }

    public function insertGetId(array $values)
    {
        return $this->insert($values, true);
    }

    public function increment(string $column, int $value = 1)
    {
        $query = $this->makeUpdateQueryIncrement($column, $value);
        return $this->execute($query, $this->PARAMS);
    }

    public function decrement(string $column, int $value = 1)
    {
        $query = $this->makeUpdateQueryIncrement($column, $value, "-");
        return $this->execute($query, $this->PARAMS);
    }

    public function update(array $values, $return_model = false)
    {
        $this->applySelfOnlyCheck();
        $this->applySelfWhere();
        $this->setAction("update");
        $query = $this->makeUpdateQueryString($values);
        $return = $this->execute($query, $this->PARAMS);

        if ($return_model) {
            if ($this->hasModel() && $this->model->isSelfOnly()) {
                return (new $this->model->model())
                ->where($this->PRIMARY_KEY, $this->model->{$this->PRIMARY_KEY})
                ->first();
            }
        }

        return $return;
    }

    public function delete($force = false)
    {
        if (!$force && $this->hasModel()) {
            $model = $this->model;
            if ($model->hasTrashMask()) {
                return $model->setTrashMask();
            }
        }

        $this->setAction("delete");
        $query = $this->makeDeleteQueryString();
        return $this->execute($query, $this->PARAMS);
    }

    public function truncate()
    {
        return $this->execute("TRUNCATE `$this->TABLE`");
    }

    public function applyTrashMask()
    {
        if ($this->hasModel() && !$this->model->hasWithTrashed()) {
            $model = $this->model;
            if ($model->hasTrashMask()) {
                $this->whereNull($model->getTrashMaskColumn());
            }
        }
    }

    public function applySelfOnlyCheck()
    {
        if (!$this->hasModel())
            return null;
        
        if (isset($this->PRIMARY_KEY)) {
            $model = $this->model;
            if (!empty($model->{$this->PRIMARY_KEY}))
                $model->setSelfOnly(true);
        }
    }

    public function applySelfWhere()
    {
        if (!$this->hasModel())
            return null;

        $model = $this->model;

        if ($model->isSelfOnly()) {
            $this->where($this->PRIMARY_KEY, $model->{$this->PRIMARY_KEY});
        }
    }

    public function get($plain = false, $wrap_as_set = true)
    {
        $this->applyTrashMask();

        if (!empty($this->where_exists)) {
            foreach ($this->where_exists as $where_exists) {
                $this->whereRaw($where_exists, []);
            }
        }

        $query = $this->makeSelectQueryString();
        $entries = $this->execute($query, $this->PARAMS, true);

        if (!$this->hasModel() || $plain) {
            return $entries;
        }

        return $this->model->prepareSet($entries, $wrap_as_set);
    }

    public function getWithoutSetWrapper()
    {
        return $this->get(false, false);
    }

    public function pluck($column, $key = null)
    {
        $list = $this->get(true);
        $result = [];
        foreach ($list as $item) {
            if ($key == null) {
                $result[] = $this->getValue($item, $column);
            } else {
                $result[$this->getValue($item, $key)] = $this->getValue(
                    $item,
                    $column
                );
            }
        }

        return $result;
    }

    public function pluckAssoc($column)
    {
        $column = resolveAsArray($column);
        return $this->select("`" . implode("`,`", $column) . "`")->get();
    }

    public function pluckAssocFirst($column)
    {
        $column = resolveAsArray($column);
        return $this->select("`" . implode("`,`", $column) . "`")->first();
    }

    public function getArray()
    {
        return $this->get(true);
    }

    public function first($columns = [], $plain = false)
    {
        $db = $this->limit(1);

        if (count($columns)) {
            $db->select($columns);
        }

        $array = $db->get(true);

        if (count($array) == 1) {
            if (!$this->hasModel() || $plain) {
                return $array[0];
            }

            return $this->model->prepareOne($array[0]);
        }

        return false;
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  int    $id
     * @param  array  $columns
     * @return mixed|static
     */
    public function find($id, $columns = [])
    {
        return $this->where($this->PRIMARY_KEY, $id)->first($columns);
    }

    public function getSourceValueItem($struct_name)
    {
        $s_index = $this->sqlQueryStructure($struct_name);
        return $this->SOURCE_VALUE[$s_index] ?? [];
    }

    protected function addToSourceArray($struct_name, $value)
    {
        $s_index = $this->sqlQueryStructure($struct_name);
        $this->SOURCE_VALUE[$s_index][] = $value;
    }

    public function hasColumn($column)
    {
        $result = Database::rawQuery('SHOW COLUMNS FROM `'.$this->TABLE.'` LIKE "'.$column.'"');
        return (!empty($result) && !empty($result[0]));
    }

    public function hasModel()
    {
        return !empty($this->model);
    }

    public function selectRawSQL()
    {
        $raw = Database::raw($this->getSelectSQL(), $this->PARAMS);
        return $this->mapParams($raw->getRawQuery(), $raw->getRawValues());
    }

    public function mapParams($query, $params)
    {
        if (!self::$PDO_CONN)
            self::$PDO_CONN = $this->CONFIG->connect();

        $keys = [];
        $values = $params;

        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/'.$key.'/';
            } else {
                $keys[] = '/[?]/';
            }

            if (is_string($value))
                $values[$key] = self::$PDO_CONN->quote($value);

            if (is_array($value))
                $values[$key] = implode("','", self::$PDO_CONN->quote($value));

            if (is_null($value))
                $values[$key] = 'NULL';
        }

        return preg_replace($keys, $values, $query);
    }

    public function __call($name, $arguments)
    {
        return $this->model->$name($arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        $static = new static();

        return $static->model->$name($arguments);
    }
}