<?php

declare(strict_types=1);

namespace kim\present\libasynform\tests;

use pocketmine\player\Player;

/**
 * A {@see Player} that is created without its constructor, so Player's destructor (which expects a fully
 * initialised entity) must not run.
 */
final class TestPlayer extends Player{

    public function __destruct(){
    }
}
