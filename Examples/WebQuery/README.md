Web query example
=================

This is an example project for MonetDB-PHP-Deux.

Steps:

- Follow the Dockerfile instructions in the main README.md file of the project.
- Log into the Docker container
- Enter the `/var/MonetDB-PHP-Deux/Examples/DataModification` directory
- Execute `composer install`
- Execute the `Data modification` example, to have data in the `cats` table. (Or to have that table at all)
- Try the following URLs to test the application:
  - http://127.0.0.1:9292/MonetDB-PHP-Deux/
  - http://127.0.0.1:9292/MonetDB-PHP-Deux/?name=Fluffor
  - http://127.0.0.1:9292/MonetDB-PHP-Deux/?min_weight=3&max_weight=11
