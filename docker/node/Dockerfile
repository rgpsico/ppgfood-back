FROM node:18-alpine

RUN npm install -g laravel-echo-server

WORKDIR /app
COPY laravel-echo-server.json .

EXPOSE 6001

CMD ["laravel-echo-server", "start"]
