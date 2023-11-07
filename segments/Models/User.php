<?php

namespace Models;

use Models\Base\Model;
use Models\Traits\TrashMask;

class User extends Model
{
    protected $table = 'tl_users';

    use TrashMask;

    public function getFullNameproperty()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }

}
