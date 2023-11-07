<?php

namespace Bones\Skeletons\Supporters;

use Bones\Commander;
use Bones\Database;

class BackgroundAction
{
    protected $bg_actions_table = 'background_actions';

    public function __construct()
    {
        $this->console = (new Commander());
    }

    public function add($action = [])
    {
        $this->setupGround($this->bg_actions_table);
        return Database::table($this->bg_actions_table)->insert($action);
    }

    protected function pending()
    {
        return Database::table($this->bg_actions_table)->where('is_processed', 0)->orderBy('id', 'ASC')->get();
    }

    protected function setFailed($id, $reason = '')
    {
        return Database::table($this->bg_actions_table)->where('id', $id)->update([
            'failed_reason' => $reason,
            'is_processed' => 1
        ]);
    }

    protected function delete($id)
    {
        return Database::table($this->bg_actions_table)->where('id', $id)->delete();
    }

    public function clearAll()
    {
        return Database::table($this->bg_actions_table)->truncate();
    }

    public function proceedBGActions($listen = false)
    {
        $this->setupGround($this->bg_actions_table);

        $actions = $this->pending();
        if (count($actions) == 0 && !$listen) {
            $this->console->showMsgAndExit('No pending background actions found' . PHP_EOL, [], 'info');
        }
        if ($listen) {
            $this->console->showMsgAndContinue('Listening background actions...' . PHP_EOL, [], 'warning');
        }
        foreach ($actions as $action) {
            $processed = false;

            if (!$listen)
                $this->console->showMsgAndContinue('Executing process #%d for %s' . PHP_EOL, [$action->id, $action->for], 'warning');

            if (!empty($action->for)) {
                $params = unserialize($action->draft);
                $callable = new $action->for(...$params);
                if (!empty($callable) && is_object($callable) && method_exists($callable, $action->action)) {
                    try {
                        $callable->{$action->action}();
                        $processed = true;
                    } catch (\Exception $e) {
                        if (ob_get_length() > 0)
                            ob_clean();

                        $processed = false;
                        $this->setFailed($action->id, $e->getMessage() . ' at ' . $e->getFile() . ' on line #' . $e->getLine());
                        $this->console->showMsgAndContinue('process #%d for %s is failed' . PHP_EOL, [$action->id, $action->for], 'error');
                    }
                } else {
                    $this->setFailed($action->id, 'No callable action found as {'.$action->action.'} in ' . $action->for);
                    $this->console->showMsgAndContinue('process #%d for %s is failed' . PHP_EOL, [$action->id, $action->for], 'error');
                }

                if ($processed) {
                    $this->delete($action->id);
                    $this->console->showMsgAndContinue('process #%d for %s is completed' . PHP_EOL, [$action->id, $action->for], 'success');
                }
            }
        }
    }

    public function listenBGActions()
    {
        while(true) {
            $this->proceedBGActions(true);
            sleep(5);
        }
    }

    public function setupGround()
    {
        Database::rawQuery('CREATE TABLE IF NOT EXISTS `' . $this->bg_actions_table . '` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `for` varchar(255) NOT NULL,
            `draft` longtext NOT NULL,
            `action` varchar(255) DEFAULT NULL,
            `is_processed` tinyint(4) NOT NULL DEFAULT 0,
            `failed_reason` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
          )');
    }

}