# yii2-dd

Laravel's `dd()` and `dump()` for the current Yii 2, with the same calls and the same stop-and-print behavior.

```php
dd($model);
dd($model, $request->post());

dump($model);
$order = dump($order);
[$left, $right] = dump($left, $right);
```

`dd()` prints every value, sends **HTTP 500** on a web request when the headers are still open, then ends the process with exit code **1**. `dump()` prints the same way and returns the value, so the script keeps running. One argument is returned as itself; several arguments are returned as a list. An empty `dd()` or `dump()` prints a marker, and `dump()` returns `null`.

Output is the Symfony VarDumper UI that Laravel uses: an expandable HTML dump in the browser, a colored dump in the console. The file and line of the call are printed under it and linked to the editor. `VAR_DUMPER_FORMAT` (`html`, `cli`, `server`, or a `tcp://` address) is left to Symfony, as in Laravel.

Yii models are shown through their attributes, validation errors, dirty values and loaded relations. Nested application, database connection, DI container and logger objects are collapsed, the same way Laravel collapses its container and database connection.

## Requirements

- PHP 8.1 or newer
- Yii 2.0.45 or newer

## Install

```bash
composer require bladeknigt89/yii2-dd
```

The package is a Yii extension. Yii boots it from `extra.bootstrap`, so `dd()` is ready without an application config entry.

Until the package is listed on Packagist, point Composer at the repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/bladeknigt89/yii2-dd"
        }
    ],
    "require": {
        "bladeknigt89/yii2-dd": "^1.0"
    }
}
```

## Component

Configure the component only when a default should change:

```php
'components' => [
    'dd' => [
        'class' => \bladeknigt89\dd\Dd::class,
        'editor' => 'vscode',
        'maxDepth' => 3,
        'prettyModels' => true,
        'showSource' => true,
    ],
],
```

`editor` accepts `phpstorm` (the default), `vscode`, `vscode-insiders`, `sublime`, `atom`, or a pattern with `{file}` and `{line}`.

The same methods exist on the component:

```php
Yii::$app->dd->dd($model);
Yii::$app->dd->dump($model);
```

## License

BSD-3-Clause. See [LICENSE](LICENSE).
