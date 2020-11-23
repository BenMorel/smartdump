# Smart Dump

<img src="https://raw.githubusercontent.com/BenMorel/smartdump/master/logo.svg" alt="" align="left" height="125">

Exports a **referentially intact subset** of a MySQL database.

Note: although this tool targets MySQL only for now, it is designed to be able to support other RDBMS in the future.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](http://opensource.org/licenses/MIT)

## Introduction

Did you ever need to export just a couple tables from your MySQL database, but end up with broken foreign key constraints?
What if you could import every single foreign row your data depends on as well, and nothing more?
This tool does just that.

Let's say you want to dump the `order_items` table below:

<img src="https://raw.githubusercontent.com/BenMorel/smartdump/master/diagram.png" alt="">

If you use `mysqldump`, you'll get broken foreign key constraints to `orders` and `products`.

If you use `smartdump` instead, you'll get the whole `order_items` table, **plus** the rows from `orders` and `products` required to satisfy the constraints, **plus**, in turn, the rows from `users` and `countries` required to satisfy the remaining constraints! 💪

The key takeaway here is that `smartdump` **will only import the rows required to satisfy the constraints** of the requested tables.

## Installation

The only currently supported installation method is through [Composer](https://getcomposer.org/):

```
composer require benmorel/smartdump
```

## Usage

To dump some tables, just run:

```
vendor/bin/smartdump db.table1 db.table2
```

or, if all your tables are in the same database:

```
vendor/bin/smartdump --database db table1 table2
```

## Options

Options that take a value:

| Option | Description | Default value |
| ------ | ----------- | ------------- |
| `--host` | The host name | localhost |
| `--port` | The port number | 3306 |
| `--user` | The user name | root |
| `--password` | The password | |
| `--charset` | The character set | utf8mb4 |
| `--database` | The database name to prepend to table names | |

Options that don't take a value:

| Option | Description
| ------ | -----------
| `--no-create-table` | Add this option to not include a `CREATE TABLE` statement |
| `--add-drop-table` | Add this option to include a `DROP TABLE IF EXISTS` statement before `CREATE TABLE` |
| `--no-schema-name` | Add this option to not include the schema name in the output; this allows for importing the dump into a schema name other than that of the source database. |
| `--merge` | Add this option to create a dump that can be merged into an existing schema; this removes `CREATE TABLE` statements and uses upserts instead of inserts. Implies `--no-create-table`.
## Future scope (todo, ideas)

- standalone PHAR version
- support for other RDBMS
- support for loading only *n* rows in the base tables; useful to extract a sample dataset
- support for loading *incoming* relationships to the tables (**?**)  
  Right now, only the outgoing relationships are followed, it could be interesting to follow incoming relationships to each row we're exporting as well; at least as an option?
- a mode that does not dump, but scans the whole database for broken foreign key constraints

---

Database diagram courtesy [dbdiagram.io](https://dbdiagram.io/).

Logo by [Pixel perfect](https://www.flaticon.com/authors/pixel-perfect).
