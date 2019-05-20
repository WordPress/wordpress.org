### WordCamp.org

This repository mirrors https://meta.svn.wordpress.org/sites/trunk/wordcamp.org/ and can be used to contribute used Git instead of SVN.

###### Setup 

There are two primary ways to setup this repo for local development.

1. Using VVV. WordCamp.org is available as part of WordPress.org's meta network, and can be setup by following meta-environment setup guide: https://github.com/WordPress/meta-environment/blob/master/docs/install.md

1. If you'd like to only setup WordCamp.org and would like to use Docker, you can use inbuilt docker provisioning. Please follow these steps

    **Note:** This will create `.data` directory which will contain MySQL files to persist data across docker restarts.

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
        
    1. (Optional) Basic installation comes without themes and with only very basic plugins. Add plugins and themes which are installed in wordcamp.org if needed like so:
    
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
        
    1. (Optional) By default, docker will start with data defined in `.docker/wordcamp_dev.sql` and changes to data will be persisted across runs. To start with different database, delete `.data` directory and replace the `.docker/wordcamp_dev.sql` file and run `docker-compose up --build` again.
    
    After first time provisioning, docker can be started by using `docker-compose up` command from inside the directory. 
        
To contribute, you can send pull requests to this repo, or add patches to https://meta.trac.wordpress.org/.
