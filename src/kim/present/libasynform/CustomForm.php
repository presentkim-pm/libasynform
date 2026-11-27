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

use function array_key_exists;
use function count;
use function array_keys;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

class CustomForm extends BaseForm{

    /**
     * One entry per element of the form, in display order.
     * Value elements carry their response key and validator, read-only elements (label, header, divider) carry null.
     *
     * @var list<array{string|int, \Closure}|null>
     */
    private array $entries = [];
    /** @var array<string|int, true> */
    private array $usedLabels = [];
    private int $valueCount = 0;

    public function __construct(string $title = ""){
        parent::__construct($title);
        $this->data["content"] = [];
    }

    public function setIcon(string $type, string $data) : self{
        $this->data["icon"] = ["type" => $type, "data" => $data];
        return $this;
    }

    public function addLabel(string $text) : self{
        $this->addReadonlyContent(["type" => "label", "text" => $text]);
        return $this;
    }

    public function addToggle(string $text, bool $default = false, ?string $label = null) : self{
        $this->addContent(
            ["type" => "toggle", "text" => $text, "default" => $default],
            static fn($v) => is_bool($v),
            $label
        );
        return $this;
    }

    /**
     * @param int|null $step    Step size, null (default) lets the client decide
     * @param int|null $default Default value, null (default) lets the client decide
     */
    public function addSlider(
        string $text,
        int $min,
        int $max,
        ?int $step = null,
        ?int $default = null,
        ?string $label = null
    ) : self{
        $content = ["type" => "slider", "text" => $text, "min" => $min, "max" => $max];
        if($step !== null){
            $content["step"] = $step;
        }
        if($default !== null){
            $content["default"] = $default;
        }
        $this->addContent(
            $content,
            static fn($v) => (is_float($v) || is_int($v)) && $v >= $min && $v <= $max,
            $label
        );
        return $this;
    }

    /** @param list<string> $steps */
    public function addStepSlider(string $text, array $steps, int $defaultIndex = 0, ?string $label = null) : self{
        $this->addContent(
            ["type" => "step_slider", "text" => $text, "steps" => $steps, "default" => $defaultIndex],
            static fn($v) => is_int($v) && isset($steps[$v]),
            $label
        );
        return $this;
    }

    /**
     * @param list<string> $options
     * @param int|null     $default Index of the preselected option, null (default) lets the client decide
     */
    public function addDropdown(string $text, array $options, ?int $default = null, ?string $label = null) : self{
        $content = ["type" => "dropdown", "text" => $text, "options" => $options];
        if($default !== null){
            $content["default"] = $default;
        }
        $this->addContent(
            $content,
            static fn($v) => is_int($v) && isset($options[$v]),
            $label
        );
        return $this;
    }

    public function addInput(string $text, string $placeholder = "", ?string $default = null, ?string $label = null
    ) : self{
        $content = ["type" => "input", "text" => $text, "placeholder" => $placeholder];
        if($default !== null){
            $content["default"] = $default;
        }
        $this->addContent(
            $content,
            static fn($v) => is_string($v),
            $label
        );
        return $this;
    }

    public function addDivider() : self{
        $this->addReadonlyContent(["type" => "divider", "text" => ""]);
        return $this;
    }

    public function addHeader(string $text) : self{
        $this->addReadonlyContent(["type" => "header", "text" => $text]);
        return $this;
    }

    /**
     * Adds a read-only element (label, header, divider ...).
     * The client still reports a value for it, which is ignored and left out of the processed response.
     *
     * @param array<string, mixed> $content
     */
    public function addReadonlyContent(array $content) : void{
        $this->data["content"][] = $content;
        $this->entries[] = null;
    }

    /**
     * Adds a content element that produces a value in the processed response.
     *
     * The response array returned by processData() will use $label as the key
     * when it is not null. When $label is null, a 0-based integer index (counting only value elements,
     * not labels, headers and dividers) is used.
     *
     * @param array<string, mixed> $content   Raw form element definition
     * @param \Closure            $validator Validator receiving the raw value
     * @param string|null         $label     Key name for the processed response array
     *
     * @throws \InvalidArgumentException if the label is already used by another element
     */
    public function addContent(array $content, \Closure $validator, ?string $label = null) : void{
        $key = $label !== null && $label !== '' ? $label : $this->valueCount;
        //Numeric string labels become integer array keys, so normalise before comparing
        $normalized = is_string($key) ? ($this->normalizeKey($key)) : $key;
        if(array_key_exists($normalized, $this->usedLabels)){
            throw new \InvalidArgumentException("Label '$key' is already used by another element");
        }
        $this->usedLabels[$normalized] = true;
        $this->valueCount++;

        $this->data["content"][] = $content;
        $this->entries[] = [$normalized, $validator];
    }

    private function normalizeKey(string $key) : string|int{
        return array_keys([$key => 0])[0];
    }

    /**
     * Processes raw form data into a validated associative array.
     *
     * The returned array is keyed by the labels passed to addContent(), or by
     * 0-based integer indexes when labels are omitted.
     *
     * @param mixed $data Raw data from the client
     *
     * @return array<string|int, mixed>
     *
     * @throws FormValidationException
     */
    public function processData(mixed $data) : array{
        if(!is_array($data)){
            throw new FormValidationException("Expected an array response, got " . gettype($data));
        }

        //The client reports every element, read-only ones included
        if(count($data) !== count($this->entries)){
            throw new FormValidationException(
                "Expected an array response with the size " . count($this->entries) . ", got " . count($data)
            );
        }
        $new = [];
        foreach($data as $i => $v){
            if(!is_int($i) || !array_key_exists($i, $this->entries)){
                throw new FormValidationException("Invalid element " . $i);
            }
            $entry = $this->entries[$i];
            if($entry === null){
                continue;
            }
            [$key, $validator] = $entry;
            if(!$validator($v)){
                throw new FormValidationException("Invalid type given for element " . $key);
            }
            $new[$key] = $v;
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
