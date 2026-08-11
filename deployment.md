## Deployment:Dockerization, GitHub CD, VPS setup Workflow

1) Create cd.yaml in .github/workflow
2) Create VPS_PASSWORD,VPS_HOST,DOCKER_USERNAME,DOCKER_PASSWORD secrets in 
Settings → Secrets and variables → Actions → Repository secret
3) added docker-compose.yaml, docker-entrypoint,Dockerfile in local project.
4) create .env,.env.docker, app & docker-compose.yml in vps folder.
5) push code & run github workflow.
6) now for first time setup RUN
```bash
docker compose pull
docker compose up -d
```
7) check log using
```bash
docker logs -f <container_name>
```
8) check laravel folders inside dockerized container
```bash
docker exec -it <container_name> bash
```
9) if all clear, the project will be lived on the exposed port(ex:8000)
```bash
http://2.25.199.33:8000/
```
### Connect Domain to The VPS container
1. Go to Domain DNS and create A record and add (@/api) and hostname.
2. check from VPS if it is connected.(curl)

## For Caddy Installation:
## 1. Install the required core dependencies
```
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
```

## 2. Download and add the official Caddy security GPG key
```
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
```

## 3. Add the stable Caddy repository to your APT sources list
```
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
```

## 4. Update your local package index and install Caddy

```
sudo apt update
sudo apt install caddy
```

# Caddy Configuration
1. go to /etc/caddy/CaddyFile
```bash
:80 {
        # Set this path to your site's directory.
        root * /usr/share/caddy

        # Enable the static file server.
        file_server

        # Another common task is to set up a reverse proxy:
        # reverse_proxy localhost:8080

        # Or serve a PHP site through php-fpm:
        # php_fastcgi localhost:9000
}

api.connecttoinspect.com {
    reverse_proxy 127.0.0.1:8000
}
```

