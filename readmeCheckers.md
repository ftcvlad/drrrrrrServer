docker run --name laravel-app --rm -p 8080:80 php7apachecheckers ***************** to run apache server with php
docker-compose up --build **** after changes in dockerfile or vhost

docker-compose up ******  docker-compose.yml to start containers


docker stop $(docker ps -aq) -- stop all running containers
docker rm $(docker ps -aq) **** remove all containers


docker exec -it 4c1ee740cb92 bash

TODO
1) csrf middleware + send token as here: https://security.stackexchange.com/questions/36468/csrf-protection-and-single-page-apps
2) before initial fetch user logged in status incorrrect. Don't render anything (or just spinner?) until initial fetch done?
3) react-redux-fetch is missing fetch options. Wait if resolved or npm install from fork!
https://stackoverflow.com/questions/40528053/npm-install-and-build-of-forked-github-repo
fetchRequest, container, requestBuilder
4) SESSION_LIFETIME in env back to 120? 


Questions 
1) which auth they use? api token? passport? jwt?


,
        {
            test: /\.(png|jpg|ttf|...)$/,
            use: [
                { loader: 'url-loader' }
                // limit => file.size =< 8192 bytes ? DataURI : File
            ]
        }