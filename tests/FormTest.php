<?php

declare(strict_types=1);

namespace kim\present\libasynform\tests;

use kim\present\libasynform\CustomForm;
use kim\present\libasynform\ModalForm;
use kim\present\libasynform\SimpleForm;
use pocketmine\form\FormValidationException;
use pocketmine\network\mcpe\protocol\ServerSettingsResponsePacket;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

final class FormTest extends FormTestCase{

    /**
     * Runs the generator and returns a holder updated when it finishes.
     *
     * @return object{done: bool, value: mixed, error: ?\Throwable}
     */
    private function drive(\Closure $generatorFunction) : object{
        $state = new class{
            public bool $done = false;
            public mixed $value = null;
            public ?\Throwable $error = null;
        };
        Await::f2c(function() use ($generatorFunction, $state) : \Generator{
            try{
                $state->value = yield from $generatorFunction();
            }catch(\Throwable $e){
                $state->error = $e;
            }
            $state->done = true;
        });
        return $state;
    }

    public function testModalFormResolvesWithBool() : void{
        $player = $this->createPlayer();
        $form = ModalForm::create("Title", "Content", "Yes", "No");
        $state = $this->drive(fn() => $form->send($player));

        self::assertFalse($state->done, "must wait for the answer");
        self::assertSame(
            ["type" => "modal", "title" => "Title", "content" => "Content", "button1" => "Yes", "button2" => "No"],
            $this->lastFormJson()
        );

        self::assertTrue($player->onFormSubmit($this->lastFormId(), false));
        self::assertTrue($state->done);
        self::assertFalse($state->value);
        self::assertNull($state->error);
    }

    public function testClosingTheFormResolvesNull() : void{
        $player = $this->createPlayer();
        $form = ModalForm::create("T", "C", "A", "B");
        $state = $this->drive(fn() => $form->send($player));

        $player->onFormSubmit($this->lastFormId(), null);

        self::assertTrue($state->done);
        self::assertNull($state->value);
        self::assertNull($state->error);
    }

    public function testInvalidResponseRejectsAndIsAllowedToResend() : void{
        $player = $this->createPlayer();
        $form = ModalForm::create("T", "C", "A", "B");
        $state = $this->drive(fn() => $form->send($player));

        //Player::onFormSubmit() would log the exception, so call the form directly like the server does
        $form->handleResponse($player, "not a bool");

        self::assertTrue($state->done);
        self::assertInstanceOf(FormValidationException::class, $state->error);

        //The player can be shown the same form again afterwards
        $again = $this->drive(fn() => $form->send($player));
        self::assertFalse($again->done);
        $form->handleResponse($player, true);
        self::assertTrue($again->value);
    }

    public function testDoubleSendToTheSamePlayerIsRejected() : void{
        $player = $this->createPlayer();
        $form = ModalForm::create("T", "C", "A", "B");
        $first = $this->drive(fn() => $form->send($player));
        $second = $this->drive(fn() => $form->send($player));

        self::assertTrue($second->done);
        self::assertInstanceOf(FormValidationException::class, $second->error);
        self::assertCount(1, $this->sentPackets, "the second send must not reach the client");
        self::assertFalse($first->done, "the first send keeps waiting");

        $form->handleResponse($player, true);
        self::assertTrue($first->value);
    }

    public function testSecondResponseIsRejected() : void{
        $player = $this->createPlayer();
        $form = ModalForm::create("T", "C", "A", "B");
        $state = $this->drive(fn() => $form->send($player));
        $form->handleResponse($player, true);
        self::assertTrue($state->done);

        $this->expectException(FormValidationException::class);
        $form->handleResponse($player, false);
    }

    public function testResponseFromAnotherPlayerIsRejected() : void{
        $player = $this->createPlayer();
        $other = $this->createPlayer();
        $form = ModalForm::create("T", "C", "A", "B");
        $state = $this->drive(fn() => $form->send($player));

        try{
            $form->handleResponse($other, true);
            self::fail("expected FormValidationException");
        }catch(FormValidationException){
        }
        self::assertFalse($state->done);
    }

    public function testOneFormInstanceCanBeShownToTwoPlayers() : void{
        $a = $this->createPlayer();
        $b = $this->createPlayer();
        $form = ModalForm::create("T", "C", "A", "B");
        $stateA = $this->drive(fn() => $form->send($a));
        $stateB = $this->drive(fn() => $form->send($b));

        $form->handleResponse($b, false);
        self::assertFalse($stateA->done);
        self::assertFalse($stateB->value);
        $form->handleResponse($a, true);
        self::assertTrue($stateA->value);
    }

