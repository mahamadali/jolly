<?php

namespace Contributors\Particles;

use Bones\Str;
use Contributors\Particles\Traits\AttrBuilder;
use Models\Base\Model;

class Html
{
    use AttrBuilder;

    protected $reserved_attrs = ['cell', 'multifields', 'link_to', 'prefix', 'map', 'link'];

    public function tag($tag, $text, $attrs = [], $excludeAttrs = [], $return = false)
    {
        $tag = '<' . $tag . '' . $this->gluedAttrs($attrs, $excludeAttrs) . '>' . $text . '</' . $tag . '>' . PHP_EOL;
        if (!$return)
            echo $tag;
        else
            return $tag;
    }

    public function tagSelfClosed($tag, $attrs = [], $excludeAttrs = [], $return = false)
    {
        $tag = '<' . $tag . '' . $this->gluedAttrs($attrs, $excludeAttrs) . '/>' . PHP_EOL;
        if (!$return)
            echo $tag;
        else
            return $tag;
    }

    public function tagReturn($tag, $text, $attrs = [], $excludeAttrs = [])
    {
        return $this->tag($tag, $text, $attrs, $excludeAttrs, true);
    }

    public function tagSelfClosedReturn($tag, $attrs = [], $excludeAttrs = [])
    {
        return $this->tagSelfClosed($tag, $attrs, $excludeAttrs, true);
    }

    public function __startTag($tag, $attrs = [])
    {
        echo '<' . $tag . $this->gluedAttrs($this->buildAttrs($attrs)) . '>' . PHP_EOL;
    }

    public function __closeTag($tag)
    {
        echo '</' . $tag . '>' . PHP_EOL;
    }

    public function getModelNodeVal($entry, $column, $attrs = [])
    {
        if ($entry instanceof Model && isset($entry->{$column}))
            return $entry->{$column};
        else if (is_array($entry) && isset($entry[$column]))
            return $entry[$column];
        else if (is_object($entry) && isset($entry->$column))
            return $entry->$column;
    }

    public function getArrayNodeValue($entry, $column, $default = '')
    {
        if (!empty($entry) && isset($entry[$column]))
            return $entry[$column];
        
        return $default;
    }

    public function prepareCellContent($value, $column, $columnName)
    {
        if (is_array($column) && !empty($column)) {
            if (array_key_exists('type', $column)) {
                $column['type'] = strtolower($column['type']);
                if ($column['type'] == 'img') {
                    $columnName = (!empty($column['map'])) ? $column['map'] : $columnName;
                    $column['prefix'] = (!empty($column['prefix'])) ? $column['prefix'] : '';
                    $column['src'] = $column['prefix'] . $this->getModelNodeVal($value, $columnName);
                    $cell_content = $this->tagSelfClosedReturn('img', $column, array_merge($this->reserved_attrs, ['type', 'prefix']));
                } else if ($column['type'] == 'button') {
                    $column['type'] = 'button';
                    $cell_content = $this->tagReturn('button', $this->getArrayNodeValue($column, 'value', ucfirst($columnName)), $column);
                } else if ($column['type'] == 'link') {
                    $column['href'] = (!empty($column['href'])) ? $column['href'] : url(request()->currentPage());
                    $cell_content = $this->tagReturn('a', $this->getArrayNodeValue($column, 'value', ucfirst($columnName)), $column);
                } 
                return $cell_content;
            } else {
                if (!empty($column['map'])) {
                    $cell_content = $this->tagReturn('span', $this->getModelNodeVal($value, $column['map']), $column, array_merge($this->reserved_attrs, ['map']));
                    return $cell_content;
                } else if (isset($column['value'])) {
                    return $column['value'];
                } else {
                    $cell_content = $this->tagReturn('span', $this->getModelNodeVal($value, $columnName), $column, $this->reserved_attrs);
                    return $cell_content;
                }
            }
        } else {
            $cell_content = $this->tagReturn('span', $this->getModelNodeVal($value, $column), [], $this->reserved_attrs);
            return $cell_content;
        }
    }

