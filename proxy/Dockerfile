FROM nginx:1.15.8-alpine

LABEL maintainer="Jelle Raaijmakers <jelle.raaijmakers@isaac.nl>"

RUN apk --no-cache upgrade \
    && apk --no-cache add ruby \
    && sed -i '/worker_processes/c\worker_processes auto;' /etc/nginx/nginx.conf \
    && rm /etc/nginx/conf.d/default.conf

COPY entrypoint.sh /root/
COPY templates/* /root/templates/

VOLUME /var/cache/velocita

ENTRYPOINT ["/root/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
