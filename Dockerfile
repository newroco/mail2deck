FROM php:alpine3.15 AS builder

# Install all dependencies required for API calls and mail handling
RUN apk add --no-cache git php-curl php-mbstring php-imap php-xml ssmtp gawk

# Install PHP ext-imap
RUN set -eux; \
  persistentDeps=" \
    c-client \
  "; \
  buildDeps=" \
    imap-dev \
    krb5-dev \
    openssl-dev \
  "; \
  apk add --no-cache --virtual .imap-persistent-deps ${persistentDeps}; \
  apk add --no-cache --virtual .imap-build-deps ${buildDeps}; \
  \
  docker-php-ext-configure imap \
    --with-imap-ssl \
    --with-kerberos \
  ; \
  docker-php-ext-install -j$(nproc) imap; \
  \
  apk del --no-cache --no-network .imap-build-deps

# Create a non-root user to own the files and run our server
RUN adduser -D -g "Mail2deck" deckbot
WORKDIR /home/deckbot/mail2deck

# Copy scripts
# Use the .dockerignore file to control what ends up inside the image!
COPY . .

# Install dependencies
RUN docker-utils/install_composer.sh && \
    ./composer.phar update && \
    rm docker-utils/install_composer.sh composer.phar

# Setup SMTP Server
RUN docker-utils/configure_smtp.sh && \
    rm docker-utils/configure_smtp.sh

# Use our non-root user
USER deckbot

# Run script once
CMD ["php", "index.php"]