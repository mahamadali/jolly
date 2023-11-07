<?php

use Barriers\VerifyRequest;

return [

	// Add Barrier aliases to use as an alias
	'Barriers' => [
		'verify-request' => VerifyRequest::class,
	],

	'Form' => Contributors\Particles\Form::class,
	'Html' => Contributors\Particles\Html::class,

];