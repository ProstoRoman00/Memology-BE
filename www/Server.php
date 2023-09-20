<?php

use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use gamecore\Player;
use gamecore\Processor;
use gamecore\Room;

require __DIR__ . '/vendor/autoload.php';

Swoole\Runtime::enableCoroutine();


class GameCore {
    private Server $server;
    /** @var Player[] **/
    private array $players = [];
    /** @var Room[] **/
    private array $rooms = [];
    public function __construct()
    {
        $this->server = new Server("0.0.0.0", 7359);
        $this->server->on("Start", function (Server $server) {
            echo "Memology server started on http://0.0.0.0:7359\n";
        });

        $this->server->on('handshake', function (Swoole\HTTP\Request $request, Swoole\HTTP\Response $response)
        {
            $secWebSocketKey = $request->header['sec-websocket-key'];
            $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

            // At this stage if the socket request does not meet custom requirements, you can ->end() it here and return false...

            // Websocket handshake connection algorithm verification
            if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey)))
            {
                $response->end();
                return false;
            }
            $connection = true;

            if(!$connection){
                $response->end();
                return false;
            }

            echo $request->header['sec-websocket-key'];

            $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $headers = [
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => $key,
                'Sec-WebSocket-Version' => '13',
                'Server' => 'Memology Core'
            ];

            if(isset($request->header['sec-websocket-protocol']))
            {
                $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
            }

            foreach($headers as $key => $val)
            {
                $response->header($key, $val);
            }

            $this->players[$request->fd] = new Player($request->fd);

