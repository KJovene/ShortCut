FROM node:24-alpine

# Le conteneur tourne avec l'uid de l'utilisateur hôte (voir compose.yaml) :
# npm a besoin d'un cache accessible en écriture.
ENV npm_config_cache=/tmp/.npm

# node_modules vit dans un volume Docker (binaires Linux, distincts de ceux de la machine) :
# le dossier doit exister et être accessible en écriture avant le montage.
RUN mkdir -p /app/node_modules && chmod 777 /app/node_modules

WORKDIR /app
