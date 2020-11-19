# Smart Dump

<img src="https://raw.githubusercontent.com/BenMorel/smartdump/master/logo.svg" alt="" align="left" height="125">

Exports a **referentially intact subset** of a MySQL database.

Note: although this tool targets MySQL only for now, it is designed to be able to support other RDBMS in the future.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](http://opensource.org/licenses/MIT)

## Introduction

Did you ever need to download just a couple tables from your MySQL database, together with all their relationships?
This tool does just that.

Let's say you want to dump just the `order_items` table below:

<img src="https://raw.githubusercontent.com/BenMorel/smartdump/master/diagram.png" alt="">

If you use `mysqldump`, you'll get broken foreign key constraints to `orders` and `products`.

If you use `smartdump` instead, you'll get the whole `order_items` table, **plus** the rows from `orders` and `products` required to satisfy the constraints, **plus**, in turn, the rows from `users` and `countries` required to satisfy the remaining constraints! 💪

---

Database diagram courtesy [dbdiagram.io](https://dbdiagram.io/).

Logo by [Pixel perfect](https://www.flaticon.com/authors/pixel-perfect).
