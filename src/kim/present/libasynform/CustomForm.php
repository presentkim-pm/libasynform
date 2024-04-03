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

use function count;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

class CustomForm extends BaseForm{

    private array $labelMap = [];
    private array $validators = [];

    public function __construct(string $title = ""){
        parent::__construct($title);
        $this->data["content"] = [];
    }

    public function addLabel(string $text, ?string $label = null) : self{
        $this->addContent(["type" => "label", "text" => $text]);
        $this->labelMap[] = $label ?: count($this->labelMap);
        $this->validators[] = static fn($v) => $v === null;
        return $this;
    }

    public function addToggle(string $text, bool $default = null, ?string $label = null) : self{
        $content = ["type" => "toggle", "text" => $text];
        if($default !== null){
            $content["default"] = $default;
        }
        $this->addContent($content);
        $this->labelMap[] = $label ?: count($this->labelMap);
        $this->validators[] = static fn($v) => is_bool($v);
        return $this;
    }

    public function addSlider(string $text, int $min, int $max, int $step = -1, int $default = -1, ?string $label = null
    ) : self{
        $content = ["type" => "slider", "text" => $text, "min" => $min, "max" => $max];
        if($step !== -1){
            $content["step"] = $step;
        }
        if($default !== -1){
            $content["default"] = $default;
        }
        $this->addContent($content);
        $this->labelMap[] = $label ?: count($this->labelMap);
        $this->validators[] = static fn($v) => (is_float($v) || is_int($v)) && $v >= $min && $v <= $max;
        return $this;
    }

    public function addStepSlider(string $text, array $steps, int $defaultIndex = -1, ?string $label = null) : self{
        $content = ["type" => "step_slider", "text" => $text, "steps" => $steps];
        if($defaultIndex !== -1){
            $content["default"] = $defaultIndex;
        }
        $this->addContent($content);
        $this->labelMap[] = $label ?: count($this->labelMap);
        $this->validators[] = static fn($v) => is_int($v) && isset($steps[$v]);
        return $this;
    }

    public function addDropdown(string $text, array $options, int $default = null, ?string $label = null) : self{
        $this->addContent(["type" => "dropdown", "text" => $text, "options" => $options, "default" => $default]);
        $this->labelMap[] = $label ?: count($this->labelMap);
        $this->validators[] = static fn($v) => is_int($v) && isset($options[$v]);
        return $this;
    }

    public function addInput(string $text, string $placeholder = "", string $default = null, ?string $label = null
    ) : self{
        $this->addContent(["type" => "input", "text" => $text, "placeholder" => $placeholder, "default" => $default]);
        $this->labelMap[] = $label ?: count($this->labelMap);
        $this->validators[] = static fn($v) => is_string($v);
        return $this;
    }

    private function addContent(array $content) : void{
        $this->data["content"][] = $content;
    }

    public function processData($data) : array{
        if(!is_array($data)){
            throw new FormValidationException("Expected an array response, got " . gettype($data));
        }

        if(count($data) !== count($this->validators)){
            throw new FormValidationException(
                "Expected an array response with the size " . count($this->validators) . ", got " . count($data)
            );
        }
        $new = [];
        foreach($data as $i => $v){
            $validator = $this->validators[$i] ?? null;
            if($validator === null){
                throw new FormValidationException("Invalid element " . $i);
            }
            if(!$validator($v)){
                throw new FormValidationException("Invalid type given for element " . $this->labelMap[$i]);
            }
            $new[$this->labelMap[$i]] = $v;
        }
        return $new;
    }

    public static function getType() : string{
        return "custom_form";
    }

    public static function create(string $title) : self{
        return new self($title);
    }
}
