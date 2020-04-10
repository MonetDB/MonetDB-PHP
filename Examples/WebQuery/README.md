Web query example
=================

This is an example project for MonetDB-PHP-Deux.

Steps:

- Follow the Dockerfile instructions in the main README.md file of the project.
- Execute `composer install` in this directory.
- Execute the `Data modification` example, to have data in the `cats` table. (Or to have that table at all)
- Try the following URLs to test the application:
  - http://127.0.0.1:9292/MonetDB-PHP-Deux/
  - http://192.168.1.166:8989/MonetDB-PHP-Deux/?name=Fluffor
  - http://192.168.1.166:8989/MonetDB-PHP-Deux/?min_weight=3&max_weight=11
