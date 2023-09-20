<?php

namespace gamecore;

class Player
{
    public string $userName;
    public int $connectionID;
    public int $roomID =  0;
    public int $gamesWON = 0;

    public int $points =  0;
    public int $selectedMem = 0;
    public int $voted =  0;
    public array $imagesList = [];

    function __construct(int $connectionID, string $userName = "Player")
    {
        $this->userName = $userName.$connectionID;
        $this->connectionID = $connectionID;
    }

    public function getUserData(){
        return [
            "id"=>$this->connectionID,
            "name"=>$this->userName,
            "gamesWon"=>$this->gamesWON,
            "roomID"=>$this->roomID
        ];
    }

    public function startRoomData(array $imagesList, int $roomID){
        $this->imagesList = $imagesList;
        $this->points = 0;
        $this->voted = 0;
        $this->selectedMem = 0;
        $this->roomID = $roomID;
    }
    public function nextTurn(){
        $this->voted = 0;
        $this->selectedMem = 0;
    }

    public function endRoom(bool $isWinner){
        $this->imagesList = [];
        $this->points = 0;
        $this->voted = 0;
        $this->gamesWON+=$isWinner;
        $this->roomID = 0;
    }

    public function setSelectedImage(int $index){
        $imageIndex = array_search($index,$this->imagesList);
        if($this->selectedMem || !$imageIndex){
            return false;
        }
        $this->selectedMem = $index;
        array_splice($this->imagesList, $imageIndex, 1);
        return true;
    }

    public function setVoted(int $playerID){
        if($this->voted != 0){
            return false;
        }
        $this->voted = $playerID;
        return true;
    }
}