            $response->status(101);
            $response->end();
            $returnData['userData']=$this->players[$request->fd]->getUserData();
            $returnData['rooms']=$this->getRoomsList();
            $this->server->push($request->fd, json_encode($returnData));
            echo "connected! ".$request->fd. PHP_EOL;
            return true;
        });

        $this->server->on('Message', function (Server $server, Frame $frame) {
            try {
                $data = json_decode($frame->data, true);
                switch ($data['type']) {
                    case "getRoomsList":
                        $returnData['rooms'] = $this->getRoomsList();
                        $returnData['userData']=$this->players[$frame->fd]->getUserData();
                        $server->push($frame->fd, json_encode($returnData));
                        break;
                    case "createRoom":
                        $this->rooms[$frame->fd] = Processor::createRoom($this->players[$frame->fd], $frame->fd);
                        if($this->rooms[$frame->fd]){
                            $returnData = $this->rooms[$frame->fd]->returnTurnData($this->players[$frame->fd]);
                            $returnData['userData']=$this->players[$frame->fd]->getUserData();
                            $returnData['rooms'] = $this->getRoomsList();
                            $server->push($frame->fd, json_encode($returnData));
                        }
                        break;
                    case "joinRoom":
                        if ($data['roomID'] && $this->rooms[$data['roomID']]) {
                            if ($this->rooms[$data['roomID']]->addPlayer($this->players[$frame->fd])) {
                                foreach ($this->rooms[$this->players[$frame->fd]->roomID]->players as $anyPlayer) {
                                    $returnData = $this->rooms[$anyPlayer->roomID]->returnTurnData($anyPlayer);
                                    $returnData['userData']=$anyPlayer->getUserData();
                                    $server->push($anyPlayer->connectionID, json_encode($returnData));
                                }
                            } else {
                                $returnData['message'] = [
                                    "type" => "error",
                                    "code" => "cannotJoinRoom"
                                ];
                                $returnData['userData']=$this->players[$frame->fd]->getUserData();
                                $server->push($frame->fd, json_encode($returnData));
                            }
                        } else {
                            $returnData['message'] = [
                                "type" => "error",
                                "code" => "cannotJoinRoom"
                            ];
                            $returnData['userData']=$this->players[$frame->fd]->getUserData();
                            $server->push($frame->fd, json_encode($returnData));
                        }
                        break;
                    case "startGame":
                        $player = $this->players[$frame->fd];
                        print_r($this->rooms[$player->roomID]->players);
                        if ($player->roomID == $frame->fd && $this->rooms[$player->roomID]) {
                            if ($this->rooms[$player->roomID]->startGame()) {
                                foreach ($this->rooms[$player->roomID]->players as $anyPlayer) {
                                    $returnData = $this->rooms[$player->roomID]->returnTurnData($anyPlayer);
                                    $returnData['userData']=$anyPlayer->getUserData();
                                    $server->push($anyPlayer->connectionID, json_encode($returnData));
                                }
                            } else {
                                $returnData['message'] = [
                                    "type" => "error",
                                    "code" => "alreadyStarted"
                                ];
                                $returnData['userData']=$this->players[$frame->fd]->getUserData();
                                $server->push($frame->fd, json_encode($returnData));
                            }
                        } else {
                            $returnData['message'] = [
                                "type" => "error",
                                "code" => "onlyLeaderCanStart"
                            ];
                            $returnData['userData']=$this->players[$frame->fd]->getUserData();
                            $server->push($frame->fd, json_encode($returnData));
                        }
                        break;
                    case "setSelectedImage":
                        $player = $this->players[$frame->fd];
                        if ($player->roomID && $this->rooms[$player->roomID]) {
                            $room = $this->rooms[$player->roomID];
                            if ($room->setSelectedImage($player, $data['imageID'])) {
                                if ($room->areAllSelected()) {
                                    $room->startVoting();
                                    foreach ($room->players as $anyPlayer) {
                                        $returnData = $room->returnTurnData($anyPlayer);
                                        $returnData['userData']=$anyPlayer->getUserData();
                                        $server->push($anyPlayer->connectionID, json_encode($returnData));
                                    }
                                } else {
                                    $returnData = $room->returnTurnData($player);
                                    $returnData['userData']=$this->players[$frame->fd]->getUserData();
                                    $server->push($frame->fd, json_encode($returnData));
                                }
                            } else {
                                $returnData = $room->returnTurnData($player);
                                $returnData['message'] = [
                                    "type" => "error",
                                    "code" => "cannotSelectImage"
                                ];
                                $returnData['userData']=$this->players[$frame->fd]->getUserData();
                                $server->push($frame->fd, json_encode($returnData));
                            }
                        }
                        break;
                    case "setVote":
                        $player = $this->players[$frame->fd];
                        if ($player->roomID && $this->rooms[$player->roomID]) {
                            $room = $this->rooms[$player->roomID];
                            if ($room->setVote($player, $data['userID'])) {
                                if ($room->areAllVoted()) {
                                    foreach ($room->players as $anyPlayer) {
                                        $returnData = $room->returnTurnData($anyPlayer);
                                        $returnData['userData']=$anyPlayer->getUserData();
                                        $server->push($anyPlayer->connectionID, json_encode($returnData));
                                    }
                                } else {
                                    $returnData = $room->returnTurnData($player);
                                    $returnData['userData']=$this->players[$frame->fd]->getUserData();
                                    $server->push($frame->fd, json_encode($returnData));
                                }
                            } else {
                                $returnData = $room->returnTurnData($player);
                                $returnData['message'] = [
                                    "type" => "error",
                                    "code" => "cannotVote"
                                ];
                                $returnData['userData']=$this->players[$frame->fd]->getUserData();
                                $server->push($frame->fd, json_encode($returnData));
                            }
                        }
                        break;
                }
            }catch (Exception $e){

            }
            echo "received message: {$frame->data}\n";
        });

        $this->server->on('Close', function (Server $server, int $fd) {
            if($this->players[$fd]->roomID > 0 && $this->rooms[$this->players[$fd]->roomID] != null){
                $this->rooms[$this->players[$fd]->roomID]->removePlayer($this->players[$fd]);
            }
            unset($this->players[$fd]);
            echo "connection close: {$fd}\n";
        });

        $this->server->on('Disconnect', function (Server $server, int $fd) {
            unset($this->players[$fd]);
            echo "connection disconnect: {$fd}\n";
        });

        $this->server->start();
    }

    function getRoomsList(){
        $data = [];
        foreach ($this->rooms as $key => $room){
            if($room != null){
                $data[$room->roomID] = $room->getRoomData();
            }
            if(count($room->players) == 0){
                unset($this->rooms[$key]);
            }
        }
        return $data;
    }
}
new GameCore();
