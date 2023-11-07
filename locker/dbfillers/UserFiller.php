<?php

namespace Bones\Skeletons\DBFiller;

use Bones\Database;

return new class
{
	protected $table = 'tl_users';

	public function fill()
	{
		Database::table($this->table)->insertMulti([
			[
				'first_name' => 'Mahammadali',
				'last_name' => 'Manknojiya',
				'age' => '29',
				'address' => 'Near new masid, chadotar - 385001'
			],
			[
				'first_name' => 'Ayat Zahera',
				'last_name' => 'Manknojiya',
				'age' => '3',
				'address' => 'Baagh-e-Batul, Near new masid, chadotar - 385001'
			]
		]);
	}

};