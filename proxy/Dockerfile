FROM nginx:1.17.3-alpine

LABEL maintainer="Jelle Raaijmakers <jelle.raaijmakers@isaac.nl>"

RUN apk --no-cache upgrade \
    && apk --no-cache add ruby \
    && sed -i '/worker_processes/c\worker_processes auto;' /etc/nginx/nginx.conf \
    && rm /etc/nginx/conf.d/default.conf

COPY entrypoint.sh /root/
COPY templates/* /root/templates/

VOLUME /var/cache/velocita

EXPOSE 80/tcp 443/tcp

ENTRYPOINT ["/root/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
