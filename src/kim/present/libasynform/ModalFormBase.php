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
use function is_bool;

class ModalFormBase extends BaseStringContentForm{

	public function __construct(
		string $title = "",
		string $content = "",
		string $button1 = "",
		string $button2 = ""
	){
		parent::__construct($title, $content);
		$this->data["button1"] = $button1;
		$this->data["button2"] = $button2;
	}

	public function getButton1() : string{
		return $this->data["button1"];
	}

	public function setButton1(string $text) : self{
		$this->data["button1"] = $text;
		return $this;
	}

	public function getButton2() : string{
		return $this->data["button2"];
	}

	public function setButton2(string $text) : self{
		$this->data["button2"] = $text;
		return $this;
	}

	protected function processData($data) : bool{
		if(!is_bool($data)){
			throw new FormValidationException("Expected a boolean response, got " . gettype($data));
		}

		return $data;
	}

	public static function getType() : string{
		return "modal";
	}

	public static function create(
		string $title = "",
		string $content = "",
		string $button1 = "",
		string $button2 = ""
	) : self{
		return new self($title, $content, $button1, $button2);
	}
}
