<?php

namespace Bones;

use Bones\Skeletons\Supporters\AutoMethodMap;
use Contributors\Mail\Mailer;
use Contributors\SMS\Texter;
use Bones\AlertException;

class Alert extends AutoMethodMap
{
    protected $notifiable;

    public function __construct($notifiable = null)
    {
        $this->notifiable = $notifiable;
    }

    public function __as($notifiable)
    {
        $this->notifiable = $notifiable;
        return $this;
    }

    protected function runInBackground()
    {
        return !empty($this->notifiable->run_in_background) && $this->notifiable->run_in_background == true;
    }

    public function notify()
    {
        if (!empty($this->notifiable)) {
            if ($this->runInBackground()) {
                $this->notifiable->setAction();
                return true;
            } else {
                if (is_subclass_of($this->notifiable, Mailer::class)) {
                    return $this->notifiable->prepare()->send();
                } else if (is_subclass_of($this->notifiable, Texter::class)) {
                    return $this->notifiable->prepare()->send();
                }
            }
        }

        throw new AlertException('Empty notifiable entity passed');
    }

}