<?php

namespace SMS;

use Contributors\SMS\Texter;

class AccountActivated extends Texter
{
	protected $user;

	public function __construct($user)
	{
		$this->user = $user;
	}

	public function prepare()
	{
		return $this->template('sms/account-activated', ['user' => $this->user])
					->to($this->user->contact_number);
	}
}