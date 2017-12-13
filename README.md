# crypto-investor

## Installation

### With nginx-proxy

Create the default network for proxy management
```
docker network create nginx-proxy
```

Create a `docker-compose.yml` with the following content
```
version: '2'
services:
  nginx-proxy:
    image: jwilder/nginx-proxy:latest
    volumes:
      - /etc/nginx/ssl/certs:/etc/nginx/certs:ro
      - /etc/nginx/vhost.d
      - /usr/share/nginx/html
      - /var/run/docker.sock:/tmp/docker.sock:ro
    labels:
      - com.github.jrcs.letsencrypt_nginx_proxy_companion.nginx_proxy
    ports:
      - 80:80
      - 443:443

  nginx-proxy-ssl:
    image: jrcs/letsencrypt-nginx-proxy-companion:latest
    volumes:
      - /etc/nginx/ssl/certs:/etc/nginx/certs:rw
      - /var/run/docker.sock:/var/run/docker.sock:ro
    volumes_from:
      - nginx-proxy

networks:
    default:
        external:
            name: nginx-proxy
```

Start your `docker-compose.yml` for this project