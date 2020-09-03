Web query example
=================

This is an example project for MonetDB-PHP.

Steps:

- Follow the Dockerfile instructions in the main README.md file of the project.
- Log into the Docker container with `docker/login.sh`.
- See appendix 1 on how to prepare the database.
- Enter the `/var/MonetDB-PHP/Examples/DataModification` directory
- Execute `composer install`
- Try the following URLs to test the application:
  - http://127.0.0.1:9292/MonetDB-PHP/
  - http://127.0.0.1:9292/MonetDB-PHP/?name=Fluffor
  - http://127.0.0.1:9292/MonetDB-PHP/?min_weight=3&max_weight=11

## Appendix 1: preparing the database

As an alternative you can execute the `DataModification` example first. But to create the proper schema, you only need to copy paste some commands.

Login into the docker container and connect to the database:

```bash
docker/login.sh
mclient -d myDatabase
```

Then copy/paste the following SQL commands:

```sql
drop schema if exists "mySchema" cascade;
create schema "mySchema";
set schema "mySchema";

create table "cats" (
    "name" text,
    "weight_kg" decimal(8, 2),
    "category" text,
    "birth_date" date,
    "net_worth_usd" decimal(20, 4)
);

insert into
    "cats"
    ("name", "weight_kg", "category", "birth_date", "net_worth_usd")
values
    ('Tiger', 8.2, 'fluffy', '2012-04-23', 2340000),
    ('Oscar', 3.4, 'spotted', '2014-02-11', 556235.34),
    ('Coco', 2.52, 'spotted', '2008-12-31', 1470500000),
    ('Max', 4.23, 'spotted', '2010-01-15', 100),
    ('Sooty', 7.2, 'shorthair', '2016-10-01', 580000),
    ('Milo', 5.87, 'spotted', '2015-06-23', 1500.53),
    ('Muffin', 12.6, 'fluffy', '2013-04-07', 230000),
    ('Ginger', 9.4, 'shorthair', '2012-06-19', 177240.5),
    ('Fluffor', 13.12, 'fluffy', '2000-10-07', 5730180200.12),
    ('Lucy', 3.12, 'shorthair', '2018-06-29', 5780000),
    ('Chloe', 2.12, 'spotted', '2013-05-01', 13666200),
    ('Misty', 1.96, 'shorthair', '2014-11-24', 12000000),
    ('Sam', 3.45, 'fluffy', '2018-12-19', 580.4),
    ('Gizmo', 4.65, 'fluffy', '2016-05-11', 120300),
    ('Kimba', 1.23, 'spotted', '2020-01-08', 890000);
```
