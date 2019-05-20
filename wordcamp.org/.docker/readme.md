If you'd like to only setup WordCamp.org and would like to use Docker, you can use inbuilt docker provisioning. Please follow these steps

**Note:** This will create `.docker/database` directory which will contain MySQL files to persist data across docker restarts.

1. Make sure you have Docker installed and Docker daemon running on your system.

1. Clone the repo: 
    ```
    git clone git@github.com:WordPress/wordcamp.org.git
    ```

1. Change into the directory, and run docker compose command. This could take some time depending upon the internet speed.
    ```
    cd wordcamp.org && docker-compose up --build -d
    ```
     This will also start the docker environment for WordCamp.org development. At the end of the process, you should see these messages:
     ```bash
       Creating wordcamporg_wordcamp.db_1   ... done
       Creating wordcamporg_wordcamp.test_1 ... done
    ```

1. Add the following resolutions to your host file:
    ```
    127.0.0.1	central.wordcamp.test
    127.0.0.1	wordcamp.test
    127.0.0.1	2014.content.wordcamp.test
    127.0.0.1	2014.misc.wordcamp.test
    127.0.0.1	2014.new-site.wordcamp.test
    ```
    
    `/wp-admin` pages for these sites should now be accessible. Use `admin` as username and `password` as password to login. Front end pages will not be accessible until you complete the remaining steps.
    
    **Note:** `https` URL scheme must be used to visit these sites. Security exception will be required in first time run.
    
1. The installation doesn't have any 3rd-party plugins or themes yet, you must add them like so:

    1. From the project directory, run this command to get inside the docker
        ```bash
           docker-compose exec wordcamp.test bash
        ```
    
    1. Run predefined command for installing plugins and themes. This could take some time depending on your internet connection:
        ```bash
           sh install-plugin-theme.sh
        ```

1. By default, docker will start with data defined in `.docker/wordcamp_dev.sql` and changes to data will be persisted across runs in `.docker/database`. To start with different database, delete `.docker/database` directory and replace the `.docker/wordcamp_dev.sql` file and run `docker-compose up --build -d` again.

1. Note that if you want to work on WordCamp blocks, [you would have to install all the node dependencies](../public_html/wp-content/mu-plugins/blocks/readme.md). This can be done either inside, or even from outside the Docker.

#### Useful Docker Commands:

Note: All of these commands are meant to be executed from project directory.

1. To start WordCamp docker containers, use:
    ```bash
    docker-compose up -d
    ```
    
    here `-d` flags directs docker to run in background.
    
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

1. To execute a command inside docker running `nginx` and `php`, use:

    ```bash
    docker-compose exec wordcamp.test bash
    ```
    
    here
    `wordcamp.test` is the name of docker service running `nginx` and `php`
    `bash` is the name of command that we want to execute. This particular command will give us shell access inside the docker.
    
    Similarly, for MySQL, you can use:
    
    ```bash
    docker-compose exec wordcamp.db bash
    ```
    
    here
    `wordcamp.db` is the name of docker service running MySQL server
 
1. To stop the running docker containers, use the command like so:
    
    ```bash
    docker-compose stop
    ```
    
    
Once the Docker instance has started, you can visit [2014.content.wordcamp.org](2014.content.wordcamp.org) to view a sample WordCamp site. WordCamp central would be [central.wordcamp.test](central.wordcamp.test). You can also visit [localhost:1080](localhost:1080) to view the MailCatcher dashboard.
