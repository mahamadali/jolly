<?php

namespace Models\Traits;

trait TrashMask
{
    public function getTrashMaskColumn()
    {
        return !empty($this->trash_mask_column)
            ? $this->trash_mask_column
            : "deleted_at";
    }

    public function setTrashMask()
    {
        $this->setSelfOnly(false);
        return $this->update([
            $this->getTrashMaskColumn() => date("Y-m-d H:i:s"),
        ]);
    }
}