    public function prepareLink($link, $entry)
    {
        $link_segments = explode('/', $link);

        if (count($link_segments) == 0) return $link;

        foreach ($link_segments as &$segment) {
            if (Str::startsWith($segment, '{:') && Str::endsWith($segment, '}')) {
                $pretty_segment = Str::removeCharAt($segment, 0);
                $pretty_segment = Str::removeCharAt($pretty_segment, 0);
                $pretty_segment = Str::removeCharAt($pretty_segment, strlen($pretty_segment) - 1);
                $segment = $this->getModelNodeVal($entry, $pretty_segment);
            }
        }

        return implode('/', $link_segments);
    }

    public static function table($data, $columns = [], $attrs = [])
    {
        $caller = (new static);
        echo '<table' . $caller->gluedAttrs($attrs) . '>' . PHP_EOL;

        $headers = [];
        foreach($columns as $columnName => $column) {
            $heading = $column;
            if (is_array($heading))
                $heading = (!empty($column) && !empty($column['heading'])) ? $heading['heading'] : $columnName;
            else
                $heading = $column;

            $headers[] = Str::toReadable($heading);
        }

        echo '<thead>' . PHP_EOL;
        echo '<tr>' . PHP_EOL;
        foreach ($headers as $heading) {
            echo $caller->tag('th', $heading);
        }
        echo '</tr>' . PHP_EOL;
        echo '</thead>' . PHP_EOL;

        foreach ($data as $key => $value) {
            echo '<tr>' . PHP_EOL;
            foreach ($columns as $columnName => $column) {
                $cell_attrs = (!empty($column['cell'])) ? $column['cell'] : [];
                $has_link = (!empty($column['link'])) ? $column['link'] : null;

                $field_map_approach = 'single';
                if (is_array($column) && array_key_exists('multifields', $column) && $column['multifields']) 
                    $field_map_approach = 'multifields';
                
                if ($field_map_approach == 'single') {
                    $cell_content = $caller->prepareCellContent($value, $column, $columnName);
                    if (!empty($has_link)) {
                        $has_link['href'] = (!empty($has_link['href'])) ? $caller->prepareLink($has_link['href'], $value) : url(request()->currentPage());
                        $cell_link_wrapper = $caller->tagReturn('a', $cell_content, $has_link);
                        echo $caller->tag('td', $cell_link_wrapper, $cell_attrs);
                    } else {
                        echo $caller->tag('td', $cell_content, $cell_attrs);
                    }
                } else if ($field_map_approach == 'multifields') {
                    $cell_content = '';
                    foreach ($column['fields'] as $field) {
                        $cell_content .= $caller->prepareCellContent($value, $field, $columnName);
                    }
                    if (!empty($has_link)) {
                        $has_link['href'] = (!empty($has_link['href'])) ? $caller->prepareLink($has_link['href'], $value) : url(request()->currentPage());
                        $cell_link_wrapper = $caller->tagReturn('a', $cell_content, $has_link);
                        echo $caller->tag('td', $cell_link_wrapper, $cell_attrs);
                    } else {
                        echo $caller->tag('td', $cell_content, $cell_attrs);
                    }
                }
            }
            echo '</tr>' . PHP_EOL;
        }
        echo '</table>' . PHP_EOL;
    }

    public static function __callStatic($name, $arguments)
    {
        if (in_array($name, ['startTag', 'closeTag']))
            return (new static)->{'__' . $name}(...$arguments);

        $arguments = [(new static)->_argv($arguments, 0), (new static)->_argv($arguments, 1, [])];
        array_unshift($arguments, $name);
        $call = 'tag';
        return (new static)->{$call}(...$arguments);
    }

    public function __call($name, $arguments)
    {
        if (in_array($name, ['startTag', 'closeTag']))
            return $this->{'__' . $name}(...$arguments);
        
        $arguments = [$this->_argv($arguments, 0), $this->_argv($arguments, 1, [])];
        array_unshift($arguments, $name);
        $call = 'tag';
        return $this->{$call}(...$arguments);
    }

}