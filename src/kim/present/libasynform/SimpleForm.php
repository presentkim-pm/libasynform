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

use pocketmine\form\FormValidationException;

use function gettype;
use function is_int;

class SimpleForm extends BaseStringContentForm{

    public const IMAGE_TYPE_PATH = 0;
    public const IMAGE_TYPE_URL = 1;

    public function __construct(string $title = "", string $content = ""){
        parent::__construct($title, $content);
        //A form without a "buttons" key is rejected by the client, so always send the list
        $this->data["buttons"] = [];
    }

    /**
     * @param string   $text      Button text
     * @param int|null $imageType {@see IMAGE_TYPE_PATH} or {@see IMAGE_TYPE_URL}, or null (default) for no image
     * @param string   $imagePath Texture path or URL, depending on $imageType
     *
     * @return $this
     * @throws \InvalidArgumentException if $imageType is not a known image type
     */
    public function addButton(string $text, ?int $imageType = null, string $imagePath = "") : self{
        $content = ["text" => $text];
        if($imageType !== null){
            $content["image"] = [
                "type" => match ($imageType) {
                    self::IMAGE_TYPE_PATH => "path",
                    self::IMAGE_TYPE_URL => "url",
                    default => throw new \InvalidArgumentException("Unknown image type $imageType")
                },
                "data" => $imagePath
            ];
        }
        $this->data["buttons"][] = $content;
        return $this;
    }

    protected function processData(mixed $data) : int{
        if(!is_int($data)){
            throw new FormValidationException("Expected an integer response, got " . gettype($data));
        }

        if(!isset($this->data["buttons"][$data])){
            throw new FormValidationException("Button $data does not exist");
        }

        return $data;
    }

    public static function getType() : string{
        return "form";
    }

    public static function create(string $title = "", string $content = "") : self{
        return new self($title, $content);
    }
}
