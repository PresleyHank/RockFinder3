![img](logo.svg)

Combine the power of ProcessWire selectors and SQL

![img](https://i.imgur.com/6FbDwQK.png)

# Preface

## Why this module exists

Initially RockFinder1 was built to feed client side datatables with an array of ProcessWire page data. Loading all pages into memory via a `$pages->find()` query can quickly get inefficient. Querying the database directly via SQL can quickly get very complex on the other hand.

RockFinder is here to help you in such situations and makes finding (or aggregating) data stored in your ProcessWire installation easy, efficient and fun.

Possible use cases:

* Find data for any kind of tabular data (tabulator.info, datatables.net, ag-grid.com).
* Reduce the amount of necessary SQL queries ([see here](https://processwire.com/talk/topic/22205-rockfinder2-combine-the-power-of-pw-selectors-and-sql/?do=findComment&comment=200406)).
* Find data for a CSV or XML export.
* Find data for a REST-API.

## Differences to previous RockFinder modules

* RF3 supports chaining: `$RockFinder3->find("template=foo")->addColumns(['foo'])`.
* RF3 fully supports [multi-language](#multi-language).
* RF3 makes it super-easy to [add custom columnTypes](#custom-column-types).
* RF3 makes it easier to use [custom SQL statements](#custom-sql).

## Getting help / Contribute

* If you need help please head over to the PW forum thread and ask your question there: // TODO
* If you found an issue/bug please report it on [GitHub](https://github.com/baumrock/RockFinder3/issues).
* If you can help to improve RockFinder I'm happy to accept [Pull Requests](https://github.com/baumrock/RockFinder3/pulls).

## Example Snippets

TracyDebugger is not necessary for using RockFinder3 but it is recommended. All examples in this readme show dumps of RockFinder instances using the Tracy Console. The ProcessModule of RockFinder3 does use Tracy for dumping the results, so TracyDebugger is required for the ProcessModule to run.

**Special thanks to Adrian once more for the brilliant TracyDebugger and the quick help for adding dumping support to the Tracy Console! This was tremendously helpful for developing this module and also for writing these docs.**

![img](hr.svg)

# Basic Concept

The concept of RockFinder is to get the base query of the `$pages->find()` call and modify it for our needs so that we get the best of both worlds: Easy PW selectors and powerful and efficient SQL operations.

In PW every find operation is turned into a `DatabaseQuerySelect` object. This class is great for working with SQL via PHP because you can easily modify the query at any time without complex string concatenation operations:

![img](https://i.imgur.com/iwI7gGB.png)

This is the magic behind RockFinder3! It provides an easy to use API to modify that base query and then fires one efficient SQL query and gets an array of stdClass objects as result.

![img](hr.svg)

# Installation

Install the RockFinder3Master module. The master module is an autoload module that adds a new variable `$RockFinder3` to the PW API and also installs the `RockFinder3` module that is responsible for all the finding stuff.

![img](hr.svg)

# Usage

In the most basic setup the only thing you need to provide to a RockFinder is a regular PW selector via the `find()` method:

```php
// either via the API variable
$RockFinder3->find("template=foo");

// or via a modules call
$modules->get('RockFinder3')->find("template=foo");
```

## Adding columns

You'll most likely don't need only ids, so there is the `addColumns()` method for adding additional columns:

```php
$RockFinder3
  ->find("template=admin, limit=3")
  ->addColumns(['title', 'created']);
```

![img](https://i.imgur.com/k0gHwXW.png)

This makes it possible to easily add any field data of the requested page.

## Dumping data

For small finders Tracy's `dump()` feature is enough, but if you have more complex finders or you have thousands of pages this might get really inconvenient. That's why RockFinder3 ships with a custom `dump()` method that works in the tracy console and turns the result of the finder into a paginated table (using tabulator.info):

```php
$RockFinder3
  ->find("id>0")
  ->addColumns(['title', 'created'])
  ->dump();
```

![img](https://i.imgur.com/dfHdrG7.png)

## Dumping the SQL of the finder

To understand what is going on it is important to know the SQL that is executed. You can easily dump the SQL query via the `dumpSQL()` method. This even supports chaining:

```php
$RockFinder3
  ->find("template=cat")
  ->addColumns(['title'])
  ->dumpSQL()
  ->addColumns(['owner'])
  ->dumpSQL()
  ->dump();
```

![img](https://i.imgur.com/AfUy2OF.png)

## Renaming columns (column aliases)

Sometimes you have complicated fieldnames like `my_great_module_field_foo` and you just want to get the values of this field as column `foo` in your result:

```php
$RockFinder3
  ->find("template=person")
  ->addColumns(['title' => 'Name', 'age' => 'Age in years', 'weight' => 'KG'])
  ->dump();
```

![img](https://i.imgur.com/TIpk3pu.png)

## Custom column types

You can add custom column types easily. Just place them in a folder and tell RockFinder to scan this directory for columnTypes:

```php
// do this on the master module!
$modules->get('RockFinder3Master')->loadColumnTypes('/your/directory/');
```

See the existing columnTypes as learning examples.

![img](hr.svg)

# Working with options fields

By default RockFinder will query the `data` column in the DB for each requested field. That's fine for lots of fields (like Text or Textarea fields), but for more complex fields this will often just return an ID value instead of the value that we would like to see (like a file name, an option value, etc):

```php
$RockFinder3
  ->find("template=cat")
  ->addColumns(['title', 'sex'])
  ->dump();
```

![img](https://i.imgur.com/teIe2va.png)

### Option 1: OptionsValue and OptionsTitle columnTypes

In case of the `Options` Fieldtype we have a `title` and a `value` entry for each option. That's why RockFinder ships with two custom columnTypes that query those values directly from the DB (thanks to a PR from David Karich @RockFinder2). You can even get both values in one single query:

```php
$RockFinder3
  ->find("template=cat")
  ->addColumns([
    'title',
    'sex' => 'sex_id',
    'OptionsValue:sex' => 'sex_value',
    'OptionsTitle:sex' => 'sex_title',
  ])
  ->dump();
```

![img](https://i.imgur.com/H4jpr2E.png)

Note that the column aliases are necessary here to prevent duplicate columns with the same name!

### Option 2: Options relation

Option 1 is very handy but also comes with a drawback: It loads all values and all titles into the returned resultset. In the example above this means we'd have around 50x `m`, 50x `f`, 50x `Male` and 50x `female` on 100 rows. Multiply that by the number of rows in your resultset and you get a lot of unnecessary data!

Option 2 lets you save options data in the finder's `getData()->option` property so that you can then work with it at runtime (like via JS in a grid that only renders a subset of the result):

```php
$RockFinder3
  ->find("template=cat")
  ->addColumns([
    'title',
    'sex',
  ])
  ->addOptions('sex');
```

![img](https://i.imgur.com/ocF3UJt.png)

```php
$finder->options->sex[2]->value; // f
$finder->options->sex[2]->title; // Female
```

You can also use the helper functions:

```php
$finder->getOptions('sex');
$finder->getOption('sex', 2);
```
![img](https://i.imgur.com/ujyx7gD.png)

![img](hr.svg)

# Multi-Language

Usually data of a field is stored in the `data` db column of the field. On a multi-language setup though, the data is stored in the column for the user's current language, eg `data123`. This makes the queries more complex, because you need to fallback to the default language if the current language's column has no value. RockFinder3 does all that for you behind the scenes and does just return the column value in the users language:

```php
$user->language = $languages->get(1245);
$RockFinder3
  ->find("template=cat")
  ->addColumns([
    'title',
    'sex',
  ])
  ->dump();
```

![img](https://i.imgur.com/1R6ukB8.png)

Even setting up new columnTypes is easy! Just use the built in `select` proerty of the column and it will return the correct SQL query for you:

```php
class Text extends \RockFinder3\Column {
  public function applyTo($finder) {
    $finder->query->leftjoin("`{$this->table}` AS `{$this->tableAlias}` ON `{$this->tableAlias}`.`pages_id` = `pages`.`id`");
    $finder->query->select("{$this->select} AS `{$this->alias}`");
  }
}
```

This will use these values behind the scenes (here for the `title` field):

![img](https://i.imgur.com/gQA22HA.png)

![img](hr.svg)

# Joins

What if we had a template `cat` that holds data of the cat, but also references one single owner. And what if we wanted to get a list of all cats including their owners names and age? The owner would be a single page reference field, so the result of this column would be the page id of the owner:

```php
$RockFinder3
  ->find("template=cat")
  ->addColumns(['title', 'owner'])
  ->dump();
```

![img](https://i.imgur.com/Y7lgIjb.png)

Joins to the rescue:

```php
$owners = $RockFinder3
  ->find("template=person")
  ->addColumns(['title', 'age'])
  ->setName('owner'); // set name of target column
$RockFinder3
  ->find("template=cat")
  ->addColumns(['title', 'owner'])
  ->join($owners)
  ->dump();
```

![img](https://i.imgur.com/9JyMKrs.png)

If you don't want to join all columns you can define an array of column names to join. You can also set the `removeID` option to true if you want to remove the column holding the id of the joined data:

```php
->join($owners, ['columns' => ['title'], 'removeID' => true])
```

![img](https://i.imgur.com/zf1imb4.png)

Joins work great on single page reference fields. But what if we had multiple pages referenced in one single page reference field?

![img](hr.svg)

# Relations

Let's take a simple example where we have a page reference field on template `cat` that lets us choose `kittens` for this cat:

![img](https://i.imgur.com/QoKCU7i.png)

This is what happens if we query the field in our finder:

```php
$RockFinder3
  ->find("template=cat")
  ->addColumns(['title', 'kittens'])
  ->dump();
```

![img](https://i.imgur.com/JcdULfz.png)

So, how do we get data of those referenced pages? We might want to list the name of the kitten (the `title` field). This could be done in a similar way as we did on the options field above. But what if we also wanted to show other field data of that kitten, like the sex and age? It would get really difficult to show all that informations in one single cell of output!

Relations to the rescue:

```php
// setup kittens finder that can later be added as relation
$kittens = $RockFinder3
  ->find("template=kitten")
  ->setName("kittens")
  ->addColumns(['title', 'OptionsTitle:sex', 'age']);

// setup main finder that finds cats
$finder = $RockFinder3
  ->find("template=cat,limit=1")
  ->setName("cats")
  ->addColumns(['title', 'kittens'])
  ->addRelation($kittens);

// dump objects
db($finder);
db($finder->relations->first());
```

![img](https://i.imgur.com/IFkxrmW.png)

**NOTE**

Look at the result of the `kittens` finder: It returned three rows as result even though we did not define any limit on the initial setup of that finder! That is because RockFinder will automatically return only the rows of the relation that are listed in the column of the main finder!

You can see what happens in the SQL query:

```php
db($finder->relations->first()->getSQL());
```

```sql
SELECT
  `pages`.`id` AS `id`,
  `_field_title_5eca947b3da27`.`data` AS `title` 
FROM `pages` 
LEFT JOIN `field_title` AS `_field_title_5eca947b3da27`
  ON `_field_title_5eca947b3da27`.`pages_id` = `pages`.`id` 
WHERE (pages.templates_id=51) 
AND (pages.status<1024) 
AND pages.id IN (258138,258171,258137) /* here is the limit */
GROUP BY pages.id
```

If you need to access those kittens `258138,258171,258137` via PHP you can do this:

```php
$relation = $finder->relations->first();
db($relation->getRowsById("258138,258171,258137"));
db($relation->getRowById(258138));
```

![img](https://i.imgur.com/71UHptF.png)

There's a lot you can do already simply using the RockFinder API, but I promised something about using SQL...

![img](hr.svg)

# Custom SQL

## Option 1: DatabaseQuerySelect

RockFinder3 is heavily based on the `DatabaseQuerySelect` class of ProcessWire. This is an awesome class for building all kinds of SQL `SELECT` statements - from simple to very complex ones. You can access this query object at any time via the `query` property of the finder:

```php
$owners = $RockFinder3
  ->find("template=person")
  ->addColumns(['title', 'age', 'weight']);
db($owners->query);
```

![img](https://i.imgur.com/1xCve1R.png)

This means you have full control over your executed SQL command:

```php
$finder = $RockFinder3->find(...)->addColumns(...);
$finder->query->select("foo AS foo");
$finder->query->select("bar AS bar");
$finder->query->where("this = that");
```

The only thing you need to take care of is to query the correct tables and columns. This might seem a little hard because many times the names are made unique by a temporary suffix. It's very easy to access these values though:

```php
$owners = $RockFinder3
  ->find("template=person")
  ->setName('owner')
  ->addColumns(['title', 'age']);
db($owners->columns->get('age'));
```

![img](https://i.imgur.com/iRnrfPJ.png)

## Option 2: SQL String Modification

Another technique is to get the resulting SQL and wrap it around a custom SQL query:

```php
$owners = $RockFinder3
  ->find("template=person")
  ->addColumns(['title', 'age', 'weight'])
  ->setName('owner');
$cats = $RockFinder3
  ->find("template=cat")
  ->addColumns(['title', 'owner'])
  ->join($owners)
  ->getSQL();

db($RockFinder3->getObject("SELECT AVG(`owner:age`) FROM ($cats) AS tmp"));
```

![img](https://i.imgur.com/NwqatSv.png)

You SQL skills are the limit ;)

```php
db($RockFinder3->getObject("
  SELECT
  AVG(`owner:age`) AS `age`,
  `owner:weight` as `weight`
  FROM ($cats) AS tmp
  WHERE `owner:age`>50
"));
```

![img](https://i.imgur.com/05rQ7oQ.png)

You see that you can build very complex queries with very little and easy SQL!

![img](hr.svg)

# Thank you

...for reading the docs and using RockFinder3. If you find RockFinder3 helpful consider giving it a star on github or [saying thank you](https://www.paypal.me/baumrock). I'm also always happy to get feedback in the PW forum!

**Happy finding!**
