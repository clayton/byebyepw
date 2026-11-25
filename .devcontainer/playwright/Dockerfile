FROM mcr.microsoft.com/playwright:v1.65.1-noble

ENV DEBIAN_FRONTEND=noninteractive
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        libnss3-tools \
        openssl; \
    rm -rf /var/lib/apt/lists/*

# Install playwright at /node_modules so Node ESM resolves 'playwright' from any mounted test path (e.g. /tests)
WORKDIR /
COPY package.json /package.json
RUN npm install --no-audit --no-fund && rm -f /package.json /package-lock.json

ENV NODE_PATH=/node_modules

COPY entrypoint.sh /usr/local/bin/playwright-entrypoint.sh
RUN chmod +x /usr/local/bin/playwright-entrypoint.sh

WORKDIR /tests

ENTRYPOINT ["/usr/local/bin/playwright-entrypoint.sh"]
CMD ["node", "/tests/smoke-webauthn.mjs"]
