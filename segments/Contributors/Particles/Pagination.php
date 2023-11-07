<?php

namespace Contributors\Particles;

use Bones\URL;

class Pagination
{
    protected $attributes = [];
    protected $classes = [];

    public function __construct($attributes = [], $query_param = 'page')
    {
        $this->attributes = (object) $attributes;
        $this->attributes->query_param = $query_param;
        $this->classes[] = 'pagination';
    }

    public function addClass($class)
    {
        $this->classes[] = $class;
        $this->classes = array_unique($this->classes);
        return $this;
    }

    public function links()
    {
        $start      = (($this->attributes->current_page - $this->attributes->per_page) > 0) ? $this->attributes->current_page - $this->attributes->per_page : 1;
        $end        = (($this->attributes->current_page + $this->attributes->per_page) < $this->attributes->total_pages) ? $this->attributes->current_page + $this->attributes->per_page : $this->attributes->total_pages;

        $pagination = '<ul class="'.implode(' ', $this->classes).'">';
        $class      = ($this->attributes->current_page == 1) ? "disabled" : "";

        if ($this->attributes->current_page > $start) {
            $pagination .= '<li class="nav ' . $class . '"><a href="' . URL::addQueryParamToCurrentPage($this->attributes->query_param, 1) . '"><<</a></li>';
            $pagination .= '<li class="nav ' . $class . '"><a href="' . URL::addQueryParamToCurrentPage($this->attributes->query_param, $this->attributes->current_page - 1) . '"><</a></li>';
        }

        if ($start > 1) {
            $pagination   .= '<li><a href="' . URL::addQueryParamToCurrentPage($this->attributes->query_param, 1) . '">1</a></li>';
            $pagination   .= '<li class="disabled"><span>...</span></li>';
        }

        for ($page = $start; $page <= $end; $page++) {
            $class  = ($this->attributes->current_page == $page) ? "active" : "";
            $pagination .= '<li class="' . $class . '"><a href="' . URL::addQueryParamToCurrentPage($this->attributes->query_param, $page) . '">' . $page . '</a></li>';
        }

        if ($end < $this->attributes->total_pages) {
            $pagination .= '<li class="disabled"><span>...</span></li>';
            $pagination .= '<li><a href="' . URL::addQueryParamToCurrentPage($this->attributes->query_param, $this->attributes->total_pages) . '">' . $this->attributes->total_pages . '</a></li>';
        }

        $class = ($this->attributes->current_page == $this->attributes->total_pages) ? "disabled" : "";

        if ($this->attributes->current_page < $this->attributes->total_pages) {
            $pagination .= '<li class="nav ' . $class . '"><a href="' . URL::addQueryParamToCurrentPage($this->attributes->query_param, ($this->attributes->current_page + 1)) . '">></a></li>';
            $pagination .= '<li class="nav ' . $class . '"><a href="' . URL::addQueryParamToCurrentPage($this->attributes->query_param, $end) . '">>></a></li>';
        }

        $pagination .= '</ul>';

        return $pagination;
    }
}