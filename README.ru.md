# rasuvaeff/understudy-phpstan

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/understudy-phpstan/v)](https://packagist.org/packages/rasuvaeff/understudy-phpstan)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/understudy-phpstan/downloads)](https://packagist.org/packages/rasuvaeff/understudy-phpstan)
[![Build](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/understudy-phpstan/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/understudy-phpstan/php)](https://packagist.org/packages/rasuvaeff/understudy-phpstan)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[English version](README.md)

PHPStan-расширение для [understudy](https://github.com/rasuvaeff/understudy).

> Работаете с AI-ассистентом? Дайте ему [llms.txt](llms.txt).

## Требования

- PHP 8.3 - 8.5
- `phpstan/phpstan` ^2.2.2
- `rasuvaeff/understudy` ^0.1

## Установка

```bash
composer require --dev rasuvaeff/understudy-phpstan
```

С [phpstan/extension-installer](https://github.com/phpstan/extension-installer)
этого достаточно. Без него подключите расширение сами:

```neon
includes:
    - vendor/rasuvaeff/understudy-phpstan/extension.neon
```

## Что оно делает

understudy описывает вызов, делая этот вызов внутри замыкания:

```php
when(fn () => $repository->find(Arg::int(min: 1)))->returns($book);
```

Четыре вещи в этой строке PHPStan сам не видит — расширение и есть эти
четыре вещи.

### 1. Матчер подходит под то, что объявлено в контракте

`Arg::int()` объявлен как `mixed`, потому что матчер обязан проходить туда,
где контракт объявил что угодно. На level 9 и выше PHPStan сообщает
`Parameter #1 $id … expects int, mixed given` — верно про тип и неверно про
код.

Расширение типизирует любой матчер как `never`, нижний тип, который принимает
любой параметр. **Ничего не подавляется.** Неверный аргумент рядом с
матчером, отсутствующий у дубля метод, выражения вокруг замыкания — всё
сохраняет свои сообщения:

```php
when(fn () => $repository->rename(Arg::any(), 'not an int'));
//                                            ^^^^^^^^^^^^ по-прежнему ошибка
when(fn () => $repository->missing(Arg::any()));
//                        ^^^^^^^ по-прежнему ошибка
```

Ниже level 9 чинить тут нечего — PHPStan не проверяет `mixed` против
объявленного параметра, — а остальная часть расширения работает на любом
уровне.

Две идиомы understudy 0.4 покрыты так же:

- **`Arg::rest()`** легитимно передаёт меньше аргументов, чем объявляет
  контракт, — `when(fn () => $storage->recordOutcome('svc', Arg::rest()))` —
  поэтому репорт `arguments.count` игнорируется там, где последний написанный
  аргумент вызова — `Arg::rest()`. В настоящем вызове это по-прежнему ошибка,
  и сообщается она как та ошибка, которой является:
  `understudy.matcherLeak` называет утёкший матчер (сам вызов движок отвечает
  `ArgumentCountError`).
- **`$captor->capture()`** из `Arg::captor()` — матчер в одежде вызова
  метода: типизируется `never`, как `Arg::`-фабрики (решает тип получателя —
  чужой `capture()` не трогается), не считается против «ровно один вызов на
  замыкание» и сообщается `understudy.matcherLeak`, когда доезжает до
  настоящего вызова. Сам `Arg::captor()` — фабрика, а не матчер, и легитимно
  живёт вне замыкания.

### 2. `returns()` проверяется против описываемого метода

Ядро объявляет `when(): WhenBuilder<mixed>`, и иначе не может: какой метод
описывается, известно только из замыкания. Расширение подставляет параметр
шаблона, дальше PHPStan справляется сам:

```php
when(fn () => $gate->open(1))->returns('yes');
// Parameter #1 ...$values of method WhenBuilder<bool>::returns() expects bool, string given.
```

### 3. `wire()` имеет форму того класса, который связали

```php
$wired = Understudy::wire(Checkout::class);

$wired['doubles']['repository'];  // Offset 'repository' does not exist on
                                  // array{books: BookRepository, clock: Clock}.
$wired['doubles']['clock']->tick();  // Call to an undefined method Clock::tick().
```

### 4. Спецификации, которые не могут работать, сообщаются

У каждой есть рантайм-двойник — движок бросает исключение либо ожидание
никогда не совпадает. Статическая проверка даёт то, чего рантайм дать не
может: спецификацию, которая не может совпасть никогда, зелёный сьют как раз
и прячет.

| Идентификатор | Когда сообщается |
|---|---|
| `understudy.closure` | Замыкание ничего не описывает, делает больше одного вызова или зовёт статический метод, который дубль не перехватит |
| `understudy.cardinality` | `times(5, 2)`, отрицательная граница, `verify(…, never: true, times: 3)`, `times` рядом с `minimum` |
| `understudy.matcher` | Матчер, чей вид параметр не примет никогда: `Arg::int()` там, где объявлен `string` |
| `understudy.returns` | `returns()` у метода, объявленного `void`, где значение никто не наблюдает |
| `understudy.matcherLeak` | Матчер вне спецификации — там он доходит до кода как значение |

Правила молчат везде, где не уверены. Уточнённый тип параметра —
`non-empty-string`, диапазон int — отвечает «может быть» на свой базовый вид,
и матчер способен выдать подходящее значение, поэтому сообщения не будет.
Ложное обвинение здесь дороже пропущенного: то, что расширение пропустит,
движок всё равно поймает в рантайме.

Заглушить любое — по его идентификатору:

```neon
parameters:
    ignoreErrors:
        - identifier: understudy.matcherLeak
```

## Зачем нужен `understudy.matcherLeak`

Типизация матчера как `never` — это и есть то, что позволяет ему стоять на
месте типизированного параметра, и работает она везде, включая настоящий
вызов:

```php
$repository->find(Arg::int());  // не спецификация: матчер и есть аргумент
```

Без правила для этого случая расширение оказалось бы *хуже*, чем его
отсутствие: PHPStan сам сообщил бы про такой аргумент. Сказать прямо к тому
же лучше, чем ошибка типа, которую это заменяет: в рантайме матчер доходит до
кода объектом-сентинелом, и падение, которое из этого следует, не называет ни
матчера, ни строки.

## Безопасность

Расширение работает внутри PHPStan, читает исходники и рефлексию и сообщает
найденное. Оно не исполняет код анализируемого проекта и ничего не пишет.

## Примеры

См. [examples/README.md](examples/README.md). Исполняемая демонстрация — набор
фикстурных проектов в `tests/Integration/Fixtures`, каждый из которых
анализируется настоящим процессом PHPStan в составе `composer build`, включая
контрольный прогон с выключенным расширением: именно он отличает работающее
расширение от того, которое загрузилось и ничего не делает.

## Семейство understudy

| Пакет | Что это |
|---|---|
| [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) | Движок: дубли, матчеры, ожидания, верификация. |
| [rasuvaeff/understudy-testo](https://github.com/rasuvaeff/understudy-testo) | Testo-адаптер — верификация и сброс вокруг каждого теста. |
| [rasuvaeff/understudy-phpunit](https://github.com/rasuvaeff/understudy-phpunit) | Адаптер для PHPUnit и Pest — то же самое, через трейт. |
| [rasuvaeff/understudy-psalm](https://github.com/rasuvaeff/understudy-psalm) | Psalm-плагин — спецификации с матчерами и диагностики ошибок. |
| **rasuvaeff/understudy-phpstan** *(этот пакет)* | PHPStan-расширение — то же самое для PHPStan, плюс свои правила. |

## Разработка

```bash
make build          # validate + normalize + require-checker + cs + psalm + test + integration
make test           # unit-сьют
make test-coverage  # unit-сьют с покрытием
make mutation       # мутационное тестирование
make release-check  # build + rector + bc-check + mutation
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
