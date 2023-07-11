FROM alpine:3.18

RUN apk update && apk add bash curl jq openssl
COPY get-freepbx-credentials.sh /usr/local/bin/get-freepbx-credentials.sh

CMD ["bash", "/usr/local/bin/get-freepbx-credentials.sh"]
