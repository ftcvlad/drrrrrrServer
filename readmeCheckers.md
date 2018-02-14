docker run --name laravel-app --rm -p 8080:80 php7apachecheckers ***************** to run apache server with php
docker-compose up --build **** after changes in dockerfile or vhost

docker-compose up ******  docker-compose.yml to start containers


docker stop $(docker ps -aq) -- stop all running containers
docker rm $(docker ps -aq) **** remove all containers


docker exec -it 4c1ee740cb92 bash