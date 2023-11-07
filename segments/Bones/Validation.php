<?php

namespace Bones;

use Exception;
use Bones\Database;
use Bones\BadMethodException;

class Validation
{
    protected $rules;
    protected $errors = [];
    protected $errMsgs;

    public function __construct(array $rules = [], array $errMsgs = [])
    {
        $this->rules = $rules;
        $this->errMsgs = $errMsgs;
    }

    public function check(array $params = [], array $files = [])
    {
        foreach ($this->rules as $ruleFor => $rule) {
            $ruleDef = (is_array($rule)) ? $rule : explode('|', $rule);
            if (array_key_exists($ruleFor, $params)) {
                foreach ($ruleDef as $ruleName) {
                    if ($ruleName == 'required') {
                        if (empty($params[$ruleFor])) {
                            $this->errors[]  = $this->getErrorMsg($ruleFor, $ruleName);
                        }
                    }
                    if ($ruleName == 'numeric') {
                        if (!is_numeric($params[$ruleFor])) {
                            $this->errors[]  = $this->getErrorMsg($ruleFor, $ruleName);
                        }
                    }
                    if (Str::startsWith($ruleName, 'max:')) {
                        $max = trim(str_replace('max:', '', $ruleName));
                        if (!is_numeric($max))
                            throw new Exception('Validation rule '.$ruleName.' must have numeric value as argument');
                        if (strlen($params[$ruleFor]) > $max) {
                            $this->errors[]  = $this->getErrorMsg($ruleFor, 'max', ['charCount' => $max]);
                        }
                    }
                    if (Str::startsWith($ruleName, 'min:')) {
                        $min = trim(str_replace('min:', '', $ruleName));
                        if (!is_numeric($min))
                            throw new Exception('Validation rule '.$ruleName.' must have numeric value as argument');
                        if (strlen($params[$ruleFor]) < $min) {
                            $this->errors[]  = $this->getErrorMsg($ruleFor, 'min', ['charCount' => $min]);
                        }
                    }
                    if ($ruleName == 'email') {
                        if (!Str::isEmail($params[$ruleFor])) {
                            $this->errors[]  = $this->getErrorMsg($ruleFor, $ruleName);
                        }
                    }
                    if (Str::startsWith($ruleName, 'eqt:')) {
                        $equalTo = trim(str_replace('eqt:', '', $ruleName));
                        if ($params[$equalTo] != $params[$ruleFor]) {
                            $this->errors[]  = $this->getErrorMsg($ruleFor, 'eqt', ['as' => $equalTo]);
                        }
                    }
                    if (Str::startsWith($ruleName, 'unique:')) {
                        $uniqueAttrs = explode(',', trim(str_replace('unique:', '', $ruleName)));
                        $tableToCheck = $uniqueAttrs[0];
                        $columnToCheck = $uniqueAttrs[1];
                        $exceptId = (!empty($uniqueAttrs[2])) ? $uniqueAttrs[2] : null;
                        if (count($uniqueAttrs) < 2 || (Str::empty($tableToCheck) || Str::empty($columnToCheck))) 
                            throw new Exception('Validation rule: unique rule must have {table}, {column} to check.');
                        $uniqueRecord = Database::table($tableToCheck)->where($columnToCheck, $params[$ruleFor]);
                        if (!empty($exceptId)) {
                            $uniqueRecord->whereNot('id', $exceptId);
                        }
                        $uniqueRecord = $uniqueRecord->pluckAssocFirst($columnToCheck);
                        if (!empty($uniqueRecord) && !empty($uniqueRecord->$columnToCheck)) {
                            $this->errors[]  = $this->getErrorMsg($ruleFor, 'unique', ['attrs' => $uniqueAttrs, 'value' => $params[$ruleFor]]);
                        }
                    }
                }
            }
        }
        return $this;
    }

    public function hasError()
    {
        return count($this->errors) > 0;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function getErrorMsg(string $ruleFor, string $rule, array $extraAttrs = [])
    {
        if ($rule == 'required')
            return (!empty($this->errMsgs[$ruleFor.'.'.$rule])) ? $this->errMsgs[$ruleFor.'.'.$rule] : $ruleFor.' field is required field';
        if ($rule == 'numeric')
            return (!empty($this->errMsgs[$ruleFor.'.'.$rule])) ? $this->errMsgs[$ruleFor.'.'.$rule] : $ruleFor.' field must have numeric value';
        if ($rule == 'max')
            return (!empty($this->errMsgs[$ruleFor.'.'.$rule])) ? $this->errMsgs[$ruleFor.'.'.$rule] : $ruleFor.' field can not be longer than '.$extraAttrs['charCount'].' characters';
        if ($rule == 'min')
            return (!empty($this->errMsgs[$ruleFor.'.'.$rule])) ? $this->errMsgs[$ruleFor.'.'.$rule] : $ruleFor.' field can not be shorter than '.$extraAttrs['charCount'].' characters';
        if ($rule == 'email')
            return (!empty($this->errMsgs[$ruleFor.'.'.$rule])) ? $this->errMsgs[$ruleFor.'.'.$rule] : $ruleFor.' field must be a valid email address';
        if ($rule == 'eqt')
            return (!empty($this->errMsgs[$ruleFor.'.'.$rule])) ? $this->errMsgs[$ruleFor.'.'.$rule] : $ruleFor.' field and '.$extraAttrs['as'].' field must be same';
        if ($rule == 'unique')
            return (!empty($this->errMsgs[$ruleFor.'.'.$rule])) ? $this->errMsgs[$ruleFor.'.'.$rule] : $ruleFor.' field must be unique but `'.$extraAttrs['value'].'` already exists.';
    }

    public function __get($attribute)
    {
        if (method_exists($this, $attribute)) {
            return $this->$attribute();
        }
        throw new Exception($attribute.' property not found');
    }

    public function __call($name, $arguments)
    {
        if ($name == 'errorFirst') {
            return (!empty($this->errors())) ? $this->errors()[0] : '';
        }
        throw new BadMethodException($name.' method not found');
    }

}