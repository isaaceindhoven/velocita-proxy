FROM nginx:1.19.1-alpine

LABEL maintainer="Jelle Raaijmakers <jelle.raaijmakers@isaac.nl>"

RUN apk --no-cache upgrade \
    && apk --no-cache add ruby \
    && sed -i '/^user /d' /etc/nginx/nginx.conf \
    && sed -i '/^worker_processes/c\worker_processes auto;' /etc/nginx/nginx.conf \
    && sed -i '/^pid /c\pid /tmp/nginx.pid;' /etc/nginx/nginx.conf \
    && rm /etc/nginx/conf.d/default.conf

COPY entrypoint.sh /usr/local/bin/

RUN mkdir -p /usr/local/lib/velocita/
COPY templates/* /usr/local/lib/velocita/

VOLUME /var/cache/velocita/

EXPOSE 8080/tcp 8443/tcp

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
