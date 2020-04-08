MonetDB-PHP-Deux
================

A PHP client library for accessing MonetDB.

# Examples

```php
$connection = new Connection("127.0.0.1", 50000,
    "monetdb", "monetdb", "myDatabase");

$result = $connection->Query(<<<EOF
    select
        "name"
    from
        "cats"
    where
        "weight" > 35
EOF
);

foreach($result as $cat) {
    echo $cat["name"]."\n";
}

$stats = $result->GetStatusRecords()[0];

echo "Execution time: {$stats->GetExecutionTime()} ms\n";

```

<hr />

# API Reference

<!-- API START -->


<!-- API END -->

# Development setup through the Docker image

- Build the Docker image:

        docker build --tag monetdb-php ./

- Create the Docker container and the host user inside it:

        docker run -d -h monetdb-php --restart unless-stopped -p 9292:80\
            -v ${PWD}:/var/MonetDB-PHP-Deux --name monetdb-php monetdb-php
        
        docker exec --user root -it monetdb-php sh -c \
            "useradd -m -s /bin/bash -u $(id -u) $(whoami)\
            && usermod -a -G monetdb $(whoami)"

- Login into the container as the host user, create a database and log into it:

        docker exec --user $(whoami) -it monetdb-php /bin/bash
        monetdb create myDatabase
        monetdb release myDatabase
        monetdb start myDatabase
        mclient -d myDatabase

- When you don't need the MonetDB-PHP-Deux container anymore, you can get rid of it easily: (this also removes the unused images)

        docker stop monetdb-php\
        && docker image rm monetdb-php --force\
        && docker system prune --force
