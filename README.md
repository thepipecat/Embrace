# Embrace

## About

`new Embrace()` is a personal study case for template engineering that could be
simple and fast as resourceful enough to support a 

## License

**Embrace** is under the open source MIT License (MIT). See *COPY* for more
information.

## Requires

* PHP 5.3+

## Usage

In your logical file, e.g. *index.php*:
`require_once 'Embrace.class.php';

$embraced = new Embrace('template.php');

$embraced->foo = 'Hello world!';

$embrace(); // short hand for Embrace->render()`

In your template file, e.g. *template.php*:
`*<quote>*
I have some to say to you:*<br />*
<strong>**[[foo]]**</strong>
*</quote>*`

Will output:
> I have some to say to you:
> **Hello world!**

## Features

1. General logical analysis (===, !==, <, >, <=, >=);
2. Template hierarchy with parentage resolution `[[include:template.php]]`;
3. Literal `[[literal]] [[render as is]] [[/literal]]`;
4. PHP code interpretation `[[php]] print 'Embraced!'; [[/php]]`;
5. Cache per file with hierarchy influence, e.g. *child with cache disabled
turns off parents cache*;
6. Some others... *(laziness)*

## Demo

See *Embrace*/*demo* for some usage cases. **(soon)**

## Benchmark

Not yet. Will be welcome any related experience.

## Issues

See [Issues](https://bitbucket.org/thepipecat/embrace/issues) list.
If you find something be welcome to contribute with a ticket.

## Roadmap

Nothing defined yet. But evolves ever! ;)