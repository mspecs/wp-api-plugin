FROM wordpress:latest

RUN mkdir -p ./wp-content/plugins/wp-api-plugin

COPY ./wp-api-plugin /usr/src/wordpress/wp-content/plugins/wp-api-plugin