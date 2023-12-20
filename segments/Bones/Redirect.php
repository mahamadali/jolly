<?php

namespace Bones;

use Bones\Router;
use Bones\RedirectException;
use Bones\BadMethodException;

class Redirect
{
    protected $status;
    protected $headers = [];
    protected $url;

    public function __construct(string $to = '', int $status = 301, array $headers = [])
    {
        $this->url = $to;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function back(int $status = 301, array $headers = [])
    {
        $this->appendHeaders($headers);
        $this->url = (!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : Router::prevRoute();
        $this->status = $status;
        $this->go();
    }

    public function to(string $to, int $status = 301, array $headers = [])
    {
        $this->url = $to;
        $this->appendHeaders($headers);
        $this->status = $status;
        return $this;
    }

    public function go()
    {
        if (empty($this->url))
            throw new RedirectException('Redirect: Empty URL given');
        foreach ($this->headers as $header) {
            foreach ($header as $key => $value) {
                header($key . ':' . $value);
            }
        }
        header('Location:' . $this->url, true, $this->status);
    }

    public function setHeaderLine(string $key, string $value)
    {
        if (!empty($key) && !empty($value))
        {
            $this->headers[] = [ $key => $value ];
            return $this->headers;
        }

        throw new RedirectException('Empty header can not be passed while redirecting to target url');
    }

    public function appendHeaders(array $headers)
    {
        if (!empty($headers) && is_array($headers)) {
            foreach ($headers as $key => $value) {
                $this->setHeaderLine($key, $value);
            }
        }
        return $this->headers;
    }

    public function __call($method, $arguments)
    {
        if (trim($method) == 'with') {
            if (!empty($arguments) && gettype($arguments[0]) == 'string') {
                Session::set($arguments[0], $arguments[1]);
                return $this;
            } else if (!empty($arguments) && (gettype($arguments[0]) == 'array' || gettype($arguments[0]) == 'object')) {
                foreach ($arguments[0] as $argName => $argVal) {
                    if (gettype($argVal) == 'object') {
                        $argVal = (array) $argVal;
                    }
                    Session::set($argName, $argVal);
                }
                return $this;
            }
        }
        else if (Str::contains($method, 'with')) {
            $methodParticles = preg_split("/with/i", $method);

            if (count($methodParticles) >= 2 && !empty($methodParticles[0]) && !empty($methodParticles[1]) && !Str::startsWith($methodParticles[1], 'flash')) {
                if (method_exists($this, $methodParticles[0])) {
                    Session::set(Str::camelize($methodParticles[1]), $arguments[0]);
                }
            } else if (Str::startsWith($methodParticles[1], 'flash')) {
                $method_definer_split = explode($methodParticles[1], 'flash');
                if (count($method_definer_split) == 1)
                    Session::setFlash($arguments[0], $arguments[1]);
                else
                    Session::setFlash(Str::remove($methodParticles[1], 'flash'), $arguments[0]);
            }
            
            if (method_exists($this, $methodParticles[0])) {
                return call_user_func('self::'.$methodParticles[0]);
            } else {
                $methodParticles = preg_split("/with/i", $method);
                Session::set(Str::camelize($methodParticles[1]), $arguments[0]);
                return $this;
            }
        }

        throw new BadMethodException($method . ' method not found in ' . get_class($this));
    }

}