    public function testSendToDisconnectedPlayerRejectsInsteadOfHanging() : void{
        $player = $this->createPlayer(false);
        $form = ModalForm::create("T", "C", "A", "B");
        $state = $this->drive(fn() => $form->send($player));

        self::assertTrue($state->done);
        self::assertInstanceOf(FormValidationException::class, $state->error);

        //and the failed attempt must not block a later one
        $state = $this->drive(fn() => $form->send($player));
        self::assertInstanceOf(FormValidationException::class, $state->error);
        self::assertStringNotContainsString("already", $state->error->getMessage());
    }

    public function testSendAsServerSettingUsesServerSettingsPacket() : void{
        $player = $this->createPlayer();
        $form = CustomForm::create("Settings")->addToggle("Sound", true, "sound");
        $state = $this->drive(fn() => $form->send($player, true));

        self::assertInstanceOf(ServerSettingsResponsePacket::class, $this->sentPackets[0]);
        self::assertSame("Settings", $this->lastFormJson()["title"]);
        self::assertTrue($player->hasPendingForm($this->lastFormId()));

        $player->onFormSubmit($this->lastFormId(), [false]);
        self::assertSame(["sound" => false], $state->value);
        self::assertFalse($player->hasPendingForm(0));
    }

    public function testServerSettingToDisconnectedPlayerRejects() : void{
        $player = $this->createPlayer(false);
        $form = CustomForm::create("Settings");
        $state = $this->drive(fn() => $form->send($player, true));

        self::assertInstanceOf(FormValidationException::class, $state->error);
    }

    public function testFormIdsAreUnique() : void{
        $player = $this->createPlayer();
        $form = ModalForm::create("T", "C", "A", "B");
        $this->drive(fn() => $form->send($player));
        $first = $this->lastFormId();
        $form->handleResponse($player, true);
        $this->drive(fn() => $form->send($player));
        self::assertNotSame($first, $this->lastFormId());
    }

    public function testSimpleFormButtonsAndResponse() : void{
        $player = $this->createPlayer();
        $form = SimpleForm::create("Menu", "Pick one")
            ->addButton("Plain")
            ->addButton("Texture", SimpleForm::IMAGE_TYPE_PATH, "textures/items/apple")
            ->addButton("Remote", SimpleForm::IMAGE_TYPE_URL, "https://example.com/a.png");
        $state = $this->drive(fn() => $form->send($player));

        self::assertSame([
            "type" => "form",
            "title" => "Menu",
            "content" => "Pick one",
            "buttons" => [
                ["text" => "Plain"],
                ["text" => "Texture", "image" => ["type" => "path", "data" => "textures/items/apple"]],
                ["text" => "Remote", "image" => ["type" => "url", "data" => "https://example.com/a.png"]],
            ]
        ], $this->lastFormJson());

        $player->onFormSubmit($this->lastFormId(), 2);
        self::assertSame(2, $state->value);
    }

    public function testSimpleFormWithoutButtonsStillSendsEmptyList() : void{
        self::assertSame([], SimpleForm::create("T")->jsonSerialize()["buttons"]);
    }

    public function testSimpleFormRejectsUnknownButton() : void{
        $player = $this->createPlayer();
        $form = SimpleForm::create("Menu")->addButton("Only");
        $state = $this->drive(fn() => $form->send($player));

        $form->handleResponse($player, 1);
        self::assertInstanceOf(FormValidationException::class, $state->error);
    }

    public function testSimpleFormRejectsNonInteger() : void{
        $form = SimpleForm::create("Menu")->addButton("Only");
        $player = $this->createPlayer();
        $state = $this->drive(fn() => $form->send($player));
        $form->handleResponse($player, "0");
        self::assertInstanceOf(FormValidationException::class, $state->error);
    }

    public function testSimpleFormRejectsUnknownImageType() : void{
        $this->expectException(\InvalidArgumentException::class);
        SimpleForm::create("Menu")->addButton("Bad", 5, "x");
    }

    public function testSetters() : void{
        $form = ModalForm::create()->setTitle("T")->setContent("C")->setButton1("1")->setButton2("2");
        self::assertSame("T", $form->getTitle());
        self::assertSame("C", $form->getContent());
        self::assertSame("1", $form->getButton1());
        self::assertSame("2", $form->getButton2());
    }

    public function testCustomFormResponseKeyedByLabel() : void{
        $player = $this->createPlayer();
        $form = CustomForm::create("Menu")
            ->addToggle("Open", true, "open")
            ->addSlider("Amount", 0, 10, 1, 5, "amount")
            ->addStepSlider("Step", ["a", "b", "c"], 0, "step")
            ->addDropdown("Drop", ["x", "y"], 1, "drop")
            ->addInput("Input", "hint", "def", "text");
        $state = $this->drive(fn() => $form->send($player));

        $player->onFormSubmit($this->lastFormId(), [true, 7, 2, 0, "hello"]);

        self::assertSame(["open" => true, "amount" => 7, "step" => 2, "drop" => 0, "text" => "hello"], $state->value);
    }

