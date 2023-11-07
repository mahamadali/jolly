<?php

namespace Contributors\Particles;

use Bones\BadMethodException;
use Bones\Str;
use Contributors\Particles\Traits\AttrBuilder;

class Form
{
    use AttrBuilder;

    public function generateOptions($options = [], $selected_value = '')
    {
        $option_tags = [];
        $option_tags_html = '';

        if (!empty($options)) {
            if (array() === $options || array_keys($options) === range(0, count($options) - 1))
                foreach ($options as $value) {
                    $option_tags[$value] = $value;
                }
            else
                foreach ($options as $key => $value) {
                    $option_tags[$key] = $value;
                }
        }

        foreach ($option_tags as $option_text => $option_value) {
            $selected = ($selected_value == $option_value) ? ' selected="selected"' : '';
            $option_tags_html .= '<option value="' . $option_value . '"' . $selected . '>' . $option_text . '</option>';
        }

        return $option_tags_html;
    }

    public function __open($method = 'get', $attrs = [])
    {
        echo '<form ' . $this->gluedAttrs($this->buildAttrs($attrs, compact('method'))) . '>' . PHP_EOL;
    }

    public function __close()
    {
        echo '</form>' . PHP_EOL;
    }

    public function __prevent_csrf()
    {
        echo prevent_csrf_field();
    }

    public function tag($tag, $text = '', $attrs = [])
    {
        echo '<' . $tag . '' . $this->gluedAttrs($attrs) . '>' . $text . '</' . $tag . '>' . PHP_EOL;
    }

    public function input($type, $name = '', $value = '', $attrs = [])
    {
        echo '<input' . $this->gluedAttrs($this->buildAttrs($attrs, compact('type', 'name', 'value'))) . ' />' . PHP_EOL;
    }

    public function actionButton($type, $text = '', $value = '', $attrs = [], $name = '')
    {
        echo '<button' . $this->gluedAttrs($this->buildAttrs($attrs, compact('type', 'value', 'name'))) . '>' . $text . '</button>' . PHP_EOL;
    }

    public function __select($name, $options = [], $value = '', $attrs = [])
    {
        $options = $this->generateOptions($options, $value);
        $selectField = '<select' . $this->gluedAttrs($this->buildAttrs($attrs, compact('name'))) . '>';
        $selectField .= $options;
        $selectField .= '</select>' . PHP_EOL;
        echo $selectField;
    }

    public function __textarea($name = '', $value = '', $attrs = [])
    {
        echo '<textarea' . $this->gluedAttrs($this->buildAttrs($attrs, compact('name', 'value'))) . '>' . $value . '</textarea>'. PHP_EOL;
    }

    public function specialInput($type = null, $name = null, $value = null, $attrs = [])
    {
        echo $this->input($type, $name, $value, $attrs);
    }

    public function getCallables($name, $arguments)
    {
        $call = null;

        if (in_array($name, ['hidden', 'text', 'color', 'date', 'dateTimeLocal', 'email', 'month', 'number', 'url', 'week', 'password', 'search', 'tel', 'checkbox', 'radio'])) {
            $name = Str::multiReplace($name, ['dateTimeLocal'], ['datetime-local']);
            $call = 'input';
        } else if (in_array($name, ['file', 'reset', 'imageBtn'])) {
            if (in_array($name, ['file']))
                $arguments = [$this->_argv($arguments, 0), null, $this->_argv($arguments, 1, [])];
            if (in_array($name, ['imageBtn'])) {
                $args = $this->_argv($arguments, 1, []);
                $args['src'] = $this->_argv($arguments, 0);
                $arguments = [$this->_argv($arguments, 2), null, $args];
                $name = 'image';
            } else if ($name == 'reset')
                $arguments = [$this->_argv($arguments, 2), $this->_argv($arguments, 0), $this->_argv($arguments, 1, [])];

            $call = 'specialInput';
        } else if (in_array($name, ['submit', 'button'])) {
            $call = 'actionButton';
        } else if (in_array($name, ['label'])) {
            $arguments = [$this->_argv($arguments, 0), $this->_argv($arguments, 1, [])];
            $call = 'tag';
        }

        return [$call, $name, $arguments];
    }

    public static function __callStatic($name, $arguments)
    {
        $callables = (new static)->getCallables($name, $arguments);

        if (!empty($callables[0]) && method_exists((new static), $callables[0])) {
            array_unshift($callables[2], $callables[1]);
            return (new static)->{$callables[0]}(...$callables[2]);
        }

        if (method_exists((new static), '__' . $callables[1]))
            return (new static)->{'__' . $callables[1]}(...$callables[2]);

        throw new BadMethodException('Method {' . $callables[1] . '} not found in ' . get_class(new static));
    }

    public function __call($name, $arguments)
    {
        $callables = $this->getCallables($name, $arguments);

        if (!empty($callables[0]) && method_exists($this, $callables[0])) {
            array_unshift($callables[2], $callables[1]);
            return $this->{$callables[0]}(...$callables[2]);
        }

        if (method_exists($this, '__' . $callables[1]))
            return $this->{'__' . $callables[1]}(...$callables[2]);

        throw new BadMethodException('Method {' . $callables[1] . '} not found in ' . get_class($this));
    }
}