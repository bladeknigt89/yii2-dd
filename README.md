# yii2-dd

Laravel-kompatibilis `dd()` és `dump()` a Yii 2-höz. A hívás és a leállás ugyanaz, mint Laravelben: a Symfony VarDumper felülete, weben HTTP 500, utána kilépés 1-es kóddal.

Csomag: [`bladeknigt89/yii2-dd`](https://packagist.org/packages/bladeknigt89/yii2-dd)  
Stabil kiadás: `v1.0.0`  
Forrás: https://github.com/bladeknigt89/yii2-dd  
Licenc: BSD-3-Clause

## Követelmények

- PHP 8.1 vagy újabb
- Yii 2.0.45 vagy újabb (`yiisoft/yii2: ~2.0.45`)
- `symfony/var-dumper` 6.4, 7 vagy 8 (a csomag behúzza)

## Telepítés

A csomag a Packagisten van, a stabil vonal az `1.0`:

```bash
composer require bladeknigt89/yii2-dd
```

Ez a `^1.0` megszorítást írja a projekt `composer.json` fájljába, és a `v1.0.0` kiadást telepíti.

Yii2 alkalmazásban a `yiisoft/yii2-composer` plugin engedélyezve van. A kiterjesztés a `extra.bootstrap` bejegyzésből (`bladeknigt89\dd\Bootstrap`) magától elindul, a `config` fájlhoz nem kell hozzányúlni. A `dd()` és a `dump()` a Composer autoload után azonnal hívható.

Ha a plugin nem fut, a bootstrap osztály kézzel is beírható:

```php
'bootstrap' => [
    'bladeknigt89\dd\Bootstrap',
],
```

## Használat

Ugyanaz a minta, mint Laravelben. Kontrollert, konzol parancsot, modellt és sima változót is így lehet kiíratni.

Egy érték, aztán a script megáll:

```php
public function actionView($id)
{
    $model = Post::findOne($id);

    dd($model);
}
```

Több érték egymás után. A kiírásban `1`, `2`, … sorszám különbözteti meg őket:

```php
public function actionCreate()
{
    dd(Yii::$app->request->post(), Yii::$app->user->identity);
}
```

A `dump()` kiír, visszaadja az értéket, és a script megy tovább. Egy argumentumnál magát az értéket adja vissza, ezért a hívás láncolható:

```php
public function actionUpdate($id)
{
    $model = Post::findOne($id);

    dump($model)->save();
}
```

Több argumentumnál a visszaadott érték a paraméterek listája:

```php
[$post, $user] = dump(Yii::$app->request->post(), Yii::$app->user->identity);
```

Konzol parancsban a kimenet színes szöveg a terminálon:

```php
public function actionIndex()
{
    dd(Customer::find()->limit(5)->all());
}
```

Üres hívás egy jelölőt ír ki. A `dump()` ilyenkor `null`-t ad vissza, a `dd()` ezután is leáll:

```php
dd();
dump();
```

A komponens metódusai ugyanezt csinálják:

```php
Yii::$app->dd->dd($model);
Yii::$app->dd->dump($model);
```

## Mit csinál a dd()

1. Webes kérésnél, ha a fejlécek még nincsenek elküldve, `HTTP 500`-at küld. CLI-n, `phpdbg` alatt és `embed` SAPI-n nincs státuszkód.
2. Minden argumentumot kiír a Symfony VarDumperrel: böngészőben lenyitható HTML, konzolon színes szöveg.
3. A hívás fájlja és sora a dump alatt jelenik meg, szerkesztő linkkel.
4. `exit(1)` leállítja a folyamatot.

A `dump()` az 1–3. lépést csinálja, és nem állítja le a scriptet.

Yii `Model` és `ActiveRecord` esetén a kiírás az attribútumokat, a validációs hibákat, új rekordnál az `isNew` jelzőt, valamint a nem üres dirty mezőket, a régi attribútumokat és a már betöltött relációkat mutatja. A komponens belső esemény- és behavior listája nem kerül a dumpba. Ha egy másik érték belsejében szerepel az alkalmazás, a modul, az adatbázis-kapcsolat, a DI konténer, a logger, a cache, a session, a mutex vagy az RBAC manager, az összecsukva látszik.

A `VAR_DUMPER_FORMAT` környezeti változó Symfonyé, ugyanúgy, mint Laravelben: `html`, `cli`, `server`, vagy `tcp://127.0.0.1:9912`. Ha be van állítva, a csomag nem cseréli le a Symfony kezelőjét.

## Komponens

Alapból a `dd` komponens a bootstrap során jön létre. Csak akkor kell a configba írni, ha egy alapértéket módosítasz:

```php
'components' => [
    'dd' => [
        'class' => \bladeknigt89\dd\Dd::class,
        'editor' => 'phpstorm',
        'showSource' => true,
        'prettyModels' => true,
        'basePath' => null,
        'maxDepth' => null,
        'maxItems' => null,
        'maxString' => null,
        'extraCollapse' => [],
    ],
],
```

| Tulajdonság | Alapérték | Jelentés |
| --- | --- | --- |
| `editor` | `phpstorm` | `phpstorm`, `vscode`, `vscode-insiders`, `sublime`, `atom`, vagy minta `{file}` és `{line}` helyőrzővel |
| `showSource` | `true` | A hívás fájlja és sora a dump mellett |
| `prettyModels` | `true` | A modellek attribútumai a komponens belső mezői helyett |
| `basePath` | az alkalmazás `basePath` értéke | Ehhez képest rövidül a forrásútvonal |
| `maxDepth` | Symfony alap | Hány szint legyen kinyitva |
| `maxItems` | Symfony alap (2500) | Legfeljebb ennyi elem kerül a klónba |
| `maxString` | Symfony alap | Szöveg levágása, `-1` esetén nincs vágás |
| `extraCollapse` | `[]` | További osztálynevek, amelyek beágyazva összecsukódnak |

Saját szerkesztő minta:

```php
'editor' => 'vscode://file/{file}:{line}',
```

## Fejlesztés

```bash
composer update
composer test
```

A tesztekhez a PHP `dom`, `xml` és `xmlwriter` bővítménye kell, mert a PHPUnit ezeket kéri.

## Licenc

BSD-3-Clause. A szöveg a [LICENSE](LICENSE) fájlban van.
