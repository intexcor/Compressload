# Compressload
A bot that downloads and compresses messages up to 19 MB

## Requirements
- Docker, Docker-compose

## Install docker and docker compose (via official install script)
```bash
sudo apt-get update
sudo apt-get install ca-certificates curl gnupg -y
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
echo \
  "deb [arch="$(dpkg --print-architecture)" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  "$(. /etc/os-release && echo "$VERSION_CODENAME")" stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin -y
```

## Clone the repo
```bash
git clone https://github.com/intexcor/Compressload.git
cd Compressload
```

## Edit main.php
```bash
nano main.php
```

## Build the container
```bash
docker compose up -d --build
```

# Maintenance guide

## How to reboot the container?
```bash
docker compose up -d --force-recreate
```

## How to get logs
```bash
docker compose logs
# or
docker compose logs <service name>
```

## How to stop the container
```bash
docker compose stop
```