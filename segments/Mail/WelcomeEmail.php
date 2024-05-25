<?php

namespace Mail;

use Contributors\Mail\Mailer;

class WelcomeEmail extends Mailer
{
    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function prepare()
    {
        return $this->html('hello')
                    ->to('test')
                    ->subject('Welcome to ' . setting('app.title', 'Jolly Framework!'))
                    ->attach('path/to/file/one.csv')
                    ->attach('path/to/file/two.txt', 'custom-name.txt') // Attach with custom name
                    ->attach([ // Attach set of attachments
                        'path/to/file/three.png',
                        'path/to/file/four.gif' => 'dancing-elephant.gif' // Attach with custom name
                    ]);
    }

}