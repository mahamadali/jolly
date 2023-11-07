<?php

use Bones\DataWing;
use Bones\Skeletons\DataWing\Skeleton;

return new class 
{

	protected $table = 'tl_user_posts';

	public function arise()
	{
		DataWing::create($this->table, function (Skeleton $table)
		{
			$table->id()->primaryKey();
			$table->unsignedBigInteger('user_id')->nullable(false);
			$table->unsignedBigInteger('post_id')->nullable(false);
			$table->timestamps();

			return $table;
		});
	}

	public function fall()
	{
		DataWing::drop($this->table);
	}

};
