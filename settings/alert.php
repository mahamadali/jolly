<?php

return [

	// Mail configuration
	'mail' => [

		'via' => 'smtp', // default | SMTP

		'from' => [
				'email' => 'admin@administration.com',
				'name' => 'Administration',
		],

		'reply' => [
				'email' => 'reply@administration.com',
				'name' => 'Administration',
		],

		'smtp' => [
				'host' => 'smtp.example.com',
				'username' => 'username',
				'password' => 'password',
				'port' => 465,
				'encryption' => 'tls', // SSL | TLS
				'debug' => false,
				'auth' => true,
		],

	],

	// SMS configuration
	'sms' => [

		'via' => 'twilio', // twilio

		'twilio' => [
			'account_sid' => 'TWILIO_ACCOUNT_SID',
			'auth_token' => 'TWILIO_AUTH_TOKEN',
			'from_number' => 'TWILIO_FROM_NUMBER',
		],

	],

];