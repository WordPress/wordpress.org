## Initial Setup

Follow these steps to setup a local WordCamp.org environment using [Docker](https://www.docker.com/).

**Note:** This will create `.docker/database` directory which will contain MySQL files to persist data across docker restarts.

1. Make sure you have Docker installed and Docker daemon running on your system.

1. Clone the repo:
    ```bash
    git clone git@github.com:WordPress/wordcamp.org.git wordcamp.test
    cd wordcamp.test
    ```

1. Generate and trust the SSL certificates, so you get a green bar and can adequately test service workers.
	```bash
	brew install mkcert
	brew install nss
	mkcert -install
	mkcert -cert-file wordcamp.test.pem -key-file wordcamp.test.key.pem    wordcamp.test *.wordcamp.test *.content.wordcamp.test *.new-site.wordcamp.test *.misc.wordcamp.test *.atlanta.wordcamp.test *.sf.wordcamp.test *.seattle.wordcamp.test *.columbus.wordcamp.test *.toronto.wordcamp.test *.us.wordcamp.test *.rhodeisland.wordcamp.test buddycamp.test *.buddycamp.test *.brighton.buddycamp.test
	```

	_Note: That list of domains is generated with `docker-compose exec wordcamp.test wp site list --field=url`, but that command won't be available until after you've built the environment in the next steps._

	_Note: When adding a new domain to your local environment, make sure you add it to the example above, and commit the change, so that others can just copy/paste the command, rather than re-doing the work of generating the full list. Add `*.{city}.wordcamp.test` rather than specific years like `2019.{city}.wordcamp.test`. Third-level domains like `central.wordcamp.test` are already covered by the `*.wordcamp.test`, and should not be added to the list._

1. Build and boot the Docker environment.
    ```bash
    docker-compose up --build -d
	```

    It could take some time depending upon the speed of your Internet connection. At the end of the process, you should see these messages:

     ```bash
    Creating wordcamporg_wordcamp.db_1   ... done
    Creating wordcamporg_wordcamp.test_1 ... done
    ```

    _Note: You won't be able to test in your browser just yet, so continue with the next steps._

1. Add all the sites listed to your hosts file.
    To get the list of sites, run the following command, and then remove the `http(s)://` prefix and `/` suffix.

    ```bash
    docker-compose exec wordcamp.test wp site list --field=url --allow-root
    ```

    Example hosts file entry:
    ```bash
    127.0.0.1 central.wordcamp.test plan.wordcamp.test 2014.content.wordcamp.test 2014.misc.wordcamp.test 2016.misc.wordcamp.test 2014.atlanta.wordcamp.test 2013.sf.wordcamp.test 2014.seattle.wordcamp.test 2014.columbus.wordcamp.test 2014.toronto.wordcamp.test 2014.sf.wordcamp.test buddycamp.test 2015.brighton.buddycamp.test 2015-experienced.seattle.wordcamp.test 2015-beginner.seattle.wordcamp.test 2015.us.wordcamp.test 2015.rhodeisland.wordcamp.test new-site.wordcamp.test 2014.new-site.wordcamp.test 2019.seattle.wordcamp.test
    ```

1. The installation doesn't have any 3rd-party plugins or themes yet, you must add them like so:

	```bash
	docker-compose exec wordcamp.test sh install-plugin-theme.sh
	```

    This could take some time depending on your internet connection.

1. `/wp-admin` pages for these sites should now be accessible. Use `admin` as username and `password` as password to login. Front end pages will not be accessible until you complete the remaining steps.

	If your browser warns you about the self-signed certificates, then the CA certificate is not properly installed. For Chrome, [manually add the CA cert to Keychain Access](https://deliciousbrains.com/ssl-certificate-authority-for-local-https-development/). For Firefox, import it to `Preferences > Certificates > Advanced > Authorities`.

1. By default, docker will start with data defined in `.docker/wordcamp_dev.sql` and changes to data will be persisted across runs in `.docker/database`. To start with different database, delete `.docker/database` directory and replace the `.docker/wordcamp_dev.sql` file and run `docker-compose up --build -d` again.

1. Note that if you want to work on WordCamp blocks, [you would have to install all the node dependencies](../public_html/wp-content/mu-plugins/blocks/readme.md). This can be done either inside, or even from outside the Docker.


## Useful Docker Commands:

Note: All of these commands are meant to be executed from project directory.

1. To start WordCamp docker containers, use:
    ```bash
    docker-compose up -d
    ```

    here `-d` flags directs docker to run in background.

    todo why does it do so much stuff when booting? shouldn't reprovision stuff, just boot. provision should be seprate process like vvv, otherwise slow

1. To stop the running docker containers, use the command like so:

    ```bash
    docker-compose stop
    ```

1. To open a shell inside the web container, use:

    ```bash
    docker-compose exec wordcamp.test bash
    ```

    here
    `wordcamp.test` is the name of docker service running `nginx` and `php`
    `bash` is the name of command that we want to execute. This particular command will give us shell access inside the docker.

    Similarly, for the MySQL container, you can use:

    ```bash
    docker-compose exec wordcamp.db bash
    ```

    here
    `wordcamp.db` is the name of docker service running MySQL server

1. To view `nginx` and `php-logs` use:
    ```bash
    docker-compose logs -f --tail=100 wordcamp.test
    ```

    here

    `-f` flag is used for consistently following the logs. Omit this to only dump the logs in terminal.

    `--tail=100` flag is used to view only last `100` lines of log. Omit this if you'd like to see the entire log since docker started.

    `wordcamp.test` is the name of the docker service which is running `nginx` and `php`

    Similarly, to view MySQL server logs (note: this does not show MySQL queries made by application, these are just server logs), use:

    ```bash
    docker-compose logs -f --tail=100 wordcamp.db
    ```

    here

    `wordcamp.db` is the name of docker service which is running MySQL server.


Once the Docker instance has started, you can visit [2014.content.wordcamp.org](2014.content.wordcamp.org) to view a sample WordCamp site. WordCamp central would be [central.wordcamp.test](central.wordcamp.test). You can also visit [localhost:1080](localhost:1080) to view the MailCatcher dashboard.
