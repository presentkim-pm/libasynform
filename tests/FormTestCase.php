<?php

declare(strict_types=1);

namespace kim\present\libasynform\tests;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\player\Player;
use PHPUnit\Framework\TestCase;

use function array_key_last;

/**
 * Builds real {@see Player} objects (so Player::sendForm() and Player::onFormSubmit() are the server's own code)
 * whose network session only records the packets it is asked to send.
 */
abstract class FormTestCase extends TestCase{

    /** @var list<ClientboundPacket> */
    protected array $sentPackets = [];

    protected function createPlayer(bool $connected = true) : Player{
        $session = $this->createStub(NetworkSession::class);
        $session->method('sendDataPacket')->willReturnCallback(function(ClientboundPacket $packet) use ($connected) : bool{
            if($connected){
                $this->sentPackets[] = $packet;
            }
            return $connected;
        });
        $session->method('onFormSent')->willReturnCallback(
            function(int $id, \JsonSerializable $form) use ($connected) : bool{
                if($connected){
                    $this->sentPackets[] = ModalFormRequestPacket::create($id, json_encode($form, JSON_THROW_ON_ERROR));
                }
                return $connected;
            }
        );

        $player = (new \ReflectionClass(TestPlayer::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(Player::class, 'networkSession');
        $property->setValue($player, $session);
        return $player;
    }

    /** Id of the form most recently sent as a normal form or as server settings. */
    protected function lastFormId() : int{
        $packet = $this->sentPackets[array_key_last($this->sentPackets)];
        self::assertTrue($packet instanceof ModalFormRequestPacket || $packet instanceof ServerSettingsResponsePacket);
        return $packet->formId;
    }

    /** @return array<string, mixed> */
    protected function lastFormJson() : array{
        $packet = $this->sentPackets[array_key_last($this->sentPackets)];
        self::assertTrue($packet instanceof ModalFormRequestPacket || $packet instanceof ServerSettingsResponsePacket);
        return json_decode($packet->formData, true, 512, JSON_THROW_ON_ERROR);
    }
}