    public function testCustomFormUnlabelledValuesUseIndexOfValueElements() : void{
        $form = CustomForm::create("Menu")->addHeader("H")->addToggle("A")->addLabel("L")->addInput("B");

        self::assertSame([0 => true, 1 => "x"], $form->processData([null, true, null, "x"]));
    }

    public function testCustomFormReadonlyElementsAreAcceptedAndOmitted() : void{
        $player = $this->createPlayer();
        $form = CustomForm::create("Menu")
            ->addLabel("Intro")
            ->addDivider()
            ->addHeader("Header")
            ->addToggle("Open", false, "open");
        $state = $this->drive(fn() => $form->send($player));

        //The client reports a value for every element, null for the read-only ones
        $player->onFormSubmit($this->lastFormId(), [null, null, null, true]);

        self::assertNull($state->error);
        self::assertSame(["open" => true], $state->value);
    }

    public function testCustomFormJsonOmitsUnsetOptionalFields() : void{
        $json = CustomForm::create("Menu")
            ->addSlider("S", -5, 5)
            ->addDropdown("D", ["a"])
            ->addInput("I")
            ->jsonSerialize()["content"];

        self::assertSame(["type" => "slider", "text" => "S", "min" => -5, "max" => 5], $json[0]);
        self::assertSame(["type" => "dropdown", "text" => "D", "options" => ["a"]], $json[1]);
        self::assertSame(["type" => "input", "text" => "I", "placeholder" => ""], $json[2]);
    }

    public function testCustomFormSliderAllowsNegativeDefaultAndStep() : void{
        $json = CustomForm::create("Menu")->addSlider("S", -5, 5, 1, -1)->jsonSerialize()["content"][0];
        self::assertSame(-1, $json["default"]);
        self::assertSame(1, $json["step"]);
    }

    public function testCustomFormIcon() : void{
        $json = CustomForm::create("Menu")->setIcon("path", "textures/x")->jsonSerialize();
        self::assertSame(["type" => "path", "data" => "textures/x"], $json["icon"]);
        self::assertSame("custom_form", $json["type"]);
    }

    public function testCustomFormDuplicateLabelThrows() : void{
        $form = CustomForm::create("Menu")->addToggle("A", false, "same");
        $this->expectException(\InvalidArgumentException::class);
        $form->addToggle("B", false, "same");
    }

    public function testCustomFormLabelCollidingWithAutoIndexThrows() : void{
        $form = CustomForm::create("Menu")->addToggle("A");
        $this->expectException(\InvalidArgumentException::class);
        $form->addToggle("B", false, "0");
    }

    /** @return iterable<string, array{\Closure, mixed}> */
    public static function invalidResponses() : iterable{
        $form = static fn() => CustomForm::create("M")
            ->addToggle("t", false, "t")
            ->addSlider("s", 0, 10, null, null, "s")
            ->addStepSlider("ss", ["a", "b"], 0, "ss")
            ->addDropdown("d", ["a", "b"], null, "d")
            ->addInput("i", "", null, "i");
        yield "not an array" => [$form, "x"];
        yield "too short" => [$form, [true, 1, 0, 0]];
        yield "too long" => [$form, [true, 1, 0, 0, "a", "b"]];
        yield "toggle not bool" => [$form, [1, 1, 0, 0, "a"]];
        yield "slider above max" => [$form, [true, 11, 0, 0, "a"]];
        yield "slider below min" => [$form, [true, -1, 0, 0, "a"]];
        yield "slider string" => [$form, [true, "5", 0, 0, "a"]];
        yield "step slider out of range" => [$form, [true, 1, 2, 0, "a"]];
        yield "dropdown out of range" => [$form, [true, 1, 0, 2, "a"]];
        yield "dropdown negative" => [$form, [true, 1, 0, -1, "a"]];
        yield "input not string" => [$form, [true, 1, 0, 0, 5]];
        yield "string keys" => [$form, ["a" => true, "b" => 1, "c" => 0, "d" => 0, "e" => "a"]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidResponses')]
    public function testCustomFormInvalidResponses(\Closure $factory, mixed $response) : void{
        $this->expectException(FormValidationException::class);
        $factory()->processData($response);
    }

    public function testCustomFormAcceptsFloatSlider() : void{
        $form = CustomForm::create("M")->addSlider("s", 0, 10, null, null, "s");
        self::assertSame(["s" => 2.5], $form->processData([2.5]));
    }

    public function testInvalidCustomResponseRejectsGenerator() : void{
        $player = $this->createPlayer();
        $form = CustomForm::create("M")->addToggle("t");
        $state = $this->drive(fn() => $form->send($player));
        $form->handleResponse($player, ["nope"]);
        self::assertInstanceOf(FormValidationException::class, $state->error);
    }
}
