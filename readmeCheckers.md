docker run --name laravel-app --rm -p 8080:80 php7apachecheckers ***************** to run apache server with phpdocker-compose up --build **** after changes in dockerfile or vhost

docker-compose up ******  docker-compose.yml to start containers


docker stop $(docker ps -aq) -- stop all running containers
docker rm $(docker ps -aq) **** remove all containers


docker exec -it 4c1ee740cb92 bash



----redis---
docker exec -it f42035862b00  sh
redis-cli keys "*"

TODO
1) csrf middleware + send token as here: https://security.stackexchange.com/questions/36468/csrf-protection-and-single-page-apps
2) before initial fetch user logged in status incorrrect. Don't render anything (or just spinner?) until initial fetch done?
4) SESSION_LIFETIME in env back to 120? 


5) replace file cache with redis. handle games concurrency!!! Store each game separately?

Questions 
1) which auth they use? api token? passport? jwt?

