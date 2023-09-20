<?php

namespace gamecore;

class Room
{
    public int $roomID = 0;
    /** @var Player[] **/
    public array $players = [];
    public int $turn = 0;
    public int $limitTurns = 2;
    public int $playersLimit = 2;
    public array $availableImagesList = [];
    public array $availableTopicsList = [];
    public int $topicID = 0;
    public bool $voteTime = false;
    /** @var Player[] **/
    public array $winners = [];
    public function __construct(Player $player, int $roomID)
    {
        if($player->roomID > 0){
            return null;
        }
        $this->roomID = $roomID;
        $imagesList = file_get_contents(dirname(__FILE__).'/ImagesList.json');
        $this->availableImagesList = json_decode($imagesList,true);
        $topicsList = file_get_contents(dirname(__FILE__).'/TopicsList.json');
        $this->availableTopicsList = json_decode($topicsList,true);
        $this->addPlayer($player);
    }

    private function generateMems(): array{
        $availableList = array_rand($this->availableImagesList,6);
        foreach($availableList as $key) {
            unset($this->availableImagesList[$key]);
        }
        return $availableList;
    }
    private function generateTopic(){
        $this->topicID = array_rand($this->availableTopicsList,1);
        unset($this->availableTopicsList[$this->topicID]);
    }

    public function addPlayer(Player $player){
        if(count($this->players) >= $this->playersLimit){
            return false;
        }
        $this->players[$player->connectionID] = $player;
        $player->startRoomData($this->generateMems(), $this->roomID);
        return true;
    }

    public function removePlayer(Player $player){
        if(!$this->players[$player->connectionID]){
            return false;
        }
        unset($this->players[$player->connectionID]);
        return true;
    }

    public function setSelectedImage(Player $player, int $imageID){
        if($imageID > 0){
            return $player->setSelectedImage($imageID);
        }
        return false;
    }

    public function startVoting(){
        if($this->voteTime == false){
            $this->voteTime = true;
            return true;
        }
        return false;
    }

    public function areAllSelected(){
        $allSelected = true;
        /** @var Player $player */
        foreach ($this->players as $player) {
            if ($player->selectedMem == 0) {
                $allSelected = false;
            }
        }
        return $allSelected;
    }

    public function areAllVoted(){
        $allVoted = true;
        /** @var Player $player */
        foreach ($this->players as $player) {
            if ($player->voted == 0) {
                $allVoted = false;
            }
        }
        if($allVoted){
            if($this->turn >= $this->limitTurns){
                return $this->finishBattle();
            }
            $this->nextTurn();
        }
        return $allVoted;
    }

    public function setVote(Player $player, int $target){
        if(!$this->players[$player->connectionID]){
            return false;
        }
        if(!$this->players[$target]){
            return false;
        }
        if(!$this->voteTime){
            return false;
        }
        return $player->setVoted($target);
    }

    public function startGame(){
        if($this->turn > 0 ){
            return false;
        }
        $this->turn+=1;
        $this->generateTopic();
        return true;
    }

    public function nextTurn(){
        /** @var Player $player */
        foreach ($this->players as $player) {
            $this->players[$player->voted]->points+=100;
            $player->nextTurn();
        }
        $this->turn+=1;
        $this->voteTime = false;
        $this->generateTopic();
    }

    public function getMaxPoints(){
        $points = 0;
        /** @var Player $player */
        foreach ($this->players as $player) {
            if ($player->points > $points) {
                $points = $player->points;
            }
        }
        return $points;
    }

    public function finishBattle(): bool|array{
        if($this->turn >= $this->limitTurns){
            $points = $this->getMaxPoints();
            /** @var Player $player */
            foreach ($this->players as $player){
                $isWinner = false;
                if($player->points == $points){
                    $this->winners[] = $player;
                    $isWinner = true;
                }
                $player->endRoom($isWinner);
            }
            return true;
        }
        return false;
    }

    public function getVotingList(Player $currentPlayer){
        $votingList = [];
        /** @var Player $player */
        foreach ($this->players as $player) {
            if($player->connectionID != $currentPlayer->connectionID){
                $votingList[$player->connectionID] = $player->selectedMem;
            }
        }
        return $votingList;
    }

    public function getPlayersList(Player $currentPlayer){
        $playersList = [];
        /** @var Player $player */
        foreach ($this->players as $player) {
            $playersList[$player->connectionID] = [
                "name"=>$player->userName,
                "points"=>$player->points,
                "self"=>$player->connectionID == $currentPlayer->connectionID
            ];
        }
        return $playersList;
    }

    public function returnTurnData(Player $player){
        if(count($this->winners) > 0){
            $returnData = [
                "winners"=>$this->winners,
                "waiting"=>false,
                "selection"=>false,
                "voting" => false,
                "finished"=>true
            ];
        }else if($this->voteTime) {
            $returnData = [
                "waiting"=>false,
                "selection"=>false,
                "voting" => true,
                "finished"=>false
            ];
            if ($player->voted == 0) {
                $returnData['voteList'] = $this->getVotingList($player);

            }
        }else if($this->turn == 0){
            $returnData = [
                "waiting"=>true,
                "selection"=>false,
                "voting" => false,
                "finished"=>false,
                "canStart"=>$player->connectionID == $this->roomID
            ];
        }else{
            $returnData = [
                "waiting"=>false,
                "selection"=>true,
                "voting" => false,
                "finished"=>false
            ];
            if($player->selectedMem == 0){
                $returnData['topicID'] = $this->topicID;
                $returnData['imageList'] = $player->imagesList;
            }
        }
        $returnData['playersList'] = $this->getPlayersList($player);
        return $returnData;
    }
    public function getRoomData(){
        return [
            "id"=>$this->roomID,
            "name"=>$this->players[$this->roomID]->userName ?? "Player",
            "count"=>count($this->players),
            "limit"=>$this->playersLimit
        ];
    }
}
