<?php

namespace Controllers;

use Bones\Database;
use Bones\Request;
use Models\User;

class DBTestController
{
	public function index(Request $request)
	{
		if ($request->has('command')) {
			if (in_array($request->command, ['select'])) {
				return $this->execute($request->command, $request->all());
			} else {
				return response()->json([
					'status' => 400,
					'message' => 'please_provide_correct_command'
				]);
			}
		}

		return response()->json([
			'status' => 402,
			'message' => 'invalid_command'
		]);
	}

	public function execute($command, $attributes = [])
	{
		if (!empty($command)) {
			if ($command == 'select') {
				$entries = User::where('age', '<', 12)->get();

				opd($entries);

				foreach ($entries as $entry) {
					opd($entry);
				}

				return Database::table($attributes['table_name'])->get();
			}
		}
	}

}
