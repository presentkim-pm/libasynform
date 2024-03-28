<!-- PROJECT BADGES -->
<div align="center">

[![Poggit CI][poggit-ci-badge]][poggit-ci-url]
[![Stars][stars-badge]][stars-url]
[![License][license-badge]][license-url]

</div>


<!-- PROJECT LOGO -->
<br />
<div align="center">
  <img src="https://raw.githubusercontent.com/presentkim-pm/libasynform/main/assets/icon.png" alt="Logo" width="80" height="80"/>
  <h3>libasynform</h3>
  <p align="center">
    Provides form classes for await-generator!

[Contact to me][author-discord] · [Report a bug][issues-url] · [Request a feature][issues-url]

  </p>
</div>


<!-- ABOUT THE PROJECT -->

## About The Project

:heavy_check_mark: Provides form classes for [await-generator](https://github.com/SOF3/await-generator)

- `kim\present\libasynform\CustomForm`
- `kim\present\libasynform\ModalForm`
- `kim\present\libasynform\SimpleForm`

##

-----

## Usage

1. Use static method `::create()` to create form object
2. Fill in the contents using the methods of each form class
3. Use member method `->send(Player)` to send the form to the player
   > Don't use `Player::sendForm()` method yourself

##

example)

````php
use kim\present\libasynform\CustomForm;
use SOFe\AwaitGenerator\Await;

Await::f2c(function() use($player) : \Generator{
    $recieve = yield from CustomForm::create("OpenMenu")
        ->addToggle("I want open menu", true, "open")
        ->addToggle("I want close menu", false, "close")
        ->addDropdown("I want ", ["close menu", "open menu"], 1, "select")
        ->addInput("I want ", "what do you want", "open menu", "want")
        ->send($player);

    // if player close form, $recieve is null
    // else, below
    var_dump($recieve);
    /*
        array(4) {
            ["open"]=>
            bool(true)
            ["close"]=>
            bool(false)
            ["select"]=>
            int(1)
            ["want"]=>
            string(9) "open menu"
        }
    */
});
````

##

-----

## Installation

See [Official Poggit Virion Documentation](https://github.com/poggit/support/blob/master/virion.md)

##

-----

## How to use?

See [Main Document](https://github.com/presentkim-pm/libasynform/blob/main/docs/README.md)

##

-----

## License

Distributed under the **MIT**. See [LICENSE][license-url] for more information

##

-----

[author-discord]: https://discordapp.com/users/345772340279508993

[poggit-ci-badge]: https://poggit.pmmp.io/ci.shield/presentkim-pm/libasynform/libasynform?style=for-the-badge

[poggit-version-badge]: https://poggit.pmmp.io/shield.api/libasynform?style=for-the-badge

[poggit-downloads-badge]: https://poggit.pmmp.io/shield.dl.total/libasynform?style=for-the-badge

[version-badge]: https://img.shields.io/github/v/release/presentkim-pm/libasynform?display_name=tag&style=for-the-badge&label=VERSION

[release-badge]: https://img.shields.io/github/downloads/presentkim-pm/libasynform/total?style=for-the-badge&label=GITHUB%20

[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/libasynform.svg?style=for-the-badge

[license-badge]: https://img.shields.io/github/license/presentkim-pm/libasynform.svg?style=for-the-badge

[poggit-ci-url]: https://poggit.pmmp.io/ci/presentkim-pm/libasynform/libasynform

[poggit-release-url]: https://poggit.pmmp.io/p/libasynform

[stars-url]: https://github.com/presentkim-pm/libasynform/stargazers

[releases-url]: https://github.com/presentkim-pm/libasynform/releases

[issues-url]: https://github.com/presentkim-pm/libasynform/issues

[license-url]: https://github.com/presentkim-pm/libasynform/blob/main/LICENSE

[project-icon]: https://raw.githubusercontent.com/presentkim-pm/libasynform/main/assets/icon.png

[project-preview]: https://raw.githubusercontent.com/presentkim-pm/libasynform/main/assets/preview.gif
