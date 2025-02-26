## Start
docker compose up -d

## Generate big file
docker compose exec -it billion-app php artisan billion:write

## Search in big file
docker compose exec -it billion-app php artisan billion:search

## Start daemonds
docker compose exec -it billion-app php artisan queue:listen --timeout=120 --memory=192

## Sort bit file
