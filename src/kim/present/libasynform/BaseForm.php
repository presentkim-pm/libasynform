<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\libasynform;

use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

abstract class BaseForm implements Form{

    abstract public static function getType() : string;

    abstract protected function processData(mixed $data) : mixed;

    /**
     * Pending promises of the players who are viewing this form.
     *
     * Keyed by the player object itself (not by spl_object_id(), which is reused once a player object is freed),
     * and weakly, so a player who quits without answering does not keep the generator's closures alive.
     *
     * @var \WeakMap<Player, array{\Closure, \Closure}>
     */
    private \WeakMap $promises;

    /** @var array<string, mixed> */
    protected array $data;

    public function __construct(
        string $title = ""
    ){
        $this->promises = new \WeakMap();
        $this->data = [
            "type" => static::getType(),
            "title" => $title
        ];
    }

    public function getTitle() : string{
        return $this->data["title"];
    }

    public function setTitle(string $title) : self{
        $this->data["title"] = $title;
        return $this;
    }

    /**
     * Called by the server when the player answers (or closes) the form.
     * Resumes the generator waiting in {@see send()}.
     */
    public function handleResponse(Player $player, $data) : void{
        if(!isset($this->promises[$player])){
            throw new FormValidationException("Received response from player who didn't receive the form");
        }
        [$resolve, $reject] = $this->promises[$player];
        unset($this->promises[$player]);

        if($data === null){
            $resolve(null);
            return;
        }

        try{
            $data = $this->processData($data);
        }catch(\Exception $e){
            $reject($e);
            return;
        }

        $resolve($data);
    }

    /**
     * Sends this form to the player and waits for the answer.
     *
     * Returns the processed response, or null when the player closed the form.
     * Throws {@see FormValidationException} when the response is invalid, when the player is already viewing this form
     * or when the form could not be sent (e.g. the player is disconnected).
     *
     * NOTE: If the player quits while the form is open the server never reports it, so the generator is never resumed.
     * Combine with Await::race() and your own quit handling when that matters.
     *
     * @phpstan-return \Generator<mixed, mixed, mixed, mixed>
     */
    public function send(Player $player, bool $isServerSetting = false) : \Generator{
        return yield from Await::promise(function(\Closure $resolve, \Closure $reject) use ($player, $isServerSetting) : void{
            if(isset($this->promises[$player])){
                $reject(new FormValidationException("Player is already viewing a form"));
                return;
            }

            //Register first, so a response that is handled while sending is never lost
            $this->promises[$player] = [$resolve, $reject];
            try{
                $sent = $isServerSetting ? $this->sendAsServerSetting($player) : $this->sendAsForm($player);
            }catch(\Throwable $e){
                unset($this->promises[$player]);
                $reject($e);
                return;
            }

            if(!$sent){
                unset($this->promises[$player]);
                $reject(new FormValidationException("Form was not sent to the player"));
            }
        });
    }

    private function sendAsForm(Player $player) : bool{
        $player->sendForm($this);

        //Player::sendForm() returns nothing and silently drops the form when the packet could not be sent.
        return \Closure::bind( //HACK: Closure bind hack to access inaccessible members
            closure: fn(Form $form) : bool => in_array($form, $this->forms, true),
            newThis: $player,
            newScope: Player::class
        )($this);
    }

    private function sendAsServerSetting(Player $player) : bool{
        return \Closure::bind( //HACK: Closure bind hack to access inaccessible members
            closure: function(Form $form) : bool{
                $encoded = json_encode($form, JSON_THROW_ON_ERROR);
                $id = $this->formIdCounter++;
                if(!$this->getNetworkSession()->sendDataPacket(ServerSettingsResponsePacket::create($id, $encoded))){
                    return false;
                }
                $this->forms[$id] = $form;
                return true;
            },
            newThis: $player,
            newScope: Player::class
        )($this);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize() : array{
        return $this->data;
    }
}
