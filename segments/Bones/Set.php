<?php

namespace Bones;

use ArrayIterator;
use Closure;
use Exception;
use IteratorAggregate;
use Models\Base\Model;
use Traversable;

class Set implements IteratorAggregate
{
    private $records = [];

    public function __construct($records = [])
    {
        $this->records = $records;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->records);
    }

    public function count()
    {
        return count($this->records);
    }

    public function shuffle()
    {
        shuffle($this->records);

        return $this;
    }

    public function shift($count = 1)
    {
        $count = (is_numeric($count)) ? $count : 1;

        while ($count > 0) {
            array_shift($this->records);
            $count--;
        }

        return $this;
    }

    public function all()
    {
        return $this->records;
    }

    public function search($key, $strict = false)
    {
        if ($key instanceof Closure) {
            foreach ($this->records as $index => $record) {
                if ($key($record)) {
                    return $index;
                }
            }

            return false;
        }

        $search_index = array_search($key, $this->records, $strict);

        if ($search_index >= 0)
            return $search_index;

        return false;
    }

    public function unique()
    {
        $this->records = array_unique($this->records);

        return $this;
    }

    public function map(Closure $closure)
    {
        $this->records = array_map($closure, (is_array($this->records) ? $this->records : [$this->all()]));

        return $this;
    }

    public function reject(Closure $closure)
    {
        $records_to_reject = array_filter($this->records, $closure);

        return $this->remove($records_to_reject);
    }

    public function remove($records_to_reject)
    {
        $records_to_reject = (is_array($records_to_reject)) ? $records_to_reject : [$records_to_reject];

        foreach ($this->records as $index => $record) {
            if (in_array($record, $records_to_reject))
                unset($this->records[$index]);
        }

        $this->records = array_values($this->records);

        return $this;
    }

    private function prop($haystack, $needle)
    {
        if ($haystack instanceof Model) {
            $prop = (!empty($haystack->$needle)) ? $haystack->$needle : null;
        } else if (is_object($haystack) && isset($haystack->$needle)) {
            $prop = $haystack->$needle;
        } else if (is_array($haystack) && isset($haystack[$needle])) {
            $prop = $haystack[$needle];
        }

        if (!empty($prop)) {
            return $prop;
        } else {
            $chainable_needle = explode('.', $needle);
            foreach ($chainable_needle as $count => $chainable) {
                $haystack = $haystack->$chainable;
                if (!empty($haystack) && $count == (count($chainable_needle) - 1)) {
                    return $haystack;
                }
            }
        }

        return null;
    }

    public function mapColumn($column)
    {
        return $this->map(function ($record) use ($column) {
            return $this->prop($record, $column);
        })->toArray();
    }

    public function implode($column, $delimiter = ',')
    {
        return implode($delimiter, $this->mapColumn($column));
    }

    public function implodeUnique($column, $delimiter)
    {
        return implode($delimiter, $this->unique($this->mapColumn($column))->all());
    }

    public function toArray()
    {
        return json_decode(json_encode($this->records), true);
    }

    public function toJson()
    {
        $json = json_encode($this->toArray());

        if ($json_error_code = json_last_error() >= 1) {
            $json_error_codes = [
                '1' => 'JSON_ERROR_DEPTH',
                '2' => 'JSON_ERROR_STATE_MISMATCH',
                '3' => 'JSON_ERROR_CTRL_CHAR',
                '4' => 'JSON_ERROR_SYNTAX',
                '5' => 'JSON_ERROR_UTF8'
            ];
            throw new Exception('JSON_ERROR: CODE_' . $json_error_code . '- ' . $json_error_codes[$json_error_code]);
        }

        return $json;
    }

    public function __get($property)
    {
        $records = (is_array($this->records) ? $this->records : [$this->all()]);

        foreach ($records as $record) {
            if (!empty($value = $this->prop($record, $property)))
                return $value;
        }

        throw new Exception('{' . $property . '} not found on records');
    }

    public function __call($name, $arguments)
    {
        $callable = $this->all();

        if ($callable instanceof Model)
            return $callable->$name(...$arguments);
    }

}