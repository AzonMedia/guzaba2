<?php


namespace Guzaba2\Database;


class FileSystem implements TargetInterface
{

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return 'filesystem';
    }
}