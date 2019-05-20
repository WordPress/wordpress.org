If you'd like to only setup WordCamp.org and would like to use Docker, you can use inbuilt docker provisioning. Please follow these steps

**Note:** This will create `.docker/database` directory which will contain MySQL files to persist data across docker restarts.

1. Make sure you have Docker installed and Docker daemon running on your system.

1. Clone the repo: 
    ```
    git clone git@github.com:WordPress/wordcamp.org.git
    ```

1. Change into the directory, and run docker compose command. This could take some time depending upon the internet speed.
    ```
    cd wordcamp.org && docker-compose up --build
    ```
     This will also start the docker environment for WordCamp.org development.
     
1. Add the following resolutions to your host file:
    ```
    127.0.0.1	central.wordcamp.test
    127.0.0.1	wordcamp.test
    127.0.0.1	2014.content.wordcamp.test
    127.0.0.1	2014.misc.wordcamp.test
    127.0.0.1	2014.new-site.wordcamp.test
    ```
    
    `/wp-admin` pages for these sites should now be accessible. Use `admin` as username and `password` as password to login.
    
    **Note:** `https` URL scheme must be used to visit these sites. Security exception will be required in first time run.
    
1. Basic installation comes without themes and with only very basic plugins. Add plugins and themes which are installed in wordcamp.org if needed like so:

    1. Find the docker which is running WordCamp. It will start with `wordcamporg_wordcamp.test` and will most likely be `wordcamporg_wordcamp.test_1`
    
    1. Go inside this docker:
    
       **Note:** You might need to change `wordcamporg_wordcamp.test_1` to whatever is docker name in your setup.
        ```bash
           docker exec -it wordcamporg_wordcamp.test_1 bash
        ```
    
    1. Run predefined command for installing plugins and themes. This could take some time depending on your internet connection:
        ```bash
           sh install-plugin-theme.sh
        ```
        
    1. Activate plugin or apply theme as needed.
    
1. By default, docker will start with data defined in `.docker/wordcamp_dev.sql` and changes to data will be persisted across runs in `.docker/database`. To start with different database, delete `.docker/database` directory and replace the `.docker/wordcamp_dev.sql` file and run `docker-compose up --build` again.

1. Note that if you want to work on WordCamp blocks, [you would have to install all the node dependencies](../public_html/wp-content/mu-plugins/blocks/readme.md). This can be done either inside, or even from outside the Docker.

After first time provisioning, docker can be started by using `docker-compose up` command from inside the directory. 

Once the Docker instance has started, you can visit [2014.content.wordcamp.org](2014.content.wordcamp.org) to view a sample WordCamp site. WordCamp central would be [central.wordcamp.test](central.wordcamp.test). You can also visit [localhost:1080](localhost:1080) to view the MailCatcher dashboard.
