<?php

namespace Bones\Traits\Supporter;

use Bones\Skeletons\Supporters\BackgroundAction;
use ReflectionClass;

trait RunInBackground
{
    public $run_in_background = true;
    public $action;

    public function setAction()
    {
        $this->action = $this;
        
        (new BackgroundAction())->add([
            'for' => get_class($this),
            'draft' => serialize($this->getParameters()),
            'action' => (!empty($this->background_callable_method)) ? $this->background_callable_method : 'prepare'
        ]);

        return $this;
    }

    public function runInBackground()
    {
        return $this->setAction();
    }

    protected function getParameters()
    {
        $ref = new ReflectionClass($this);
        if (! $ref->isInstantiable()) {
            return [];
        }

        $parameters = [];
        $constructor = $ref->getConstructor();
        $params = $constructor->getParameters();

        foreach ($params as $param) {
            $name = $param->getName();
            $parameters[$name] = $this->{$name};
        }

        return $parameters;
    }

}