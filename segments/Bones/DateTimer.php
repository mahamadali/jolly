<?php

namespace Bones;

use DateTime;
use DateTimeZone;

class DateTimer extends DateTime
{
    public function __construct($datetime = 'now')
    {
        parent::__construct($datetime, new DateTimeZone(setting('app.timezone', 'Asia/Kolkata')));
    }

    public function __current()
    {
        return $this;
    }

    public function __currentTimestamp()
    {
        return $this->format('U');
    }

    public function __timestamp()
    {
        return $this->getTimestamp();
    }

    public function modifyAttr($modifier, $value, $op = '+')
    {
        $this->modify($op . (int) $value . ' ' . $modifier);
        return $this;
    }

    public static function __callStatic($method, $parameters)
    {
        if (method_exists((new static), '__'.$method)) {
            return (new static)->{'__'.$method}(...$parameters);
        }

        if (Str::startsWith($method, 'add') || Str::startsWith($method, 'sub')) {
            $executableMethodAttr = Str::removeWords($method, ['add', 'sub']);
            $executableMethodOperator = (Str::startsWith($method, 'add')) ? '+' : '-';
            $executableMethod = 'modifyAttr';
            $args = [];
            $args[] = $executableMethodAttr;
            $args[] = $parameters[0];
            $args[] = $executableMethodOperator;
            return self::$executableMethod(...$args);
        }

        throw new BadMethodException('Method {'.$method.'} not found in ' . get_class(new static));
    }

    public function __call(string $method, $parameters)
    {
        if (method_exists($this, '__'.$method)) {
            return $this->{'__'.$method}(...$parameters);
        }

        if (Str::startsWith($method, 'add') || Str::startsWith($method, 'sub')) {
            $executableMethodAttr = Str::removeWords($method, ['add', 'sub']);
            $executableMethodOperator = (Str::startsWith($method, 'add')) ? '+' : '-';
            $executableMethod = 'modifyAttr';
            $args = [];
            $args[] = $executableMethodAttr;
            $args[] = $parameters[0];
            $args[] = $executableMethodOperator;
            return $this->$executableMethod(...$args);
        }

        throw new BadMethodException('Method {'.$method.'} not found in ' . get_class($this));
    }

}