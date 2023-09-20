<?php

namespace gamecore;

class Processor
{
    public static function createRoom(Player $player, int $roomID): ?Room {
        if($player->roomID > 0){
            return null;
        }
        return new Room($player, $roomID);
    }
}
